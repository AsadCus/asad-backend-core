<?php

namespace App\Services\MaidManagement\DataExtractor;

use App\Services\MaidManagement\DataExtractor\Patterns\BoxFieldExtractor;
use App\Services\MaidManagement\DataExtractor\Patterns\PersonalInformationPatterns;
use App\Services\MaidManagement\DataExtractor\Patterns\SmartPatternMatcher;

class PersonalInformationExtractor
{
    public function extract(string $text, ?string $photoUrl = null): array
    {
        $dob = SmartPatternMatcher::match($text, PersonalInformationPatterns::dob());
        if (! $dob || $dob === 'n') {
            $dob = BoxFieldExtractor::extractDob($text);
        }

        $age = SmartPatternMatcher::match($text, PersonalInformationPatterns::age());
        if (! $age) {
            $age = BoxFieldExtractor::extractAge($text);
        }

        $hwData = SmartPatternMatcher::matchMultiple($text, PersonalInformationPatterns::heightWeight());
        if (empty($hwData[0]) || $hwData[0] === 'b') {
            $hwData = BoxFieldExtractor::extractHeightWeight($text);
        }

        // Extract children count first
        $childrenCountMatch = SmartPatternMatcher::match($text, PersonalInformationPatterns::childrenCount());
        $expectedChildrenCount = ($childrenCountMatch && is_numeric($childrenCountMatch)) ? (int) $childrenCountMatch : 0;

        // Extract children ages with validation
        $childrenAgesRaw = SmartPatternMatcher::match($text, PersonalInformationPatterns::childrenAges()) ?? '';
        $childrenAges = $this->parseChildrenAges($childrenAgesRaw, $text, $expectedChildrenCount);

        // If still empty but there is a children count, try to sniff ages in parentheses right after the word "children"
        if (empty($childrenAges) && $expectedChildrenCount > 0) {
            if (preg_match('/children[^\n]{0,40}\(([^\)]*)\)/i', $text, $mAges)) {
                $sniffed = $this->parseChildrenAges($mAges[1] ?? '', $text, $expectedChildrenCount);
                if (! empty($sniffed)) {
                    $childrenAges = $sniffed;
                }
            }
        }

        // Use explicit count or derived from ages
        $childrenCount = ! empty($childrenAges) ? count($childrenAges) : $expectedChildrenCount;

        $religion = SmartPatternMatcher::match($text, PersonalInformationPatterns::religion());
        if (! $religion) {
            $religion = BoxFieldExtractor::extractReligion($text);
        }

        $education = SmartPatternMatcher::match($text, PersonalInformationPatterns::education());
        if (! $education) {
            $education = BoxFieldExtractor::extractEducation($text);
        }

        // Try to parse siblings in format like "03 OF 03 BROTHER & SISTER"
        $siblings = SmartPatternMatcher::match($text, PersonalInformationPatterns::siblings());
        if (! $siblings) {
            if (preg_match('/([0-9]{1,2})\s*OF\s*([0-9]{1,2})\s*BROTHER\s*&\s*SISTER/i', $text, $m)) {
                // Take the larger or the second number as total siblings
                $siblings = (string) max((int) $m[1], (int) $m[2]);
            } elseif (preg_match('/([0-9]{1,2})\s*OF\s*([0-9]{1,2})/i', $text, $m)) {
                $siblings = (string) max((int) $m[1], (int) $m[2]);
            }
        }

        // Extract name and clean up
        $name = SmartPatternMatcher::match($text, PersonalInformationPatterns::name());
        $name = $this->cleanName($name);

        return [
            'name' => $name,
            'photo_profile' => $photoUrl,
            'dob' => $dob,
            'age' => $age,
            'birth_place' => SmartPatternMatcher::match($text, PersonalInformationPatterns::birthPlace()),
            'height' => $hwData[0] ?? null,
            'weight' => $hwData[1] ?? null,
            'nationality' => SmartPatternMatcher::match($text, PersonalInformationPatterns::nationality()),
            'address' => SmartPatternMatcher::match($text, PersonalInformationPatterns::address()),
            'repatriation_to' => SmartPatternMatcher::match($text, PersonalInformationPatterns::repatriation()),
            'contact_number' => SmartPatternMatcher::match($text, PersonalInformationPatterns::contact()),
            'religion' => $religion,
            'education' => $education,
            'siblings' => $siblings,
            'marital_status' => SmartPatternMatcher::match($text, PersonalInformationPatterns::maritalStatus()),
            'children' => $childrenCount,
            'children_ages' => json_encode($childrenAges),
        ];
    }

    /**
     * Parse children ages from raw string with validation against expected count
     *
     * Strategy: Only extract ages from explicit "Age(s) of children" field in Section A1.
     * Ignore any other numbers that might appear elsewhere (like in employment history).
     *
     * @param  string  $raw  Raw string containing ages - should be from children ages pattern only
     * @param  string  $fullText  Full Section A1 text (NOT full document) for context validation
     * @param  int  $expectedCount  Expected number of children from explicit count field
     * @return array Validated array of ages
     */
    private function parseChildrenAges(string $raw, string $fullText = '', int $expectedCount = 0): array
    {
        // Early return if empty or clearly invalid
        if (empty(trim($raw)) || strlen(trim($raw)) <= 1) {
            return [];
        }

        // Additional validation: if fullText provided, ensure the raw string appears
        // in context of "Age(s) of children" keywords in Section A1 only
        if (! empty($fullText)) {
            // Check if raw appears near "Age" or "children" keywords (within 100 chars)
            // This ensures we're only extracting from the correct field
            $contextPattern = '/Age.*?children.*?\:?\s*'.preg_quote(trim($raw), '/').'/is';
            $altPattern = '/children.*?\(if any\).*?\:?\s*'.preg_quote(trim($raw), '/').'/is';

            if (! preg_match($contextPattern, $fullText) && ! preg_match($altPattern, $fullText)) {
                // Raw string doesn't appear in proper context, skip it
                return [];
            }
        }

        // Normalize common separators and labels
        $normalized = $raw;
        $normalized = preg_replace('/\s*AND\s*/i', ',', $normalized);
        $normalized = str_replace(['&', ';', '/'], ',', $normalized);
        $normalized = str_replace(['(', ')', '[', ']'], ' ', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        // Remove age labels BEFORE extracting numbers
        $normalized = str_ireplace(
            ['YO', 'Y.O.', 'YRS', 'YRS.', 'YEARS', 'YEARS OLD', 'YEAR OLD', 'Y.O', 'YRS OLD', 'YRS-OLD'],
            ' ',
            $normalized
        );

        // Extract 1-2 digit numbers only (reasonable children ages: 0-18)
        preg_match_all('/\b(\d{1,2})\b/', $normalized, $matches);
        $ages = array_map('intval', $matches[1] ?? []);

        // Filter out unreasonable ages (keep only 0-18 range for children)
        $ages = array_filter($ages, function ($age) {
            return $age >= 0 && $age <= 18;
        });

        $ages = array_values($ages);

        // Validate against expected count if provided
        if ($expectedCount > 0 && count($ages) !== $expectedCount) {
            // If count doesn't match, return empty to avoid incorrect data
            // Let the system rely on explicit children count instead
            return [];
        }

        return $ages;
    }

    /**
     * Clean extracted name - remove DOB text that sometimes leaks in PDF extraction
     *
     * @param  string|null  $name  Raw name extracted from pattern
     * @return string|null Cleaned name
     */
    private function cleanName(?string $name): ?string
    {
        if (empty($name)) {
            return $name;
        }

        // Remove "Date of birth" or "DOB" text that sometimes appears after name in PDFs
        // Common pattern: "NARSIH LESTARI\nDate of birth"
        $name = preg_replace('/\s*(?:\n|\r\n?)\s*Date\s*of\s*birth\s*$/i', '', $name);
        $name = preg_replace('/\s*(?:\n|\r\n?)\s*DOB\s*$/i', '', $name);
        $name = preg_replace('/\s*(?:\n|\r\n?)\s*D\.O\.B\.?\s*$/i', '', $name);

        // Remove trailing newlines and extra spaces
        $name = trim(preg_replace('/\s+/', ' ', $name));

        return $name;
    }
}
