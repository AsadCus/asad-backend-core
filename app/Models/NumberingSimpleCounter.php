<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NumberingSimpleCounter extends Model
{
    protected $fillable = [
        'model_key',
        'latest_number',
    ];
}
