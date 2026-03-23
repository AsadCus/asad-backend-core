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
            $table->text('train_description')->nullable()->after('ticket_type');
        });

        Schema::table('package_accommodations', function (Blueprint $table) {
            $table->string('ic')->nullable()->after('hotel_name');
        });

        Schema::table('package_officials', function (Blueprint $table) {
            $table->string('hotel')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('package_officials', function (Blueprint $table) {
            $table->dropColumn('hotel');
        });

        Schema::table('package_accommodations', function (Blueprint $table) {
            $table->dropColumn('ic');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('train_description');
        });
    }
};
