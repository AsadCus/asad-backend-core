<?php

namespace Database\Factories;

use App\Models\MenuUserPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MenuUserPreference>
 */
class MenuUserPreferenceFactory extends Factory
{
    protected $model = MenuUserPreference::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'menu_key' => 'nav.'.Str::random(8),
            'is_favorite' => null,
            'is_hidden' => null,
            'sort_order' => null,
        ];
    }
}
