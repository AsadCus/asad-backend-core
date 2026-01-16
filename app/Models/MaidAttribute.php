<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaidAttribute extends Model
{
    protected $fillable = [
        'maid_id',
        'attribute_category',
        'attribute_name',
        'details',
    ];

    public function maid(): BelongsTo
    {
        return $this->belongsTo(Maid::class, 'maid_id');
    }
}
