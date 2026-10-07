<?php

declare(strict_types=1);

use App\Models\Terminal;
use App\Models\TransportCompany;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create()->assignRole('admin');
});

it('lists all users with roles', function (): void {
    User::factory()->create(['name' => 'Planner'])->assignRole('planner');

    $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/users')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'email', 'role']]]);
});

it('filters users by role', function (): void {
    User::factory()->create()->assignRole('planner');
    User::factory()->create()->assignRole('driver');

    $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/users?role=planner')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('returns 403 for non-admin on user list', function (): void {
    $planner = User::factory()->create()->assignRole('planner');

    $this->actingAs($planner)
        ->getJson('/api/v1/admin/users')
        ->assertForbidden();
});

it('creates a user with role', function (): void {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/users', [
            'name' => 'New Planner',
            'email' => 'newplanner@tas.test',
            'password' => 'Password123x',
            'role' => 'planner',
        ])
        ->assertCreated()
        ->assertJsonPath('data.role', 'planner');

    $this->assertDatabaseHas('users', ['email' => 'newplanner@tas.test']);
});

it('creates gate-officer with terminal assignment', function (): void {
    $terminal = Terminal::factory()->create();

    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/users', [
            'name' => 'Gate Officer',
            'email' => 'officer@tas.test',
            'password' => 'Password123x',
            'role' => 'gate-officer',
            'terminal_id' => $terminal->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.terminal_id', $terminal->id);
});

it('creates transporter with company assignment', function (): void {
    $company = TransportCompany::factory()->create();

    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/users', [
            'name' => 'Dispatcher',
            'email' => 'dispatcher@company.test',
            'password' => 'Password123x',
            'role' => 'transporter',
            'company_id' => $company->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.company_id', $company->id);
});

it('rejects duplicate email on create', function (): void {
    User::factory()->create(['email' => 'taken@tas.test']);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/users', [
            'name' => 'X',
            'email' => 'taken@tas.test',
            'password' => 'Password123x',
            'role' => 'planner',
        ])
        ->assertUnprocessable();
});

it('updates a user and changes role', function (): void {
    $user = User::factory()->create(['email' => 'old@tas.test'])->assignRole('planner');

    $this->actingAs($this->admin)
        ->putJson("/api/v1/admin/users/{$user->id}", [
            'name' => 'Updated',
            'email' => 'old@tas.test',
            'role' => 'gate-officer',
        ])
        ->assertOk()
        ->assertJsonPath('data.role', 'gate-officer');
});

it('updates user without changing password when omitted', function (): void {
    $user = User::factory()->create()->assignRole('planner');
    $oldHash = $user->password;

    $this->actingAs($this->admin)
        ->putJson("/api/v1/admin/users/{$user->id}", [
            'name' => 'Updated',
            'email' => $user->email,
            'role' => 'planner',
        ])
        ->assertOk();

    expect($user->fresh()->password)->toBe($oldHash);
});

it('revokes every token of a user when admin changes their password', function (): void {
    $driver = User::factory()->create()->assignRole('driver');
    $stolen = $driver->createToken('phone')->plainTextToken;
    $driver->createToken('tablet');
    $adminToken = $this->admin->createToken('admin')->plainTextToken;

    $this->withToken($adminToken)
        ->putJson("/api/v1/admin/users/{$driver->id}", [
            'name' => $driver->name,
            'email' => $driver->email,
            'role' => 'driver',
            'password' => 'Password-baru-123',
        ])
        ->assertOk();

    expect($driver->tokens()->count())->toBe(0);

    app('auth')->forgetGuards();
    $this->withToken($stolen)->getJson('/api/v1/me')->assertUnauthorized();
});

it('keeps tokens when the password is not changed', function (): void {
    $driver = User::factory()->create()->assignRole('driver');
    $driver->createToken('phone');

    $this->withToken($this->admin->createToken('admin')->plainTextToken)
        ->putJson("/api/v1/admin/users/{$driver->id}", [
            'name' => 'Nama Baru',
            'email' => $driver->email,
            'role' => 'driver',
        ])
        ->assertOk();

    expect($driver->tokens()->count())->toBe(1);
});

it('keeps the current token but revokes others when admin changes own password', function (): void {
    $current = $this->admin->createToken('laptop');
    $other = $this->admin->createToken('old-phone');

    $this->withToken($current->plainTextToken)
        ->putJson("/api/v1/admin/users/{$this->admin->id}", [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'role' => 'admin',
            'password' => 'Password-baru-123',
        ])
        ->assertOk();

    expect($this->admin->tokens()->pluck('id')->all())->toBe([$current->accessToken->id])
        ->and($other->accessToken->fresh())->toBeNull();

    app('auth')->forgetGuards();
    $this->withToken($current->plainTextToken)->getJson('/api/v1/me')->assertOk();
});

it('deletes a user', function (): void {
    $user = User::factory()->create()->assignRole('planner');

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/admin/users/{$user->id}")
        ->assertNoContent();

    $this->assertModelMissing($user);
});

it('refuses to delete own account', function (): void {
    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/admin/users/{$this->admin->id}")
        ->assertUnprocessable();
});

// Kebijakan password (Password::defaults() di AppServiceProvider): min 10, huruf besar+kecil, angka.
// Tanpa konfigurasi, Password::defaults() == min:8 → tanpa test ini penggantian `min:8` tak berarti apa-apa.
dataset('weak passwords', [
    'terlalu pendek (9)' => 'Abcdefg12',
    'tanpa huruf besar' => 'abcdefghij12',
    'tanpa huruf kecil' => 'ABCDEFGHIJ12',
    'tanpa angka' => 'Abcdefghijkl',
]);

it('rejects a weak password on create', function (string $password): void {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/users', [
            'name' => 'Weak',
            'email' => 'weak@tas.test',
            'password' => $password,
            'role' => 'planner',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('password');

    $this->assertDatabaseMissing('users', ['email' => 'weak@tas.test']);
})->with('weak passwords');

it('rejects a weak password on update and keeps the old hash', function (string $password): void {
    $user = User::factory()->create()->assignRole('planner');
    $oldHash = $user->password;

    $this->actingAs($this->admin)
        ->putJson("/api/v1/admin/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'planner',
            'password' => $password,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('password');

    expect($user->fresh()->password)->toBe($oldHash);
})->with('weak passwords');

it('accepts a password that meets the policy exactly', function (): void {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/users', [
            'name' => 'Strong',
            'email' => 'strong@tas.test',
            'password' => 'Abcdefghi1',
            'role' => 'planner',
        ])
        ->assertCreated();
});

// Email non-ASCII ditolak di login (kolasi MySQL menyamakannya dengan padanan ASCII → bypass throttle),
// jadi akun ber-email non-ASCII tak boleh dibuat — kalau tidak, ia tak akan pernah bisa login.
it('rejects a non-ascii email on create and update', function (): void {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/users', [
            'name' => 'Aksen',
            'email' => 'plánner@tas.test',
            'password' => 'Abcdefghi1',
            'role' => 'planner',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('email');

    $user = User::factory()->create()->assignRole('planner');

    $this->actingAs($this->admin)
        ->putJson("/api/v1/admin/users/{$user->id}", [
            'name' => $user->name,
            'email' => 'plánner@tas.test',
            'role' => 'planner',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('email');
});

it('answers 422, not 500, when the admin sends a non-string email', function (): void {
    $user = User::factory()->create()->assignRole('planner');

    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/users', ['name' => 'X', 'email' => ['a@tas.test'], 'password' => 'Abcdefghi1', 'role' => 'planner'])
        ->assertUnprocessable()->assertJsonValidationErrorFor('email');

    $this->actingAs($this->admin)
        ->putJson("/api/v1/admin/users/{$user->id}", ['name' => 'X', 'email' => ['a@tas.test'], 'role' => 'planner'])
        ->assertUnprocessable()->assertJsonValidationErrorFor('email');
});
