<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NumberingSequence extends Model
{
    protected $fillable = [
        'model_key',
        'sequence_key',
        'sequence_year',
        'current_number',
    ];

    protected function casts(): array
    {
        return [
            'current_number' => 'integer',
        ];
    }
}
