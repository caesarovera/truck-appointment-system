<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class, // ← RBAC duluan: role & permission harus ada
            DemoSeeder::class,           //   sebelum DemoSeeder assign role ke user
        ]);
    }
}
