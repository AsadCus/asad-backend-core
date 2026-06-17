<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'country_id',
        'address',
        'phone',
        'latitude',
        'longitude',
        'geofence_radius_meters',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'geofence_radius_meters' => 'integer',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sales::class, 'branch_id');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'branch_id');
    }

    public function admins(): HasMany
    {
        return $this->hasMany(Admin::class, 'branch_id');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(Operation::class, 'branch_id');
    }

    public function enquiries(): HasMany
    {
        return $this->hasMany(Enquiry::class, 'branch_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'branch_id');
    }
}
