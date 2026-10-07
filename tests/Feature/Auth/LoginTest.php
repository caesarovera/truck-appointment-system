<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

use function Pest\Laravel\postJson;
use function Pest\Laravel\seed;

beforeEach(fn () => seed(RolePermissionSeeder::class));

it('issues a token with role-based abilities on valid credentials', function (): void {
    $user = User::factory()->create(['email' => 'planner@tas.test']);
    $user->assignRole('planner');

    $response = postJson('/api/v1/login', [
        'email' => 'planner@tas.test',
        'password' => 'password',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'roles', 'permissions']])
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.roles', ['planner']);

    // SPA (stores/auth.ts) membaca bentuk FLAT ini — pembungkus `data` akan merusak login.
    expect(array_keys($response->json()))->toBe(['token', 'token_type', 'user']);

    // Token abilities mencerminkan permission role (scope per role).
    $token = PersonalAccessToken::query()->firstOrFail();
    expect($token->abilities)->toContain('slot.manage')
        ->and($token->abilities)->not->toContain('gate.process');
});

it('rejects invalid password (422)', function (): void {
    $user = User::factory()->create(['email' => 'planner@tas.test']);
    $user->assignRole('planner');

    postJson('/api/v1/login', [
        'email' => 'planner@tas.test',
        'password' => 'wrong-password',
    ])->assertStatus(422)->assertJsonValidationErrorFor('email');

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('rejects unknown email with a generic error (422)', function (): void {
    postJson('/api/v1/login', [
        'email' => 'nobody@tas.test',
        'password' => 'password',
    ])->assertStatus(422)->assertJsonValidationErrorFor('email');
});

// Selisih waktu login = oracle enumerasi email (audit 2026-09-16). Jalur "email tak terdaftar"
// wajib membayar satu Hash::check terhadap hash bcrypt SUNGGUHAN ber-cost sama dengan konfigurasi.
// Menghitung panggilan saja tak cukup: hash tiruan '' membuat bcrypt langsung `false` (±1,5 ms) dan
// test hitung-panggilan tetap hijau (terbukti lewat mutasi, review 2026-10-07). Stopwatch bcrypt
// cost 4 di test tak bisa dibedakan dari derau → yang diperiksa adalah ARGUMEN hash-nya.
function loginHashSpy(): object
{
    $spy = new class(app('hash'))
    {
        /** @var list<string> */
        public array $checked = [];

        public function __construct(private readonly object $inner) {}

        public function check(string $value, string $hashed, array $options = []): bool
        {
            $this->checked[] = $hashed;

            return $this->inner->check($value, $hashed, $options);
        }

        /** @param array<int, mixed> $arguments */
        public function __call(string $method, array $arguments): mixed
        {
            return $this->inner->{$method}(...$arguments);
        }
    };
    Hash::swap($spy);

    return $spy;
}

function bcryptCost(string $hash): ?int
{
    $info = password_get_info($hash);

    return $info['algoName'] === 'bcrypt' ? $info['options']['cost'] : null;
}

it('checks an unknown email against a real hash of the configured cost (no timing oracle)', function (): void {
    $spy = loginHashSpy();

    postJson('/api/v1/login', [
        'email' => 'nobody@tas.test',
        'password' => 'password',
    ])->assertStatus(422)->assertJsonValidationErrorFor('email');

    expect($spy->checked)->toHaveCount(1)
        ->and(bcryptCost($spy->checked[0]))->toBe((int) config('hashing.bcrypt.rounds'));
});

it('rebuilds the dummy hash when the hashing cost changes', function (): void {
    config(['hashing.bcrypt.rounds' => 4]);
    $spy = loginHashSpy();
    Hash::forgetDrivers();

    postJson('/api/v1/login', ['email' => 'nobody@tas.test', 'password' => 'password']);

    config(['hashing.bcrypt.rounds' => 5]);
    Hash::forgetDrivers();

    postJson('/api/v1/login', ['email' => 'other@tas.test', 'password' => 'password']);

    expect(array_map(bcryptCost(...), $spy->checked))->toBe([4, 5]);
});
// Pertahanan berlapis: walau hash tiruan "cocok" (mustahil secara praktis), email tak terdaftar
// tetap ditolak — tanpa guard `$user === null` login lanjut dengan user null (fatal).
it('never logs in an unknown email even if the dummy hash check passes', function (): void {
    Hash::shouldReceive('make')->andReturn('dummy-hash');
    Hash::shouldReceive('check')->once()->andReturnTrue();

    postJson('/api/v1/login', [
        'email' => 'nobody@tas.test',
        'password' => 'password',
    ])->assertStatus(422)->assertJsonValidationErrorFor('email');

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('pays exactly one hash check when the password is wrong', function (): void {
    $user = User::factory()->create(['email' => 'planner@tas.test']);
    $user->assignRole('planner');

    $spy = loginHashSpy();

    postJson('/api/v1/login', [
        'email' => 'planner@tas.test',
        'password' => 'wrong-password',
    ])->assertStatus(422)->assertJsonValidationErrorFor('email');

    expect($spy->checked)->toHaveCount(1);
});

it('validates required fields', function (): void {
    postJson('/api/v1/login', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});
