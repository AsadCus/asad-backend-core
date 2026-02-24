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
        'package' => ['prefix' => 'PKG', 'padding' => 4],
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
}
