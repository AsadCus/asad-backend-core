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
        Schema::create('report_settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->text('company_address')->nullable();
            $table->string('company_phone', 50)->nullable();
            $table->string('company_email')->nullable();
            $table->string('logo_path')->nullable();
            $table->text('footer_text')->nullable();
            $table->string('stamp_path')->nullable();
            $table->string('signature_path')->nullable();
            $table->string('brand_color', 7)->default('#c05427');
            $table->string('page_margin_preset', 20)->default('normal');
            $table->string('section_spacing_preset', 20)->default('normal');
            $table->string('signature_stamp_layout', 20)->default('default');
            $table->string('custom_stamp_path')->nullable();
            $table->string('custom_signature_path')->nullable();
            $table->json('custom_signature_stamp_layout')->nullable();
            $table->string('qr_image_path')->nullable();
            $table->string('qr_alignment', 10)->default('center');
            $table->unsignedSmallInteger('qr_width')->default(120);
            $table->unsignedSmallInteger('qr_height')->default(120);
            $table->json('module_templates')->nullable();
            $table->json('registered_modules')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_settings');
    }
};
