<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'address',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function maids(): HasMany
    {
        return $this->hasMany(Maid::class, 'supplier_id');
    }

    public function getTotalCostOfMaid()
    {
        return $this->maids()->sum('cost_of_maid');
    }
}
