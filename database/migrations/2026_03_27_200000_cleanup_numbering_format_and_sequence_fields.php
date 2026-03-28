<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('numbering_formats')) {
            $this->migrateLegacyFormatNamesToTemplates();

            Schema::table('numbering_formats', function (Blueprint $table) {
                $columns = [
                    'prefix',
                    'separator',
                    'include_year',
                    'year_format',
                ];

                $existing = array_values(array_filter(
                    $columns,
                    fn (string $column): bool => Schema::hasColumn('numbering_formats', $column),
                ));

                if (! empty($existing)) {
                    $table->dropColumn($existing);
                }
            });
        }

        if (Schema::hasTable('numbering_sequences')) {
            $this->normalizeGlobalSequenceRows();

            Schema::table('numbering_sequences', function (Blueprint $table) {
                if (Schema::hasColumn('numbering_sequences', 'numbering_format_id')) {
                    $table->dropConstrainedForeignId('numbering_format_id');
                }
            });

            if (Schema::hasColumn('numbering_sequences', 'sequence_year')) {
                DB::table('numbering_sequences')
                    ->whereNull('sequence_year')
                    ->update(['sequence_year' => '']);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('numbering_formats')) {
            Schema::table('numbering_formats', function (Blueprint $table) {
                if (! Schema::hasColumn('numbering_formats', 'prefix')) {
                    $table->string('prefix', 100)->nullable()->after('name');
                }

                if (! Schema::hasColumn('numbering_formats', 'separator')) {
                    $table->string('separator', 10)->default('-')->after('prefix');
                }

                if (! Schema::hasColumn('numbering_formats', 'include_year')) {
                    $table->boolean('include_year')->default(true)->after('separator');
                }

                if (! Schema::hasColumn('numbering_formats', 'year_format')) {
                    $table->string('year_format', 20)->default('Y')->after('include_year');
                }
            });
        }

        if (Schema::hasTable('numbering_sequences')) {
            Schema::table('numbering_sequences', function (Blueprint $table) {
                if (! Schema::hasColumn('numbering_sequences', 'numbering_format_id')) {
                    $table->foreignId('numbering_format_id')
                        ->nullable()
                        ->after('sequence_key')
                        ->constrained('numbering_formats')
                        ->nullOnDelete();
                }
            });
        }
    }

    private function migrateLegacyFormatNamesToTemplates(): void
    {
        $columnsNeeded = [
            'name',
            'prefix',
            'separator',
            'include_year',
            'year_format',
        ];

        foreach ($columnsNeeded as $column) {
            if (! Schema::hasColumn('numbering_formats', $column)) {
                return;
            }
        }

        $rows = DB::table('numbering_formats')
            ->select(['id', 'name', 'prefix', 'separator', 'include_year', 'year_format'])
            ->get();

        foreach ($rows as $row) {
            $name = trim((string) ($row->name ?? ''));
            if ($name !== '' && str_contains($name, '%I%')) {
                continue;
            }

            $parts = [];
            $prefix = trim((string) ($row->prefix ?? ''));
            if ($prefix !== '') {
                $parts[] = $prefix;
            }

            $includeYear = (bool) ($row->include_year ?? false);
            if ($includeYear) {
                $parts[] = (string) ($row->year_format ?? 'Y') === 'y' ? '%YY%' : '%YYYY%';
            }

            $parts[] = '%I%';

            $separator = trim((string) ($row->separator ?? '-'));
            if ($separator === '') {
                $separator = '-';
            }

            $template = implode($separator, $parts);
            if ($template === '') {
                $template = '%I%';
            }

            DB::table('numbering_formats')
                ->where('id', (int) $row->id)
                ->update(['name' => $template]);
        }
    }

    private function normalizeGlobalSequenceRows(): void
    {
        if (! Schema::hasColumn('numbering_sequences', 'sequence_year')) {
            return;
        }

        $groups = DB::table('numbering_sequences')
            ->select([
                'model_key',
                'sequence_key',
                DB::raw("COALESCE(sequence_year, '') as normalized_year"),
                DB::raw('COUNT(*) as total_rows'),
                DB::raw('MAX(current_number) as max_current_number'),
            ])
            ->groupBy('model_key', 'sequence_key', DB::raw("COALESCE(sequence_year, '')"))
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $normalizedYear = (string) ($group->normalized_year ?? '');

            $rows = DB::table('numbering_sequences')
                ->where('model_key', (string) $group->model_key)
                ->where('sequence_key', (string) $group->sequence_key)
                ->where(function ($query) use ($normalizedYear): void {
                    if ($normalizedYear === '') {
                        $query->whereNull('sequence_year')
                            ->orWhere('sequence_year', '');

                        return;
                    }

                    $query->where('sequence_year', $normalizedYear);
                })
                ->orderBy('id')
                ->get(['id']);

            $keepId = (int) ($rows->first()->id ?? 0);
            if ($keepId <= 0) {
                continue;
            }

            DB::table('numbering_sequences')
                ->where('id', $keepId)
                ->update([
                    'sequence_year' => $normalizedYear,
                    'current_number' => (int) ($group->max_current_number ?? 0),
                ]);

            $deleteIds = $rows
                ->pluck('id')
                ->map(fn ($value): int => (int) $value)
                ->filter(fn (int $value): bool => $value !== $keepId)
                ->values();

            if ($deleteIds->isNotEmpty()) {
                DB::table('numbering_sequences')
                    ->whereIn('id', $deleteIds->all())
                    ->delete();
            }
        }
    }
};
