<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ManifestMember extends Model
{
    protected $table = 'manifest_members';

    protected $fillable = [
        'manifest_id',
        'manifest_sharing_group_id',
        'customer_confirmation_member_id',
        'package_official_id',
        'role',
        'sharing_plan',
        'name',
        'arabic_name',
        'contact_number',
        'nationality',
        'passport_number',
        'gender',
        'date_of_birth',
        'passport_issue_date',
        'passport_expiry_date',
        'passport_place_of_issue',
        'place_of_birth',
        'address',
        'first_time_umrah',
        'has_chronic_disease',
        'chronic_disease_details',
        'passport_path',
        'photo_path',
        'sort_order',
        'remarks',
    ];

    protected $casts = [
        'manifest_sharing_group_id' => 'integer',
        'customer_confirmation_member_id' => 'integer',
        'package_official_id' => 'integer',
        'date_of_birth' => 'date',
        'passport_issue_date' => 'date',
        'passport_expiry_date' => 'date',
        'first_time_umrah' => 'boolean',
        'has_chronic_disease' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(Manifest::class);
    }

    public function confirmationMember(): BelongsTo
    {
        return $this->belongsTo(CustomerConfirmationMember::class, 'customer_confirmation_member_id');
    }

    public function sharingGroup(): BelongsTo
    {
        return $this->belongsTo(ManifestSharingGroup::class, 'manifest_sharing_group_id');
    }

    public function packageOfficial(): BelongsTo
    {
        return $this->belongsTo(PackageOfficial::class, 'package_official_id');
    }

    public function roomAssignments(): HasMany
    {
        return $this->hasMany(ManifestRoomMember::class, 'manifest_member_id');
    }

    public function roomMembers(): HasMany
    {
        return $this->roomAssignments();
    }

    public function collectionItem(): HasOne
    {
        return $this->hasOne(ManifestMemberCollectionItem::class, 'manifest_member_id');
    }

    public function files(): MorphMany
    {
        return $this->morphMany(ModelFile::class, 'fileable');
    }
}
