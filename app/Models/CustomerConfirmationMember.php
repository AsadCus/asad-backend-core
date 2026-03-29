<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerConfirmationMember extends Model
{
    protected $table = 'customer_confirmation_members';

    protected $fillable = [
        'customer_confirmation_id',
        'customer_id',
        'is_leader',
        'status',
        'sharing_plan',
        'relationship',
    ];

    protected function casts(): array
    {
        return [
            'is_leader' => 'boolean',
            'sharing_plan' => 'string',
            'relationship' => 'string',
        ];
    }

    public function confirmation(): BelongsTo
    {
        return $this->belongsTo(CustomerConfirmation::class, 'customer_confirmation_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function manifestMembers(): HasMany
    {
        return $this->hasMany(ManifestMember::class, 'customer_confirmation_member_id');
    }

    public function quotationItems(): HasMany
    {
        return $this->hasMany(QuotationItem::class, 'customer_confirmation_member_id');
    }
}
