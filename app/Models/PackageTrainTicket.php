<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageTrainTicket extends Model
{
    protected $fillable = [
        'package_id',
        'from',
        'to',
        'travel_date',
        'travel_time',
        'remarks',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'travel_date' => 'date',
            'sort_order' => 'integer',
        ];
    }

    public function getTravelDateFormattedAttribute(): ?string
    {
        return $this->travel_date
            ? Carbon::parse($this->travel_date)->translatedFormat('d F Y')
            : null;
    }

    public function setTravelDateAttribute(mixed $value): void
    {
        $this->attributes['travel_date'] = ! empty($value)
            ? Carbon::parse($value)->format('Y-m-d')
            : null;
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
