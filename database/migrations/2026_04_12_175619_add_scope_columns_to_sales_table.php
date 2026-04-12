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
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('country_id')
                ->nullable()
                ->after('branch_id')
                ->constrained('countries')
                ->nullOnDelete();
            $table->json('branch_ids')->nullable()->after('country_id');
            $table->json('country_ids')->nullable()->after('branch_ids');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['branch_ids', 'country_ids']);
            $table->dropConstrainedForeignId('country_id');
        });
    }
};
