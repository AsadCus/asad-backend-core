<?php

namespace App\Helpers;

class FormatService
{
    public function cleanDecimal($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (float) $value;
    }

    public static function formatCurrency($value, $showDecimals = 'auto')
    {
        if ($value === null || $value === '') {
            return '';
        }

        $sign = $value < 0 ? '-' : '';
        $absValue = abs($value);

        if ($showDecimals === 'auto') {
            $decimals = ($absValue == floor($absValue)) ? 0 : 2;
        } else {
            $decimals = $showDecimals ? 2 : 0;
        }

        return $sign . '$' . number_format($absValue, $decimals);
    }
}
