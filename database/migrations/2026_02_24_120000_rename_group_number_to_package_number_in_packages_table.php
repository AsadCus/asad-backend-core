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
        if (Schema::hasColumn('packages', 'group_number') && ! Schema::hasColumn('packages', 'package_number')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->renameColumn('group_number', 'package_number');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('packages', 'package_number') && ! Schema::hasColumn('packages', 'group_number')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->renameColumn('package_number', 'group_number');
            });
        }
    }
};
