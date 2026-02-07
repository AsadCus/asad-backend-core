<?php

use App\Models\FinancialYear;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('financial_years', 'start_date')) {
            Schema::table('financial_years', function (Blueprint $table) {
                $table->date('start_date')->nullable()->after('year');
            });
        }

        if (!Schema::hasColumn('financial_years', 'end_date')) {
            Schema::table('financial_years', function (Blueprint $table) {
                $table->date('end_date')->nullable()->after('start_date');
            });
        }

        if (!Schema::hasColumn('financial_years', 'is_active')) {
            Schema::table('financial_years', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('default');
            });
        }

        $existingYear = FinancialYear::first();
        if ($existingYear && !$existingYear->start_date) {
            $now = Carbon::now();
            $startDate = Carbon::create($now->year, 1, 1);
            $endDate = Carbon::create($now->year, 12, 31);

            $existingYear->update([
                'start_date' => $startDate,
                'end_date' => $endDate,
                'is_active' => true,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('financial_years', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'end_date', 'is_active']);
        });
    }
};
