<?php

namespace App\Services\MaidManagement\DataExtractor\Patterns;

class BoxFieldExtractor
{
    public static function extractDob(string $text): ?string
    {
        if (preg_match('/Date of Birth\s*:\s*([0-9\/-]+)/i', $text, $match)) {
            return $match[1];
        }

        if (preg_match('/Date of Birth\s*:\s*[^\n]*?\n([0-9\/-]+)/i', $text, $match)) {
            return $match[1];
        }

        if (preg_match('/Date of Birth\s*:.*?Age\s*:\s*([0-9]+)/is', $text, $ageMatch)) {
            if (preg_match('/Name\s*:.*?([0-9]{2}\/[0-9]{2}\/[0-9]{4})/is', $text, $dobMatch)) {
                return $dobMatch[1];
            }
        }

        if (preg_match('/A1Personal Information.*?([0-9]{2}\/[0-9]{2}\/[0-9]{4})/is', $text, $match)) {
            return $match[1];
        }

        return null;
    }

    public static function extractAge(string $text): ?string
    {
        if (preg_match('/Age\s*:\s*([0-9]+)\s*YO/i', $text, $match)) {
            return $match[1];
        }

        if (preg_match('/Age\s*:\s*\n?\s*([0-9]+)/i', $text, $match)) {
            return $match[1];
        }

        if (preg_match('/Date of Birth.*?Age\s*:.*?\n.*?([0-9]{2})\s*(?:Place|Height)/is', $text, $match)) {
            return $match[1];
        }

        if (preg_match('/Age\s*:\s*[\n\s\d]+?([0-9]{2})\s*(?:Place|Height|[A-Z]{2,})/is', $text, $match)) {
            return $match[1];
        }

        return null;
    }

    public static function extractHeightWeight(string $text): array
    {
        // Explicitly treat placeholder lines like "b cm kg" as empty
        if (preg_match('/Height\s*&(?:amp;)?\s*weight\s*:\s*b\s*cm\s*(?:b|\-|0)?\s*kg/i', $text)) {
            return [null, null];
        }

        if (preg_match('/Height\s*&(?:amp;)?\s*weight\s*:\s*([0-9]+)\s*cm\s*(?:&\s*weight:\s*)?([0-9,]+)\s*kg/i', $text, $match)) {
            return [$match[1], str_replace(',', '.', $match[2])];
        }

        if (preg_match('/Height\s*&\s*weight\s*:\s*([0-9]+)\s*cm\s*([0-9,]+)\s*Age/is', $text, $match)) {
            return [$match[1], str_replace(',', '.', $match[2])];
        }

        if (preg_match('/Height\s*&(?:amp;)?\s*weight\s*:\s*[^\n]*?\n.*?([0-9]{3})\s*cm.*?([0-9]{2,3})\s*(?:kg|KG)/is', $text, $match)) {
            return [$match[1], $match[2]];
        }

        $height = null;
        $weight = null;

        // Compressed or no-space formats like "156cm70kg" (with or without the label)
        if (preg_match('/\b([0-9]{3})\s*cm\s*([0-9]{2,3}(?:[,.][0-9]+)?)\s*(?:kg|KG)\b/i', $text, $mw)) {
            $height = $mw[1];
            $weight = str_replace(',', '.', $mw[2]);
        }

        if (preg_match('/Height.*?([0-9]{3})\s*cm/i', $text, $hMatch)) {
            $height = $hMatch[1];
        }

        if (preg_match('/weight.*?([0-9]{2,3}(?:[,.][0-9]+)?)\s*kg/i', $text, $wMatch)) {
            $weight = str_replace(',', '.', $wMatch[1]);
        }

        if (!$height && preg_match('/Place of birth.*?\n[\d\s]+([0-9]{3})\s*cm/is', $text, $hMatch)) {
            $height = $hMatch[1];
        }

        if (!$weight && preg_match('/cm.*?([0-9]{2,3}(?:[,.][0-9]+)?)\s*(?:Age|kg)/is', $text, $wMatch)) {
            $weight = str_replace(',', '.', $wMatch[1]);
        }

        // Sanitize obviously invalid placeholders
        if (is_string($height) && in_array(strtolower($height), ['b', '0'], true)) {
            $height = null;
        }
        if (is_string($weight)) {
            // Treat leading-zero integers like "02", "03" as invalid
            if (preg_match('/^0+\d+$/', $weight)) {
                $weight = null;
            }
        }
        if ($weight !== null) {
            // Discard implausibly tiny values (e.g., 0-10 captured from noise)
            $num = (float) $weight;
            if ($num > 0 && $num <= 10) {
                $weight = null;
            }
        }

        return [$height, $weight];
    }

    public static function extractReligion(string $text): ?string
    {
        // Try to find religion after contact number
        if (preg_match('/Contact number in home country\s*:\s*[0-9\-+]+\s*Religion\s*:\s*([A-Z][A-Za-z]+)/i', $text, $match)) {
            return $match[1];
        }

        // Try to find religion in a line by itself
        if (preg_match('/Religion\s*:\s*\n?\s*([A-Z][A-Za-z]+)(?=\s*\n|\s*Education)/i', $text, $match)) {
            return $match[1];
        }

        // Try to find religion near education
        if (preg_match('/Religion\s*:\s*([A-Z][A-Za-z]+)\s*Education/i', $text, $match)) {
            return $match[1];
        }

        return null;
    }

    public static function extractEducation(string $text): ?string
    {
        // Try to find education after religion
        if (preg_match('/Religion\s*:\s*[A-Z]+\s*Education level\s*:\s*([A-Z][A-Za-z\s]+?)(?=\s*\n|\s*Number|\s*\d+\.)/i', $text, $match)) {
            return trim($match[1]);
        }

        // Try to find education in a line by itself
        if (preg_match('/Education level\s*:\s*\n?\s*([A-Z][A-Za-z\s]+?)(?=\s*\n|\s*Number|\s*\d+\.)/i', $text, $match)) {
            return trim($match[1]);
        }

        // Try to find education near number of siblings
        if (preg_match('/Education level\s*:\s*([A-Z][A-Za-z\s]+?)\s*Number of siblings/i', $text, $match)) {
            return trim($match[1]);
        }

        return null;
    }

    public static function extractRestDay(string $text): ?string
    {
        // Try various patterns for rest day
        $patterns = [
            '/Preference\s*for\s*rest\s*day\s*:\s*([0-9]+)\s*rest\s*day\(s\)\s*per\s*month/i',
            '/rest\s*day\(s\)\s*per\s*month\s*[:\.]?\s*([0-9]+)/i',
            '/Preference\s*for\s*rest\s*day\s*:\s*\n?\s*([0-9]+)/i',
            '/([0-9]+)\s*rest\s*day\(s\)\s*per\s*month/i',
            '/([0-9]+)\s*rest\s*day/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return $match[1];
            }
        }

        return null;
    }

    public static function extractRemarks(string $text): ?string
    {
        // Try to find remarks after "Any other remarks"
        if (preg_match('/Any\s*other\s*remarks\s*:\s*\n?\s*([A-Z][^\(]+?)(?=\s*\(B\)|\s*SKILL|$)/is', $text, $match)) {
            return trim($match[1]);
        }

        // Try to find remarks in section A3
        if (preg_match('/A3.*?remarks\s*:\s*\n?\s*([^\n]+?)(?=\s*\(B\)|\s*SKILL|\n\n|$)/is', $text, $match)) {
            return trim($match[1]);
        }

        return null;
    }
}
