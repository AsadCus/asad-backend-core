<?php

namespace App\Services\MaidManagement\DataExtractor;

use App\Services\MaidManagement\DataExtractor\Patterns\PatternMatcher;
use App\Services\MaidManagement\DataExtractor\Patterns\EmploymentPatterns;

/**
 * Employment History Extractor - Simplified Pattern-Based Approach
 * 
 * Extracts employment history from FDW biodata Section C using pattern matching.
 * Handles two main tables: C1 (Overseas History) and C3 (Feedback)
 */
class EmploymentExtractor
{
    private array $rawData = [];

    /**
     * Main extraction method
     * 
     * @param string $sectionC Full Section C text containing employment history
     * @param array $sections Pre-extracted sections (C1, C2, C3)
     * @return array Employment data with overseas entries, Singapore experience, and feedback
     */
    public function extract(string $sectionC, array $sections = []): array
    {
        $this->rawData = [
            'original_text' => $sectionC,
            'text_length' => strlen($sectionC),
        ];

        // Extract Singapore experience checkbox
        $singaporeExperience = $this->extractSingaporeExperience($sections['c2'] ?? $sectionC);

        // Extract overseas employment entries from C1
        $overseasEntries = $this->extractOverseasEmployment($sections['c1'] ?? $sectionC);

        // Extract feedback from C3
        $feedback = $this->extractFeedback($sections['c3'] ?? $sectionC);

        // Format entries for backward compatibility
        $formattedEntries = $this->formatEntriesForBackwardCompatibility($overseasEntries);

        return [
            // New structure
            'overseas' => $overseasEntries,
            'singapore_experience' => $singaporeExperience,
            'feedback' => $feedback,
            
            // Backward compatibility
            'entries' => $formattedEntries,
            'total_entries' => count($overseasEntries),
            'extraction_method' => 'pattern_based',
            'raw_data' => $this->rawData,
        ];
    }

    /**
     * Extract Singapore work experience (Yes/No checkbox)
     */
    private function extractSingaporeExperience(string $text): bool
    {
        // Look for checkbox patterns (☒ or X next to Yes)
        if (preg_match('/Previous working experience in Singapore.*?(☒|X|x)\s*Yes/is', $text)) {
            return true;
        }
        
        // Look for checkbox pattern (☒ or X next to No) - return false
        if (preg_match('/Previous working experience in Singapore.*?(☒|X|x)\s*No/is', $text)) {
            return false;
        }
        
        // Look for "Yes" appearing alone (not followed immediately by "No")
        if (preg_match('/Previous working experience in Singapore\s*:?\s*Yes\s*$/im', $text)) {
            return true;
        }
        
        // If both "Yes No" appear without checkmarks, default to No (unchecked)
        if (preg_match('/Previous working experience in Singapore\s*:?\s*Yes\s+No/i', $text)) {
            return false;
        }

        return false;
    }

    /**
     * Extract overseas employment entries from C1 table
     */
    private function extractOverseasEmployment(string $text): array
    {
        $entries = [];

        // Preprocess text for OCR errors
        $rawText = $text;
        $preprocessed = $this->preprocessText($text);

        // Remove table headers to avoid false matches
        $cleanText = preg_replace('/Date.*?From.*?To.*?Country.*?Employer.*?Work Duties.*?Remarks?/is', '', $preprocessed);
        $cleanText = preg_replace('/From\s+To\s+Country/i', '', $cleanText);

        // Try stacked/vertical table parsing (multiple entries support)
        $stackedRaw = EmploymentPatterns::stackedVerticalEntries($rawText);
        if (!empty($stackedRaw)) {
            foreach ($stackedRaw as $raw) {
                $entry = $this->normalizeEntry($raw);
                // Flexible validation: hanya butuh from & to
                if ($entry['from'] && $entry['to']) {
                    $entries[] = $entry;
                }
            }
            return $entries;
        }

        // Try inline patterns
        $allMatches = [];
        
        // Pattern 1: Dash format (2023 - 2024)
        if (preg_match_all('/(\d{4})\s*[-–]\s*(\d{4})`?\s+([A-Z][^\n]+?)\s+(GENERAL|TAKE|HELPER|COOK|WASH|CLEAN|CARING|[A-Z][A-Z\s,.\(\)]+?)(?=\d{4}\s*[-–]|C2|C3|Previous|Feedback|$)/is', $cleanText, $matches, PREG_SET_ORDER)) {
            $allMatches = array_merge($allMatches, $matches);
        }

        // Pattern 2: Space-separated format (2014 2016 DUBAI ARAB TAKE...)
        // Capture everything after country until next year or section
        if (preg_match_all('/(\d{4})\s+(\d{4})\s+([A-Z]+)\s+(.+?)(?=\d{4}\s+\d{4}|C2|C3|Previous|$)/is', $cleanText, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                if (!$this->isDuplicate($match, $allMatches)) {
                    $allMatches[] = $match;
                }
            }
        }

        // Pattern 3: Flexible pattern (no keyword requirement)
        if (empty($allMatches)) {
            $flexiblePatterns = EmploymentPatterns::flexibleEmploymentEntry();
            foreach ($flexiblePatterns as $pattern) {
                if (preg_match_all($pattern, $cleanText, $matches, PREG_SET_ORDER)) {
                    $allMatches = array_merge($allMatches, $matches);
                    break;
                }
            }
        }

        foreach ($allMatches as $match) {
            $countryRaw = trim($match[3] ?? '');
            $fullText = trim($match[4] ?? '');
            
            // Try to separate country from employer if they're concatenated
            // Pattern: DUBAI ARAB -> Country: DUBAI, Employer: ARAB
            // Pattern: SINGAPURA INDIA MELAYU -> Country: SINGAPURA, Employer: INDIA MELAYU
            $separated = $this->separateCountryAndEmployer($countryRaw, $fullText);
            $countryRaw = $separated['country'];
            $fullText = $separated['employer_and_duties'];
            
            // Split employer and duties
            $parsed = $this->splitEmployerAndDuties($fullText);
            
            $raw = [
                'from' => $match[1] ?? null,
                'to' => $match[2] ?? null,
                'country_raw' => $countryRaw,
                'employer' => $parsed['employer'],
                'duties_raw' => $parsed['duties'],
            ];

            // Flexible: ambil entry meskipun duties pendek
            $entries[] = $this->normalizeEntry($raw);
        }

        return $entries;
    }

    /**
     * Separate country from employer when they're concatenated
     * Example: "DUBAI ARAB" -> Country: DUBAI, Employer: ARAB
     * Example: "SINGAPURA INDIA MELAYU" -> Country: SINGAPURA, Employer: INDIA MELAYU
     */
    private function separateCountryAndEmployer(string $countryText, string $fullText): array
    {
        // Known city/country names that should be separated
        $knownLocations = ['DUBAI', 'SINGAPURA', 'SINGAPORE', 'JAKARTA', 'BANDUNG', 'TANGERANG', 'SAUDI', 'ARAB'];
        
        // Employer keywords
        $employerKeywords = ['ARAB', 'ARABIC', 'INDIA', 'INDIAN', 'MELAYU', 'MALAY', 'CHINESE', 'FILIPINO', 'SAUDI'];
        
        $words = preg_split('/\s+/', trim($countryText), -1, PREG_SPLIT_NO_EMPTY);
        
        if (count($words) <= 1) {
            // Single word, no separation needed
            return [
                'country' => $countryText,
                'employer_and_duties' => $fullText,
            ];
        }
        
        // Check if first word is a known location and subsequent words are employer types
        $firstWord = strtoupper($words[0]);
        $remainingWords = array_slice($words, 1);
        
        $isFirstWordLocation = in_array($firstWord, $knownLocations);
        $areRemainingEmployer = !empty($remainingWords) && in_array(strtoupper($remainingWords[0]), $employerKeywords);
        
        if ($isFirstWordLocation && $areRemainingEmployer) {
            // Separate: first word is country, rest is employer
            $country = $firstWord;
            $employer = implode(' ', $remainingWords);
            
            // Prepend employer to full text
            $employerAndDuties = trim($employer . ' ' . $fullText);
            
            return [
                'country' => $country,
                'employer_and_duties' => $employerAndDuties,
            ];
        }
        
        // No clear separation, return as-is
        return [
            'country' => $countryText,
            'employer_and_duties' => $fullText,
        ];
    }

    /**
     * Preprocess text to handle OCR errors and add spacing
     */
    private function preprocessText(string $text): string
    {
        // Apply OCR normalization
        $text = EmploymentPatterns::normalizeOCRText($text);
        
        return $text;
    }

    /**
     * Normalize entry with country mapping, remarks extraction, and duties cleaning
     */
    private function normalizeEntry(array $raw): array
    {
        $dutiesRaw = $raw['duties_raw'] ?? null;
        $countryRaw = $raw['country_raw'] ?? null;
        $employer = $raw['employer'] ?? null;

        // Split duties and remarks based on table structure
        // In table: "Work Duties" column comes before "Remarks" column
        // Pattern: duties content, then remarks at the end
        $duties = null;
        $remarks = null;
        
        if ($dutiesRaw) {
            $separated = $this->separateDutiesAndRemarks($dutiesRaw);
            $duties = $separated['duties'];
            $remarks = $separated['remarks'];
        }

        return [
            'from' => $raw['from'] ?? null,
            'to' => $raw['to'] ?? null,
            'country' => $countryRaw ? $this->parseCountry($countryRaw) : null,
            'country_raw' => $countryRaw,
            'employer' => $employer ?: 'Not specified',
            'duties' => $duties,
            'duties_raw' => $dutiesRaw,
            'remarks' => $remarks,
        ];
    }
    
    /**
     * Separate duties and remarks from combined text
     * Strategy: Parse based on table column structure, not aggressive pattern matching
     * 
     * In PDF table structure:
     * - Work Duties column: main tasks description (may include age references like "CHILDREN 5 YO")
     * - Remarks column: additional notes ("FINISH", "1, 10 YO", etc.)
     * 
     * Problem: PDF parser merges columns into continuous text, losing column boundaries
     * Solution: Use explicit remarks patterns only, don't split age references from duties
     */
    private function separateDutiesAndRemarks(string $text): array
    {
        $text = trim($text);
        $duties = $text;
        $remarks = null;
        
        // Strategy: Look for EXPLICIT remarks patterns only
        // Remarks patterns: FINISH, FAMILY MEMBER, standalone age lists like "1, 10 YO"
        $remarksPatterns = [
            '/\bFINISH\b/i',
            '/\bFAMILY MEMBER\b/i',
            '/\bRESIGN\b/i',
            '/\bCOMPLETE\?/i',
            '/\bTERMINATE\b/i',
        ];
        
        $foundRemarks = [];
        $remarksPositions = [];
        
        foreach ($remarksPatterns as $pattern) {
            if (preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE)) {
                $foundRemarks[] = trim($match[0][0]);
                $remarksPositions[] = $match[0][1];
            }
        }
        
        // Strategy 2: Look for standalone age list patterns (NOT part of duty context)
        // Example: "1, 10 YO, 20 YO, 30 YO" appearing AFTER duty text ends
        // Pattern: Pure age list (multiple ages separated by comma, with or without spaces)
        // Match: "1, 10 YO, 20 YO, 30 YO" or "5 YO , 10 YO" etc
        if (preg_match('/^(.+?)[\s,]+((?:\d+\s*,?\s*)+(?:\d+\s+YO(?:\s*,\s*\d+\s+YO)*))$/i', $text, $match)) {
            $potentialDuties = trim($match[1]);
            $potentialRemarks = trim($match[2]);
            
            // Only separate if duties part has duty keywords AND remarks doesn't
            $dutyKeywords = ['TAKE CARE', 'GENERAL', 'HOUSEWORK', 'COOKING', 'CLEANING', 'WASHING', 'CHILDREN', 'LOREM', 'IPSUM'];
            $hasDutyInFirst = false;
            $hasDutyInSecond = false;
            
            foreach ($dutyKeywords as $keyword) {
                if (stripos($potentialDuties, $keyword) !== false) {
                    $hasDutyInFirst = true;
                }
                if (stripos($potentialRemarks, $keyword) !== false) {
                    $hasDutyInSecond = true;
                }
            }
            
            // Only separate if first part has duties and second part is pure age list
            if ($hasDutyInFirst && !$hasDutyInSecond) {
                $duties = $potentialDuties;
                $foundRemarks[] = $potentialRemarks;
                $remarksPositions[] = strlen($potentialDuties);
            }
        }
        
        // If found remarks, extract them and clean duties
        if (!empty($foundRemarks)) {
            // Use position-based extraction (remove from earliest remark position)
            if (!empty($remarksPositions)) {
                $earliestPos = min($remarksPositions);
                $duties = trim(substr($text, 0, $earliestPos));
                $remarks = trim(substr($text, $earliestPos));
            } else {
                $remarks = implode(', ', array_unique($foundRemarks));
                
                // Remove remarks from duties
                foreach ($foundRemarks as $remark) {
                    $duties = str_ireplace($remark, '', $duties);
                }
            }
            
            // Clean up duties
            $duties = preg_replace('/[,\s]+$/', '', $duties);
            $duties = preg_replace('/\s*,\s*,+\s*/', ', ', $duties);
            $duties = preg_replace('/\s+/', ' ', $duties);
            $duties = trim($duties, " ,\t\n\r\0\x0B");
        }
        
        return [
            'duties' => $duties ?: null,
            'remarks' => $remarks,
        ];
    }

    /**
     * Check if match is duplicate
     */
    private function isDuplicate(array $match, array $existing): bool
    {
        foreach ($existing as $entry) {
            if ($entry[1] === $match[1] && $entry[2] === $match[2]) {
                return true;
            }
        }
        return false;
    }

    /**
     * Split employer and duties from combined text
     * Format: EMPLOYER DUTIES or EMPLOYER\nDUTIES
     */
    private function splitEmployerAndDuties(string $text): array
    {
        // Common employer keywords (can be nationality/ethnicity)
        $employerKeywords = ['ARAB', 'ARABIC', 'INDIA', 'INDIAN', 'MELAYU', 'MALAY', 'CHINESE', 'FILIPINO', 'SAUDI'];
        
        // Words that are countries/cities, NOT employer types
        $countryKeywords = ['INDONESIA', 'SINGAPORE', 'MALAYSIA', 'THAILAND', 'PHILIPPINES'];
        
        // Duty keywords that should NOT be considered as employer
        $dutyKeywords = ['GENERAL', 'TAKE', 'CARE', 'COOKING', 'CLEANING', 'WASHING', 'IRONING', 'HOUSEWORK', 'HELPER', 'SHOWERING', 'CHANGE'];
        
        // Try to find employer at the beginning
        $employer = null;
        $duties = $text;
        
        // Check if text starts with employer keyword(s)
        $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        $employerWords = [];
        
        foreach ($words as $i => $word) {
            $wordUpper = strtoupper($word);
            
            // Skip country names - these are not employer types
            if (in_array($wordUpper, $countryKeywords)) {
                break;
            }
            
            // Stop if we hit a duty keyword
            if (in_array($wordUpper, $dutyKeywords)) {
                break;
            }
            
            // Collect employer keywords
            if (in_array($wordUpper, $employerKeywords)) {
                $employerWords[] = $word;
            } else {
                // Stop at first non-employer word (unless it's a continuation)
                break;
            }
        }
        
        if (!empty($employerWords)) {
            $employer = implode(' ', $employerWords);
            // Remove employer from duties
            $duties = trim(substr($text, strlen($employer)));
        }
        
        return [
            'employer' => $employer,
            'duties' => $duties ?: null,
        ];
    }

    // Note: vertical-stacked parsing now delegated to EmploymentPatterns::stackedVerticalEntries()

    /**
     * Extract feedback from C3 table
     */
    private function extractFeedback(string $text): array
    {
        $feedback = [
            'employer_1' => null,
            'employer_2' => null,
        ];

        // Try to extract feedback using pattern
        if (preg_match('/Employer\s*1\s*(.+?)Employer\s*2\s*(.+?)(?=\(D\)|$)/is', $text, $match)) {
            $feedback['employer_1'] = trim($match[1]);
            $feedback['employer_2'] = trim($match[2]);
        }

        return $feedback;
    }

    /**
     * Parse country from raw text (handle parentheses only, NO aggressive city mapping)
     */
    private function parseCountry(string $raw): string
    {
        $raw = trim($raw);
        
        // Handle format: JAKARTA (INDONESIA) -> Use text in parentheses
        if (preg_match('/\(([A-Z\s]+)\)/', $raw, $match)) {
            return trim($match[1]);
        }
        
        // Handle format: CITY NAME (without parentheses) - only for clear city names
        // Map ONLY specific Indonesian cities, not ambiguous terms like SAUDI, ARAB, etc.
        $specificCityMap = [
            'JAKARTA' => 'INDONESIA',
            'BANDUNG' => 'INDONESIA',
            'TANGERANG' => 'INDONESIA',
            'SURABAYA' => 'INDONESIA',
            'MEDAN' => 'INDONESIA',
            'SINGAPURA' => 'SINGAPORE',
            'DUBAI' => 'UAE',
            'ABU DHABI' => 'UAE',
        ];
        
        $normalized = strtoupper($raw);
        
        // Only check exact matches for cities
        if (isset($specificCityMap[$normalized])) {
            return $specificCityMap[$normalized];
        }
        
        // Return as-is (could be country name like SAUDI ARABIA, UAE, etc.)
        return trim(preg_replace('/\s+/', ' ', $raw));
    }

    /**
     * Extract remarks from duties text (including age references)
     */
    private function extractRemarks(string $text): ?string
    {
        $remarks = [];

        // Use enhanced remarks patterns
        $patterns = EmploymentPatterns::remarksEnhanced();
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $match) {
                    $cleaned = trim($match, " ,\t\n\r\0\x0B");
                    if (!empty($cleaned)) {
                        $remarks[] = $cleaned;
                    }
                }
            }
        }

        // Remove duplicates (case-insensitive)
        $remarks = array_unique(array_map('strtoupper', $remarks));
        $remarks = array_values($remarks); // Re-index
        
        return !empty($remarks) ? implode(', ', $remarks) : null;
    }

    /**
     * Clean text helper
     */
    private function cleanText(string $text): string
    {
        return preg_replace('/\s+/', ' ', trim($text));
    }

    /**
     * Clean duties text by removing remarks
     */
    private function cleanDuties(string $text): string
    {
        // Remove remarks patterns
        $patterns = EmploymentPatterns::remarksEnhanced();
        foreach ($patterns as $pattern) {
            $text = preg_replace($pattern, '', $text);
        }
        
        // Clean up leftover punctuation and spaces
        $text = preg_replace('/,\s*,+/', ',', $text);  // Remove double/multiple commas
        $text = preg_replace('/\s*,\s*/', ', ', $text); // Normalize comma spacing
        $text = preg_replace('/,\s*$/', '', $text);     // Remove trailing comma
        $text = preg_replace('/^\s*,/', '', $text);     // Remove leading comma
        $text = preg_replace('/\s+/', ' ', $text);      // Normalize spaces
        
        return trim($text);
    }



    /**
     * Format entries for backward compatibility with old structure
     */
    private function formatEntriesForBackwardCompatibility(array $overseasEntries): array
    {
        $formatted = [];

        foreach ($overseasEntries as $index => $entry) {
            $formatted[] = [
                'entry_number' => $index + 1,
                'country' => $entry['country'],
                'country_raw' => $entry['country_raw'],
                'employer' => $entry['employer'] ?? 'Not specified',
                'period_from' => $entry['from'],
                'period_to' => $entry['to'],
                'duties' => $entry['duties'],
                'duties_raw' => $entry['duties_raw'],
                'remarks' => $entry['remarks'],
                'validation_score' => 80, // Default score for pattern-based extraction
            ];
        }

        return $formatted;
    }

    /**
     * Get raw extraction data for debugging
     *
     * @return array Raw extraction metadata
     */
    public function getRawData(): array
    {
        return $this->rawData;
    }
}
