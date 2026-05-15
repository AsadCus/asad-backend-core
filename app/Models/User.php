<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Auth\Traits\CanResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use CanResetPassword, HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'contact',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'selected_country_ids' => 'array',
        ];
    }

    public function sales(): HasOne
    {
        return $this->hasOne(Sales::class, 'user_id');
    }

    public function admin(): HasOne
    {
        return $this->hasOne(Admin::class, 'user_id');
    }

    public function operation(): HasOne
    {
        return $this->hasOne(Operation::class, 'user_id');
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class, 'user_id');
    }

    public function ghostUser(): HasOne
    {
        return $this->hasOne(GhostUser::class, 'user_id');
    }

    public function isGhostUser(): bool
    {
        if ($this->relationLoaded('ghostUser')) {
            return $this->ghostUser !== null;
        }

        return $this->ghostUser()->exists();
    }

    public function userNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class, 'user_id');
    }
}
