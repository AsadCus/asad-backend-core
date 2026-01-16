<?php

namespace App\Models;

use App\Helpers\NumberGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Customer extends Model
{
    protected $fillable = [
        'customer_number',
        'nric_number',
        'user_id',
        'address',
        'age_preferences',
        'country_preferences',
        'experience_preferences',
        'branch_id',
        'handled_by',
        'last_login',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function maids(): BelongsToMany
    {
        return $this->belongsToMany(Maid::class, 'customer_maid_recommendations');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($customer) {
            if (empty($customer->customer_number)) {
                $customer->customer_number = NumberGenerator::generate('customer');
            }
        });
    }
}
