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
        Schema::table('manifests', function (Blueprint $table) {
            if (! Schema::hasColumn('manifests', 'in_charge_official_id')) {
                $table->foreignId('in_charge_official_id')
                    ->nullable()
                    ->after('package_id')
                    ->constrained('package_officials')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manifests', function (Blueprint $table) {
            if (Schema::hasColumn('manifests', 'in_charge_official_id')) {
                $table->dropConstrainedForeignId('in_charge_official_id');
            }
        });
    }
};
