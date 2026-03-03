<?php

namespace App\Services\MaidManagement\DataExtractor\Patterns;

class EmploymentPatterns
{
    /**
     * Pattern untuk section header C1 - Employment History Overseas
     */
    public static function sectionC1Header(): array
    {
        return [
            '/C1\.?\s*Employment History Overseas/i',
            '/C1\.?\s*Employment History of the FDW/i',
            '/\(C\s*\)\s*EMPLOYMENT HISTORY OF THE FDW.*?C1/is',
        ];
    }

    /**
     * Pattern untuk section header C2 - Employment History in Singapore
     */
    public static function sectionC2Header(): array
    {
        return [
            '/C2\.?\s*Employment History in Singapore/i',
            '/Previous working experience in Singapore/i',
        ];
    }

    /**
     * Pattern untuk section header C3 - Feedback
     */
    public static function sectionC3Header(): array
    {
        return [
            '/C3\.?\s*Feedback from previous employers in Singapore/i',
        ];
    }

    /**
     * Pattern untuk checkbox Singapore experience (Yes/No)
     */
    public static function singaporeExperience(): array
    {
        return [
            '/Previous working experience in Singapore\s*[:\-]?\s*(Yes|No|☒\s*Yes|☒\s*No|X\s*Yes|X\s*No)/i',
            '/Previous working experience in Singapore.*?(☒|X)\s*(Yes|No)/is',
            '/Singapore.*?experience.*?(Yes|No)/i',
        ];
    }

    /**
     * Pattern untuk table header C1 (Date, Country, Employer, Work Duties, Remarks)
     */
    public static function tableC1Headers(): array
    {
        return [
            '/Date.*?From.*?To.*?Country.*?Employer.*?Work Duties/is',
            '/From\s*To\s*Country.*?Employer.*?Work\s*Duties/is',
            '/Date.*?Country.*?Employer.*?Work\s*Duties.*?Remarks/is',
        ];
    }

    /**
     * Pattern untuk extract single employment entry (row-based)
     * Format: Year Year Country Employer? Duties
     */
    public static function employmentEntry(): array
    {
        return [
            // Format: 2023 2025 SAUDI ARABIC TAKE CARE...
            '/(\d{4})\s*[-–]\s*(\d{4})\s+([A-Z][A-Z\s]+?)\s+([A-Z][A-Z\s,.\(\)]+)/i',

            // Format dengan tab/spasi banyak: 2023    2025    COUNTRY    DUTIES
            '/(\d{4})\s+(\d{4})\s+([A-Z][A-Z\s]+?)\s{2,}(.+?)(?=\d{4}|\n|$)/is',

            // Format: 2008 - 2009 BANDUNG (INDONESIA) GENERAL HOUSE MAID...
            '/(\d{4})\s*[-–]\s*(\d{4})\s+([A-Z\(\)][^\n]+?)\s+([A-Z][A-Z\s,.\(\)]+)/i',
        ];
    }

    /**
     * Pattern untuk extract employment dengan multi-line duties
     */
    public static function employmentEntryMultiLine(): array
    {
        return [
            // Format dengan duties di baris berbeda
            '/(\d{4})\s+(\d{4})\s+([A-Z][A-Z\s]+?)\s+(.+?)(?=\d{4}|C2|C3|Previous working|$)/is',

            // Format dengan backtick: 2022 - 2024` JAKARTA
            '/(\d{4})\s*[-–]\s*(\d{4})`?\s+([A-Z][A-Z\s\(\)]+?)\s+(.+?)(?=\d{4}|C2|C3|$)/is',
        ];
    }

    /**
     * Pattern untuk country (handling parentheses)
     */
    public static function country(): array
    {
        return [
            '/([A-Z][A-Z\s]+)\s*\(([A-Z]+)\)/i',  // JAKARTA (INDONESIA)
            '/([A-Z][A-Z\s]+)/i',  // SAUDI ARABIC
        ];
    }

    /**
     * Pattern untuk remarks (family member, bedroom, toilet info)
     */
    public static function remarks(): array
    {
        return [
            '/(\d+\s*FAMILY MEMBER)/i',
            '/(\d+\s*BEDROOM)/i',
            '/(\d+\s*TOILET)/i',
            '/(FINISH)/i',
        ];
    }

    /**
     * Pattern untuk table C3 Feedback
     */
    public static function feedbackTable(): array
    {
        return [
            '/Employer\s*Feedback.*?Employer\s*1\s*(.+?)Employer\s*2\s*(.+?)(?=\(D\)|$)/is',
            '/Feedback.*?Employer\s*1\s*(.+?)Employer\s*2\s*(.+?)(?=\(D\)|$)/is',
        ];
    }

    /**
     * Pattern untuk detect table structure
     */
    public static function hasTableStructure(): array
    {
        return [
            '/From\s+To/i',
            '/Date.*?Country/is',
            '/Work\s+Duties/i',
        ];
    }

    /**
     * Parse vertical-stacked entries under C1 headers where labels and values are line-by-line.
     * Returns an array of raw entries with keys: from, to, country_raw, employer, duties_raw
     * Note: Normalization (country parsing, duties cleaning, remarks) should be done by the extractor.
     */
    public static function stackedVerticalEntries(string $text): array
    {
        $lines = preg_split('/\r?\n/', $text);
        if (! $lines) {
            return [];
        }
        $lines = array_map(static function ($l) {
            return trim($l);
        }, $lines);

        // Remove empty lines
        $lines = array_values(array_filter($lines, static function ($l) {
            return $l !== '';
        }));

        // Find indices of 'From' and 'To' markers
        $fromIdx = null;
        $toIdx = null;
        for ($i = 0; $i < count($lines); $i++) {
            if ($fromIdx === null && preg_match('/^From$/i', $lines[$i])) {
                $fromIdx = $i;

                continue;
            }
            if ($toIdx === null && preg_match('/^To$/i', $lines[$i])) {
                $toIdx = $i;
            }
            if ($fromIdx !== null && $toIdx !== null) {
                break;
            }
        }

        if ($fromIdx === null || $toIdx === null || $toIdx <= $fromIdx) {
            return [];
        }

        // Collect values after the 'To' header until a new section marker
        $values = [];
        for ($i = $toIdx + 1; $i < count($lines); $i++) {
            $line = $lines[$i];
            if (preg_match('/^C2\.?/i', $line) || preg_match('/^C3\.?/i', $line) || stripos($line, 'Previous working') !== false) {
                break;
            }
            $values[] = $line;
        }

        if (count($values) < 4) {
            return [];
        }

        // Try to parse multiple entries
        return self::parseMultipleVerticalEntries($values);
    }

    /**
     * Parse multiple vertical entries from values array
     */
    private static function parseMultipleVerticalEntries(array $values): array
    {
        $entries = [];
        $pos = 0;
        $employerKeywords = ['ARAB', 'SAUDI', 'INDIA', 'MELAYU', 'CHINESE', 'MALAY', 'FILIPINO', 'INDONESIAN'];

        while ($pos < count($values)) {
            // Check if we have a year pair
            if (! preg_match('/^\d{4}$/', $values[$pos] ?? '')) {
                break;
            }

            $fromYear = $values[$pos++];
            if (! preg_match('/^\d{4}$/', $values[$pos] ?? '')) {
                break;
            }
            $toYear = $values[$pos++];

            // Get country - bisa multi-word (BANDUNG INDONESIA atau ARAB SAUDI)
            $countryLine = $values[$pos++] ?? null;
            $countryRaw = $countryLine;

            // Check if country line contains multiple words (e.g., BANDUNG INDONESIA)
            if ($countryLine) {
                $words = preg_split('/\s+/', $countryLine, -1, PREG_SPLIT_NO_EMPTY);

                // If 2 words and second is country name, use second word
                if (count($words) === 2) {
                    $secondWord = strtoupper($words[1]);
                    $knownCountries = ['INDONESIA', 'SINGAPORE', 'MALAYSIA', 'THAILAND', 'PHILIPPINES', 'VIETNAM', 'CAMBODIA', 'MYANMAR', 'BRUNEI', 'LAOS'];

                    if (in_array($secondWord, $knownCountries)) {
                        // Format: CITY COUNTRY (e.g., BANDUNG INDONESIA)
                        $countryRaw = $countryLine; // Keep both for raw
                    }
                }
            }

            // Check if next line is also country part (for multi-line country)
            if ($pos < count($values)) {
                $nextLine = trim($values[$pos]);

                // If next line is short, all caps, and contains country keyword
                if (preg_match('/^[A-Z\s]+$/', $nextLine) && strlen($nextLine) < 20) {
                    $nextWords = preg_split('/\s+/', $nextLine, -1, PREG_SPLIT_NO_EMPTY);
                    $hasCountryKeyword = false;

                    foreach ($nextWords as $word) {
                        if (in_array(strtoupper($word), ['ARAB', 'SAUDI', 'INDIA', 'CHINA', 'HONG', 'KONG'])) {
                            $hasCountryKeyword = true;
                            break;
                        }
                    }

                    // Only append if it's not duplicate and has country keyword
                    if ($hasCountryKeyword && strtoupper($countryLine) !== strtoupper($nextLine)) {
                        $countryRaw .= ' '.$nextLine;
                        $pos++;
                    }
                }
            }

            // Get employer - bisa multi-word (INDIA MELAYU) atau single line
            $employer = null;
            $employerParts = [];
            $maxEmployerLines = 2;
            $linesChecked = 0;

            while ($pos < count($values) && ! preg_match('/^\d{4}$/', $values[$pos]) && $linesChecked < $maxEmployerLines) {
                $line = trim($values[$pos]);

                // Skip if same as country (duplicate)
                if (strtoupper($line) === strtoupper($countryRaw)) {
                    $employer = $line;
                    $pos++;
                    break;
                }

                $words = preg_split('/\s+/', $line, -1, PREG_SPLIT_NO_EMPTY);
                $isEmployerLine = false;

                // Check if any word in line is employer keyword
                foreach ($words as $word) {
                    if (in_array(strtoupper($word), $employerKeywords)) {
                        $isEmployerLine = true;
                        break;
                    }
                }

                if ($isEmployerLine) {
                    $employerParts[] = $values[$pos++];
                    $linesChecked++;
                } else {
                    // Not employer, must be duties
                    break;
                }
            }

            if (! $employer && ! empty($employerParts)) {
                $employer = implode(' ', $employerParts);
            }

            // Collect duties until next year or end
            $dutiesParts = [];
            while ($pos < count($values) && ! preg_match('/^\d{4}$/', $values[$pos])) {
                $dutiesParts[] = $values[$pos++];
            }

            $dutiesRaw = trim(implode(' ', $dutiesParts));
            if ($dutiesRaw === '') {
                $dutiesRaw = null;
            }

            $entries[] = [
                'from' => $fromYear,
                'to' => $toYear,
                'country_raw' => $countryRaw,
                'employer' => $employer,
                'duties_raw' => $dutiesRaw,
            ];
        }

        return $entries;
    }

    /**
     * Flexible pattern for employment entries (no keyword requirement)
     */
    public static function flexibleEmploymentEntry(): array
    {
        return [
            // Match: YEAR YEAR COUNTRY ANY_TEXT (multi-line support)
            '/(\d{4})\s+(\d{4})\s+([A-Z]+)\s+(.+?)(?=\d{4}\s+\d{4}|C2|C3|Previous|Feedback|$)/is',
        ];
    }

    /**
     * Normalize OCR text by adding spaces to concatenated words
     */
    public static function normalizeOCRText(string $text): string
    {
        // Fix common concatenated words first (specific patterns)
        $commonWords = [
            'TAKECARE' => 'TAKE CARE',
            'TAKE CAREOF' => 'TAKE CARE OF',
            'TAKECAREOF' => 'TAKE CARE OF',
            'GENERALHOUSEWORK' => 'GENERAL HOUSEWORK',
            'GENERAL HOUSE WORK' => 'GENERAL HOUSEWORK',  // normalize variations
            'OFCHILD' => 'OF CHILD',
        ];

        foreach ($commonWords as $wrong => $correct) {
            $text = str_ireplace($wrong, $correct, $text);
        }

        // Add space after comma if missing (,2 → , 2, ,GENERAL → , GENERAL)
        $text = preg_replace('/,([A-Z0-9])/', ', $1', $text);

        // Add space before capital letters in concatenated words
        // TAKECAREOFCHILDREN → TAKE CARE OF CHILDREN
        // But avoid breaking words like BANDUNG
        $text = preg_replace('/([a-z])([A-Z])/', '$1 $2', $text);

        // Add space between capital word followed by OF/AND + capital word
        // CAREOFCHILDREN → CARE OF CHILDREN (minimum 2 chars before/after)
        // CHILDRENAND → CHILDREN AND (minimum 2 chars before/after)
        $text = preg_replace('/([A-Z]{2,})(OF)([A-Z]{2,})/', '$1 $2 $3', $text);
        $text = preg_replace('/([A-Z]{2,})(AND)([A-Z]{2,})/', '$1 $2 $3', $text);

        // Add space between word and A- pattern (like COOKINGA-4 → COOKING A-4)
        $text = preg_replace('/([A-Z]+)(A-\d+)/', '$1 $2', $text);

        // Add space before numbers if preceded by letters
        // CHILDREN5 → CHILDREN 5, CHILD2 → CHILD 2
        $text = preg_replace('/([A-Z]+)(\d+)/', '$1 $2', $text);

        // Add space before numbers followed by YO
        // 5YO → 5 YO, 2YOAND → 2 YO AND
        $text = preg_replace('/(\d+)(YO)/i', '$1 $2', $text);

        // Add space after YO if followed by capital letter or comma
        // YOAND → YO AND, YO,2 → YO, 2
        $text = preg_replace('/(YO)([A-Z,])/i', '$1 $2', $text);

        // Normalize multiple spaces
        $text = preg_replace('/\s+/', ' ', $text);

        return $text;
    }

    /**
     * City to country mapping
     */
    public static function cityToCountryMap(): array
    {
        return [
            'JAKARTA' => 'INDONESIA',
            'BANDUNG' => 'INDONESIA',
            'TANGERANG' => 'INDONESIA',
            'SURABAYA' => 'INDONESIA',
            'MEDAN' => 'INDONESIA',
            'DUBAI' => 'UAE',
            'ABU DHABI' => 'UAE',
            'SINGAPURA' => 'SINGAPORE',
            'SINGAPORE' => 'SINGAPORE',
            'SAUDI' => 'SAUDI ARABIA',
            'ARAB SAUDI' => 'SAUDI ARABIA',
            'SAUDI ARAB' => 'SAUDI ARABIA',
            'RIYADH' => 'SAUDI ARABIA',
            'JEDDAH' => 'SAUDI ARABIA',
            'HONG KONG' => 'HONG KONG',
            'TAIPEI' => 'TAIWAN',
            'KUALA LUMPUR' => 'MALAYSIA',
        ];
    }

    /**
     * Enhanced remarks patterns - LESS AGGRESSIVE
     * Only capture remarks that are clearly separated from work duties
     */
    public static function remarksEnhanced(): array
    {
        return [
            '/(\d+\s*FAMILY MEMBER)/i',
            '/(\d+\s*BEDROOM)/i',
            '/(\d+\s*TOILET)/i',
            '/(\d+[-–]\d+\s*YO)/i',  // Age range: 1-4 YO
            '/(\bFINISH\b|\bCOMPLETED\b)/i',
            '/(\bNEWBORN\b)/i',
            '/(\bINFANT\b)/i',
            // Standalone numbers at end of text (likely remarks, not duties)
            '/,\s*(\d+[a-z\s]*),?\s*$/i',
        ];
    }

    /**
     * Pattern to extract ALL age references (for remarks field specifically)
     * This should be used ONLY after cleaning from duties
     */
    public static function ageReferencesAll(): array
    {
        return [
            '/(\d+\s*YO)/i',          // Single age: 5 YO, 2 YO
            '/(\d+YO)/i',             // Age without space: 5YO
        ];
    }
}
