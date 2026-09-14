<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Samakan $_SERVER dengan $_ENV SEBELUM app test dibuat.
     *
     * `<env ... force="true"/>` di phpunit.xml hanya menulis $_ENV + putenv(), sedangkan
     * env() Laravel membaca $_SERVER LEBIH DULU. Di dalam container, $_SERVER sudah berisi
     * env var service (DB_DATABASE=tas, CACHE_STORE/QUEUE_CONNECTION=redis,
     * BROADCAST_CONNECTION=reverb) — jadi tanpa ini, force tak berefek dan seluruh suite di
     * container diam-diam memakai DB DEV + Redis + Reverb sungguhan sambil tetap hijau.
     * Terbukti D6' lewat probe (env() = "tas" walau $_ENV = "tas_testing") dan lewat satu
     * file test non-paralel yang mengosongkan DB dev (users 7 → 0).
     *
     * $_ENV adalah satu-satunya tempat PHPUnit menaruh nilai yang ia MAKSUD (forced, atau
     * nilai environment yang sudah ada untuk baris tanpa force), jadi menyalinnya ke
     * $_SERVER mengikuti semantik phpunit.xml apa adanya — tak ada daftar kunci kedua
     * yang bisa basi. Di native Windows & CI keduanya sudah sama; ini no-op di sana.
     */
    public function createApplication()
    {
        foreach ($_ENV as $key => $value) {
            $_SERVER[$key] = $value;
        }

        return parent::createApplication();
    }
}
