<?php

namespace App\Services\MaidManagement\DataExtractor;

use App\Services\MaidManagement\DataExtractor\Patterns\SkillsAssesmentPattern;
use App\Services\MaidManagement\DataExtractor\Patterns\SmartPatternMatcher;

class SkillsAssessmentExtractor
{
    private $text;
    private $matchedPattern;

    public function __construct(string $text)
    {
        $this->text = $text;
        $this->matchedPattern = $this->detectPattern();
    }

    private function detectPattern(): ?array
    {
        $patterns = SkillsAssesmentPattern::getPatterns();
        $scores = [];
        
        foreach ($patterns as $key => $pattern) {
            $score = 0;
            
            // Check table markers (high weight)
            if (preg_match($pattern['table_markers']['start'], $this->text)) {
                $score += 50;
            }
            
            // Check areas of work
            foreach ($pattern['areas_of_work'] as $area) {
                $label = preg_quote($area['label'], '/');
                if (preg_match("/" . $label . "/i", $this->text)) {
                    $score += 5;
                }
            }
            
            // Check for common document headers
            if (preg_match('/biodata|bio\s*data|fdw|foreign\s*domestic\s*worker/i', $this->text)) {
                $score += 10;
            }
            
            // Check for evaluation sections
            if (preg_match('/evaluation|assessment|skills/i', $this->text)) {
                $score += 15;
            }
            
            if ($score > 0) {
                $scores[$key] = $score;
            }
        }
        
        if (empty($scores)) {
            return null;
        }
        
        arsort($scores);
        $bestPattern = array_key_first($scores);
        
        return ['key' => $bestPattern, 'config' => $patterns[$bestPattern]];
    }

    private function normalizeValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        
        $value = trim($value);
        
        // Empty or whitespace only
        if ($value === '') {
            return null;
        }
        
        // Common "empty" indicators
        $emptyIndicators = [
            '-', '–', '—',  // Dashes
            'nil', 'n/a', 'na', 'n.a.', 'n.a',  // Not applicable
            'none', 'no', 'not applicable',
            '___', '...', // Placeholders
        ];
        
        if (in_array(strtolower($value), $emptyIndicators)) {
            return null;
        }
        
        // Only dashes/underscores
        if (preg_match('/^[\-_\s]+$/', $value)) {
            return null;
        }
        
        return $value;
    }

    public function extract(): array
    {
        $evaluation = $this->extractEvaluationSummary($this->text);

        if (!$this->matchedPattern) {
            return [
                'error' => 'No matching pattern found',
                'evaluation' => $evaluation,
            ];
        }

        $tables = $this->extractTables($evaluation);
        
        return [
            'pattern_detected' => $this->matchedPattern['key'],
            'total_tables' => count($tables),
            'tables' => $tables,
            'evaluation' => $evaluation,
        ];
    }

    private function extractTables(array $evaluation): array
    {
        return $this->extractTablesFromSection($this->text, $evaluation);
    }

    private function extractTablesFromSection(string $section, array $evaluation): array
    {
        $pattern = $this->matchedPattern['config'];
        $tables = [];
        
        preg_match_all($pattern['table_markers']['start'], $section, $matches, PREG_OFFSET_CAPTURE);
        
        if (empty($matches[0])) {
            if (preg_match_all('/Areas\s*of\s*Work.*?Willingness/is', $section, $fallbackMatches, PREG_OFFSET_CAPTURE)) {
                $matches = $fallbackMatches;
            } else {
                return [];
            }
        }
        
        // REMOVED: Wrong logic that skipped single-table documents
        // OLD: if (count($matches[0]) === 1) return [];
        // NEW: Process all matches (1 or more tables)
        
        // Extract up to 2 tables (numeric and qualitative)
        $numTables = min(2, count($matches[0]));
        for ($i = 0; $i < $numTables; $i++) {
            $startPos = $matches[0][$i][1];
            
            if (isset($matches[0][$i + 1])) {
                $endPos = $matches[0][$i + 1][1];
            } else {
                if (preg_match('/\(C\s*\)|Employment History/i', $section, $endMatch, PREG_OFFSET_CAPTURE, $startPos)) {
                    $endPos = $endMatch[0][1];
                } else {
                    $endPos = strlen($section);
                }
            }
            
            $tableText = substr($section, $startPos, $endPos - $startPos);
            
            $tableData = $this->parseTable($tableText, $pattern, $i);
            
            if (!empty($tableData)) {
                $tables[] = [
                    'table_number' => $i + 1,
                    'type' => $i === 0 ? 'numeric' : 'qualitative',
                    'evaluation_methods' => $evaluation['legacy_methods'] ?? [],
                    'data' => $tableData
                ];
            }
        }
        
        return $tables;
    }

    private function parseTable(string $tableText, array $pattern, int $tableIndex): array
    {
        $data = [];
        $areasOfWork = $pattern['areas_of_work'];
        
        foreach ($areasOfWork as $index => $area) {
            $nextArea = $areasOfWork[$index + 1] ?? null;
            $areaData = $this->extractAreaDataImproved($tableText, $area, $nextArea, $pattern['regex_patterns']);
            
            if ($areaData && !empty(array_filter($areaData))) {
                $data[$area['key']] = $areaData;
            }
        }
        // Heuristic: drop tables that are effectively empty (e.g., only header ratings like "1 2 3 4 5 N.A.")
        // Keep table only if at least one area has a real signal: willingness/experience/experience_years
        if (!empty($data)) {
            $hasSignal = false;
            $onlyHeaderRatings = true;

            foreach ($data as $areaKey => $areaValues) {
                $w = $areaValues['willingness'] ?? null;
                $e = $areaValues['experience'] ?? null;
                $yrs = $areaValues['experience_years'] ?? null;
                $assess = $areaValues['assessment'] ?? null;

                // If any real signal exists, mark
                if ($w !== null || $e !== null || $yrs !== null) {
                    $hasSignal = true;
                }

                // If assessment is not a plain single digit 1-5 or NA, then it's not just header
                if ($assess !== null && !preg_match('/^(?:[1-5]|N\.?A\.?)$/i', (string)$assess)) {
                    $onlyHeaderRatings = false;
                }

                // If any area has no assessment at all, ratings are not the only thing
                if ($assess === null) {
                    $onlyHeaderRatings = false;
                }
            }

            // For the first (numeric) table, if there's no signal and only header-like ratings, drop it
            if ($tableIndex === 0 && !$hasSignal && $onlyHeaderRatings) {
                return [];
            }
        }

        return $data;
    }

    private function extractAreaDataImproved(string $text, array $area, ?array $nextArea, array $regexPatterns): ?array
    {
        $label = $area['label'];
        $labelPattern = preg_quote($label, '/');
        $labelPattern = str_replace(' ', '\s*', $labelPattern);
        
        $alternativeLabels = [
            'Care of elderly' => 'Care\s*(?:of\s*)?elderly',
            'Care of disabled' => 'Care\s*(?:of\s*)?disabled',
            'Care of infants/children' => 'Care\s*(?:of\s*)?infants?\s*\/\s*children',
            'Other skills, if any' => 'Other(?:s)?\s*skills,?\s*if\s*any',
        ];
        
        if (isset($alternativeLabels[$label])) {
            $labelPattern = $alternativeLabels[$label];
        }
        
        if ($nextArea) {
            $nextLabel = $nextArea['label'];
            if (isset($alternativeLabels[$nextLabel])) {
                $endPattern = $alternativeLabels[$nextLabel];
            } else {
                $endPattern = preg_quote($nextLabel, '/');
                $endPattern = str_replace(' ', '\s*', $endPattern);
            }
        } else {
            $endPattern = '(?:Interviewed|\(C\)|\(D\)|\(E\))';
        }
        
        $areaPattern = "/\d+\.\s*{$labelPattern}(.*?)(?=\d+\.\s*{$endPattern}|{$endPattern}|$)/is";
        
        if (!preg_match($areaPattern, $text, $areaMatch)) {
            $areaPattern = "/{$labelPattern}(.*?)(?={$endPattern}|$)/is";
            if (!preg_match($areaPattern, $text, $areaMatch)) {
                return null;
            }
        }
        
        $areaText = $areaMatch[1];
        $cleanText = preg_replace('/_{3,}/', '', $areaText);
        
        $willingness = $this->extractWillingness($cleanText);
        $experience = $this->extractExperience($cleanText);
        $experienceYears = $this->extractExperienceYears($cleanText);
        $assessment = $this->extractAssessmentImproved($cleanText);
        
        // Build result with assessment broken down into rating and observation
        $result = [
            'willingness' => $this->normalizeValue($willingness),
            'experience' => $this->normalizeValue($experience),
            'experience_years' => $this->normalizeValue($experienceYears),
        ];
        
        // Handle assessment: if it's an array, split into rating and observation
        if (is_array($assessment)) {
            $result['assessment'] = $this->normalizeValue($assessment['rating']);
            $result['observation'] = $this->normalizeValue($assessment['observation']);
        } else {
            // Backward compatibility: if assessment is string, keep as is
            $result['assessment'] = $this->normalizeValue($assessment);
        }
        
        return array_filter($result, fn($v) => $v !== null && $v !== '');
    }

    private function extractWillingness(string $text): ?string
    {
        // Check for dash/hyphen indicating "No" (common in forms)
        // Must check this BEFORE YES/NO to avoid false positives from other text
        if (preg_match('/^\s*[\-–]\s*$/m', $text) || preg_match('/Willingness[^\n]*[\-–]\s*(?:Experience|\n|$)/i', $text)) {
            return 'NO';
        }
        
        // Get first YES/NO occurrence (willingness)
        if (preg_match('/\b(YES|NO|Yes|No)\b/i', $text, $match)) {
            return strtoupper($match[1]);
        }
        
        return null;
    }

    private function extractExperience(string $text): ?string
    {
        // First check for date ranges (e.g., "2023 – 2025", "2020-2023")
        // Support unicode dashes (en dash, em dash, regular hyphen)
        // This indicates experience even if YES/NO not explicitly stated twice
        if (preg_match('/\b(\d{4})\s*[\x{2012}-\x{2015}\-]\s*(\d{4})\b/u', $text)) {
            return 'YES';
        }
        
        // Check for explicit year mentions (e.g., "3 years", "5 years")
        if (preg_match('/\b(\d+)\s*(?:years?|yrs?)\b/i', $text)) {
            return 'YES';
        }
        
        // Check for "If yes, how many years? X" pattern
        if (preg_match('/\bIf\s+yes[,\s]+(?:how\s+many\s+years\??\s*)?(\d{1,2})\b/i', $text)) {
            return 'YES';
        }
        
        // Fallback to YES/NO pattern (second occurrence for traditional format)
        preg_match_all('/\b(YES|NO|Yes|No)\b/i', $text, $matches);
        if (isset($matches[1][1])) {
            return strtoupper($matches[1][1]);
        }
        
        // If only one YES found, check if there's a date range after it
        // This handles PDF format where single YES means both willingness AND experience with date range
        if (isset($matches[1][0]) && count($matches[1]) === 1) {
            $afterYes = substr($text, strpos($text, $matches[0][0]) + strlen($matches[0][0]));
            // ONLY check for date range (4-digit years), NOT just any number
            if (preg_match('/\b(\d{4})\s*[\x{2012}-\x{2015}\-]\s*(\d{4})\b/u', $afterYes)) {
                return 'YES';
            }
        }
        
        // No explicit experience indicator found
        return null;
    }

    private function extractExperienceYears(string $text): ?string
    {
        // Pattern 1: Date range format (e.g., "2023 – 2025", "2020-2023", "2021 - 2022")
        // Support unicode dashes (en dash U+2012-2015, em dash, regular hyphen)
        if (preg_match('/\b(\d{4})\s*[\x{2012}-\x{2015}\-]\s*(\d{4})\b/u', $text, $match)) {
              // Return raw date range string as-is (e.g., "2023-2025")
              return $match[1] . '-' . $match[2];
        }
        
        // Pattern 2: Explicit years mention (e.g., "3 years", "5 yrs")
        if (preg_match('/\b(\d+)\s*(?:years?|yrs?)\b/i', $text, $match)) {
            return $match[1];
        }
        
        // Pattern 3: "If yes, how many years? 3" or "If yes, state the no. of years 5"
        if (preg_match('/\bIf\s+yes[,\s]+(?:how\s+many\s+years\??\s*|state\s+the\s+(?:no\.|number)\s+of\s+years\s*)(\d{1,2})\b/i', $text, $match)) {
            return $match[1];
        }
        
        // Pattern 4: Standalone 1-2 digit number that appears in EARLY part of text (likely years in Experience column)
        // BUT avoid last number if text is long (that's likely assessment rating)
        // Strategy: find ALL standalone 1-2 digit numbers, return the FIRST one that's reasonable (1-30)
        if (preg_match_all('/(?:^|\n)\s*(\d{1,2})\s*(?:\n|$)/m', $text, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $match) {
                $num = (int)$match[0];
                $pos = $match[1];
                // Accept if it's in first 60% of text and is reasonable years (1-30)
                if ($num >= 1 && $num <= 30 && $pos < strlen($text) * 0.6) {
                    return (string)$num;
                }
            }
        }
        
        return null;
    }

    private function extractAssessmentImproved(string $text)
    {
        // Strip common helper/placeholder phrases that shouldn't be treated as data
        $text = preg_replace('/Please\s*specify\s*(?:age\s*range|cuisines)?:?/i', '', $text);
        $text = preg_replace('/Please\s*Specify:?/i', '', $text);
        $text = preg_replace('/Ex:\s*[^\n]*/i', '', $text);
        // New: remove standalone placeholder lines like "Rate the FDW" and "Observations"
        $text = preg_replace('/^\s*Rate\s*the\s*FDW\s*:?\s*$/im', '', $text);
        $text = preg_replace('/^\s*Observations?\s*:?\s*$/im', '', $text);
        // New: avoid misreading the standalone years number as a rating when it follows "If yes ..."
        // Example block:
        //   If yes, how many years?
        //   3
        // We remove that numeric line from assessment consideration only
        // Remove the standalone numeric line following an "If yes ..." prompt
        // Keep the surrounding context, drop only the number
        $text = preg_replace('/(If\s+yes[\s\S]*?\n\s*)([0-9]{1,2})(\s*(?=\n|$))/i', '$1$3', $text);
        
        $lines = explode("\n", $text);
        $foundRating = null;
        $ratingLineIndex = null;
        
        foreach ($lines as $idx => $line) {
            $line = trim($line);
            // Accept plain numeric rating or 'N.A'
            if (preg_match('/^([1-5]|N\.?A\.?)$/i', $line)) {
                $foundRating = $line;
                $ratingLineIndex = $idx;
                break;
            }
            // Also accept formats like '3 (Good)' -> capture '3'
            if (preg_match('/^([1-5])\s*\([^\)]*\)\s*$/i', $line, $m)) {
                $foundRating = $m[1];
                $ratingLineIndex = $idx;
                break;
            }
        }
        
        if ($foundRating) {
            // Extract observation text (everything after the rating line)
            $observationLines = array_slice($lines, $ratingLineIndex + 1);
            
            // Filter out lines that are just ratings or empty
            $observationLines = array_filter($observationLines, function($line) {
                $line = trim($line);
                // Skip empty lines
                if (empty($line)) return false;
                // Skip lines that are just plain numeric ratings (1-5) or N.A
                if (preg_match('/^([1-5]|N\.?A\.?)$/i', $line)) return false;
                // Skip lines that are rating with label like "3 (Good)"
                if (preg_match('/^([1-5])\s*\([^\)]*\)\s*$/i', $line)) return false;
                return true;
            });
            
            $observation = trim(implode("\n", $observationLines));
            
            // Return as array with both rating and observation
            return [
                'rating' => $foundRating,
                'observation' => !empty($observation) ? $observation : null
            ];
        }
        
        $qualitativePatterns = [
            '/TAKE\s*CARE[A-Z0-9\s,]+?(?=\n\d+\.|$)/is',
            '/GENERAL\s*HOUSEWORK[A-Z\s]+?(?=\n\d+\.|$)/is',
            '/(?:NASI|SOUP|CURRY|RENDANG)[A-Z\s,]+?(?=\n\d+\.|$)/is',
            '/INDONESIA[A-Z\s,]+?(?=\n|$)/is',
            '/BASIC/i'
        ];
        
        foreach ($qualitativePatterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $result = trim($match[0]);
                $result = preg_replace('/\n\d+$/', '', $result);
                if (strlen($result) > 2) {
                    // Return qualitative text as observation with null rating
                    return [
                        'rating' => null,
                        'observation' => $result
                    ];
                }
            }
        }
        
        return null;
    }

    /**
     * Extract evaluation methods from Section B1
     * Simplified version - merges 8 functions into 3
     */
    private function extractEvaluationSummary(string $text): array
    {
        // NEW APPROACH: Don't look for "Singapore/Overseas Evaluation Method"
        // Instead, split by "Interviewed by overseas" as the boundary
        
        // Extract Singapore section: From start of Section B until "Interviewed by overseas"
        $singaporeSection = '';
        $overseasSection = '';
        
        if (preg_match('/(Method of Evaluation.*?)(?=Interviewed by overseas|Section C|\(C\)|Employment History|$)/is', $text, $sgMatch)) {
            $singaporeSection = $sgMatch[1];
        }
        
        // Extract Overseas section: From "Interviewed by overseas" until Section C
        if (preg_match('/(Interviewed by overseas.*?)(?=Section C|\(C\)|Employment History|$)/is', $text, $osMatch)) {
            $overseasSection = $osMatch[1];
        }

        // Parse Singapore checkboxes (6 types)
        // IMPORTANT: Only detect CHECKED boxes (☒, ☑, ✓, ✔, [x])
        // Ignore UNCHECKED boxes (☐, [ ])
        $singapore = [
            'declaration_no_eval' => $this->hasCheckedBox($singaporeSection, [
                'Based on FDW', 'no evaluation', 'declaration'
            ]),
            'interview' => $this->hasCheckedBox($singaporeSection, [
                'Interviewed by Singapore', 'Singapore EA'
            ]),
            'phone' => $this->hasCheckedBox($singaporeSection, [
                'telephone', 'teleconference'
            ]),
            'video' => $this->hasCheckedBox($singaporeSection, [
                'videoconference', 'video-conference'
            ]),
            'in_person' => $this->hasCheckedBox($singaporeSection, [
                'Interviewed in person'
            ]),
            'observation' => $this->hasCheckedBox($singaporeSection, [
                'made observation', 'observation of FDW'
            ]),
        ];

        // Parse Overseas checkboxes (4 types) + 2 text fields
        $overseas = [
            // If "Interviewed by overseas" section exists with name/cert, assume interview=true
            'interview' => !empty($overseasSection) && (
                stripos($overseasSection, 'Interviewed by overseas') !== false ||
                stripos($overseasSection, 'overseas training') !== false
            ),
            'phone' => $this->hasCheckedBox($overseasSection, [
                'telephone', 'teleconference'
            ]),
            'video' => $this->hasCheckedBox($overseasSection, [
                'videoconference', 'video-conference'
            ]),
            'in_person' => $this->hasCheckedBox($overseasSection, [
                'Interviewed in person'
            ]),
            'observation' => $this->hasCheckedBox($overseasSection, [
                'made observation', 'observation of FDW'
            ]),
            'name' => $this->extractTextField($overseasSection, [
                // Pattern: "EA: tes)" or "centre: XYZ)" - extract value after colon before closing paren
                '/(?:EA|centre|center)\s*:\s*([^):\n]+)\)/i',
                // Pattern: "Name of foreign training centre / EA: ABC"
                '/(?:foreign|overseas)\s+training\s+(?:centre|center)\s*\/\s*EA\s*:\s*([^\n)]+)/i',
            ]),
            'certificate' => $this->extractTextField($overseasSection, [
                // Pattern 1: "audited periodically by the EA: VALUE"
                // Must have actual text after EA:, not empty
                '/audited\s+periodically\s+by\s+the\s+EA\s*:\s*([A-Za-z0-9][^\n]{2,})/i',
                // Pattern 2: "certified...by the EA: VALUE" 
                '/certified[^:]{5,50}by\s+the\s+EA\s*:\s*([A-Za-z0-9][^\n]{2,})/i',
                // Pattern 3: Standalone "ISO XXXX" or "ISOXXXX" (NOT in parentheses as example)
                // Must not be preceded by "e.g." or within parentheses
                '/(?<!e\.g\.\s)(?<!\()ISO\s*\d{4,5}(?!\))/i',
            ]),
        ];

        // Build legacy methods array for backward compatibility
        $legacy = [];
        if ($singapore['declaration_no_eval']) $legacy[] = 'fdw_declaration';
        if ($singapore['interview']) $legacy[] = 'interviewed_sg_ea';
        if ($singapore['phone'] || $overseas['phone']) $legacy[] = 'telephone';
        if ($singapore['video'] || $overseas['video']) $legacy[] = 'videoconference';
        if ($singapore['in_person'] || $overseas['in_person']) $legacy[] = 'in_person';
        if ($singapore['observation'] || $overseas['observation']) $legacy[] = 'in_person_observation';

        return [
            'declaration_no_eval' => $singapore['declaration_no_eval'],
            'singapore' => $singapore,
            'overseas' => $overseas,
            'legacy_methods' => array_values(array_unique($legacy)),
        ];
    }

    /**
     * Extract a section between start and end markers
     * Replaces sliceSection() - more robust with multiple end markers
     */
    private function extractSection(string $text, string $keyword, array $endMarkers): string
    {
        // Find section start - look for "Singapore Evaluation" or "Overseas Evaluation"
        $patterns = [
            "/{$keyword}\s+Evaluation\s+Method/i",
            "/Interviewed\s+by\s+{$keyword}/i",
        ];

        $startPos = false;
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE)) {
                $startPos = $match[0][1];
                break;
            }
        }

        if ($startPos === false) {
            return '';
        }

        // Find section end - use closest end marker
        $endPos = strlen($text);
        foreach ($endMarkers as $marker) {
            $pos = stripos($text, $marker, $startPos + 10);
            if ($pos !== false && $pos < $endPos) {
                $endPos = $pos;
            }
        }

        return trim(substr($text, $startPos, $endPos - $startPos));
    }

    /**
     * Check if checkbox is checked (☑✓✔[x])
     * IMPORTANT: This must ONLY match CHECKED boxes, not unchecked ☐
     * Merges: containsCheckedLabel() + buildCheckboxPattern()
     */
    private function hasCheckedBox(string $text, array $keywords): bool
    {
        if (empty($text)) {
            return false;
        }

        // CHECKED checkbox symbols only (exclude ☐ unchecked)
        // ☒ = checked box with X (common in PDFs)
        // ☑ = checked box with checkmark
        // ✓ ✔ √ = standalone checkmarks
        // [x] [X] = text-based checked
        $checkMarks = '(?:☒|☑|✓|✔|√|\[[xX]\])';
        
        foreach ($keywords as $keyword) {
            $escaped = preg_quote($keyword, '/');
            $escaped = str_replace(' ', '\s+', $escaped); // Allow flexible spacing
            
            // Pattern 1: ☒ Label (checkbox before text)
            // Pattern 2: Label ☒ (checkbox after text)
            $patterns = [
                "/{$checkMarks}\s*{$escaped}/iu",
                "/{$escaped}\s*{$checkMarks}/iu",
            ];
            
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $text)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Extract text field value (evaluator name, certificate, etc)
     * Merges: extractFieldValue() + cleanEvaluationValue()
     */
    private function extractTextField(string $text, array $patterns): string
    {
        if (empty($text)) {
            return '';
        }

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $value = trim($matches[1] ?? '');
                
                // Clean up: remove underscores, multiple spaces, trailing punctuation
                $value = preg_replace('/_{3,}/', '', $value);
                $value = preg_replace('/\s{2,}/', ' ', $value);
                $value = trim($value, " \t\n\r\0\x0B:_-");
                
                // Filter out empty indicators
                $upper = strtoupper($value);
                if (in_array($upper, ['', 'NA', 'N/A', 'NIL', 'N.A', 'N.A.', '--', '-', '...'], true)) {
                    continue; // Try next pattern
                }
                
                // Filter out placeholder text
                if (preg_match('/^(?:Click to|Please|State|Enter|Fill)/i', $value)) {
                    continue;
                }
                
                // Filter out checkbox labels that leaked into text extraction
                // These indicate extraction error, not actual data
                if (preg_match('/Interviewed|telephone|videoconference|in person|observation|checkbox/i', $value)) {
                    continue; // Skip patterns that captured checkbox text
                }
                
                return $value;
            }
        }

        return '';
    }
}
