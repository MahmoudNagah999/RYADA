<?php

namespace App\Providers;

use App\Domain\Shared\Tenancy\CurrentClub;
use App\Models\Admin;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register CurrentClub as a scoped service not singleton, so each request/queue workers gets its own instance.
        $this->app->scoped(CurrentClub::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Super-admins (separate model and guard) bypass club policies. Returning
        // null for everyone else leaves club users to ClubPolicy as before.
        Gate::before(fn ($user) => $user instanceof Admin ? true : null);

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // General API limit: per user when authenticated, per IP otherwise.
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user('sanctum');

            return $user !== null
                ? Limit::perMinute(120)->by('user:'.$user->getAuthIdentifier())
                : Limit::perMinute(60)->by('ip:'.$request->ip());
        });

        // Login: slow down password guessing per email + IP, and cap the IP overall.
        RateLimiter::for('auth', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by('auth-email:'.$email.'|'.$request->ip()),
                Limit::perMinute(20)->by('auth-ip:'.$request->ip()),
            ];
        });

        // Club registration creates data, so it gets a tighter hourly cap per IP.
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by('register-ip:'.$request->ip()));
    }
}
