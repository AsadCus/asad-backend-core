<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class WfhVisitRequestAttachment extends Model
{
    protected $fillable = [
        'wfh_visit_request_id',
        'original_name',
        'path',
        'size',
        'mime_type',
        'stage',
        'uploader_id',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(WfhVisitRequest::class, 'wfh_visit_request_id');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
