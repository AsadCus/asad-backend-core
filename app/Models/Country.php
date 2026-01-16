<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'adjective',
    ];

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class, 'country_id');
    }

    public function maids(): HasMany
    {
        return $this->hasMany(Maid::class, 'country_id');
    }
}
