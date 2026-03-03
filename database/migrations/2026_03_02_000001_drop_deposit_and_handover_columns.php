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
            if (Schema::hasColumn('quotations', 'deposit_type')) {
                $table->dropColumn('deposit_type');
            }

            if (Schema::hasColumn('quotations', 'deposit_value')) {
                $table->dropColumn('deposit_value');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'handover_date')) {
                $table->dropColumn('handover_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('quotations', 'deposit_type')) {
                $table->string('deposit_type')->nullable()->after('payment_plan');
            }

            if (! Schema::hasColumn('quotations', 'deposit_value')) {
                $table->decimal('deposit_value', 10, 2)->nullable()->after('deposit_type');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'handover_date')) {
                $table->date('handover_date')->nullable()->after('payment_plan');
            }
        });
    }
};
