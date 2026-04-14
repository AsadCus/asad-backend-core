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
        Schema::table('report_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('qr_width')->default(120)->after('qr_alignment');
            $table->unsignedSmallInteger('qr_height')->default(120)->after('qr_width');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('report_settings', function (Blueprint $table) {
            $table->dropColumn(['qr_width', 'qr_height']);
        });
    }
};
