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
        if (! Schema::hasColumn('customer_confirmations', 'deleted_at')) {
            Schema::table('customer_confirmations', function (Blueprint $table): void {
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('customer_confirmations', 'deleted_at')) {
            Schema::table('customer_confirmations', function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }
    }
};
