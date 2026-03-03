<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaidDocumentGeneration extends Model
{
    protected $fillable = [
        'maid_id',
        'user_id',
        'document_type',
        'file_path',
        'file_name',
        'file_size',
        'status',
        'error_message',
        'generated_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'file_size' => 'integer',
    ];

    /**
     * Get the maid that owns the document generation.
     */
    public function maid(): BelongsTo
    {
        return $this->belongsTo(Maid::class);
    }

    /**
     * Get the user who generated the document.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include successful generations.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope a query to only include failed generations.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Get file size in human readable format.
     */
    public function getFileSizeHumanAttribute(): string
    {
        if (! $this->file_size) {
            return 'N/A';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = min(floor(log($this->file_size, 1024)), count($units) - 1);

        return round($this->file_size / pow(1024, $power), 2).' '.$units[$power];
    }
}
