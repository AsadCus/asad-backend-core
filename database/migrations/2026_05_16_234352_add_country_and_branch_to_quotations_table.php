<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
        });

        $resolveFirstId = static function ($value): ?int {
            if (is_string($value)) {
                $value = json_decode($value, true);
            }

            if (! is_array($value)) {
                return null;
            }

            foreach ($value as $item) {
                $id = (int) $item;

                if ($id > 0) {
                    return $id;
                }
            }

            return null;
        };

        DB::table('quotations')
            ->whereNotNull('created_by')
            ->orderBy('id')
            ->chunkById(200, function ($quotations) use ($resolveFirstId): void {
                $userIds = $quotations
                    ->pluck('created_by')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if (empty($userIds)) {
                    return;
                }

                $salesScopes = DB::table('sales')
                    ->whereIn('user_id', $userIds)
                    ->get()
                    ->keyBy('user_id');

                $adminScopes = DB::table('admins')
                    ->whereIn('user_id', $userIds)
                    ->get()
                    ->keyBy('user_id');

                foreach ($quotations as $quotation) {
                    $userId = (int) ($quotation->created_by ?? 0);

                    if ($userId <= 0) {
                        continue;
                    }

                    $scope = $salesScopes->get($userId) ?? $adminScopes->get($userId);

                    if (! $scope) {
                        continue;
                    }

                    $countryId = (int) ($scope->country_id ?? 0);
                    $branchId = (int) ($scope->branch_id ?? 0);

                    if ($countryId <= 0) {
                        $countryId = (int) ($resolveFirstId($scope->country_ids ?? null) ?? 0);
                    }

                    if ($branchId <= 0) {
                        $branchId = (int) ($resolveFirstId($scope->branch_ids ?? null) ?? 0);
                    }

                    if ($countryId <= 0 && $branchId <= 0) {
                        continue;
                    }

                    DB::table('quotations')
                        ->where('id', $quotation->id)
                        ->update([
                            'country_id' => $countryId > 0 ? $countryId : null,
                            'branch_id' => $branchId > 0 ? $branchId : null,
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('country_id');
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
