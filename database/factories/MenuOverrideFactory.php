<?php

namespace Database\Factories;

use App\Models\MenuOverride;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MenuOverride>
 */
class MenuOverrideFactory extends Factory
{
    protected $model = MenuOverride::class;

    public function definition(): array
    {
        return [
            'menu_key' => 'nav.'.Str::random(8),
            'label' => null,
            'icon' => null,
            'zone' => null,
            'sort_order' => null,
            'is_hidden' => false,
            'roles' => null,
        ];
    }
}
