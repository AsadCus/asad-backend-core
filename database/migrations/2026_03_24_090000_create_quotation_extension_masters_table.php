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
        Schema::create('quotation_extension_masters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('discount');
            $table->string('calculation_mode')->default('fixed');
            $table->decimal('calculation_value', 12, 4)->default(0);
            $table->json('payment_methods')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotation_extension_masters');
    }
};
