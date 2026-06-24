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
        Schema::table('business_trips', function (Blueprint $table) {
            // Widen to match the bigInteger money columns (income/cost/settlement).
            $table->bigInteger('variance')->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_trips', function (Blueprint $table) {
            $table->integer('variance')->default(0)->change();
        });
    }
};
