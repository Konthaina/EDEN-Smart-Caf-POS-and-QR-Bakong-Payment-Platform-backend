<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Where users are redirected after authentication.
     */
    public const HOME = '/dashboard';

    /**
     * Define your route model bindings, pattern filters, and route groups.
     */
    public function boot(): void
    {
        // OTP-only: no ResetPassword::createUrlUsing(...) needed anymore.

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
