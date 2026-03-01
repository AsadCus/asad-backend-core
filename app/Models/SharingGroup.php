<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SharingGroup extends Model
{
    protected $fillable = [
        'customer_confirmation_id',
        'sharing_plan',
        'expected_capacity',
        'status',
        'sort_order',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'expected_capacity' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function customerConfirmation(): BelongsTo
    {
        return $this->belongsTo(CustomerConfirmation::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(SharingGroupMember::class)->orderBy('sort_order');
    }

    public function confirmationMembers(): BelongsToMany
    {
        return $this->belongsToMany(
            CustomerConfirmationMember::class,
            'sharing_group_members',
            'sharing_group_id',
            'customer_confirmation_member_id',
        )->withPivot('role_in_group', 'sort_order', 'remarks')
            ->withTimestamps();
    }

    public function manifestSharingGroups(): HasMany
    {
        return $this->hasMany(ManifestSharingGroup::class);
    }
}
