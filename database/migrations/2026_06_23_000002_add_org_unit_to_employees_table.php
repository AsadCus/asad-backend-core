<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Org placement on the recursive org tree (any level).
            $table->foreignId('org_unit_id')->nullable()->after('termination_date')
                ->constrained('org_units')->nullOnDelete();
            // Physical site for attendance/geofence (a branch-type node).
            // Null = derive from the org_unit's nearest branch ancestor.
            $table->foreignId('work_location_org_unit_id')->nullable()->after('org_unit_id')
                ->constrained('org_units')->nullOnDelete();
            // Data-scope anchor; null = own branch ancestor (least privilege).
            $table->foreignId('scope_org_unit_id')->nullable()->after('work_location_org_unit_id')
                ->constrained('org_units')->nullOnDelete();
        });

        // Legacy org FKs (holding_id/business_unit_id/department_id) are left in place
        // and dropped together with their parent tables in the org-collapse step.
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['org_unit_id']);
            $table->dropForeign(['work_location_org_unit_id']);
            $table->dropForeign(['scope_org_unit_id']);
            $table->dropColumn(['org_unit_id', 'work_location_org_unit_id', 'scope_org_unit_id']);
        });
    }
};
