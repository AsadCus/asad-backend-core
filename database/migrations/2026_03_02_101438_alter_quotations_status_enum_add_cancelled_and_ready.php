<?php

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
        if (! Schema::hasTable('quotations')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $columnType = DB::table('information_schema.columns')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'quotations')
            ->where('column_name', 'status')
            ->value('data_type');

        if ($columnType !== 'enum') {
            return;
        }

        DB::statement("ALTER TABLE `quotations` MODIFY `status` VARCHAR(50) NOT NULL DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('quotations')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $columnType = DB::table('information_schema.columns')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'quotations')
            ->where('column_name', 'status')
            ->value('data_type');

        if ($columnType !== 'varchar') {
            return;
        }

        DB::statement("ALTER TABLE `quotations` MODIFY `status` ENUM('draft','sent','revised','ready','accepted','converted','rejected','expired','cancelled') NOT NULL DEFAULT 'draft'");
    }
};
