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
        Schema::table('receipts', function (Blueprint $table): void {
            $table->dropForeign(['invoice_id']);
        });

        Schema::table('receipts', function (Blueprint $table): void {
            $table->unsignedBigInteger('invoice_id')->nullable()->change();
            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table): void {
            $table->dropForeign(['invoice_id']);
        });

        Schema::table('receipts', function (Blueprint $table): void {
            $table->unsignedBigInteger('invoice_id')->nullable(false)->change();
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
        });
    }
};
