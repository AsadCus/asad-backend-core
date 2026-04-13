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
        Schema::create('customer_confirmation_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_confirmation_id')->constrained('customer_confirmations')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->boolean('is_leader')->default(false);
            $table->string('status')->default('draft');
            $table->string('relationship')->nullable();
            $table->string('sharing_plan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_confirmation_members');
    }
};
