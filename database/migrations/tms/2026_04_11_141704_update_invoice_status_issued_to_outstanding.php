<?php

use App\Support\InvoiceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        DB::table('invoices')
            ->where('status', InvoiceStatus::Issued)
            ->update(['status' => InvoiceStatus::Outstanding]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        DB::table('invoices')
            ->where('status', InvoiceStatus::Outstanding)
            ->update(['status' => InvoiceStatus::Issued]);
    }
};
