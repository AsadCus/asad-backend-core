<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageFlight extends Model
{
    protected $fillable = [
        'package_id',
        'from',
        'to',
        'description',
        'airline',
        'pnr',
        'departure_datetime',
        'arrival_datetime',
        'remarks',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'departure_datetime' => 'datetime',
            'arrival_datetime' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function getDepartureDatetimeFormattedAttribute(): ?string
    {
        return $this->departure_datetime
            ? Carbon::parse($this->departure_datetime)->translatedFormat('d F Y H:i')
            : null;
    }

    public function getArrivalDatetimeFormattedAttribute(): ?string
    {
        return $this->arrival_datetime
            ? Carbon::parse($this->arrival_datetime)->translatedFormat('d F Y H:i')
            : null;
    }

    public function setDepartureDatetimeAttribute(mixed $value): void
    {
        $this->attributes['departure_datetime'] = ! empty($value)
            ? Carbon::parse($value)->format('Y-m-d H:i:s')
            : null;
    }

    public function setArrivalDatetimeAttribute(mixed $value): void
    {
        $this->attributes['arrival_datetime'] = ! empty($value)
            ? Carbon::parse($value)->format('Y-m-d H:i:s')
            : null;
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
