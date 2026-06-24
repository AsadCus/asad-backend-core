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
            // The schedule members of this unit follow by default. Resolved up the tree when null.
            $table->foreignId('default_work_schedule_id')->nullable()->after('logo_path')
                ->constrained('work_schedules')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('org_units', function (Blueprint $table) {
            $table->dropForeign(['default_work_schedule_id']);
            $table->dropColumn('default_work_schedule_id');
        });
    }
};
