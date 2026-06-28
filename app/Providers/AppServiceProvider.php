<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\AppointmentRepositoryInterface;
use App\Contracts\CompanyRepositoryInterface;
use App\Contracts\FleetRepositoryInterface;
use App\Contracts\GateEventGateway;
use App\Contracts\GateRepositoryInterface;
use App\Contracts\SlotRepositoryInterface;
use App\Contracts\TerminalRepositoryInterface;
use App\Contracts\UserRepositoryInterface;
use App\Repositories\AppointmentRepository;
use App\Repositories\CompanyRepository;
use App\Repositories\FleetRepository;
use App\Repositories\GateRepository;
use App\Repositories\SlotRepository;
use App\Repositories\TerminalRepository;
use App\Repositories\UserRepository;
use App\Services\LoggingGateEventGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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

        // TOS seam: default log; swap saat integrasi TOS riil.
        $this->app->bind(GateEventGateway::class, LoggingGateEventGateway::class);
    }

    public function boot(): void
    {
        // Catch N+1s in dev/test, never crash production (CLAUDE.md hardening).
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventAccessingMissingAttributes(! $this->app->isProduction());

        $this->configureRateLimiters();
    }

    /**
     * Named limiter (CLAUDE.md → Hardening §rate limit). Dipasang di routes/api.php.
     * Kunci by user id; fallback ip untuk endpoint publik (login).
     */
    private function configureRateLimiters(): void
    {
        $limits = config('tas.rate_limits');

        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute($limits['login'])
            ->by($request->input('email').'|'.$request->ip()));

        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute($limits['api'])
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('booking', fn (Request $request): Limit => Limit::perMinute($limits['booking'])
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
