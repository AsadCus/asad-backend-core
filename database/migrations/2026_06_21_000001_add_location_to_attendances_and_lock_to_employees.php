<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reverse-geocoded address per punch (the detail screen shows it). Lat/lng/photo already exist.
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('check_in_location')->nullable()->after('check_in_photo_path');
            $table->string('check_out_location')->nullable()->after('check_out_photo_path');
        });

        // Attendance lock — repeated lateness/absence → HR locks the user out of check-in.
        // ponytail: flag-on-employee + reason + offending dates json; a separate audit log table
        // is the upgrade path if lock history needs to survive an unlock.
        Schema::table('employees', function (Blueprint $table) {
            $table->timestamp('attendance_locked_at')->nullable()->after('is_active');
            $table->string('attendance_lock_reason')->nullable()->after('attendance_locked_at');
            $table->json('attendance_lock_dates')->nullable()->after('attendance_lock_reason');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['check_in_location', 'check_out_location']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['attendance_locked_at', 'attendance_lock_reason', 'attendance_lock_dates']);
        });
    }
};
