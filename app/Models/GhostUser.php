<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class GhostUser extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
    ];

    protected static function booted(): void
    {
        static::saving(function (GhostUser $ghostUser): void {
            $user = User::query()->find($ghostUser->user_id);

            if (! $user || ! $user->hasRole('admin')) {
                throw ValidationException::withMessages([
                    'user_id' => 'Ghost user only supports admin role users.',
                ]);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
