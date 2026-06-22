<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Work schedules can be owned by an org unit (holding / BU / branch — all org_units).
 * A holding-owned schedule can be generated down to its descendants as independent copies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            $table->foreignId('owner_org_unit_id')->nullable()->after('code')
                ->constrained('org_units')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            $table->dropForeign(['owner_org_unit_id']);
            $table->dropColumn('owner_org_unit_id');
        });
    }
};
