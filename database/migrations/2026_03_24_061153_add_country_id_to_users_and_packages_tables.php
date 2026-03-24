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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('country_id')
                ->nullable()
                ->after('contact')
                ->constrained('countries')
                ->nullOnDelete();
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->foreignId('country_id')
                ->nullable()
                ->after('status')
                ->constrained('countries')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('country_id');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('country_id');
        });
    }
};
