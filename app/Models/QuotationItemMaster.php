<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuotationItemMaster extends Model
{
    protected $table = 'quotation_item_masters';

    protected $fillable = [
        'parent_id',
        'description',
        'is_header',
        'is_optional',
        'quantity',
        'rate',
        'sort_order',
    ];

    protected $casts = [
        'is_header' => 'boolean',
        'is_optional' => 'boolean',
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
}
