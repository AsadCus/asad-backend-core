<?php

namespace App\Services\MaidManagement;

/**
 * Extracts data from parsed document sections
 * Responsibility: Coordinate all extractors and return structured data
 */
class DataExtractionService
{
    private array $extractors;

    public function __construct(array $extractors)
    {
        $this->extractors = $extractors;
    }

    /**
     * Extract all data from raw text
     *
     * @param  string  $text  Raw text dari document
     * @param  string|null  $photoUrl  Optional photo URL dari auto-upload
     * @return array ['sections' => [...], 'personal' => [...], 'medical' => [...], etc]
     */
    public function extract(string $text, ?string $photoUrl = null): array
    {
        // Step 1: Extract sections from document
        $sections = $this->extractors['section']->extract($text);

        // Step 2: Extract data from each section
        $personal = $this->extractPersonal($sections, $photoUrl);
        $medical = $this->extractMedical($sections);
        $skills = $this->extractSkills($sections);
        $employment = $this->extractEmployment($sections);

        return [
            'sections' => $sections,
            'personal' => $personal,
            'medical' => $medical,
            'skills' => $skills,
            'employment' => $employment,
        ];
    }

    private function extractPersonal(array $sections, ?string $photoUrl = null): array
    {
        return $this->extractors['personal']->extract($sections['profile'] ?? '', $photoUrl);
    }

    private function extractMedical(array $sections): array
    {
        $medical = $this->extractors['medical']->extract(
            $sections['medical'] ?? '',
            $sections['other'] ?? ''
        );

        $illnesses = $this->extractors['medical']->extractIllnesses(
            $sections['medical'] ?? ''
        );

        return array_merge($medical, ['illnesses' => $illnesses]);
    }

    private function extractSkills(array $sections): array
    {
        $skillsText = $sections['skills'] ?? '';
        $extractor = new \App\Services\MaidManagement\DataExtractor\SkillsAssessmentExtractor($skillsText);

        return $extractor->extract();
    }

    private function extractEmployment(array $sections): array
    {
        return $sections['employment_parsed'] ?? [];
    }
}
