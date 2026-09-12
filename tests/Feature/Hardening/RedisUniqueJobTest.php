<?php

declare(strict_types=1);

use App\Jobs\AppointmentReminderJob;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

/*
|--------------------------------------------------------------------------
| ShouldBeUnique di atas Redis sungguhan — ADR-0007 slice D3'
|--------------------------------------------------------------------------
|
| KENAPA BERKAS INI ADA. `CODE-WALKTHROUGH §O.3` mencatat jebakan yang menutup temuan
| ronde 4: `ShouldBeUnique` memegang lock selama job masih PENDING di antrian, jadi
| reminder baru hasil reschedule akan DIBUANG diam-diam kalau `uniqueId()` cuma memakai
| appointment id. Perbaikannya: `uniqueId()` = "{appointmentId}:{slotWindowId}".
|
| Masalahnya, waktu itu klaim tersebut TAK BISA diuji lewat perilaku: test berjalan dengan
| `QUEUE_CONNECTION=sync`, yang melepas lock unique seketika. Catatan aslinya jujur soal ini
| — "dikunci lewat assertion langsung atas uniqueId()", yang berarti yang dijaga adalah
| STRING-nya, bukan akibatnya.
|
| Setelah D3' Redis sungguhan tersedia, dan pengujian perilaku akhirnya mungkin. Ini
| menutup celah yang sama jenisnya dengan temuan D1: aturan benar, tapi gerbangnya menguji
| representasi, bukan akibat.
|
| CATATAN SKIP. Test ini DILEWATI kalau Redis tak terjangkau — itu keadaan normal di PHP
| native Windows, yang tak punya ekstensi redis sama sekali (lihat ADR-0007). Ia berjalan
| sungguhan di dalam container dan di CI. Skip dipilih ketimbang memaksa semua orang masuk
| container di tengah migrasi; begitu jalur native dipensiunkan, skip ini boleh dicabut.
*/

/** Lewati kalau Redis tak ada/tak terjangkau — bukan kegagalan, cuma lingkungan berbeda. */
function skipWithoutRedis(): void
{
    if (! extension_loaded('redis')) {
        test()->markTestSkipped('Ekstensi redis tak terpasang (normal di PHP native Windows).');
    }

    try {
        Redis::connection()->ping();
    } catch (Throwable $e) {
        test()->markTestSkipped('Redis tak terjangkau: '.$e->getMessage());
    }
}

beforeEach(function (): void {
    skipWithoutRedis();

    // Lock unique disimpan di CACHE, dan job didorong ke QUEUE. Keduanya harus benar-benar
    // Redis di test ini — default phpunit.xml (`array` + `sync`) justru yang membuat
    // perilaku ini tak bisa diamati. Opt-in eksplisit per-test, bukan mengendurkan
    // isolasi untuk seluruh suite.
    config(['cache.default' => 'redis', 'queue.default' => 'redis']);

    flushTestRedis();
});

afterEach(function (): void {
    if (extension_loaded('redis')) {
        flushTestRedis();
    }
});

/**
 * Kosongkan DB Redis KHUSUS TEST (REDIS_DB=15 di phpunit.xml).
 *
 * Lock unique bertahan sampai job-nya diproses, dan di sini job tak pernah diproses —
 * jadi sisa dari test sebelumnya akan membuat test berikutnya gagal secara menyesatkan
 * (gejalanya: "antrian 0" padahal dispatch dipanggil).
 *
 * flushdb dipilih, BUKAN menebak-nebak kunci: prefix Redis Laravel berlapis
 * (`{app}_database_` + `{app}_cache_` + `laravel_unique_job:...`) dan pernah membuat
 * upaya pembersihan manual gagal diam-diam. Aman karena DB 15 dipakai test saja —
 * cache & antrian DEV ada di DB lain pada container Redis yang sama.
 */
function flushTestRedis(): void
{
    Redis::connection()->flushdb();
}

it('drops a duplicate reminder while the first is still pending in the queue', function (): void {
    // dispatch() TANPA disimpan ke variabel — PendingDispatch baru benar-benar mendorong
    // job saat objeknya dihancurkan. Menyimpannya ke variabel menunda push sampai variabel
    // itu ditimpa, dan pengukuran antrian jadi meleset satu langkah.
    foreach (range(1, 3) as $ignored) {
        dispatch((new AppointmentReminderJob(appointmentId: 1, slotWindowId: 2))->onQueue('probe'));
    }

    expect(Queue::connection('redis')->size('probe'))->toBe(
        1,
        'ShouldBeUnique harus menahan dispatch kedua & ketiga selama job pertama masih pending.'
    );
});

it('lets a rescheduled reminder through because the slot window is part of the unique id', function (): void {
    // Inti perbaikan ronde 4: appointment SAMA, window BERBEDA = pekerjaan berbeda.
    // Kalau uniqueId() kembali memakai appointment id saja, dispatch kedua akan dibuang
    // diam-diam dan sopir yang jadwalnya dipindah tak pernah diingatkan.
    dispatch((new AppointmentReminderJob(appointmentId: 1, slotWindowId: 2))->onQueue('probe'));
    dispatch((new AppointmentReminderJob(appointmentId: 1, slotWindowId: 3))->onQueue('probe'));

    expect(Queue::connection('redis')->size('probe'))->toBe(
        2,
        'Window berbeda = uniqueId berbeda, jadi reminder hasil reschedule HARUS ikut masuk antrian.'
    );
});

it('stores the unique lock in Redis itself, not in process memory', function (): void {
    dispatch((new AppointmentReminderJob(appointmentId: 7, slotWindowId: 9))->onQueue('probe'));

    // Dicari lewat pola, bukan kunci persis: prefix Redis Laravel berlapis dan menebaknya
    // membuat test rapuh terhadap perubahan APP_NAME/konfigurasi. Yang mau dijaga adalah
    // FAKTA lock-nya ada DI REDIS — inilah yang tak bisa dibuktikan dengan cache `array`,
    // karena di sana tiap proses punya lock sendiri dan duplikat lolos antar-proses.
    $lockKeys = array_values(array_filter(
        Redis::connection()->keys('*'),
        fn (string $key): bool => str_contains($key, 'laravel_unique_job')
            && str_contains($key, '7:9'),
    ));

    expect($lockKeys)->toHaveCount(
        1,
        'Lock unique harus tersimpan di Redis dan memuat uniqueId {appointment}:{window}.'
    );
});
