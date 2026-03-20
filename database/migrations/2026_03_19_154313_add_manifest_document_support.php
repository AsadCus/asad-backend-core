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
        Schema::table('model_files', function (Blueprint $table) {
            $table->dropUnique('model_files_unique_file_owner_field');
            $table->index(['fileable_type', 'fileable_id', 'field'], 'model_files_owner_field_index');
        });

        Schema::table('manifest_members', function (Blueprint $table) {
            $table->string('arabic_name')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('model_files', function (Blueprint $table) {
            $table->dropIndex('model_files_owner_field_index');
            $table->unique(['fileable_type', 'fileable_id', 'field'], 'model_files_unique_file_owner_field');
        });

        Schema::table('manifest_members', function (Blueprint $table) {
            $table->dropColumn('arabic_name');
        });
    }
};
