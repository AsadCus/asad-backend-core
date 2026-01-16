<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterNotes extends Model
{
    protected $fillable = [
        'model',
        'description',
        'sort_order',
    ];
}
