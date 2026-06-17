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
        Schema::table('package_officials', function (Blueprint $table) {
            $table->foreignId('official_id')->nullable()->after('package_id')
                ->constrained('officials')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('package_officials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('official_id');
        });
    }
};
