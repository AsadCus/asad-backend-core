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
}
