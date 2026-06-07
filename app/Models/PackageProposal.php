<?php

namespace App\Models;

use App\Enums\PackageProposalStatus;
use App\Helpers\NumberGenerator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageProposal extends Model
{
    protected $fillable = [
        'proposal_number',
        'name',
        'status',
        'country_id',
        'currency_symbol',

        'departure_date',
        'return_date',
        'total_seats',

        'price_single',
        'price_double',
        'price_triple',
        'price_quad',
        'child_with_bed_price',
        'child_no_bed_price',
        'infant_price',

        'expenditure',
        'passenger_simulation',
        'officials',

        'approver_user_ids',
        'submitted_at',
        'submitted_by',
        'approved_rejected_at',
        'approved_rejected_by',
        'rejection_reason',

        'created_by',
        'package_id',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'status' => PackageProposalStatus::class,
            'departure_date' => 'date',
            'return_date' => 'date',
            'total_seats' => 'integer',
            'price_single' => 'decimal:2',
            'price_double' => 'decimal:2',
            'price_triple' => 'decimal:2',
            'price_quad' => 'decimal:2',
            'child_with_bed_price' => 'decimal:2',
            'child_no_bed_price' => 'decimal:2',
            'infant_price' => 'decimal:2',
            'expenditure' => 'array',
            'passenger_simulation' => 'array',
            'officials' => 'array',
            'approver_user_ids' => 'array',
            'submitted_at' => 'datetime',
            'approved_rejected_at' => 'datetime',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvedRejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_rejected_by');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id');
    }

    public function getDepartureDateFormattedAttribute(): ?string
    {
        return $this->departure_date ? Carbon::parse($this->departure_date)->translatedFormat('d F Y') : null;
    }

    public function getReturnDateFormattedAttribute(): ?string
    {
        return $this->return_date ? Carbon::parse($this->return_date)->translatedFormat('d F Y') : null;
    }

    public function setDepartureDateAttribute(mixed $value): void
    {
        $this->attributes['departure_date'] = ! empty($value)
            ? Carbon::parse($value)->format('Y-m-d')
            : null;
    }

    public function setReturnDateAttribute(mixed $value): void
    {
        $this->attributes['return_date'] = ! empty($value)
            ? Carbon::parse($value)->format('Y-m-d')
            : null;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($proposal) {
            if (empty($proposal->proposal_number)) {
                $proposal->proposal_number = NumberGenerator::generate('package_proposal');
            }
        });
    }
}
