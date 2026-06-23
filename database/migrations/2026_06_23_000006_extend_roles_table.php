<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Role = Jabatan: extend Spatie's roles with display + classification metadata.
 * - label: human display name (machine `name` stays the immutable referential key)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('label')->nullable()->after('name');
            $table->string('description')->nullable()->after('label');
            $table->foreignId('role_group_id')->nullable()->after('description')->constrained('role_groups')->nullOnDelete();
            $table->foreignId('management_level_id')->nullable()->after('role_group_id')->constrained('management_levels')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign(['role_group_id']);
            $table->dropForeign(['management_level_id']);
            $table->dropColumn(['label', 'description', 'role_group_id', 'management_level_id']);
        });
    }
};
