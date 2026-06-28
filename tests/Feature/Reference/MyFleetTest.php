<?php

declare(strict_types=1);

use App\Models\TransportCompany;
use App\Models\Truck;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\seed;

beforeEach(fn () => seed(RolePermissionSeeder::class));

/** Bikin company + N truk + N sopir (role driver). */
function fleetCompany(int $trucks = 2, int $drivers = 2): TransportCompany
{
    $company = TransportCompany::factory()->create();
    Truck::factory()->count($trucks)->create(['company_id' => $company->id]);
    User::factory()->count($drivers)->create(['company_id' => $company->id])
        ->each(fn (User $u) => $u->assignRole('driver'));

    return $company;
}

it('returns only the transporter own company trucks and drivers', function (): void {
    $mine = fleetCompany(trucks: 2, drivers: 2);
    fleetCompany(trucks: 3, drivers: 3); // company lain → tidak boleh bocor

    $dispatcher = User::factory()->create(['company_id' => $mine->id]);
    $dispatcher->assignRole('transporter');
    Sanctum::actingAs($dispatcher);

    getJson('/api/v1/me/fleet')
        ->assertOk()
        ->assertJsonCount(2, 'data.trucks')
        ->assertJsonCount(2, 'data.drivers')
        ->assertJsonStructure(['data' => ['trucks' => [['id', 'plate_no', 'status']], 'drivers' => [['id', 'name']]]]);
});

it('does not count the transporter themselves as a driver', function (): void {
    $company = TransportCompany::factory()->create();
    Truck::factory()->create(['company_id' => $company->id]);
    // Tidak ada user role driver di company ini.

    $dispatcher = User::factory()->create(['company_id' => $company->id]);
    $dispatcher->assignRole('transporter');
    Sanctum::actingAs($dispatcher);

    getJson('/api/v1/me/fleet')
        ->assertOk()
        ->assertJsonCount(1, 'data.trucks')
        ->assertJsonCount(0, 'data.drivers');
});

it('forbids a planner without fleet.manage (403)', function (): void {
    $planner = User::factory()->create();
    $planner->assignRole('planner');
    Sanctum::actingAs($planner);

    getJson('/api/v1/me/fleet')->assertForbidden();
});

it('requires authentication (401)', function (): void {
    getJson('/api/v1/me/fleet')->assertUnauthorized();
});
