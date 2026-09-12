<?php

declare(strict_types=1);

use App\Actions\BookAppointmentAction;
use App\DataTransferObjects\BookAppointmentData;
use App\Enums\MoveType;
use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\SlotWindow;
use App\Models\TransportCompany;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/*
|--------------------------------------------------------------------------
| Test konkurensi booking — ADR-0007 slice D1
|--------------------------------------------------------------------------
|
| Menutup temuan 2026-09-12 (HANDOVER §Temuan): `it('never over-books the last slot')`
| di tests/Feature/Booking memanggil Action DUA KALI BERURUTAN dalam satu proses, jadi
| ia tetap hijau walau `->lockForUpdate()` dicabut total. Yang dijaganya adalah aturan
| `bookedCount >= capacity`, bukan lock-nya.
|
| Berkas ini memakai DUA test yang saling melengkapi, sengaja:
|
|   1. `it('lets exactly one ... win the last slot')` — BUKTI PERILAKU.
|      Beberapa proses PHP sungguhan memperebutkan slot terakhir. Ini satu-satunya
|      test di repo yang benar-benar menjalankan keadaan yang jadi alasan proyek ini ada.
|
|   2. `it('takes the slot row with a locking read')` — PENGUNCI GERBANG.
|      Deterministik: memastikan `select ... for update` benar-benar diterbitkan.
|
| KENAPA #2 ADA, dibuktikan dengan mutasi (2026-09-12) dan BUKAN diasumsikan:
| `->lockForUpdate()` dicabut total dari SlotRepository, lalu kedua test dijalankan.
| Hasilnya — #2 MERAH seperti seharusnya, tapi **#1 TETAP HIJAU**.
|
| Sebabnya: tanpa locking read, `increment('booked_count')` tetap meminta lock baris di
| UPDATE-nya. Transaksi jadi berbaris di titik itu, dan `DB::transaction(attempts: 3)`
| mengulang closure yang kalah — pengulangan itu membaca ulang kuota yang kini sudah
| benar, lalu menolaknya. Sistem "kebetulan selamat" lewat jalur yang sama sekali tak
| dirancang untuk itu, dan keselamatannya bergantung pada penjadwalan OS.
|
| Pelajarannya: test perilaku konkuren TIDAK cukup jadi gerbang. Ia membuktikan janji
| ketika janjinya ditepati, tapi tak bisa diandalkan untuk berteriak saat penjagaannya
| dicabut. #2-lah yang benar-benar menjaga. Jangan hapus #2 dengan alasan "sudah ada
| test race yang lebih realistis" — persis itu yang membuat lubang ini hidup berbulan-bulan.
|
| Catatan trait: berkas ini TIDAK memakai RefreshDatabase (lihat tests/Pest.php) —
| RefreshDatabase membungkus tiap test dalam transaksi yang di-rollback, sehingga data
| yang dibuat parent TAK PERNAH TERLIHAT oleh proses anak. Dipakai DatabaseTruncation:
| datanya benar-benar di-commit, lalu tabel dibersihkan setelah test.
*/

/**
 * @return array{actor: User, truck: Truck, driver: User, window: SlotWindow}
 */
function concurrentScenario(int $capacity = 1): array
{
    $company = TransportCompany::factory()->create();

    return [
        'actor' => User::factory()->create(['company_id' => $company->id]),
        'truck' => Truck::factory()->create(['company_id' => $company->id]),
        'driver' => User::factory()->driver()->create(['company_id' => $company->id]),
        'window' => SlotWindow::factory()->create([
            'capacity' => $capacity,
            'booked_count' => 0,
            'status' => 'OPEN',
        ]),
    ];
}

/**
 * Menyalakan $count proses PHP yang menembak booking pada detik yang sama.
 *
 * @param  array{actor: User, truck: Truck, driver: User, window: SlotWindow}  $scenario
 * @return list<string> keluaran mentah tiap anak (`OK:<id>` atau `FAIL:<kelas>`)
 */
function raceForTheLastSlot(array $scenario, int $count): array
{
    $connection = DB::connection();
    $config = $connection->getConfig();

    // Anak WAJIB menembak database yang sama dengan parent. Di mode --parallel tiap
    // worker Pest punya DB sendiri (tas_testing_test_1, _2, ...), jadi nama ini dibaca
    // saat runtime — bukan dari phpunit.xml yang cuma menyebut nama dasarnya.
    $baseEnv = [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'mysql',
        'DB_HOST' => (string) ($config['host'] ?? '127.0.0.1'),
        'DB_PORT' => (string) ($config['port'] ?? '3306'),
        'DB_DATABASE' => $connection->getDatabaseName(),
        'DB_USERNAME' => (string) ($config['username'] ?? 'root'),
        'DB_PASSWORD' => (string) ($config['password'] ?? ''),
        // Efek samping dimatikan: yang diuji booking-nya, bukan mail/broadcast/cache.
        'BROADCAST_CONNECTION' => 'null',
        'CACHE_STORE' => 'array',
        'MAIL_MAILER' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'TAS_ACTOR_ID' => (string) $scenario['actor']->id,
        'TAS_TRUCK_ID' => (string) $scenario['truck']->id,
        'TAS_DRIVER_ID' => (string) $scenario['driver']->id,
        'TAS_SLOT_WINDOW_ID' => (string) $scenario['window']->id,
    ];

    // Jeda boot: cukup untuk semua anak selesai bootstrap Laravel sebelum barrier lewat.
    // Terlalu pendek → anak lambat memulai setelah yang lain sudah commit (race hilang).
    $startAt = microtime(true) + 3.0;
    $script = __DIR__.'/bin/book-once.php';

    /** @var list<Process> $processes */
    $processes = [];

    foreach (range(1, $count) as $i) {
        $process = new Process(
            [PHP_BINARY, $script],
            base_path(),
            $baseEnv + [
                'TAS_START_AT' => (string) $startAt,
                'TAS_CONTAINER_NO' => sprintf('TASU%07d', 1000000 + $i),
            ],
            null,
            60.0,
        );

        $process->start();
        $processes[] = $process;
    }

    $output = [];

    foreach ($processes as $process) {
        $process->wait();
        $output[] = trim($process->getOutput());
    }

    return $output;
}

it('lets exactly one of several simultaneous bookings win the last slot', function (): void {
    $scenario = concurrentScenario(capacity: 1);

    $results = raceForTheLastSlot($scenario, count: 4);

    $won = array_values(array_filter($results, fn (string $r): bool => str_starts_with($r, 'OK:')));
    $lost = array_values(array_filter($results, fn (string $r): bool => str_starts_with($r, 'FAIL:')));

    // Kalau baris ini gagal dengan 0 pemenang, curigai lingkungan (anak tak bisa konek
    // DB / boot gagal), bukan lock-nya — lihat $results untuk pesan mentahnya.
    expect($won)->toHaveCount(1, 'Tepat satu booking harus menang. Hasil mentah: '.implode(' | ', $results));
    expect($lost)->toHaveCount(3);

    // Inti invarian: kuota TIDAK BOLEH terlampaui, berapa pun yang menyerbu bersamaan.
    expect($scenario['window']->fresh()->booked_count)->toBe(1);
    expect(Appointment::query()->where('slot_window_id', $scenario['window']->id)->count())->toBe(1);

    // Yang kalah harus ditolak karena KUOTA, bukan karena tabrakan lain (mis. duplikat
    // kontainer atau deadlock yang lolos). Membedakan ini penting: over-book yang
    // tersamar sebagai error lain akan lolos dari assertion di atas.
    expect($lost)->each->toBe('FAIL:'.SlotUnavailableException::class);
});

it('takes the slot row with a locking read, not a plain select', function (): void {
    $scenario = concurrentScenario(capacity: 5);

    /** @var list<string> $statements */
    $statements = [];

    DB::listen(function ($query) use (&$statements): void {
        $statements[] = strtolower($query->sql);
    });

    app(BookAppointmentAction::class)->execute(
        $scenario['actor'],
        new BookAppointmentData(
            slotWindowId: $scenario['window']->id,
            truckId: $scenario['truck']->id,
            driverId: $scenario['driver']->id,
            moveType: MoveType::DELIVERY,
            containerNo: 'TASU7654321',
        ),
    );

    $lockingReads = array_filter(
        $statements,
        fn (string $sql): bool => str_contains($sql, 'slot_windows') && str_contains($sql, 'for update'),
    );

    // Kenapa memeriksa SQL, bukan perilaku: membaca kuota TANPA lock lalu menaikkannya
    // tetap memicu lock di UPDATE-nya, jadi "transaksi kedua tertahan" BUKAN bukti bahwa
    // KEPUTUSAN kuotanya diambil di bawah lock. Yang membedakan over-book dari aman
    // adalah persis kalimat `for update` pada SELECT-nya.
    expect($lockingReads)->not->toBeEmpty(
        'BookAppointmentAction harus membaca slot_windows dengan `for update`. '
        .'Tanpa itu dua transporter bisa sama-sama membaca kuota sisa yang sudah basi. '
        .'SQL yang terekam: '.implode(' ;; ', $statements)
    );
});
