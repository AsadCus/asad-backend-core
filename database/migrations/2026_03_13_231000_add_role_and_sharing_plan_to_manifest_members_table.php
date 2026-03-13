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
        Schema::table('manifest_members', function (Blueprint $table) {
            $table->string('role')->nullable()->after('package_official_id');
            $table->string('sharing_plan')->nullable()->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manifest_members', function (Blueprint $table) {
            $table->dropColumn([
                'role',
                'sharing_plan',
            ]);
        });
    }
};
