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
        Schema::table('customer_confirmations', function (Blueprint $table) {
            $table->boolean('is_holding')->default(false)->after('package_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_confirmations', function (Blueprint $table) {
            $table->dropColumn('is_holding');
        });
    }
};
