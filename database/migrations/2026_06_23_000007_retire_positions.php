<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retire positions: jabatan is now the user's Role. The PositionLevel enum survives
 * as the approval-routing seniority scale (used by the approval matrix).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['position_id']);
            $table->dropIndex(['position_id']); // explicit index from the employees migration
            $table->dropColumn('position_id');
        });

        Schema::dropIfExists('positions');
    }

    public function down(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('level')->default('staff');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('position_id')->nullable()->after('org_unit_id')->constrained('positions')->nullOnDelete();
            $table->index('position_id');
        });
    }
};
