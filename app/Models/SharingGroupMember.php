<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharingGroupMember extends Model
{
    protected $fillable = [
        'sharing_group_id',
        'customer_confirmation_member_id',
        'role_in_group',
        'sort_order',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function sharingGroup(): BelongsTo
    {
        return $this->belongsTo(SharingGroup::class);
    }

    public function confirmationMember(): BelongsTo
    {
        return $this->belongsTo(CustomerConfirmationMember::class, 'customer_confirmation_member_id');
    }
}
