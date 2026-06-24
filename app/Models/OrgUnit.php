<?php

namespace App\Models;

use App\Enums\OrgUnitType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class OrgUnit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'parent_id',
        'type',
        'name',
        'code',
        'logo_path',
        'default_work_schedule_id',
        'sort_order',
        'address',
        'phone',
        'email',
        'latitude',
        'longitude',
        'geofence_radius_meters',
        'has_location',
        'is_active',
    ];

    protected $casts = [
        'type' => OrgUnitType::class,
        'sort_order' => 'integer',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'geofence_radius_meters' => 'integer',
        'has_location' => 'boolean',
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

    public function orgInfos(): HasMany
    {
        return $this->hasMany(OrgInfo::class);
    }

    public function defaultWorkSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class, 'default_work_schedule_id');
    }

    /**
     * The default work schedule id for this unit, inherited up the ancestor chain
     * (a Department with none falls back to its Branch/BU/Holding default).
     * Mirrors the logo fallback walk in {@see resolveLogoUrl()}.
     */
    public function resolveDefaultWorkScheduleId(): ?int
    {
        $node = $this;
        while ($node !== null) {
            if ($node->default_work_schedule_id) {
                return (int) $node->default_work_schedule_id;
            }
            $node = $node->parent;
        }

        return null;
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

    /**
     * Logo URL for this unit only (no fallback). Null if it has no logo.
     */
    public function logoUrl(): ?string
    {
        return $this->logo_path ? self::assetUrl($this->logo_path) : null;
    }

    /**
     * Logo URL resolved up the ancestor chain — a branch with no logo shows its BU/holding logo.
     */
    public function resolveLogoUrl(): ?string
    {
        $node = $this;
        while ($node !== null) {
            if ($node->logo_path) {
                return self::assetUrl($node->logo_path);
            }
            $node = $node->parent;
        }

        return null;
    }

    private static function assetUrl(string $path): string
    {
        return str_starts_with($path, '/') || str_starts_with($path, 'http')
            ? $path
            : Storage::disk('public')->url($path);
    }

    /**
     * Compact summary for the org switcher / scope payloads.
     *
     * @return array<string, mixed>
     */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'code' => $this->code,
            'parent_id' => $this->parent_id,
            'logo_url' => $this->resolveLogoUrl(),
        ];
    }
}
