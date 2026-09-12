<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Anak proses: SATU percobaan booking (ADR-0007 slice D1)
|--------------------------------------------------------------------------
|
| Dipanggil oleh tests/Concurrency/ConcurrentBookingTest.php lewat symfony/process,
| beberapa kali SEKALIGUS, untuk memperebutkan slot terakhir yang sama.
|
| KENAPA proses terpisah, bukan sekadar 2 koneksi di dalam 1 test?
| PHP satu proses berjalan satu alur. Ia tak bisa menjalankan dua booking yang
| benar-benar tumpang tindih di detik yang sama — padahal itu persis keadaan yang
| mau dibuktikan. Satu-satunya cara jujur di Windows (tanpa pcntl_fork) adalah
| menyalakan beberapa proses PHP sungguhan.
|
| Skrip ini BUKAN test dan tak akan dikoleksi PHPUnit (namanya tidak *Test.php).
|
| Kontrak I/O (sengaja sesederhana mungkin — dibaca parent lewat stdout):
|   keluaran `OK:<appointment id>`   → booking berhasil
|   keluaran `FAIL:<nama kelas exception>` → booking ditolak
| Selalu exit 0: penolakan adalah hasil yang SAH di test ini, bukan error.
*/

use App\Actions\BookAppointmentAction;
use App\DataTransferObjects\BookAppointmentData;
use App\Enums\MoveType;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

require __DIR__.'/../../../vendor/autoload.php';

/** @var Application $app */
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$env = static fn (string $key): string => (string) getenv($key);

// Barrier waktu. Tiap anak butuh ~1 detik untuk boot Laravel, dan boot-nya tak
// pernah selesai bersamaan. Tanpa titik temu ini, anak pertama sudah COMMIT
// sebelum anak terakhir selesai boot — race-nya tak pernah terjadi dan test
// jadi hijau palsu. Parent memberi satu timestamp yang sama ke semua anak.
$startAt = (float) $env('TAS_START_AT');
$waitMicroseconds = (int) (($startAt - microtime(true)) * 1_000_000);

if ($waitMicroseconds > 0) {
    usleep($waitMicroseconds);
}

try {
    $appointment = $app->make(BookAppointmentAction::class)->execute(
        User::findOrFail((int) $env('TAS_ACTOR_ID')),
        new BookAppointmentData(
            slotWindowId: (int) $env('TAS_SLOT_WINDOW_ID'),
            truckId: (int) $env('TAS_TRUCK_ID'),
            driverId: (int) $env('TAS_DRIVER_ID'),
            moveType: MoveType::DELIVERY,
            // Unik per anak: kalau semua memakai nomor kontainer yang sama, yang
            // menolak mereka adalah unique constraint (slot_window_id, container_no),
            // BUKAN kuota — dan test berhenti menguji apa yang dimaksud.
            containerNo: $env('TAS_CONTAINER_NO'),
        ),
    );

    fwrite(STDOUT, 'OK:'.$appointment->id);
} catch (Throwable $e) {
    fwrite(STDOUT, 'FAIL:'.$e::class);
}

exit(0);
