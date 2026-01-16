<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerMaidRecommendation extends Model
{
    protected $fillable = [
        'customer_id',
        'maid_id',
    ];
}
