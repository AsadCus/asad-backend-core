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
        if (! Schema::hasColumn('quotations', 'maid_id')) {
            return;
        }

        Schema::table('quotations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('maid_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('quotations', 'maid_id')) {
            return;
        }

        Schema::table('quotations', function (Blueprint $table): void {
            $table->foreignId('maid_id')
                ->nullable()
                ->after('customer_confirmation_id')
                ->constrained('maids')
                ->nullOnDelete();
        });
    }
};
