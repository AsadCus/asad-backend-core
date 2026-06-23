<?php

namespace App\Support;

use App\Models\User;

class FeatureFlag
{
    /**
     * Determine whether a feature flag is enabled for the given user.
     *
     * Disabled flags remain accessible to ghost users so they
     * can verify gated features in any environment.
     */
    public static function enabled(string $configKey, ?User $user = null, bool $default = true): bool
    {
        if ((bool) config($configKey, $default)) {
            return true;
        }

        $user ??= auth()->user();

        return $user instanceof User && $user->isGhostUser();
    }
}
