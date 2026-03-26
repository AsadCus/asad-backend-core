<?php

namespace App\Helpers;

use App\Services\NumberingService;

class NumberGenerator
{
    public static function generate(string $type): string
    {
        return app(NumberingService::class)->generateNextNumber($type);
    }

    public static function rollbackByNumbers(string $type, array $numbers): int
    {
        return app(NumberingService::class)->rollbackByNumbers($type, $numbers);
    }
}
