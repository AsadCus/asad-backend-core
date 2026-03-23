<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageOfficial extends Model
{
    protected $fillable = [
        'package_id',
        'type',
        'name',
        'hotel',
        'contact_number',
        'nationality',
        'passport_number',
        'gender',
        'date_of_birth',
        'passport_issue_date',
        'passport_expiry_date',
        'passport_place_of_issue',
        'place_of_birth',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'passport_issue_date' => 'date',
            'passport_expiry_date' => 'date',
            'sort_order' => 'integer',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
