<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WorkflowDevelopmentSnapshotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $snapshotPath = database_path('seeders/snapshots/workflow_development_snapshot.php');

        if (! is_file($snapshotPath)) {
            $this->command?->warn('Workflow snapshot file not found: '.$snapshotPath);

            return;
        }

        $snapshot = require $snapshotPath;
        $tables = is_array($snapshot) ? ($snapshot['tables'] ?? []) : [];

        if (! is_array($tables) || empty($tables)) {
            $this->command?->warn('Workflow snapshot has no table data.');

            return;
        }

        $insertOrder = [
            'countries',
            'branches',
            'users',
            'sales',
            'customers',
            'packages',
            'package_accommodations',
            'package_flights',
            'package_train_tickets',
            'package_transportation_plans',
            'package_rawdah_tasreehs',
            'package_officials',
            'enquiries',
            'general_enquiries',
            'private_enquiries',
            'enquiry_remarks',
            'customer_confirmations',
            'customer_confirmation_members',
            'quotations',
            'quotation_notes',
            'quotation_items',
            'quotation_item_taxes',
            'orders',
            'invoices',
            'invoice_items',
            'invoice_notes',
            'receipts',
            'receipt_notes',
            'manifests',
            'manifest_sharing_groups',
            'manifest_members',
            'manifest_member_collection_items',
            'manifest_rooms',
            'manifest_room_members',
            'model_files',
        ];

        $truncateOrder = array_reverse($insertOrder);

        Schema::disableForeignKeyConstraints();

        try {
            foreach ($truncateOrder as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                DB::table($table)->truncate();
            }

            foreach ($insertOrder as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $rows = $tables[$table] ?? [];
                if (! is_array($rows) || empty($rows)) {
                    continue;
                }

                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->command?->info('Workflow development snapshot seeded successfully.');
    }
}
