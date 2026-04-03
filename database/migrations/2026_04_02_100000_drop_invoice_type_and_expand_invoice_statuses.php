<?php

use App\Support\InvoiceStatus;
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
        if (Schema::hasColumn('invoices', 'type')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->dropColumn('type');
            });
        }

        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('status', 50)
                ->default(InvoiceStatus::Draft)
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->enum('status', [
                InvoiceStatus::Draft,
                InvoiceStatus::Issued,
                InvoiceStatus::Paid,
                InvoiceStatus::Overdue,
                InvoiceStatus::Cancelled,
            ])->default(InvoiceStatus::Draft)->change();
        });

        if (! Schema::hasColumn('invoices', 'type')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->enum('type', ['deposit', 'handover', 'installment'])
                    ->nullable()
                    ->after('invoice_number');
            });
        }
    }
};
