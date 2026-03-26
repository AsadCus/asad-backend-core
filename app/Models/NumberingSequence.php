<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NumberingSequence extends Model
{
    protected $fillable = [
        'model_key',
        'sequence_key',
        'numbering_format_id',
        'sequence_year',
        'current_number',
    ];

    protected function casts(): array
    {
        return [
            'numbering_format_id' => 'integer',
            'current_number' => 'integer',
        ];
    }

    public function format(): BelongsTo
    {
        return $this->belongsTo(NumberingFormat::class, 'numbering_format_id');
    }
}
