<?php

namespace App\Models;

use App\Helpers\NumberGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        return $this->hasMany(ManifestMember::class)->orderBy('sort_order')->orderBy('id');
    }

    public function members(): HasMany
    {
        return $this->travelers();
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
        return $this->hasMany(ManifestSharingGroup::class)->orderBy('sort_order')->orderBy('id');
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
