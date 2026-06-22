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
        // Ghosts and full-access roles implicitly pass every permission check, current and
        // future. Ghost power comes from the flag (a Gate::before bypass), never from a role —
        // so no amount of role editing can weaken a ghost.
        Gate::before(function (User $user, string $ability): ?bool {
            return ($user->isGhostUser() || $user->hasFullAccessRole()) ? true : null;
        });
    }
}
