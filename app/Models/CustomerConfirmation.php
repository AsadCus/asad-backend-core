<?php

namespace App\Models;

use App\Helpers\NumberGenerator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerConfirmation extends Model
{
    protected $table = 'customer_confirmations';

    protected $fillable = [
        'number',
        'enquiry_id',
        'created_by',
        'package_id',
        'is_holding',
        'package_room_type',
        'package_category',
        'date_of_application',
    ];

    protected function casts(): array
    {
        return [
            'date_of_application' => 'date',
            'is_holding' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->number)) {
                $model->number = NumberGenerator::generate('customer_confirmation');
            }
        });
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class, 'enquiry_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(CustomerConfirmationMember::class, 'customer_confirmation_id');
    }

    public function leader(): ?CustomerConfirmationMember
    {
        return $this->members()->where('is_leader', true)->first();
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class, 'customer_confirmation_id');
    }

    // Formatting Helpers
    public function getDateOfApplicationFormattedAttribute(): ?string
    {
        return $this->date_of_application
            ? Carbon::parse($this->date_of_application)->translatedFormat('d F Y')
            : null;
    }
}
