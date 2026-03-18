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
        Schema::table('manifest_sharing_groups', function (Blueprint $table) {
            $table->foreignId('source_quotation_id')
                ->nullable()
                ->after('customer_confirmation_id')
                ->constrained('quotations')
                ->nullOnDelete();
        });

        Schema::table('manifest_rooms', function (Blueprint $table) {
            $table->foreignId('source_quotation_id')
                ->nullable()
                ->after('manifest_id')
                ->constrained('quotations')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manifest_sharing_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_quotation_id');
        });

        Schema::table('manifest_rooms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_quotation_id');
        });
    }
};
