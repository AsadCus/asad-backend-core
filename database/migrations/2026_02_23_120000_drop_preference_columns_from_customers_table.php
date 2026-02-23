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
            if (Schema::hasColumn('customers', 'age_preferences')) {
                $table->dropColumn('age_preferences');
            }

            if (Schema::hasColumn('customers', 'country_preferences')) {
                $table->dropColumn('country_preferences');
            }

            if (Schema::hasColumn('customers', 'experience_preferences')) {
                $table->dropColumn('experience_preferences');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'age_preferences')) {
                $table->string('age_preferences')->nullable();
            }

            if (! Schema::hasColumn('customers', 'country_preferences')) {
                $table->string('country_preferences')->nullable();
            }

            if (! Schema::hasColumn('customers', 'experience_preferences')) {
                $table->string('experience_preferences')->nullable();
            }
        });
    }
};
