<?php

namespace App\Providers;

use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * جسر توافق: الصلاحيات القديمة ↔ الجديدة.
         * لا يستدعي can() لتجنب recursion — يعتمد على getAllPermissions().
         */
        Gate::before(function ($user, string $ability) {
            if (! $user instanceof User) {
                return null;
            }

            $names = $user->getAllPermissions()->pluck('name');

            foreach (PermissionCatalog::legacyMap() as $legacy => $mapped) {
                if ($ability === $legacy && $names->intersect($mapped)->isNotEmpty()) {
                    return true;
                }

                if (in_array($ability, $mapped, true) && $names->contains($legacy)) {
                    return true;
                }
            }

            return null;
        });
    }
}
