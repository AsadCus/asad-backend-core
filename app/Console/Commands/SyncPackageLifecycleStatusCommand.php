<?php

namespace App\Console\Commands;

use App\Models\Package;
use App\Services\PackageSeatService;
use Illuminate\Console\Command;

class SyncPackageLifecycleStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'packages:sync-lifecycle-status {--package-id= : Sync a specific package ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate package seats_left and lifecycle status (open/full/closed/ongoing/completed).';

    public function __construct(private PackageSeatService $packageSeatService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $requestedPackageId = (int) ($this->option('package-id') ?? 0);

        $query = Package::query()->orderBy('id');

        if ($requestedPackageId > 0) {
            $query->where('id', $requestedPackageId);
        }

        $packages = $query->get();

        if ($packages->isEmpty()) {
            $this->warn('No packages found to sync.');

            return self::SUCCESS;
        }

        $statusChanges = 0;
        $seatChanges = 0;

        foreach ($packages as $package) {
            $beforeStatus = strtolower(trim((string) $package->status));
            $beforeSeatsLeft = $package->seats_left;

            $this->packageSeatService->recalculateForPackageId((int) $package->id);

            $package->refresh();

            if ($beforeStatus !== strtolower(trim((string) $package->status))) {
                $statusChanges++;
            }

            if ($beforeSeatsLeft !== $package->seats_left) {
                $seatChanges++;
            }
        }

        $this->info('Package lifecycle sync completed.');
        $this->line('Processed packages: '.$packages->count());
        $this->line('Status changes: '.$statusChanges);
        $this->line('Seats-left changes: '.$seatChanges);

        return self::SUCCESS;
    }
}
