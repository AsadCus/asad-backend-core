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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number')->unique()->nullable();
            $table->decimal('amount', 10, 2);
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->string('status', 50)->default('draft');
            $table->string('description')->nullable();
            $table->string('payment_method')->nullable();
            $table->json('extensions')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
