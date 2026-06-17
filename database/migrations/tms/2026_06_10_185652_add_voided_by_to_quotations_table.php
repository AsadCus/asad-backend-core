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
            $table->foreignId('voided_by')->nullable()->after('email_sent_at')->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable()->after('voided_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn('voided_at');
        });
    }
};
