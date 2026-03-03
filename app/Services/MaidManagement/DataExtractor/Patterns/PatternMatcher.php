<?php

namespace App\Services\MaidManagement\DataExtractor\Patterns;

class PatternMatcher
{
    public static function match(string $text, array $patterns): ?string
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return trim($match[1] ?? '');
            }
        }

        return null;
    }

    public static function matchMultiple(string $text, array $patterns): array
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                array_shift($matches);

                return array_map('trim', $matches);
            }
        }

        return [];
    }
}
