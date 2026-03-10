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
        Schema::table('packages', function (Blueprint $table) {
            $table->renameColumn('arrival_date', 'return_date');
            $table->dropColumn(['airline', 'pnr']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->renameColumn('return_date', 'arrival_date');
            $table->string('airline')->nullable()->after('infant_price');
            $table->string('pnr')->nullable()->after('airline');
        });
    }
};
