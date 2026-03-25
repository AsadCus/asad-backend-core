<?php

namespace App\Services;

use App\Models\CustomerConfirmationMember;
use App\Models\ManifestMember;
use App\Models\Package;

class PackageSeatService
{
    /**
     * Member statuses that consume a package seat.
     *
     * @var string[]
     */
    private const OCCUPYING_STATUSES = ['partially_paid', 'fully_paid', 'confirmed'];

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

        if (! $package || $package->total_seats === null) {
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

        if ($package->total_seats === null) {
            $package->update(['seats_left' => null]);

            return;
        }

        $occupied = $this->occupiedSeatsCount((int) $package->id);
        $seatsLeft = max(0, (int) $package->total_seats - $occupied);

        $package->update(['seats_left' => $seatsLeft]);
    }
}
