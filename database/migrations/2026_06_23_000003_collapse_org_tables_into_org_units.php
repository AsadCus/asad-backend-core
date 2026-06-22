<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Collapse the flat holding/business_unit/department tables into the recursive
 * org_units tree. Employees now reference org_units (org_unit_id) added earlier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['holding_id']);
            $table->dropForeign(['business_unit_id']);
            $table->dropForeign(['department_id']);
            $table->dropIndex(['department_id']); // explicit index from the employees migration
            $table->dropColumn(['holding_id', 'business_unit_id', 'department_id']);
        });

        Schema::dropIfExists('departments');
        Schema::dropIfExists('business_units');
        Schema::dropIfExists('holdings');
    }

    public function down(): void
    {
        Schema::create('holdings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('address')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('business_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('holding_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_unit_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('holding_id')->nullable()->after('termination_date')->constrained('holdings')->nullOnDelete();
            $table->foreignId('business_unit_id')->nullable()->after('holding_id')->constrained('business_units')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->after('business_unit_id')->constrained('departments')->nullOnDelete();
            $table->index('department_id');
        });
    }
};
