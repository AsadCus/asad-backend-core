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
            // Any org unit (not just a Branch) can be a physical place with its own coordinates.
            $table->boolean('has_location')->default(false)->after('geofence_radius_meters');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('org_units', function (Blueprint $table) {
            $table->dropColumn('has_location');
        });
    }
};
