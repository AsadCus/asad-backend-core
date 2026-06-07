<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_proposals', function (Blueprint $table) {
            $table->id();
            $table->string('proposal_number')->unique();
            $table->string('name');
            $table->string('status')->default('draft');
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->string('currency_symbol', 10)->nullable();

            // Dates & Capacity
            $table->date('departure_date')->nullable();
            $table->date('return_date')->nullable();
            $table->unsignedInteger('total_seats')->default(0);

            // Revenue (mirrors packages table)
            $table->decimal('price_single', 10, 2)->default(0);
            $table->decimal('price_double', 10, 2)->default(0);
            $table->decimal('price_triple', 10, 2)->default(0);
            $table->decimal('price_quad', 10, 2)->default(0);
            $table->decimal('child_with_bed_price', 10, 2)->default(0);
            $table->decimal('child_no_bed_price', 10, 2)->default(0);
            $table->decimal('infant_price', 10, 2)->default(0);

            // Dynamic data
            $table->json('expenditure')->nullable();
            $table->json('passenger_simulation')->nullable();
            $table->json('officials')->nullable();

            // Approval workflow
            $table->json('approver_user_ids')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_rejected_at')->nullable();
            $table->foreignId('approved_rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();

            // Tracking
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->text('remarks')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_proposals');
    }
};
