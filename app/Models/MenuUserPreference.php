<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuUserPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'menu_key',
        'is_favorite',
        'is_hidden',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_favorite' => 'boolean',
            'is_hidden' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
