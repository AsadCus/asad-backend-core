<?php

namespace App\Services\MaidManagement\DataExtractor\Patterns;

class SmartPatternMatcher
{
    public static function match(string $text, array $patterns): ?string
    {
        $results = [];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $value = trim($match[1] ?? '');
                if (!empty($value) && $value !== 'n' && $value !== 'b') {
                    $score = self::calculateScore($value, $pattern, $text);
                    $results[] = [
                        'value' => $value,
                        'score' => $score,
                        'pattern' => $pattern
                    ];
                }
            }
        }
        
        if (empty($results)) {
            return null;
        }
        
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        
        return $results[0]['value'];
    }

    public static function matchMultiple(string $text, array $patterns): array
    {
        $results = [];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                array_shift($matches);
                $values = array_map('trim', $matches);
                
                if (!empty($values) && !in_array($values[0], ['', 'n', 'b'])) {
                    $score = self::calculateScore($values[0], $pattern, $text);
                    $results[] = [
                        'values' => $values,
                        'score' => $score
                    ];
                }
            }
        }
        
        if (empty($results)) {
            return [];
        }
        
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        
        return $results[0]['values'];
    }

    private static function calculateScore(string $value, string $pattern, string $text): int
    {
        $score = 0;
        
        // Score 1: Length of matched value (longer = better)
        $score += min(strlen($value), 50);
        
        // Score 2: Pattern specificity (more specific = higher score)
        $score += (substr_count($pattern, '\\s*') * 5);
        $score += (substr_count($pattern, '(?=') * 10);
        
        // Score 3: Penalize generic patterns
        if (strpos($pattern, '[^\\n]+?') !== false) {
            $score -= 5;
        }
        
        // Score 4: Bonus for exact field name match
        if (preg_match('/Name of port.*airport/i', $pattern)) {
            $score += 15;
        }
        if (preg_match('/Address.*Name of port/i', $pattern)) {
            $score += 15;
        }
        
        // Score 5: Bonus for numbered patterns (more structured)
        if (preg_match('/\\d\+\\.\\s\*/', $pattern)) {
            $score += 12;
        }
        
        // Score 6: Penalize if value contains suspicious patterns
        if (preg_match('/^[0-9]+$/', $value) && strlen($value) > 10) {
            $score -= 20; // Likely noise
        }
        
        // Score 7: Bonus for realistic data
        if (preg_match('/^[A-Z][A-Z\s]+$/', $value) && strlen($value) > 3) {
            $score += 10;
        }
        
        // Score 8: Bonus for address-like content (contains RT, DS, KEC, etc)
        if (preg_match('/\b(RT|DS|KEC|KAB|DSN)\b/i', $value)) {
            $score += 20;
        }
        
        // Score 9: Bonus for medical/food preference content
        if (preg_match('/\b(No pork|No beef|Others)\b/i', $value)) {
            $score += 15;
        }
        
        // Score 10: Penalize empty-like values
        if (preg_match('/^(NO|NIL|N\/A|NA|-+|_+)$/i', $value)) {
            $score -= 5;
        }
        
        return $score;
    }
}
