<?php

namespace App\Services;

use App\Models\CustomerConfirmationMember;
use App\Models\ManifestMember;
use App\Models\Package;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class PackageSeatService
{
    /**
     * Member statuses that consume a package seat.
     *
     * @var string[]
     */
    private const OCCUPYING_STATUSES = ['partially_paid', 'fully_paid', 'confirmed'];

    /**
     * Package statuses that must reject new member intake.
     *
     * @var string[]
     */
    private const BLOCKED_MEMBER_INTAKE_STATUSES = ['full', 'closed', 'ongoing', 'completed'];

    public function occupiedSeatsCount(int $packageId): int
    {
        return ManifestMember::query()
            ->whereNull('package_official_id')
            ->whereHas('manifest', function ($query) use ($packageId) {
                $query->where('package_id', $packageId);
            })
            ->count();
    }

    public function hasAvailableSeat(int $packageId, ?int $excludingMemberId = null): bool
    {
        $package = Package::query()->find($packageId);

        if (! $package) {
            return false;
        }

        if ($this->isBlockedForMemberIntake($package->status)) {
            return false;
        }

        if ($package->total_seats === null) {
            return true;
        }

        $occupiedQuery = CustomerConfirmationMember::query()
            ->whereHas('confirmation', function ($query) use ($packageId) {
                $query->where('package_id', $packageId);
            })
            ->whereIn('status', self::OCCUPYING_STATUSES);

        if ($excludingMemberId) {
            $occupiedQuery->where('id', '!=', $excludingMemberId);
        }

        return $occupiedQuery->count() < (int) $package->total_seats;
    }

    public function recalculateForPackageId(?int $packageId): void
    {
        if (! $packageId) {
            return;
        }

        $package = Package::query()->find($packageId);

        if (! $package) {
            return;
        }

        $occupied = $this->occupiedSeatsCount((int) $package->id);
        $seatsLeft = $package->total_seats === null
            ? null
            : max(0, (int) $package->total_seats - $occupied);
        $nextStatus = $this->resolveLifecycleStatus($package, $seatsLeft);

        $updates = [];

        if ($package->seats_left !== $seatsLeft) {
            $updates['seats_left'] = $seatsLeft;
        }

        if ((string) $package->status !== $nextStatus) {
            $updates['status'] = $nextStatus;
        }

        if ($updates !== []) {
            $package->update($updates);
        }
    }

    public function isBlockedForMemberIntake(?string $status): bool
    {
        return in_array(
            $this->normalizeStatus($status),
            self::BLOCKED_MEMBER_INTAKE_STATUSES,
            true,
        );
    }

    public function normalizeStatus(?string $status): string
    {
        $normalized = strtolower(trim((string) $status));

        return match ($normalized) {
            'closed' => 'closed',
            'full' => 'full',
            'ongoing' => 'ongoing',
            'completed' => 'completed',
            default => 'open',
        };
    }

    private function resolveLifecycleStatus(Package $package, ?int $seatsLeft): string
    {
        $currentStatus = $this->normalizeStatus($package->status);

        // Closed is a manual lock and must not be overridden by auto lifecycle updates.
        if ($currentStatus === 'closed') {
            return 'closed';
        }

        $today = now()->startOfDay();

        $returnDate = $this->normalizeDate($package->return_date);
        if ($returnDate !== null && $today->gte($returnDate)) {
            return 'completed';
        }

        $departureDate = $this->normalizeDate($package->departure_date);
        if ($departureDate !== null && $today->gte($departureDate)) {
            return 'ongoing';
        }

        if ($package->total_seats !== null && $seatsLeft !== null && $seatsLeft <= 0) {
            return 'full';
        }

        return 'open';
    }

    private function normalizeDate(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value->copy()->startOfDay();
        }

        if (empty($value)) {
            return null;
        }

        return Carbon::parse((string) $value)->startOfDay();
    }

    public function canSelectForGeneralFlows(Package $package): bool
    {
        if ($this->isBlockedForMemberIntake($package->status)) {
            return false;
        }

        if ($package->total_seats === null) {
            return false;
        }

        return (int) ($package->seats_left ?? 0) > 0;
    }

    public function isPrivatePackage(Package $package): bool
    {
        return str_starts_with(
            strtolower(trim((string) $package->name)),
            'private -',
        );
    }
}
