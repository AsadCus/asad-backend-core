<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('number_sequences');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('number_sequences', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->integer('year');
            $table->integer('current_number')->default(0);
            $table->timestamps();

            $table->unique(['type', 'year']);
        });
    }
};
