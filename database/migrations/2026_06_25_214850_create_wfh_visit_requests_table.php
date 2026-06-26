<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wfh_visit_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_no')->unique();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            $table->string('type'); // wfh | visit
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('total_days')->default(1);

            $table->text('reason');

            // Visit location — all optional (null = open/free check-in like WFH)
            $table->string('location_address')->nullable();
            $table->decimal('location_lat', 10, 7)->nullable();
            $table->decimal('location_lng', 10, 7)->nullable();
            $table->unsignedSmallInteger('location_radius')->nullable(); // metres

            // open = anywhere; locked = must be within radius; null = WFH (no geo check)
            $table->string('geotag_mode')->nullable();

            $table->string('status')->default('pending_supervisor');

            $table->foreignId('supervisor_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->dateTime('supervisor_decided_at')->nullable();
            $table->text('supervisor_note')->nullable();

            $table->foreignId('hr_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('hr_decided_at')->nullable();
            $table->text('hr_note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('employee_id');
            $table->index(['start_date', 'end_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wfh_visit_requests');
    }
};
