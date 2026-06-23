<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()
                ->constrained('users')->nullOnDelete();
            $table->string('employee_no')->unique();

            // Personal
            $table->string('nik')->nullable();
            $table->string('gender')->nullable();
            $table->date('birth_date')->nullable();
            $table->foreignId('religion_id')->nullable()->constrained('religions')->nullOnDelete();
            $table->foreignId('education_level_id')->nullable()->constrained('education_levels')->nullOnDelete();

            // Employment
            $table->date('hire_date');
            $table->string('employment_status')->default('probation');
            $table->date('termination_date')->nullable();

            // Org placement on the recursive org tree (any level).
            $table->foreignId('org_unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            // Physical site for attendance/geofence (a branch-type node). Null = derive from
            // the org_unit's nearest branch ancestor.
            $table->foreignId('work_location_org_unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            // Data-scope anchor; null = own branch ancestor (least privilege).
            $table->foreignId('scope_org_unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('employees')->nullOnDelete();

            // Contact
            $table->string('phone', 32)->nullable();
            $table->string('address')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 32)->nullable();

            $table->boolean('is_active')->default(true);
            // Per-employee check-in eligibility; admins opt individuals out.
            $table->boolean('can_check_in')->default(true);
            // Attendance lock — HR locks repeat offenders out of check-in.
            // ponytail: flag-on-employee + reason + offending dates json; a separate audit log
            // table is the upgrade path if lock history must survive an unlock.
            $table->timestamp('attendance_locked_at')->nullable();
            $table->string('attendance_lock_reason')->nullable();
            $table->json('attendance_lock_dates')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('supervisor_id');
            $table->index('branch_id');
            $table->index('employment_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
