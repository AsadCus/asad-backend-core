<?php

namespace App\Models;

use App\Enums\OrgUnitType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrgUnit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'parent_id',
        'type',
        'name',
        'code',
        'sort_order',
        'address',
        'phone',
        'email',
        'latitude',
        'longitude',
        'geofence_radius_meters',
        'is_active',
    ];

    protected $casts = [
        'type' => OrgUnitType::class,
        'sort_order' => 'integer',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'geofence_radius_meters' => 'integer',
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'org_unit_id');
    }

    /**
     * IDs of this node plus all of its descendants.
     * The org tree is tiny, so load (id, parent_id) once and walk in PHP.
     * ponytail: closure table / recursive CTE only if this ever shows up slow.
     *
     * @return array<int, int>
     */
    public static function subtreeIds(int $rootId): array
    {
        $childrenByParent = [];
        foreach (static::query()->pluck('parent_id', 'id') as $id => $parentId) {
            $childrenByParent[$parentId][] = $id;
        }

        $ids = [];
        $stack = [$rootId];
        while ($stack !== []) {
            $current = array_pop($stack);
            $ids[] = $current;
            foreach ($childrenByParent[$current] ?? [] as $childId) {
                $stack[] = $childId;
            }
        }

        return $ids;
    }

    /**
     * Nearest ancestor (or self) of the given type, walking up parent_id.
     * Used e.g. to resolve an employee's branch (geofence) from their placement.
     */
    public function nearestOfType(OrgUnitType $type): ?self
    {
        $node = $this;
        while ($node !== null) {
            if ($node->type === $type) {
                return $node;
            }
            $node = $node->parent;
        }

        return null;
    }

    /**
     * Whether $parent is a valid parent for this node's type (nesting rules).
     */
    public function hasValidParent(?self $parent): bool
    {
        return $this->type->canHaveParent($parent?->type);
    }
}
