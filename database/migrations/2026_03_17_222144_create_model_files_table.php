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
        Schema::create('model_files', function (Blueprint $table) {
            $table->id();
            $table->morphs('fileable');
            $table->string('field');
            $table->string('file_name');
            $table->string('file_path');
            $table->index(['fileable_type', 'fileable_id', 'field'], 'model_files_owner_field_index');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('model_files');
    }
};
