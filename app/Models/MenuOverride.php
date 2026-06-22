<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MenuOverride extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'menu_key',
        'label',
        'icon',
        'zone',
        'sort_order',
        'is_hidden',
        'roles',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_hidden' => 'boolean',
            'roles' => 'array',
        ];
    }
}
