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
        'currency_symbol',
    ];

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class, 'country_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sales::class, 'country_id');
    }

    public function admins(): HasMany
    {
        return $this->hasMany(Admin::class, 'country_id');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(Operation::class, 'country_id');
    }

    public function enquiries(): HasMany
    {
        return $this->hasMany(Enquiry::class, 'country_id');
    }
}
