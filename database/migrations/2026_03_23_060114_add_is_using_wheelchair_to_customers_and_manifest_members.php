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
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('is_using_wheelchair')->default(false)->after('has_chronic_disease');
        });

        Schema::table('manifest_members', function (Blueprint $table) {
            $table->boolean('is_using_wheelchair')->nullable()->after('has_chronic_disease');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manifest_members', function (Blueprint $table) {
            $table->dropColumn('is_using_wheelchair');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('is_using_wheelchair');
        });
    }
};
