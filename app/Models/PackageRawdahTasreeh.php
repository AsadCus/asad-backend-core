<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageRawdahTasreeh extends Model
{
    protected $fillable = [
        'package_id',
        'date',
        'women_passengers',
        'women_time',
        'men_passengers',
        'men_time',
        'remarks',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'women_passengers' => 'integer',
            'men_passengers' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function getDateFormattedAttribute(): ?string
    {
        return $this->date
            ? Carbon::parse($this->date)->translatedFormat('d F Y')
            : null;
    }

    public function setDateAttribute(mixed $value): void
    {
        $this->attributes['date'] = ! empty($value)
            ? Carbon::parse($value)->format('Y-m-d')
            : null;
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
