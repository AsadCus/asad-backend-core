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
        Schema::create('manifest_accommodation_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manifest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manifest_traveler_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->unsignedBigInteger('customer_confirmation_member_id')->nullable();
            $table->string('accommodation_key');
            $table->unsignedInteger('sort_order')->default(1);
            $table->string('sharing_group_key')->nullable();
            $table->string('room_no')->nullable();
            $table->string('room_type')->nullable();
            $table->string('bed_type')->nullable();
            $table->string('meal')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['manifest_id', 'accommodation_key', 'sort_order'], 'manifest_accommodation_order_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manifest_accommodation_assignments');
    }
};
