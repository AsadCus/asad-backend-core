<?php

namespace App\Models;

use App\Enums\HolidayType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Holiday extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'date',
        'name',
        'type',
        'description',
        'is_recurring',
    ];

    protected $casts = [
        'date' => 'date',
        'type' => HolidayType::class,
        'is_recurring' => 'boolean',
    ];
}
