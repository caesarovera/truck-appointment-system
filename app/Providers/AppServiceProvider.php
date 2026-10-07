<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\AppointmentRepositoryInterface;
use App\Contracts\AuditRepositoryInterface;
use App\Contracts\CompanyRepositoryInterface;
use App\Contracts\FleetRepositoryInterface;
use App\Contracts\GateEventGateway;
use App\Contracts\GateRepositoryInterface;
use App\Contracts\RoleRepositoryInterface;
use App\Contracts\SlotRepositoryInterface;
use App\Contracts\TerminalRepositoryInterface;
use App\Contracts\UserRepositoryInterface;
use App\Repositories\AppointmentRepository;
use App\Repositories\AuditRepository;
use App\Repositories\CompanyRepository;
use App\Repositories\FleetRepository;
use App\Repositories\GateRepository;
use App\Repositories\RoleRepository;
use App\Repositories\SlotRepository;
use App\Repositories\TerminalRepository;
use App\Repositories\UserRepository;
use App\Services\LoggingGateEventGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Response;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Data layer: Contracts → Eloquent impl.
        $this->app->bind(SlotRepositoryInterface::class, SlotRepository::class);
        $this->app->bind(AppointmentRepositoryInterface::class, AppointmentRepository::class);
        $this->app->bind(GateRepositoryInterface::class, GateRepository::class);
        $this->app->bind(FleetRepositoryInterface::class, FleetRepository::class);
        $this->app->bind(TerminalRepositoryInterface::class, TerminalRepository::class);
        $this->app->bind(CompanyRepositoryInterface::class, CompanyRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(AuditRepositoryInterface::class, AuditRepository::class);
        $this->app->bind(RoleRepositoryInterface::class, RoleRepository::class);

        // TOS seam: default log; swap saat integrasi TOS riil.
        $this->app->bind(GateEventGateway::class, LoggingGateEventGateway::class);
    }

    public function boot(): void
    {
        // Catch N+1s in dev/test, never crash production (CLAUDE.md hardening).
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventAccessingMissingAttributes(! $this->app->isProduction());
        // Kolom guarded (status/version/booked_count, ADR-0004) yang di-mass-assign
        // harus MELEDAK di dev/test, bukan dibuang diam-diam → bug ketahuan dini.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // Tanpa ini Password::defaults() == min(8) saja. Dipakai Create/UpdateUserRequest.
        Password::defaults(fn (): Password => Password::min(10)->mixedCase()->numbers());

        $this->configureRateLimiters();
    }

    /**
     * Named limiter (CLAUDE.md → Hardening §rate limit). Dipasang di routes/api.php.
     * Kunci by user id; fallback ip untuk endpoint publik (login).
     */
    private function configureRateLimiters(): void
    {
        $limits = config('tas.rate_limits');

        // Dua bucket: per email+ip (brute-force satu akun) dan per ip (password spraying lintas email).
        // Email dinormalisasi: kolasi MySQL menyamakan `Planner@` dan `planner@`, jadi variasi kapital
        // harus berbagi satu kuota. Aksen/fullwidth/ß juga disamakan kolasi, tapi TIDAK ditangani di
        // sini: email non-ASCII ditolak aturan `ascii` (LoginRequest) sebelum mencapai pencarian user.
        // Bukan string (mis. array) → '' agar validasi yang menjawab 422, bukan 500.
        // Bucket per-ip hanya menghitung upaya GAGAL (`after`), supaya banyak petugas di satu NAT yang
        // login bersamaan tak saling mengunci. Kunci per-email sengaja memuat ip: akun tak bisa dikunci
        // dari luar oleh orang yang menebak emailnya.
        RateLimiter::for('login', function (Request $request): array {
            $email = $request->input('email');
            $email = is_string($email) ? Str::lower(trim($email)) : '';

            return [
                Limit::perMinute((int) config('tas.rate_limits.login'))->by('email:'.$email.'|'.$request->ip()),
                Limit::perMinute((int) config('tas.rate_limits.login_ip'))
                    ->by('ip:'.$request->ip())
                    ->after(fn (Response $response): bool => $response->getStatusCode() !== 201),
            ];
        });

        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute($limits['api'])
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('booking', fn (Request $request): Limit => Limit::perMinute($limits['booking'])
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
