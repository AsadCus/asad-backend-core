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
            $table->string('page_margin_preset', 20)->default('normal')->after('brand_color');
            $table->string('section_spacing_preset', 20)->default('normal')->after('page_margin_preset');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('report_settings', function (Blueprint $table) {
            $table->dropColumn(['page_margin_preset', 'section_spacing_preset']);
        });
    }
};
