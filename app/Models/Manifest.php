<?php

namespace App\Models;

use App\Helpers\NumberGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Manifest extends Model
{
    protected $fillable = [
        'package_id',
        'manifest_number',
        'notes',
        'status',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function travelers(): HasMany
    {
        return $this->hasMany(ManifestTraveler::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(ManifestRoom::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ManifestPayment::class);
    }

    public function manifestSharingGroups(): HasMany
    {
        return $this->hasMany(ManifestSharingGroup::class);
    }

    public function accommodationAssignments(): HasMany
    {
        return $this->hasMany(ManifestAccommodationAssignment::class);
    }

    public function sharingGroups(): BelongsToMany
    {
        return $this->belongsToMany(
            SharingGroup::class,
            'manifest_sharing_groups',
            'manifest_id',
            'sharing_group_id',
        )->withPivot('manifest_room_id')
            ->withTimestamps();
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($manifest) {
            if (empty($manifest->manifest_number)) {
                $manifest->manifest_number = NumberGenerator::generate('manifest');
            }
        });
    }
}
