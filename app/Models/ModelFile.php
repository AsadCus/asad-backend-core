<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ModelFile extends Model
{
    protected $fillable = [
        'field',
        'file_name',
        'file_path',
    ];

    public function fileable(): MorphTo
    {
        return $this->morphTo();
    }
}
