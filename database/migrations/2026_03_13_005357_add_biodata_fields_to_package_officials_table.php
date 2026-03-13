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
        Schema::table('package_officials', function (Blueprint $table) {
            $table->string('nationality')->nullable()->after('contact_number');
            $table->string('passport_number')->nullable()->after('nationality');
            $table->string('gender')->nullable()->after('passport_number');
            $table->date('date_of_birth')->nullable()->after('gender');
            $table->date('passport_issue_date')->nullable()->after('date_of_birth');
            $table->date('passport_expiry_date')->nullable()->after('passport_issue_date');
            $table->string('passport_place_of_issue')->nullable()->after('passport_expiry_date');
            $table->string('place_of_birth')->nullable()->after('passport_place_of_issue');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('package_officials', function (Blueprint $table) {
            $table->dropColumn([
                'nationality',
                'passport_number',
                'gender',
                'date_of_birth',
                'passport_issue_date',
                'passport_expiry_date',
                'passport_place_of_issue',
                'place_of_birth',
            ]);
        });
    }
};
