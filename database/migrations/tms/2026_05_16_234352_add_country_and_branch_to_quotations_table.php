<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropConstrainedForeignId('sales_id');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->renameColumn('created_by', 'handled_by');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->foreign('handled_by')->references('id')->on('users')->nullOnDelete();

            $table->foreignId('country_id')
                ->nullable()
                ->after('handled_by')
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('branch_id')
                ->nullable()
                ->after('country_id')
                ->constrained('branches')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropConstrainedForeignId('country_id');
            $table->dropForeign(['handled_by']);
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->renameColumn('handled_by', 'created_by');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreignId('sales_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }
};
