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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_number')->unique()->nullable();
            $table->string('nric_number')->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('address')->nullable();
            $table->string('nationality')->nullable();
            $table->string('passport_number')->nullable();
            $table->date('passport_issue_date')->nullable();
            $table->date('passport_expiry_date')->nullable();
            $table->string('passport_place_of_issue')->nullable();
            $table->string('gender')->nullable();
            $table->string('marital_status')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->boolean('first_time_umrah')->nullable();
            $table->boolean('has_chronic_disease')->default(false);
            $table->boolean('is_using_wheelchair')->default(false);
            $table->text('chronic_disease_details')->nullable();
            $table->string('passport_path')->nullable();
            $table->string('photo_path')->nullable();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_login')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
