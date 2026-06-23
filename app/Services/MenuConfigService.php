<?php

namespace App\Services;

use App\Models\MenuOverride;
use App\Models\MenuUserPreference;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MenuConfigService
{
    /** Persona roles, ordered most → least privileged (mirrors the frontend resolveRealRole). */
    private const KNOWN_ROLES = ['administrator', 'hr', 'supervisor', 'manager', 'employee'];

    /**
     * The full menu config for one user: global admin overrides, the user's own preferences,
     * and the role-default favourites the frontend merges on top of its NAV_ZONES registry.
     *
     * @return array{overrides: array, myPrefs: array, roleDefaultFavorites: array}
     */
    public function configFor(User $user): array
    {
        return [
            'overrides' => MenuOverride::query()->get([
                'menu_key', 'label', 'icon', 'zone', 'sort_order', 'is_hidden', 'permission',
            ])->map(fn (MenuOverride $o) => [
                'menu_key' => $o->menu_key,
                'label' => $o->label,
                'icon' => $o->icon,
                'zone' => $o->zone,
                'sort_order' => $o->sort_order,
                'is_hidden' => $o->is_hidden,
                'permission' => $o->permission,
            ])->all(),

            'myPrefs' => MenuUserPreference::query()
                ->where('user_id', $user->id)
                ->get(['menu_key', 'is_favorite', 'is_hidden', 'sort_order'])
                ->map(fn (MenuUserPreference $p) => [
                    'menu_key' => $p->menu_key,
                    'is_favorite' => $p->is_favorite,
                    'is_hidden' => $p->is_hidden,
                    'sort_order' => $p->sort_order,
                ])->all(),

            'roleDefaultFavorites' => $this->roleDefaultFavorites($user),
        ];
    }

    /**
     * Replace the global override set with the supplied rows (upsert by menu_key, prune the rest).
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function saveOverrides(array $rows): void
    {
        DB::transaction(function () use ($rows) {
            $keptKeys = [];

            foreach ($rows as $row) {
                $menuKey = $row['menu_key'];
                $keptKeys[] = $menuKey;

                MenuOverride::updateOrCreate(
                    ['menu_key' => $menuKey],
                    [
                        'label' => $row['label'] ?? null,
                        'icon' => $row['icon'] ?? null,
                        'zone' => $row['zone'] ?? null,
                        'sort_order' => $row['sort_order'] ?? null,
                        'is_hidden' => $row['is_hidden'] ?? false,
                        'permission' => $row['permission'] ?? null,
                    ],
                );
            }

            MenuOverride::query()->whereNotIn('menu_key', $keptKeys)->delete();
        });
    }

    /**
     * Upsert the user's own preference rows by (user_id, menu_key). Each call replaces the
     * supplied keys only — preferences for keys not in the payload are left untouched.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function savePreferences(User $user, array $rows): void
    {
        DB::transaction(function () use ($user, $rows) {
            foreach ($rows as $row) {
                // Only touch the columns actually present in the row, so toggling a favourite
                // doesn't wipe the user's personal hide/order (and vice versa).
                $attributes = [];
                foreach (['is_favorite', 'is_hidden', 'sort_order'] as $field) {
                    if (array_key_exists($field, $row)) {
                        $attributes[$field] = $row[$field];
                    }
                }

                MenuUserPreference::updateOrCreate(
                    ['user_id' => $user->id, 'menu_key' => $row['menu_key']],
                    $attributes,
                );
            }
        });
    }

    /** @return array<int, string> */
    private function roleDefaultFavorites(User $user): array
    {
        $map = (array) config('menu.default_favorites', []);

        foreach (self::KNOWN_ROLES as $role) {
            if ($user->hasRole($role) || ($role === 'administrator' && $user->hasAnyRole(['admin', 'superadmin']))) {
                return $map[$role] ?? [];
            }
        }

        return $map['employee'] ?? [];
    }
}
