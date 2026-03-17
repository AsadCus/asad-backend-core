<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManifestMemberCollectionItem extends Model
{
    protected $fillable = [
        'manifest_member_id',
        'course_1',
        'course_2',
        'lanyard',
        'luggage_tag',
        'cabin_tag',
        'passport_cover',
        'umrah_guidebook',
        'sling_bag',
        'cabin_size_luggage',
        'umrah_essentials',
    ];

    protected $casts = [
        'course_1' => 'boolean',
        'course_2' => 'boolean',
        'lanyard' => 'boolean',
        'luggage_tag' => 'boolean',
        'cabin_tag' => 'boolean',
        'passport_cover' => 'boolean',
        'umrah_guidebook' => 'boolean',
        'sling_bag' => 'boolean',
        'cabin_size_luggage' => 'boolean',
        'umrah_essentials' => 'boolean',
    ];

    public function manifestMember(): BelongsTo
    {
        return $this->belongsTo(ManifestMember::class, 'manifest_member_id');
    }
}
