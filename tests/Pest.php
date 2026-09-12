<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

// Konkurensi (ADR-0007 slice D1) TIDAK boleh memakai RefreshDatabase.
// RefreshDatabase membungkus tiap test dalam satu transaksi yang di-rollback di akhir,
// jadi baris yang dibuat test BELUM ter-commit — dan proses PHP lain (yang punya koneksi
// sendiri) tak akan pernah melihatnya. Anak proses akan gagal dengan "slot window tak
// ditemukan", bukan menguji perebutan kuota.
// DatabaseTruncation meng-commit sungguhan lalu mengosongkan tabel setelah test.
pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->in('Concurrency');
