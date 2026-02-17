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
            $table->string('nationality')->nullable()->after('address');
            $table->string('passport_number')->nullable()->after('nationality');
            $table->date('passport_issue_date')->nullable()->after('passport_number');
            $table->date('passport_expiry_date')->nullable()->after('passport_issue_date');
            $table->string('passport_place_of_issue')->nullable()->after('passport_expiry_date');
            $table->string('gender')->nullable()->after('passport_place_of_issue');
            $table->string('marital_status')->nullable()->after('gender');
            $table->date('date_of_birth')->nullable()->after('marital_status');
            $table->string('place_of_birth')->nullable()->after('date_of_birth');
            $table->boolean('first_time_umrah')->nullable()->after('place_of_birth');
            $table->boolean('has_chronic_disease')->default(false)->after('first_time_umrah');
            $table->text('chronic_disease_details')->nullable()->after('has_chronic_disease');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'nationality',
                'passport_number',
                'passport_issue_date',
                'passport_expiry_date',
                'passport_place_of_issue',
                'gender',
                'marital_status',
                'date_of_birth',
                'place_of_birth',
                'first_time_umrah',
                'has_chronic_disease',
                'chronic_disease_details',
            ]);
        });
    }
};
