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
        Schema::dropIfExists('manifest_payments');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('manifest_payments')) {
            return;
        }

        Schema::create('manifest_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manifest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manifest_traveler_id')->nullable()->constrained('manifest_members')->nullOnDelete();
            $table->string('traveler_name');
            $table->string('description');
            $table->decimal('amount', 10, 2)->default(0);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('outstanding_amount', 10, 2)->default(0);
            $table->date('payment_date')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }
};
