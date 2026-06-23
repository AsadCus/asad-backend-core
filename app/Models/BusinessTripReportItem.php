<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessTripReportItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_trip_id',
        'category',
        'date',
        'description',
        'kategori',
        'amount',
        'attachment_path',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'integer',
    ];

    public function businessTrip(): BelongsTo
    {
        return $this->belongsTo(BusinessTrip::class);
    }
}
