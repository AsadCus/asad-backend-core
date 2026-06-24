<?php

namespace App\Models;

use App\Enums\HolidayType;
use Carbon\Carbon;
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

    /**
     * Whether the given date is a holiday — either an exact-date entry or a recurring
     * one matching the same month and day.
     */
    public static function isHoliday(Carbon|string $date): bool
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);

        return static::query()
            ->where(function ($q) use ($date) {
                $q->whereDate('date', $date->toDateString())
                    ->orWhere(function ($q) use ($date) {
                        $q->where('is_recurring', true)
                            ->whereMonth('date', $date->month)
                            ->whereDay('date', $date->day);
                    });
            })
            ->exists();
    }
}
