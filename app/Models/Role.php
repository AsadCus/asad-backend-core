<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Role = Jabatan. Extends Spatie's Role with display + classification metadata.
 * The machine `name` stays the immutable gating key; admins edit `label` etc.
 */
class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'guard_name',
        'label',
        'description',
        'role_group_id',
        'management_level_id',
        'is_system',
        'is_full_access',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_full_access' => 'boolean',
    ];

    public function roleGroup(): BelongsTo
    {
        return $this->belongsTo(RoleGroup::class);
    }

    public function managementLevel(): BelongsTo
    {
        return $this->belongsTo(ManagementLevel::class);
    }
}
