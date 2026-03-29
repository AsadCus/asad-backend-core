<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\Enquiry;
use App\Models\Invoice;
use App\Models\Maid;
use App\Models\Manifest;
use App\Models\NumberingFormat;
use App\Models\NumberingSequence;
use App\Models\NumberingSimpleCounter;
use App\Models\Order;
use App\Models\Package;
use App\Models\Quotation;
use App\Models\Receipt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NumberingService
{
    private const DEFAULT_FORMATS = [
        'customer' => [
            'name' => 'CUST-%YYYY%-%I%',
            'increment_padding' => 4,
            'increment_start' => 1,
            'increment_scope' => 'format',
        ],
        'quotation' => [
            'name' => 'QTN-%YYYY%-%I%',
            'increment_padding' => 3,
            'increment_start' => 1,
            'increment_scope' => 'format',
        ],
        'order' => [
            'name' => 'OR-%YYYY%-%I%',
            'increment_padding' => 3,
            'increment_start' => 1,
            'increment_scope' => 'format',
        ],
        'invoice' => [
            'name' => 'INV-%YYYY%-%I%',
            'increment_padding' => 4,
            'increment_start' => 1,
            'increment_scope' => 'format',
        ],
        'receipt' => [
            'name' => 'R-%YYYY%-%I%',
            'increment_padding' => 4,
            'increment_start' => 1,
            'increment_scope' => 'format',
        ],
        'package' => [
            'name' => 'KTG-%YYYY%-%I%',
            'increment_padding' => 3,
            'increment_start' => 1,
            'increment_scope' => 'format',
        ],
        'manifest' => [
            'name' => 'KTG-UMR-%YYYY%-%I%',
            'increment_padding' => 3,
            'increment_start' => 1,
            'increment_scope' => 'format',
        ],
        'customer_confirmation' => [
            'name' => 'CC-%YYYY%-%I%',
            'increment_padding' => 4,
            'increment_start' => 1,
            'increment_scope' => 'format',
        ],
        'maid' => [
            'name' => 'MD-%YYYY%-%I%',
            'increment_padding' => 4,
            'increment_start' => 1,
            'increment_scope' => 'format',
        ],
        'general_enquiry' => [
            'name' => 'GE-%YYYY%-%I%',
            'increment_padding' => 4,
            'increment_start' => 1,
            'increment_scope' => 'format',
        ],
        'private_enquiry' => [
            'name' => 'PE-%YYYY%-%I%',
            'increment_padding' => 4,
            'increment_start' => 1,
            'increment_scope' => 'format',
        ],
    ];

    private const MODEL_DEFINITIONS = [
        'customer' => [
            'model' => Customer::class,
            'column' => 'customer_number',
            'field' => 'customer_number',
        ],
        'quotation' => [
            'model' => Quotation::class,
            'column' => 'quotation_number',
            'field' => 'quotation_number',
        ],
        'order' => [
            'model' => Order::class,
            'column' => 'order_number',
            'field' => 'order_number',
        ],
        'invoice' => [
            'model' => Invoice::class,
            'column' => 'invoice_number',
            'field' => 'invoice_number',
        ],
        'receipt' => [
            'model' => Receipt::class,
            'column' => 'receipt_number',
            'field' => 'receipt_number',
        ],
        'package' => [
            'model' => Package::class,
            'column' => 'package_number',
            'field' => 'package_number',
        ],
        'manifest' => [
            'model' => Manifest::class,
            'column' => 'manifest_number',
            'field' => 'manifest_number',
        ],
        'customer_confirmation' => [
            'model' => CustomerConfirmation::class,
            'column' => 'number',
            'field' => 'number',
        ],
        'maid' => [
            'model' => Maid::class,
            'column' => 'maid_number',
            'field' => 'maid_number',
        ],
        'general_enquiry' => [
            'model' => Enquiry::class,
            'column' => 'enquiry_number',
            'field' => 'enquiry_number',
        ],
        'private_enquiry' => [
            'model' => Enquiry::class,
            'column' => 'enquiry_number',
            'field' => 'enquiry_number',
        ],
    ];

    public function supportedModelKeys(): array
    {
        return array_keys(self::MODEL_DEFINITIONS);
    }

    public function listFormats(string $modelKey): Collection
    {
        $this->assertModelKey($modelKey);

        $formats = NumberingFormat::query()
            ->where('model_key', $modelKey)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($formats->isNotEmpty()) {
            return $formats;
        }

        $this->bootstrapLegacyDefaultFormat($modelKey);

        return NumberingFormat::query()
            ->where('model_key', $modelKey)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function createFormat(array $payload): NumberingFormat
    {
        $modelKey = (string) ($payload['model_key'] ?? '');
        $this->assertModelKey($modelKey);

        $format = NumberingFormat::query()->create([
            'model_key' => $modelKey,
            'name' => $this->normalizeTemplate($payload['name'] ?? ''),
            'increment_padding' => max(1, (int) ($payload['increment_padding'] ?? 4)),
            'increment_start' => max(1, (int) ($payload['increment_start'] ?? 1)),
            'increment_scope' => $this->normalizeIncrementScope($payload['increment_scope'] ?? 'format'),
            'is_default' => (bool) ($payload['is_default'] ?? false),
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'sort_order' => max(1, (int) ($payload['sort_order'] ?? 1)),
        ]);

        if ($format->is_default) {
            $this->normalizeSingleDefault($modelKey, (int) $format->id);
        }

        return $format->fresh();
    }

    public function updateFormat(NumberingFormat $format, array $payload): NumberingFormat
    {
        $this->assertModelKey((string) $format->model_key);

        $nextIsDefault = array_key_exists('is_default', $payload)
            ? (bool) $payload['is_default']
            : (bool) $format->is_default;

        $format->update([
            'name' => $this->normalizeTemplate($payload['name'] ?? $format->name),
            'increment_padding' => max(1, (int) ($payload['increment_padding'] ?? $format->increment_padding)),
            'increment_start' => max(1, (int) ($payload['increment_start'] ?? $format->increment_start)),
            'increment_scope' => $this->normalizeIncrementScope($payload['increment_scope'] ?? $format->increment_scope),
            'is_default' => $nextIsDefault,
            'is_active' => array_key_exists('is_active', $payload)
                ? (bool) $payload['is_active']
                : (bool) $format->is_active,
            'sort_order' => max(1, (int) ($payload['sort_order'] ?? $format->sort_order)),
        ]);

        if ($nextIsDefault) {
            $this->normalizeSingleDefault((string) $format->model_key, (int) $format->id);
        }

        return $format->fresh();
    }

    public function deleteFormat(NumberingFormat $format): void
    {
        $modelKey = (string) $format->model_key;
        $wasDefault = (bool) $format->is_default;

        $format->delete();

        if ($wasDefault) {
            $fallbackDefaultId = NumberingFormat::query()
                ->where('model_key', $modelKey)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->value('id');

            if ($fallbackDefaultId) {
                $this->normalizeSingleDefault($modelKey, (int) $fallbackDefaultId);
            }
        }
    }

    public function suggestNextNumber(string $modelKey, ?int $formatId = null): array
    {
        return $this->suggestNextNumberWithMode($modelKey, $formatId, null);
    }

    public function suggestNextNumberWithMode(string $modelKey, ?int $formatId = null, ?string $mode = null): array
    {
        if ($this->resolveMode($mode, $formatId) === 'format') {
            return $this->suggestNextNumberByFormat($modelKey, $formatId);
        }

        return $this->suggestNextNumberSimple($modelKey);
    }

    public function getSimpleState(string $modelKey): array
    {
        $this->assertModelKey($modelKey);

        $storedLatest = NumberingSimpleCounter::query()
            ->where('model_key', $modelKey)
            ->value('latest_number');

        $latestNumber = is_string($storedLatest) ? trim($storedLatest) : '';

        if ($latestNumber === '') {
            $persistedLatest = $this->latestPersistedNumber($modelKey);
            $latestNumber = $persistedLatest ?? '';
        }

        return [
            'model_key' => $modelKey,
            'latest_number' => $latestNumber === '' ? null : $latestNumber,
            'next_number' => $this->suggestNextNumberSimple($modelKey)['number'],
            'mode' => 'simple',
        ];
    }

    public function updateSimpleLatestNumber(string $modelKey, ?string $latestNumber): array
    {
        $this->assertModelKey($modelKey);

        $candidate = is_string($latestNumber)
            ? trim($latestNumber)
            : '';

        if ($candidate !== '') {
            $this->validateSimpleNumberStructure($modelKey, $candidate);
        }

        NumberingSimpleCounter::query()->updateOrCreate(
            ['model_key' => $modelKey],
            ['latest_number' => $candidate === '' ? null : $candidate],
        );

        return $this->getSimpleState($modelKey);
    }

    public function generateNextNumber(string $modelKey, ?int $formatId = null): string
    {
        return $this->generateNextNumberWithMode($modelKey, $formatId, null);
    }

    public function generateNextNumberWithMode(string $modelKey, ?int $formatId = null, ?string $mode = null): string
    {
        if ($this->resolveMode($mode, $formatId) === 'format') {
            $generated = $this->generateNextNumberByFormat($modelKey, $formatId);
            $this->syncSimpleLatestNumber($modelKey, $generated);

            return $generated;
        }

        return $this->generateNextNumberSimple($modelKey);
    }

    public function ensureNumber(
        string $modelKey,
        mixed $requestedNumber,
        ?int $ignoreId = null,
        ?int $formatId = null,
        ?string $mode = null,
    ): string {
        $resolvedMode = $this->resolveMode($mode, $formatId);

        $number = is_string($requestedNumber)
            ? trim($requestedNumber)
            : '';

        if ($number === '') {
            return $this->generateNextNumberWithMode($modelKey, $formatId, $resolvedMode);
        }

        $this->validateProvidedNumber($modelKey, $number, $ignoreId, $formatId, $resolvedMode);

        if ($resolvedMode === 'format') {
            $this->syncSequenceFromProvidedNumber($modelKey, $number, $formatId);
            $this->syncSimpleLatestNumber($modelKey, $number);

            return $number;
        }

        $this->syncSimpleLatestNumber($modelKey, $number);

        return $number;
    }

    public function validateProvidedNumber(
        string $modelKey,
        string $number,
        ?int $ignoreId = null,
        ?int $formatId = null,
        ?string $mode = null,
    ): void {
        $this->assertModelKey($modelKey);
        $definition = self::MODEL_DEFINITIONS[$modelKey];
        $resolvedMode = $this->resolveMode($mode, $formatId);

        if ($resolvedMode === 'format') {
            if (! $this->matchesAnyActiveFormat($modelKey, $number)) {
                throw ValidationException::withMessages([
                    $definition['field'] => 'The number does not match any configured active format for this model.',
                ]);
            }
        } else {
            $this->validateSimpleNumberStructure($modelKey, $number);
        }

        $exists = $this->numberExistsForModel($modelKey, $number, $ignoreId);

        if ($exists) {
            throw ValidationException::withMessages([
                $definition['field'] => 'The number has already been used.',
            ]);
        }
    }

    private function suggestNextNumberByFormat(string $modelKey, ?int $formatId = null): array
    {
        $format = $this->resolveFormat($modelKey, $formatId);
        $nextIncrement = $this->peekNextIncrement($format);

        return [
            'model_key' => $modelKey,
            'mode' => 'format',
            'format_id' => $format->id,
            'number' => $this->composeNumber($format, $nextIncrement),
            'next_increment' => $nextIncrement,
        ];
    }

    private function generateNextNumberByFormat(string $modelKey, ?int $formatId = null): string
    {
        $format = $this->resolveFormat($modelKey, $formatId);

        return DB::transaction(function () use ($format): string {
            $sequence = $this->findOrCreateSequence($format, true);
            $nextIncrement = max(
                ((int) $sequence->current_number) + 1,
                (int) $format->increment_start,
            );

            $sequence->update(['current_number' => $nextIncrement]);

            return $this->composeNumber($format, $nextIncrement);
        });
    }

    public function rollbackByNumbers(string $modelKey, array $numbers): int
    {
        if (empty($numbers)) {
            return 0;
        }

        $this->assertModelKey($modelKey);
        $formats = $this->listFormats($modelKey)
            ->where('is_active', true)
            ->values();

        if ($formats->isEmpty()) {
            return 0;
        }

        $grouped = collect($numbers)
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn ($value) => trim((string) $value))
            ->map(function (string $number) use ($formats) {
                foreach ($formats as $format) {
                    $parsed = $this->parseNumber($format, $number);

                    if ($parsed !== null) {
                        return [
                            'format' => $format,
                            'sequence_key' => $this->sequenceKey($format),
                            'sequence_year' => $parsed['sequence_year'],
                            'increment' => $parsed['increment'],
                        ];
                    }
                }

                return null;
            })
            ->filter(fn ($row) => is_array($row))
            ->groupBy(fn (array $row) => implode('|', [
                (string) $row['sequence_key'],
                (string) ($row['sequence_year'] ?? ''),
            ]));

        if ($grouped->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($grouped): int {
            $rolledBack = 0;

            foreach ($grouped as $rows) {
                $first = $rows->first();
                if (! is_array($first) || ! isset($first['format']) || ! ($first['format'] instanceof NumberingFormat)) {
                    continue;
                }

                /** @var NumberingFormat $format */
                $format = $first['format'];
                $sequence = $this->findOrCreateSequence($format, true, $first['sequence_year'] ?? null);
                $deletedNumbersLookup = $rows
                    ->pluck('increment')
                    ->map(fn ($value) => (int) $value)
                    ->filter(fn (int $value) => $value > 0)
                    ->unique()
                    ->flip();

                $current = (int) $sequence->current_number;
                $rollbackCount = 0;

                while ($current > 0 && $deletedNumbersLookup->has($current)) {
                    $rollbackCount++;
                    $current--;
                }

                if ($rollbackCount === 0) {
                    continue;
                }

                $sequence->update([
                    'current_number' => max(0, (int) $sequence->current_number - $rollbackCount),
                ]);

                $rolledBack += $rollbackCount;
            }

            return $rolledBack;
        });
    }

    public function matchesAnyActiveFormat(string $modelKey, string $number): bool
    {
        $this->assertModelKey($modelKey);
        $trimmed = trim($number);

        if ($trimmed === '') {
            return false;
        }

        return $this->listFormats($modelKey)
            ->where('is_active', true)
            ->contains(fn (NumberingFormat $format) => $this->matchesFormat($format, $trimmed));
    }

    private function suggestNextNumberSimple(string $modelKey): array
    {
        $this->assertModelKey($modelKey);

        $latest = $this->resolveSimpleLatestNumber($modelKey);

        if ($latest !== null) {
            $nextCandidate = $this->incrementSimpleNumber($latest, $modelKey);
            $nextNumber = $this->findFirstAvailableSimpleNumber($modelKey, $nextCandidate);

            return [
                'model_key' => $modelKey,
                'mode' => 'simple',
                'latest_number' => $latest,
                'number' => $nextNumber,
            ];
        }

        $fallback = $this->suggestNextNumberByFormat($modelKey, null);
        $nextNumber = (string) ($fallback['number'] ?? '');

        return [
            'model_key' => $modelKey,
            'mode' => 'simple',
            'latest_number' => null,
            'number' => $nextNumber,
        ];
    }

    private function generateNextNumberSimple(string $modelKey): string
    {
        $suggestion = $this->suggestNextNumberSimple($modelKey);
        $number = (string) ($suggestion['number'] ?? '');

        if ($number === '') {
            throw ValidationException::withMessages([
                self::MODEL_DEFINITIONS[$modelKey]['field'] => 'Unable to generate next number for this model.',
            ]);
        }

        $this->syncSimpleLatestNumber($modelKey, $number);

        return $number;
    }

    private function resolveSimpleLatestNumber(string $modelKey): ?string
    {
        $counterLatest = NumberingSimpleCounter::query()
            ->where('model_key', $modelKey)
            ->value('latest_number');

        if (is_string($counterLatest) && trim($counterLatest) !== '') {
            return trim($counterLatest);
        }

        return $this->latestPersistedNumber($modelKey);
    }

    private function latestPersistedNumber(string $modelKey): ?string
    {
        $this->assertModelKey($modelKey);
        $definition = self::MODEL_DEFINITIONS[$modelKey];

        /** @var class-string<Model> $modelClass */
        $modelClass = $definition['model'];
        $column = (string) $definition['column'];

        $query = $modelClass::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->orderByDesc('id');

        if (in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            $query->withTrashed();
        }

        $value = $query->value($column);

        return is_string($value) && trim($value) !== ''
            ? trim($value)
            : null;
    }

    private function incrementSimpleNumber(string $number, string $modelKey): string
    {
        $parsed = $this->parseSimpleNumber($number);

        if (! is_array($parsed)) {
            throw ValidationException::withMessages([
                self::MODEL_DEFINITIONS[$modelKey]['field'] => 'The latest number must end with a numeric value.',
            ]);
        }

        $nextValue = ((int) $parsed['increment']) + 1;

        return (string) $parsed['prefix'].str_pad(
            (string) $nextValue,
            (int) $parsed['padding'],
            '0',
            STR_PAD_LEFT,
        );
    }

    private function parseSimpleNumber(string $number): ?array
    {
        $trimmed = trim($number);

        if (! preg_match('/^(.*?)(\d+)$/', $trimmed, $matches)) {
            return null;
        }

        $digits = (string) ($matches[2] ?? '');

        return [
            'prefix' => (string) ($matches[1] ?? ''),
            'digits' => $digits,
            'padding' => max(1, strlen($digits)),
            'increment' => (int) $digits,
        ];
    }

    private function validateSimpleNumberStructure(string $modelKey, string $number): void
    {
        if ($this->parseSimpleNumber($number) !== null) {
            return;
        }

        throw ValidationException::withMessages([
            self::MODEL_DEFINITIONS[$modelKey]['field'] => 'The number must end with a numeric value.',
        ]);
    }

    private function findFirstAvailableSimpleNumber(string $modelKey, string $candidate): string
    {
        $current = $candidate;

        while ($this->numberExistsForModel($modelKey, $current)) {
            $current = $this->incrementSimpleNumber($current, $modelKey);
        }

        return $current;
    }

    private function syncSimpleLatestNumber(string $modelKey, string $number): void
    {
        NumberingSimpleCounter::query()->updateOrCreate(
            ['model_key' => $modelKey],
            ['latest_number' => trim($number)],
        );
    }

    private function numberExistsForModel(string $modelKey, string $number, ?int $ignoreId = null): bool
    {
        $definition = self::MODEL_DEFINITIONS[$modelKey];

        /** @var class-string<Model> $modelClass */
        $modelClass = $definition['model'];
        $column = (string) $definition['column'];

        $query = $modelClass::query()
            ->where($column, $number)
            ->when($ignoreId, function (Builder $query) use ($ignoreId): void {
                $query->whereKeyNot($ignoreId);
            });

        if (in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            $query->withTrashed();
        }

        return $query->exists();
    }

    private function resolveMode(?string $mode, ?int $formatId = null): string
    {
        $candidate = strtolower(trim((string) $mode));

        if ($candidate === '') {
            return $formatId ? 'format' : 'simple';
        }

        return $candidate === 'format' ? 'format' : 'simple';
    }

    private function assertModelKey(string $modelKey): void
    {
        if (! isset(self::MODEL_DEFINITIONS[$modelKey])) {
            throw new \InvalidArgumentException("Unsupported model key: {$modelKey}");
        }
    }

    private function resolveFormat(string $modelKey, ?int $formatId = null): NumberingFormat
    {
        $this->assertModelKey($modelKey);

        $query = NumberingFormat::query()
            ->where('model_key', $modelKey)
            ->where('is_active', true);

        if ($formatId) {
            $format = (clone $query)
                ->whereKey($formatId)
                ->first();

            if ($format) {
                return $format;
            }
        }

        $default = (clone $query)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if (! $default) {
            $default = $this->bootstrapLegacyDefaultFormat($modelKey);
        }

        if (! $default) {
            throw ValidationException::withMessages([
                self::MODEL_DEFINITIONS[$modelKey]['field'] => 'No active numbering format is configured for this model.',
            ]);
        }

        return $default;
    }

    private function bootstrapLegacyDefaultFormat(string $modelKey): ?NumberingFormat
    {
        $template = self::DEFAULT_FORMATS[$modelKey] ?? null;

        if (! is_array($template)) {
            return null;
        }

        $format = NumberingFormat::query()->firstOrCreate(
            [
                'model_key' => $modelKey,
                'name' => $template['name'],
            ],
            [
                ...$template,
                'model_key' => $modelKey,
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        if (! $format->is_active || ! $format->is_default) {
            $format->update([
                'is_active' => true,
                'is_default' => true,
            ]);
        }

        $this->normalizeSingleDefault($modelKey, (int) $format->id);

        return $format->fresh();
    }

    private function normalizeSingleDefault(string $modelKey, int $defaultId): void
    {
        NumberingFormat::query()
            ->where('model_key', $modelKey)
            ->update([
                'is_default' => false,
            ]);

        NumberingFormat::query()
            ->where('model_key', $modelKey)
            ->whereKey($defaultId)
            ->update([
                'is_default' => true,
            ]);
    }

    private function findOrCreateSequence(
        NumberingFormat $format,
        bool $lockForUpdate = false,
        ?string $sequenceYearOverride = null,
    ): NumberingSequence {
        $sequenceYear = $sequenceYearOverride ?? $this->currentSequenceYear($format);

        $query = NumberingSequence::query()
            ->where('model_key', $format->model_key)
            ->where('sequence_key', $this->sequenceKey($format))
            ->where('sequence_year', $sequenceYear);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $existing = $query->first();
        if ($existing) {
            return $existing;
        }

        $startAt = max(1, (int) $format->increment_start);

        return NumberingSequence::query()->create([
            'model_key' => $format->model_key,
            'sequence_key' => $this->sequenceKey($format),
            'sequence_year' => $sequenceYear,
            'current_number' => $startAt - 1,
        ]);
    }

    private function peekNextIncrement(NumberingFormat $format): int
    {
        return DB::transaction(function () use ($format): int {
            $sequence = $this->findOrCreateSequence($format, true);

            return max(
                ((int) $sequence->current_number) + 1,
                (int) $format->increment_start,
            );
        });
    }

    private function composeNumber(NumberingFormat $format, int $increment): string
    {
        $template = $this->resolveTemplate($format);

        return strtr($template, [
            '%DD%' => now()->format('d'),
            '%MM%' => now()->format('m'),
            '%YY%' => now()->format('y'),
            '%YYYY%' => now()->format('Y'),
            '%I%' => str_pad(
                (string) $increment,
                max(1, (int) $format->increment_padding),
                '0',
                STR_PAD_LEFT,
            ),
        ]);
    }

    private function sequenceKey(NumberingFormat $format): string
    {
        if ($format->increment_scope === 'model') {
            return 'model';
        }

        return 'format:'.$format->id;
    }

    private function matchesFormat(NumberingFormat $format, string $number): bool
    {
        return $this->parseNumber($format, $number) !== null;
    }

    private function parseNumber(NumberingFormat $format, string $number): ?array
    {
        $template = $this->resolveTemplate($format);
        $escaped = preg_quote($template, '/');
        $pattern = str_replace(
            [
                '%YYYY%',
                '%YY%',
                '%MM%',
                '%DD%',
                '%I%',
            ],
            [
                '(?P<year4>\\d{4})',
                '(?P<year2>\\d{2})',
                '(?P<month>\\d{2})',
                '(?P<day>\\d{2})',
                '(?P<increment>\\d{'.max(1, (int) $format->increment_padding).',})',
            ],
            $escaped,
        );

        if (! preg_match('/^'.$pattern.'$/', $number, $matches)) {
            return null;
        }

        $increment = (int) ($matches['increment'] ?? 0);
        if ($increment <= 0) {
            return null;
        }

        $sequenceYear = '';
        if (str_contains($template, '%YYYY%')) {
            $sequenceYear = (string) ($matches['year4'] ?? '');
        } elseif (str_contains($template, '%YY%')) {
            $sequenceYear = (string) ($matches['year2'] ?? '');
        }

        return [
            'sequence_year' => $sequenceYear,
            'increment' => $increment,
        ];
    }

    private function syncSequenceFromProvidedNumber(string $modelKey, string $number, ?int $preferredFormatId = null): void
    {
        $format = $this->resolveMatchingFormatForNumber(
            $modelKey,
            $number,
            $preferredFormatId,
        );

        if (! $format) {
            return;
        }

        $parsed = $this->parseNumber($format, $number);
        if (! is_array($parsed)) {
            return;
        }

        $increment = (int) ($parsed['increment'] ?? 0);
        if ($increment <= 0) {
            return;
        }

        DB::transaction(function () use ($format, $parsed, $increment): void {
            $sequence = $this->findOrCreateSequence(
                $format,
                true,
                isset($parsed['sequence_year']) && is_string($parsed['sequence_year'])
                    ? $parsed['sequence_year']
                    : '',
            );

            if ((int) $sequence->current_number >= $increment) {
                return;
            }

            $sequence->update([
                'current_number' => $increment,
            ]);
        });
    }

    private function resolveMatchingFormatForNumber(
        string $modelKey,
        string $number,
        ?int $preferredFormatId = null,
    ): ?NumberingFormat {
        $formats = $this->listFormats($modelKey)
            ->where('is_active', true)
            ->values();

        if ($formats->isEmpty()) {
            return null;
        }

        if ($preferredFormatId) {
            $preferred = $formats
                ->first(fn (NumberingFormat $format) => (int) $format->id === $preferredFormatId);

            if ($preferred && $this->parseNumber($preferred, $number) !== null) {
                return $preferred;
            }
        }

        return $formats
            ->first(fn (NumberingFormat $format) => $this->parseNumber($format, $number) !== null);
    }

    private function normalizeTemplate(mixed $value): string
    {
        $string = trim((string) $value);

        if ($string === '' || ! str_contains($string, '%I%')) {
            throw ValidationException::withMessages([
                'name' => 'The format template must include %I%.',
            ]);
        }

        return $string;
    }

    private function normalizeIncrementScope(mixed $value): string
    {
        $scope = strtolower(trim((string) $value));

        return in_array($scope, ['format', 'model'], true)
            ? $scope
            : 'format';
    }

    private function resolveTemplate(NumberingFormat $format): string
    {
        return $this->normalizeTemplate($format->name);
    }

    private function currentSequenceYear(NumberingFormat $format): string
    {
        $template = $this->resolveTemplate($format);

        if (str_contains($template, '%YYYY%')) {
            return now()->format('Y');
        }

        if (str_contains($template, '%YY%')) {
            return now()->format('y');
        }

        return '';
    }
}
