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
        Schema::table('manifests', function (Blueprint $table) {
            // Add manifest_number (auto-generated via NumberGenerator)
            $table->string('manifest_number')->unique()->after('package_id');

            // Drop deprecated columns
            $table->dropUnique(['reference_number']);
            $table->dropColumn([
                'reference_number',
                'company_address',
                'company_phone',
                'departure_date',
                'return_date',
                'duration',
                'makkah_hotel',
                'makkah_check_in',
                'makkah_check_out',
                'madinah_hotel',
                'madinah_check_in',
                'madinah_check_out',
                'flight_details',
                'first_meal',
                'last_meal',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manifests', function (Blueprint $table) {
            $table->dropColumn('manifest_number');

            $table->string('reference_number')->unique()->after('package_id');
            $table->string('company_address')->nullable();
            $table->string('company_phone')->nullable();
            $table->date('departure_date');
            $table->date('return_date');
            $table->string('duration')->nullable();
            $table->string('makkah_hotel')->nullable();
            $table->date('makkah_check_in')->nullable();
            $table->date('makkah_check_out')->nullable();
            $table->string('madinah_hotel')->nullable();
            $table->date('madinah_check_in')->nullable();
            $table->date('madinah_check_out')->nullable();
            $table->json('flight_details')->nullable();
            $table->string('first_meal')->nullable();
            $table->string('last_meal')->nullable();
        });
    }
};
