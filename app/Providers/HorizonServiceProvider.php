<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Only a signed-in super-admin (the `admin` session guard) can open the dashboard.
     * The default guard is not checked on purpose: club users and API tokens never get in.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null): bool {
            return auth('admin')->check();
        });
    }
}
