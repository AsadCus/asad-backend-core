<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('org_units', function (Blueprint $table) {
            // Day-of-month the payroll/attendance period starts on (1 = a plain calendar
            // month). E.g. 16 means the period runs the 16th of one month through the 15th
            // of the next. Null = not set here; resolved up the tree, defaulting to 1.
            $table->unsignedTinyInteger('attendance_cutoff_day')->nullable()->after('has_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('org_units', function (Blueprint $table) {
            $table->dropColumn('attendance_cutoff_day');
        });
    }
};
