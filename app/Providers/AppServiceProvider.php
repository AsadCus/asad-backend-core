<?php

namespace App\Providers;

use App\Models\User;
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
        // The ghost relation is the lone bypass: a ghost implicitly passes every permission
        // check, current and future. It comes purely from the ghost_users relation, never from
        // a role — so no amount of role editing can grant or weaken it.
        Gate::before(function (User $user, string $ability): ?bool {
            return $user->isGhostUser() ? true : null;
        });
    }
}
