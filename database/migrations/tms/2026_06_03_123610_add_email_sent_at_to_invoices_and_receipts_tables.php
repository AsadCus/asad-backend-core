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
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('email_sent_at')->nullable()->after('status');
        });

        Schema::table('receipts', function (Blueprint $table) {
            $table->timestamp('email_sent_at')->nullable()->after('payment_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('email_sent_at');
        });

        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn('email_sent_at');
        });
    }
};
