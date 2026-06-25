<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Auth\Traits\CanResetPassword;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
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
        'photo_profile',
        'signature_path',
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

    /**
     * Public URL for the profile photo, or null when none is set.
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->photo_profile
                ? Storage::disk('public')->url($this->photo_profile)
                : null,
        );
    }

    /**
     * Public URL for the saved signature image, or null when none is set.
     */
    protected function signatureUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->signature_path
                ? Storage::disk('public')->url($this->signature_path)
                : null,
        );
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

    public function official(): HasOne
    {
        return $this->hasOne(Official::class, 'user_id');
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

    /**
     * Effective permission names: every permission for a ghost (the lone bypass),
     * otherwise the permissions granted via the user's roles.
     *
     * @return Collection<int, string>
     */
    public function effectivePermissionNames(): Collection
    {
        if ($this->isGhostUser()) {
            return Permission::query()->orderBy('name')->pluck('name');
        }

        return $this->getAllPermissions()->pluck('name');
    }

    public function userNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class, 'user_id');
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class, 'user_id');
    }
}
