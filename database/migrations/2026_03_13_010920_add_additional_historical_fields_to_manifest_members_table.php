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
        Schema::table('manifest_members', function (Blueprint $table) {
            $table->text('address')->nullable()->after('place_of_birth');
            $table->boolean('first_time_umrah')->nullable()->after('address');
            $table->boolean('has_chronic_disease')->nullable()->after('first_time_umrah');
            $table->text('chronic_disease_details')->nullable()->after('has_chronic_disease');
            $table->string('passport_path')->nullable()->after('chronic_disease_details');
            $table->string('photo_path')->nullable()->after('passport_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manifest_members', function (Blueprint $table) {
            $table->dropColumn([
                'address',
                'first_time_umrah',
                'has_chronic_disease',
                'chronic_disease_details',
                'passport_path',
                'photo_path',
            ]);
        });
    }
};
