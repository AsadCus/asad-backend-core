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
        Schema::table('quotations', function (Blueprint $table) {
            if (Schema::hasColumn('quotations', 'payment_method')) {
                $table->dropColumn('payment_method');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('quotations', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('payment_plan');
            }
        });
    }
};
