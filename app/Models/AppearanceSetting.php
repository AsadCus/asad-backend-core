<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppearanceSetting extends Model
{
    protected $fillable = [
        'auth_bg',
        'auth_card_bg',
        'primary_color',
        'border_radius',
    ];

    protected $casts = [
        'primary_color' => 'string',
        'border_radius' => 'string',
    ];
}
