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
        Schema::create('numbering_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('model_key', 100);
            $table->string('sequence_key', 120);
            $table->foreignId('numbering_format_id')
                ->nullable()
                ->constrained('numbering_formats')
                ->nullOnDelete();
            $table->string('sequence_year', 20)->nullable();
            $table->unsignedBigInteger('current_number')->default(0);
            $table->timestamps();

            $table->unique(['model_key', 'sequence_key', 'sequence_year'], 'numbering_sequences_scope_unique');
            $table->index(['model_key', 'numbering_format_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('numbering_sequences');
    }
};
