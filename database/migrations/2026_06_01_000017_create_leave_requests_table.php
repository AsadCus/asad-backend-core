<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_no')->unique();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();

            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days', 5, 2);

            $table->text('reason');
            $table->string('attachment_path')->nullable();

            $table->string('status')->default('pending_supervisor');

            $table->foreignId('supervisor_id')->nullable()
                ->constrained('employees')->nullOnDelete();
            $table->dateTime('supervisor_decided_at')->nullable();
            $table->text('supervisor_note')->nullable();

            $table->foreignId('hr_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->dateTime('hr_decided_at')->nullable();
            $table->text('hr_note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('employee_id');
            $table->index('start_date');
            $table->index('end_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
