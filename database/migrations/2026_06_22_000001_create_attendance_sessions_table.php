<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each row is one check-in/check-out pair. The parent `attendances` row stays the
        // daily summary (first-in, last-out, totals); sessions hold the individual punches
        // so employees can clock in/out multiple times a day.
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained()->cascadeOnDelete();

            // Check-in
            $table->dateTime('check_in_at');
            $table->decimal('check_in_lat', 10, 8)->nullable();
            $table->decimal('check_in_lng', 11, 8)->nullable();
            $table->string('check_in_photo_path')->nullable();
            $table->string('check_in_location')->nullable();
            $table->foreignId('check_in_branch_id')->nullable()->constrained('branches')->nullOnDelete();

            // Check-out (null while the session is open)
            $table->dateTime('check_out_at')->nullable();
            $table->decimal('check_out_lat', 10, 8)->nullable();
            $table->decimal('check_out_lng', 11, 8)->nullable();
            $table->string('check_out_photo_path')->nullable();
            $table->string('check_out_location')->nullable();
            $table->foreignId('check_out_branch_id')->nullable()->constrained('branches')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['attendance_id', 'check_out_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};
