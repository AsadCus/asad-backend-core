<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuotationItem extends Model
{
    protected $table = 'quotation_items';

    protected $fillable = [
        'quotation_id',
        'customer_confirmation_member_id',
        'parent_id',
        'description',
        'is_header',
        'quantity',
        'rate',
        'sort_order',
    ];

    protected $casts = [
        'is_header' => 'boolean',
        'quantity' => 'decimal:2',
        'rate' => 'decimal:2',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function confirmationMember(): BelongsTo
    {
        return $this->belongsTo(CustomerConfirmationMember::class, 'customer_confirmation_member_id');
    }

    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class, 'invoice_items');
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(QuotationItemTax::class, 'quotation_item_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
