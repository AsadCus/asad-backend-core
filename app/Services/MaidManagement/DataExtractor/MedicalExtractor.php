<?php

namespace App\Services\MaidManagement\DataExtractor;

use App\Services\MaidManagement\DataExtractor\Patterns\BoxFieldExtractor;
use App\Services\MaidManagement\DataExtractor\Patterns\MedicalPatterns;
use App\Services\MaidManagement\DataExtractor\Patterns\SmartPatternMatcher;

class MedicalExtractor
{
    public function extract(string $text, string $otherSectionText = ''): array
    {
        $foodPreferencesRaw = SmartPatternMatcher::match($text, MedicalPatterns::foodPreferences());
        $foodPreferences = $this->parseFoodPreferences($foodPreferencesRaw ?? '');

        // Try to find rest_day in A2 (medical) first, then A3 (other)
        $restDay = SmartPatternMatcher::match($text, MedicalPatterns::restDay());

        if (! $restDay) {
            $restDay = BoxFieldExtractor::extractRestDay($text);
        }

        // If still not found, try A3 (other section)
        if (! $restDay && ! empty($otherSectionText)) {
            $restDay = SmartPatternMatcher::match($otherSectionText, MedicalPatterns::restDay());

            if (! $restDay) {
                $restDay = BoxFieldExtractor::extractRestDay($otherSectionText);
            }
        }

        // Try to find remarks in A2 (medical) first, then A3 (other)
        $remarks = SmartPatternMatcher::match($text, MedicalPatterns::remarks());

        if (! $remarks) {
            $remarks = BoxFieldExtractor::extractRemarks($text);
        }

        // If still not found, try A3 (other section)
        if (! $remarks && ! empty($otherSectionText)) {
            $remarks = SmartPatternMatcher::match($otherSectionText, MedicalPatterns::remarks());

            if (! $remarks) {
                $remarks = BoxFieldExtractor::extractRemarks($otherSectionText);
            }
        }

        return [
            'allergies' => SmartPatternMatcher::match($text, MedicalPatterns::allergies()) ?? '',
            'physical_disabilities' => SmartPatternMatcher::match($text, MedicalPatterns::physicalDisabilities()) ?? '',
            'dietary_restrictions' => SmartPatternMatcher::match($text, MedicalPatterns::dietaryRestrictions()) ?? '',
            'food_preferences' => $foodPreferences,
            'rest_day' => $restDay ?? '',
            'remarks' => $remarks ?? '',
        ];
    }

    public function extractIllnesses(string $text): array
    {
        $illnessesFound = [];
        $illnessList = MedicalPatterns::illnesses();

        // Normalize text: add space before capital letters in concatenated words (e.g., "Mentalillness" → "Mental illness")
        $normalizedText = preg_replace('/([a-z])([A-Z])/', '$1 $2', $text);

        // Check if document has ANY checkbox symbols at all (if none, skip extraction entirely)
        // Include common Unicode and some PDF Wingdings-like glyphs seen in OCR (e.g., , )
        $hasAnySymbols = preg_match('/[☒☐□☑✓√×◻]/u', $normalizedText);

        if (! $hasAnySymbols) {
            // No checkbox symbols found in entire medical section → all illnesses unchecked
            return [];
        }

        // Detect document format by looking at the header structure
        // Format 1: "Mental illness☒" (single checkbox after illness name)
        // Format 2: "Yes No\ni…Mental illness × √" (two columns: first=YES, second=NO)
        $hasYesNoColumns = preg_match('/Yes\s+No.*?(?:Mental\s*illness|Epilepsy|Asthma)/is', $normalizedText);

        foreach ($illnessList as $illness) {
            $added = false;

            // Flexible illness name matching (with or without spaces)
            $illnessNormalized = str_replace(' ', '\s*', preg_quote($illness, '/'));

            if ($hasYesNoColumns) {
                // Format 2: Yes/No columns
                // Pattern: "illness × √" where first symbol = YES column, second = NO column
                // √ or ✓ or ☑ = checked/filled, × or ☐ or □ = unchecked/empty
                $pairRegex = '/'.$illnessNormalized.'\s*([×√☐☒☑✓□◻])\s*([×√☐☒☑✓□◻])/u';
                if (preg_match($pairRegex, $normalizedText, $m)) {
                    $yesCol = $m[1] ?? '';
                    $noCol = $m[2] ?? '';

                    // Checkmark symbols = checked/selected
                    $yesChecked = in_array($yesCol, ['√', '✓', '☑', '☒', ''], true);
                    $noChecked = in_array($noCol, ['√', '✓', '☑', '☒', ''], true);

                    if ($yesChecked && ! $noChecked) {
                        // YES checked, NO unchecked → has illness
                        $added = true;
                    } elseif (! $yesChecked && $noChecked) {
                        // YES unchecked, NO checked → no illness
                        $added = false;
                    }
                    // else: both or neither checked → ambiguous, don't add
                }
            } else {
                // Format 1: Single checkbox right after illness name
                // ☒ or  or × = filled/checked (YES)
                // ☐ or □ = empty/unchecked (NO)
                $singleRegex = '/'.$illnessNormalized.'\s*([☒×☐□☑✓√◻])/u';
                if (preg_match($singleRegex, $normalizedText, $m2)) {
                    $sym = $m2[1] ?? '';
                    // Filled box or check-like = YES
                    if (in_array($sym, ['☒', '×', '☑', '✓', '√', ''], true)) {
                        $added = true;
                    } elseif (in_array($sym, ['☐', '□', '◻', ''], true)) {
                        // Explicitly unchecked symbols → NO
                        $added = false;
                    }
                }
            }

            if ($added) {
                if (strtolower($illness) === 'others') {
                    $othersValue = SmartPatternMatcher::match($normalizedText, MedicalPatterns::illnessOthersValue());
                    if ($othersValue) {
                        $illnessesFound[] = trim($othersValue);
                    }
                } else {
                    $illnessesFound[] = $illness;
                }
            }
        }

        return $illnessesFound;
    }

    private function parseFoodPreferences(string $raw): string
    {
        $selections = [];

        if (preg_match('/(☒|✓|X|√)\s*No pork/i', $raw)) {
            $selections[] = 'No pork';
        }
        if (preg_match('/(☒|✓|X|√)\s*No beef/i', $raw)) {
            $selections[] = 'No beef';
        }
        if (preg_match('/(☒|✓|X|√)\s*Others\s*:\s*([^\n☐]+)/i', $raw, $match)) {
            $selections[] = 'Others: '.trim($match[2]);
        }

        if (empty($selections) && ! empty($raw)) {
            return $raw;
        }

        return implode(', ', $selections);
    }
}
