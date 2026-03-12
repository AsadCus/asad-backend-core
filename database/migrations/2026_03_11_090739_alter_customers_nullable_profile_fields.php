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
        Schema::table('customers', function (Blueprint $table) {
            $table->string('nric_number')->nullable()->change();
            $table->text('address')->nullable()->change();
            $table->string('nationality')->nullable()->change();
            $table->string('passport_number')->nullable()->change();
            $table->date('passport_issue_date')->nullable()->change();
            $table->date('passport_expiry_date')->nullable()->change();
            $table->string('passport_place_of_issue')->nullable()->change();
            $table->string('gender')->nullable()->change();
            $table->string('marital_status')->nullable()->change();
            $table->date('date_of_birth')->nullable()->change();
            $table->string('place_of_birth')->nullable()->change();
            $table->boolean('first_time_umrah')->nullable()->change();
            $table->boolean('has_chronic_disease')->nullable()->change();
            $table->text('chronic_disease_details')->nullable()->change();
            $table->string('passport_path')->nullable()->change();
            $table->string('photo_path')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('nric_number')->nullable()->change();
            $table->text('address')->nullable()->change();
            $table->string('nationality')->nullable()->change();
            $table->string('passport_number')->nullable()->change();
            $table->date('passport_issue_date')->nullable()->change();
            $table->date('passport_expiry_date')->nullable()->change();
            $table->string('passport_place_of_issue')->nullable()->change();
            $table->string('gender')->nullable()->change();
            $table->string('marital_status')->nullable()->change();
            $table->date('date_of_birth')->nullable()->change();
            $table->string('place_of_birth')->nullable()->change();
            $table->boolean('first_time_umrah')->nullable()->change();
            $table->boolean('has_chronic_disease')->nullable()->change();
            $table->text('chronic_disease_details')->nullable()->change();
            $table->string('passport_path')->nullable()->change();
            $table->string('photo_path')->nullable()->change();
        });
    }
};
