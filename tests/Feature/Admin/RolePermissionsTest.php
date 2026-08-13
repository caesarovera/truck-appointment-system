<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\getJson;
use function Pest\Laravel\putJson;
use function Pest\Laravel\seed;

beforeEach(fn () => seed(RolePermissionSeeder::class));

function adminActor(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    Sanctum::actingAs($admin);

    return $admin;
}

it('lists every role with its current permissions plus the full permission universe', function (): void {
    adminActor();

    $response = getJson('/api/v1/admin/roles')
        ->assertOk()
        ->assertJsonCount(5, 'data');

    $planner = collect($response->json('data'))->firstWhere('name', 'planner');
    expect($planner['permissions'])->toContain('slot.manage', 'report.read')
        ->and($planner['immutable'])->toBeFalse();

    $admin = collect($response->json('data'))->firstWhere('name', 'admin');
    expect($admin['immutable'])->toBeTrue();

    expect($response->json('meta.all_permissions'))->toContain('role.manage', 'appointment.write');
});

it('replaces a non-admin role permission set (sync, not append)', function (): void {
    adminActor();

    putJson('/api/v1/admin/roles/gate-officer/permissions', ['permissions' => ['gate.process', 'report.read']])
        ->assertOk()
        ->assertJsonPath('data.name', 'gate-officer')
        ->assertJsonPath('data.permissions', ['gate.process', 'report.read']);

    // appointment.read & slot.read (permission awal gate-officer) HARUS hilang —
    // ini sync/replace, bukan tambah.
    $role = Role::where('name', 'gate-officer')->where('guard_name', 'api')->first();
    expect($role->permissions->pluck('name')->all())->toBe(['gate.process', 'report.read']);
});

it('refuses to change the admin role permissions (422 role_immutable)', function (): void {
    adminActor();

    putJson('/api/v1/admin/roles/admin/permissions', ['permissions' => ['slot.read']])
        ->assertStatus(422)
        ->assertJsonPath('error', 'role_immutable');

    $role = Role::where('name', 'admin')->where('guard_name', 'api')->first();
    expect($role->permissions->pluck('name')->all())->toContain('user.manage', 'role.manage');
});

it('rejects an unknown permission name (422 validation)', function (): void {
    adminActor();

    putJson('/api/v1/admin/roles/planner/permissions', ['permissions' => ['not.a.real.permission']])
        ->assertStatus(422);
});

it('404s for an unknown role name', function (): void {
    adminActor();

    putJson('/api/v1/admin/roles/not-a-role/permissions', ['permissions' => []])->assertNotFound();
});

it('forbids a non-admin from listing or editing roles (403)', function (): void {
    $planner = User::factory()->create();
    $planner->assignRole('planner');
    Sanctum::actingAs($planner);

    getJson('/api/v1/admin/roles')->assertForbidden();
    putJson('/api/v1/admin/roles/driver/permissions', ['permissions' => []])->assertForbidden();
});

it('requires authentication (401)', function (): void {
    getJson('/api/v1/admin/roles')->assertUnauthorized();
});
