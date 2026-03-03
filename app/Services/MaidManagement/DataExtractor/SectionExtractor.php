<?php

namespace App\Services\MaidManagement\DataExtractor;

class SectionExtractor
{
    private SkillsAssessmentExtractor $skillsExtractor;

    private EmploymentExtractor $employmentExtractor;

    public function __construct()
    {
        $this->skillsExtractor = new SkillsAssessmentExtractor('');
        $this->employmentExtractor = new EmploymentExtractor;
    }

    public function extract(string $text): array
    {
        // Allow optional space inside parentheses like (C ) or without parentheses
        preg_match('/\(A\s*\).*?(?=\(B\s*\)|SKILLS OF FDW|$)/s', $text, $sectionA);

        // Section B: Try with marker first, fallback to "SKILLS OF FDW" header
        preg_match('/\(B\s*\).*?(?=\(C\s*\)|EMPLOYMENT HISTORY|$)/s', $text, $skillsSection);
        if (empty($skillsSection[0])) {
            // Fallback: Extract from "SKILLS OF FDW" to next section
            preg_match('/SKILLS OF FDW.*?(?=EMPLOYMENT HISTORY|$)/is', $text, $skillsSection);
        }

        preg_match('/(?:\(C\s*\)|EMPLOYMENT HISTORY OF THE FDW).*?(?=\(D\s*\)|AVAILABILITY|$)/s', $text, $employmentSection);
        preg_match('/(?:\(D\s*\)|AVAILABILITY OF FDW).*?(?=\(E\s*\)|OTHER REMARKS|$)/s', $text, $availabilitySection);
        preg_match('/(?:\(E\s*\)|OTHER REMARKS).*?(?=FDW Name|I have gone|$)/s', $text, $remarksSection);
        preg_match('/A1.*?(?=A2|$)/s', $sectionA[0] ?? '', $profileSection);
        preg_match('/A2.*?(?=A3|\(B\)|SKILL|$)/s', $sectionA[0] ?? '', $medicalSection);
        preg_match('/A3.*?(?=\(B\)|SKILL|$)/s', $sectionA[0] ?? '', $otherSection);

        // Extract Skills Assessment (Section B)
        $this->skillsExtractor = new SkillsAssessmentExtractor($text);
        $skillsParsed = $this->skillsExtractor->extract();

        // Extract Employment History (Section C) with sub-sections
        $employmentText = trim($employmentSection[0] ?? '');
        $employmentParsed = [];
        $employmentSections = [];
        $employmentExtractionMethod = null;

        if (! empty($employmentText)) {
            // Extract sub-sections C1, C2, C3 (handle both formats)
            preg_match('/C1\.?\s*Employment.*?(?=C2|$)/is', $employmentText, $c1Section);
            preg_match('/C2\.?\s*Employment.*?(?=C3|$)/is', $employmentText, $c2Section);
            preg_match('/C3\.?\s*Feedback.*?(?=\(D\)|AVAILABILITY|$)/is', $employmentText, $c3Section);

            $employmentSections = [
                'c1' => trim($c1Section[0] ?? ''),
                'c2' => trim($c2Section[0] ?? ''),
                'c3' => trim($c3Section[0] ?? ''),
            ];

            // Extract employment data using new pattern-based approach
            $employmentResult = $this->employmentExtractor->extract($employmentText, $employmentSections);
            $employmentParsed = $employmentResult['entries'] ?? [];
            $employmentExtractionMethod = $employmentResult['extraction_method'] ?? 'pattern_based';
        }

        return [
            'profile' => trim($profileSection[0] ?? ''),
            'medical' => trim($medicalSection[0] ?? ''),
            'other' => trim($otherSection[0] ?? ''),
            'skills' => trim($skillsSection[0] ?? ''),
            'skills_parsed' => $skillsParsed,
            'employment' => $employmentText,
            'employment_sections' => $employmentSections,
            'employment_parsed' => $employmentParsed,
            'employment_extraction_method' => $employmentExtractionMethod ?? 'none',
            'availability' => trim($availabilitySection[0] ?? ''),
            'remarks' => trim($remarksSection[0] ?? ''),
        ];
    }
}
