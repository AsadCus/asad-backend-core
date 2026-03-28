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
        Schema::create('numbering_formats', function (Blueprint $table) {
            $table->id();
            $table->string('model_key', 100);
            $table->string('name', 100);
            $table->unsignedSmallInteger('increment_padding')->default(4);
            $table->unsignedBigInteger('increment_start')->default(1);
            $table->string('increment_scope', 20)->default('format');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();

            $table->index(['model_key', 'is_active']);
            $table->unique(['model_key', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('numbering_formats');
    }
};
