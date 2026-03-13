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
        Schema::table('packages', function (Blueprint $table) {
            $table->string('vehicle_driver_name')->nullable()->after('vehicle_type');
            $table->string('vehicle_driver_contact_number')->nullable()->after('vehicle_driver_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['vehicle_driver_name', 'vehicle_driver_contact_number']);
        });
    }
};
