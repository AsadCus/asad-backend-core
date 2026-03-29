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
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_number')->unique()->nullable();
            $table->date('quotation_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_confirmation_id')->nullable()->constrained('customer_confirmations')->nullOnDelete();
            $table->foreignId('sales_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payment_plan')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->string('reason')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
