<?php

namespace App\Helpers;

use App\Models\NumberSequence;
use Illuminate\Support\Facades\DB;

class NumberGenerator
{
    private static $formats = [
        'customer' => ['prefix' => 'CUST', 'padding' => 4],
        'quotation' => ['prefix' => 'QTN', 'padding' => 3],
        'order' => ['prefix' => 'OR', 'padding' => 3],
        'invoice' => ['prefix' => 'INV', 'padding' => 4],
        'receipt' => ['prefix' => 'R', 'padding' => 4],
        'schedule' => ['prefix' => 'SCH', 'padding' => 4],
        'agreement' => ['prefix' => 'AGR', 'padding' => 4],
        'package' => ['prefix' => 'KTG', 'padding' => 3],
        'manifest' => ['prefix' => 'KTG-UMR', 'padding' => 3],
        'customer_confirmation' => ['prefix' => 'CC', 'padding' => 4],
    ];

    public static function generate(string $type): string
    {
        if (! isset(self::$formats[$type])) {
            throw new \InvalidArgumentException("Invalid type: {$type}");
        }

        $year = date('Y');
        $format = self::$formats[$type];

        return DB::transaction(function () use ($type, $year, $format) {
            $sequence = NumberSequence::lockForUpdate()
                ->firstOrCreate(
                    ['type' => $type, 'year' => $year],
                    ['current_number' => 0]
                );

            $sequence->increment('current_number');
            $sequence->refresh();

            $number = str_pad($sequence->current_number, $format['padding'], '0', STR_PAD_LEFT);

            return "{$format['prefix']}-{$year}-{$number}";
        });
    }

    public static function rollbackByNumbers(string $type, array $numbers): int
    {
        if (! isset(self::$formats[$type])) {
            throw new \InvalidArgumentException("Invalid type: {$type}");
        }

        $year = (int) date('Y');
        $format = self::$formats[$type];

        return DB::transaction(function () use ($type, $numbers, $year, $format): int {
            $sequence = NumberSequence::lockForUpdate()
                ->where('type', $type)
                ->where('year', $year)
                ->first();

            if (! $sequence) {
                return 0;
            }

            $deletedNumbers = collect($numbers)
                ->map(fn ($number) => self::extractSequenceNumber(
                    is_string($number) ? $number : null,
                    $format['prefix'],
                    $year,
                ))
                ->filter(fn ($number) => $number !== null)
                ->unique()
                ->values()
                ->all();

            if (empty($deletedNumbers)) {
                return 0;
            }

            $deletedLookup = array_fill_keys($deletedNumbers, true);
            $current = (int) $sequence->current_number;
            $rollbackCount = 0;

            while ($current > 0 && isset($deletedLookup[$current])) {
                $rollbackCount++;
                $current--;
            }

            if ($rollbackCount === 0) {
                return 0;
            }

            $sequence->update([
                'current_number' => max(0, (int) $sequence->current_number - $rollbackCount),
            ]);

            return $rollbackCount;
        });
    }

    private static function extractSequenceNumber(?string $number, string $prefix, int $year): ?int
    {
        if (! $number) {
            return null;
        }

        $pattern = '/^'.preg_quote($prefix, '/').'-'.$year.'-(\d+)$/';

        if (! preg_match($pattern, $number, $matches)) {
            return null;
        }

        $sequenceNumber = (int) $matches[1];

        return $sequenceNumber > 0 ? $sequenceNumber : null;
    }
}
