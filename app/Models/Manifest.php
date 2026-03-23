<?php

namespace App\Models;

use App\Helpers\NumberGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Manifest extends Model
{
    protected $fillable = [
        'package_id',
        'in_charge_official_id',
        'manifest_number',
        'notes',
        'ops_movement_extension',
    ];

    protected function casts(): array
    {
        return [
            'ops_movement_extension' => 'array',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function inChargeOfficial(): BelongsTo
    {
        return $this->belongsTo(PackageOfficial::class, 'in_charge_official_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ManifestMember::class)->orderBy('sort_order')->orderBy('id');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(ManifestRoom::class);
    }

    public function files(): MorphMany
    {
        return $this->morphMany(ModelFile::class, 'fileable');
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
