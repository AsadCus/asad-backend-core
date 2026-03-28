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
            $table->string('signature_stamp_layout', 20)
                ->default('default')
                ->after('brand_color');
            $table->string('custom_stamp_path')->nullable()->after('signature_stamp_layout');
            $table->string('custom_signature_path')->nullable()->after('custom_stamp_path');
            $table->json('custom_signature_stamp_layout')->nullable()->after('custom_signature_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('report_settings', function (Blueprint $table) {
            $table->dropColumn([
                'signature_stamp_layout',
                'custom_stamp_path',
                'custom_signature_path',
                'custom_signature_stamp_layout',
            ]);
        });
    }
};
