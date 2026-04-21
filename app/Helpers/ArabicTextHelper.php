<?php

namespace App\Helpers;

use ArPHP\I18N\Arabic;

class ArabicTextHelper
{
    public static function shapeForPdf(?string $text): string
    {
        $value = trim((string) $text);

        if ($value === '') {
            return (string) $text;
        }

        if (! preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}]/u', $value)) {
            return (string) $text;
        }

        $arabic = new Arabic;

        // $arabicClass = 'ArPHP\\I18N\\Arabic';
        // if (! class_exists($arabicClass)) {
        //     return (string) $text;
        // }

        // $arabic = new $arabicClass;
        // if (! method_exists($arabic, 'utf8Glyphs')) {
        //     return (string) $text;
        // }

        return $arabic->utf8Glyphs((string) $text, 50, false, false);
    }
}
