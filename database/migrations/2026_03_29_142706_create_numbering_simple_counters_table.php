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
        Schema::create('numbering_simple_counters', function (Blueprint $table) {
            $table->id();
            $table->string('model_key', 100)->unique();
            $table->string('latest_number', 191)->nullable();
            $table->timestamps();

            $table->index('model_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('numbering_simple_counters');
    }
};
