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
        Schema::table('package_flights', function (Blueprint $table) {
            $table->text('remarks')->nullable()->after('arrival_datetime');
        });

        Schema::table('package_accommodations', function (Blueprint $table) {
            $table->text('remarks')->nullable()->after('ic');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('package_accommodations', function (Blueprint $table) {
            $table->dropColumn('remarks');
        });

        Schema::table('package_flights', function (Blueprint $table) {
            $table->dropColumn('remarks');
        });
    }
};
