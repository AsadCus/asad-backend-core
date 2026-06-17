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
        Schema::table('package_accommodations', function (Blueprint $table) {
            $table->string('first_meal')->nullable()->after('type_of_meal');
            $table->string('last_meal')->nullable()->after('first_meal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('package_accommodations', function (Blueprint $table) {
            $table->dropColumn(['first_meal', 'last_meal']);
        });
    }
};
