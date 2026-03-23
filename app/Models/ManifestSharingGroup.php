<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManifestSharingGroup extends Model
{
    protected $fillable = [
        'manifest_id',
        'customer_confirmation_id',
        'source_quotation_id',
        'sort_order',
        'group_relationship',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'source_quotation_id' => 'integer',
        ];
    }

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(Manifest::class);
    }

    public function customerConfirmation(): BelongsTo
    {
        return $this->belongsTo(CustomerConfirmation::class, 'customer_confirmation_id');
    }

    public function sourceQuotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'source_quotation_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ManifestMember::class, 'manifest_sharing_group_id')->orderBy('sort_order')->orderBy('id');
    }
}
