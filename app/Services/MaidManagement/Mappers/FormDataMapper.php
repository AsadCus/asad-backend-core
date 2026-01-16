<?php

namespace App\Services\MaidManagement\Mappers;

/**
 * Maps extracted data to form structure
 * Responsibility: Transform extracted data into form-ready format
 */
class FormDataMapper
{
    private ReferenceDataMapper $referenceMapper;

    public function __construct(ReferenceDataMapper $referenceMapper)
    {
        $this->referenceMapper = $referenceMapper;
    }

    /**
     * Map extracted data to form structure
     */
    public function map(array $extractedData): array
    {
        $personal = $extractedData['personal'] ?? [];
        $medical = $extractedData['medical'] ?? [];
        $skills = $extractedData['skills'] ?? [];
        $employment = $extractedData['employment'] ?? [];
        $sections = $extractedData['sections'] ?? [];

        // Map personal info first
        $personalData = $this->mapPersonalInfo($personal);
        
        // REMOVED: Fallback extraction from employment is incorrect
        // Employment history contains ages of children being CARED FOR, not the FDW's own children
        // Children ages should ONLY come from Section A1 "Age(s) of children" field
        // if (!empty($personalData['number_of_children']) && 
        //     empty($personalData['children_ages']) && 
        //     !empty($employment)) {
        //     $extractedAges = $this->extractChildrenAgesFromEmployment($employment);
        //     if (!empty($extractedAges)) {
        //         $personalData['children_ages'] = $extractedAges;
        //     }
        // }

        return [
            // Personal Information
            ...$personalData,
            
            // Medical & Attributes
            ...$this->mapMedicalInfo($medical),
            
            // Skills Assessment
            ...$this->mapSkills($skills),
            
            // Employment History
            ...$this->mapEmployment($employment, $sections),
            
            // Availability & Remarks
            ...$this->mapAvailability($sections),
            
            // Default status
            'status' => 'available',
        ];
    }

    private function mapPersonalInfo(array $personal): array
    {
        // Try to get country from nationality, fallback to place_of_birth
        $countryId = $this->referenceMapper->mapCountryId($personal['nationality'] ?? null);
        if (!$countryId && !empty($personal['birth_place'])) {
            // Try to extract country from place_of_birth (e.g., "KARAWANG" -> "Indonesia")
            $countryId = $this->referenceMapper->mapCountryIdFromLocation($personal['birth_place']);
        }
        
        return [
            'name' => $personal['name'] ?? '',
            'date_of_birth' => $this->referenceMapper->formatDateOfBirth($personal['dob'] ?? null),
            'place_of_birth' => $personal['birth_place'] ?? '',
            'height' => $this->toStringOrEmpty($personal['height'] ?? ''),
            'weight' => $this->normalizeWeight($personal['weight'] ?? ''),
            'country_id' => $countryId,
            'address' => $personal['address'] ?? '',
            'repatriation_port_airport' => $personal['repatriation_to'] ?? '',
            'contact_number_home_country' => $this->normalizeContactNumber($personal['contact_number'] ?? ''),
            'religion_id' => $this->referenceMapper->mapReligionId($personal['religion'] ?? null),
            'education_level_id' => $this->referenceMapper->mapEducationId($personal['education'] ?? null),
            'marital_status' => $this->referenceMapper->normalizeMaritalStatus($personal['marital_status'] ?? null),
            'number_of_siblings' => $this->toStringOrEmpty($personal['siblings'] ?? ''),
            'number_of_children' => $this->toStringOrEmpty($personal['children'] ?? ''),
            'children_ages' => $this->formatChildrenAges($personal['children_ages'] ?? ''),
            'photo_url' => $personal['photo_profile'] ?? $personal['photo_url'] ?? null,
        ];
    }

    private function mapMedicalInfo(array $medical): array
    {
        $illnesses = $medical['illnesses'] ?? [];
        
        // Build attributes array from illnesses
        $attributes = array_map(fn($illness) => [
            'attribute_category' => 'ILLNESS',
            'attribute_name' => $illness,
        ], $illnesses);

        return [
            'allergies' => $this->normalizeMedicalField($medical['allergies'] ?? ''),
            'physical_disabilities' => $this->normalizeMedicalField($medical['physical_disabilities'] ?? ''),
            'dietary_restrictions' => $this->normalizeMedicalField($medical['dietary_restrictions'] ?? ''),
            'food_preferences' => trim($medical['food_preferences'] ?? ''), // Don't normalize, preserve full text
            'rest_days_per_month' => $medical['rest_day'] ?? '',
            'other_remarks' => $medical['remarks'] ?? '',
            'attributes' => $attributes,
        ];
    }

    private function mapSkills(array $skills): array
    {
        $skillsSingapore = [];
        $skillsOverseas = [];

        $evaluation = $skills['evaluation'] ?? [];
        $singaporeEval = $evaluation['singapore'] ?? [];
        $overseasEval = $evaluation['overseas'] ?? [];
        $overseasName = isset($overseasEval['name']) ? trim((string) $overseasEval['name']) : '';
        $overseasCert = isset($overseasEval['certificate']) ? trim((string) $overseasEval['certificate']) : '';

        if (!empty($skills['tables'])) {
            foreach ($skills['tables'] as $table) {
                $tableType = $table['type'] ?? '';
                $tableData = $table['data'] ?? [];
                
                foreach ($tableData as $key => $skillData) {
                    $areaName = $this->formatSkillAreaName($key);
                    
                    $row = [
                        'area' => $areaName,
                        'willingness' => ucfirst(strtolower($skillData['willingness'] ?? '')),
                        'experience' => ucfirst(strtolower($skillData['experience'] ?? '')),
                    ];
                    
                    if ($tableType === 'numeric') {
                        // Singapore assessment (numeric ratings)
                        $row['assessment'] = $skillData['assessment'] ?? '';
                        $row['observation'] = $skillData['observation'] ?? '';
                        // Backward compatibility: combined field
                        $row['assessment_observation'] = $skillData['assessment'] ?? '';
                        $skillsSingapore[] = $row;
                    } elseif ($tableType === 'qualitative') {
                        // Overseas assessment (qualitative/years of experience)
                        $row['experience_years'] = $skillData['experience_years'] ?? '';
                        $row['assessment'] = $skillData['assessment'] ?? '';
                        $row['observation'] = $skillData['observation'] ?? '';
                        // Backward compatibility: combined field
                        $row['assessment_observation'] = $skillData['assessment'] ?? '';
                        $skillsOverseas[] = $row;
                    }
                }
            }
        }

        return [
            'skills_assessment_singapore' => $skillsSingapore,
            'skills_assessment_overseas' => $skillsOverseas,
            'eval_declaration_no_eval' => (bool) ($evaluation['declaration_no_eval'] ?? false),
            'eval_sg_interview' => (bool) ($singaporeEval['interview'] ?? false),
            'eval_sg_phone' => (bool) ($singaporeEval['phone'] ?? false),
            'eval_sg_video' => (bool) ($singaporeEval['video'] ?? false),
            'eval_sg_in_person' => (bool) ($singaporeEval['in_person'] ?? false),
            'eval_sg_in_person_observed' => (bool) ($singaporeEval['observation'] ?? false),
            'eval_overseas_interview' => (bool) ($overseasEval['interview'] ?? false),
            'eval_overseas_name' => $overseasName,
            'eval_overseas_cert' => $overseasCert,
            'eval_overseas_phone' => (bool) ($overseasEval['phone'] ?? false),
            'eval_overseas_video' => (bool) ($overseasEval['video'] ?? false),
            'eval_overseas_in_person' => (bool) ($overseasEval['in_person'] ?? false),
            'eval_overseas_in_person_observed' => (bool) ($overseasEval['observation'] ?? false),
        ];
    }

    private function mapEmployment(array $employment, array $sections): array
    {
        $employmentHistory = [];
        
        foreach ($employment as $job) {
            $periodFrom = $job['from'] ?? $job['period_from'] ?? '';
            $periodTo = $job['to'] ?? $job['period_to'] ?? '';
            $duties = $job['work_duties'] ?? $job['duties'] ?? '';
            
            $employmentHistory[] = [
                'country' => $job['country'] ?? '',
                'employer' => $job['employer'] ?? '',
                'period' => trim($periodFrom . ' - ' . $periodTo, ' -'),
                'duties' => $duties,
                'remarks' => $job['remarks'] ?? '',
            ];
        }

        return [
            'employment_history' => $employmentHistory,
            'singapore_experience' => $this->extractSingaporeExperience($sections),
            'employment_feedback' => $this->extractEmploymentFeedback($sections),
        ];
    }

    private function mapAvailability(array $sections): array
    {
        $availabilityChecks = $this->parseAvailabilityCheckboxes($sections['availability'] ?? '');
        
        return [
            // Section D: Interview availability checkboxes
            'interview_not_available' => $availabilityChecks['not_available'] ?? false,
            'interview_by_phone' => $availabilityChecks['by_phone'] ?? false,
            'interview_by_video' => $availabilityChecks['by_video'] ?? false,
            'interview_in_person' => $availabilityChecks['in_person'] ?? false,
            // Section E: Remarks
            'availability_remarks' => $this->cleanSectionText($sections['remarks'] ?? ''),
        ];
    }

    // Helper methods

    private function toStringOrEmpty($value): string
    {
        return (isset($value) && $value !== '') ? (string) $value : '';
    }

    private function normalizeContactNumber(string $contact): string
    {
        // Treat '-' and '--' as empty
        if (preg_match('/^\s*-{1,2}\s*$/', $contact)) {
            return '';
        }
        
        // Remove all spaces and common separators for cleaner storage
        $cleaned = preg_replace('/[\s\-\(\)]+/', '', $contact);
        
        // If it starts with country code without +, add it
        if (preg_match('/^(62|65|63|60|66)\d{8,}$/', $cleaned)) {
            return '+' . $cleaned;
        }
        
        // If already has +, keep as is
        if (strpos($cleaned, '+') === 0) {
            return $cleaned;
        }
        
        // Return cleaned version
        return $cleaned;
    }

    private function normalizeWeight(string $weight): string
    {
        if (empty($weight)) {
            return '';
        }
        // Replace comma with dot for decimal separator
        return str_replace(',', '.', $weight);
    }

    private function normalizeAllergies(string $allergies): string
    {
        return $this->normalizeMedicalField($allergies);
    }

    private function normalizeMedicalField(string $field): string
    {
        // Handle empty input
        if (empty($field) || trim($field) === '') {
            return 'NO';
        }
        
        // Remove trailing dash or space-dash patterns (NO -, NO-, NO  -, etc)
        $cleaned = trim(preg_replace('/\s*-+\s*$/', '', $field));

        // If the value is just the label text captured by OCR (e.g., "Allergies", "Physical disabilities"), treat as NO
        if (preg_match('/^(allerg(?:y|ies)|physical\s+disabilities|dietary\s+restrictions)$/i', $cleaned)) {
            return 'NO';
        }
        
        // If result is just "NO" or becomes empty after cleaning, return "NO"
        if (empty($cleaned) || strtoupper($cleaned) === 'NO' || strtoupper($cleaned) === 'NIL') {
            return 'NO';
        }
        
        return $cleaned;
    }

    private function formatChildrenAges($childrenAges): string
    {
        if (empty($childrenAges)) {
            return '';
        }

        if (is_string($childrenAges)) {
            $decoded = json_decode($childrenAges, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return implode(', ', array_map('intval', $decoded));
            }
            
            $ages = array_map('trim', explode(',', $childrenAges));
            return implode(', ', array_filter($ages));
        }

        if (is_array($childrenAges)) {
            return implode(', ', array_map('intval', $childrenAges));
        }

        return '';
    }

    private function extractChildrenAgesFromEmployment(array $employment): string
    {
        $ages = [];
        
        foreach ($employment as $job) {
            $remarks = $job['remarks'] ?? '';
            $duties = $job['work_duties'] ?? $job['duties'] ?? '';
            
            // Look for patterns like "2 YO", "4 YO", "5 YO", "NEWBORN"
            $text = $remarks . ' ' . $duties;
            
            // Extract numeric ages (e.g., "2 YO", "4 YO", "10 YO")
            if (preg_match_all('/(\d+)\s*(?:YO|YEARS?\s*OLD)/i', $text, $matches)) {
                foreach ($matches[1] as $age) {
                    if (is_numeric($age) && $age > 0 && $age < 18) {
                        $ages[] = (int)$age;
                    }
                }
            }
            
            // Check for "NEWBORN" pattern
            if (stripos($text, 'newborn') !== false) {
                $ages[] = 0;
            }
        }
        
        if (empty($ages)) {
            return '';
        }
        
        // Remove duplicates and sort
        $ages = array_unique($ages);
        sort($ages);
        
        return implode(', ', $ages);
    }

    private function extractSingaporeExperience(array $sections): bool
    {
        if (empty($sections['employment_sections']['c2'])) {
            return false;
        }

        $c2Text = $sections['employment_sections']['c2'];
        return (stripos($c2Text, '☒ Yes') !== false || stripos($c2Text, '☒Yes') !== false);
    }

    private function extractEmploymentFeedback(array $sections): string
    {
        if (empty($sections['employment_sections']['c3'])) {
            return '';
        }

        $c3Text = $sections['employment_sections']['c3'];
        $feedbackPattern = '/Employer\s+Feedback(.*)/s';
        
        if (preg_match($feedbackPattern, $c3Text, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    private function parseAvailabilityCheckboxes(string $availabilityText): array
    {
        // Section D: AVAILABILITY OF FDW TO BE INTERVIEWED BY PROSPECTIVE EMPLOYER
        // Look for checked boxes (☒, ☑, ✓, [x], etc.)
        $checkPatterns = [
            'not_available' => '/(☒|☑|✓|✔|\[x\]|X)\s*(FDW\s+is\s+)?not\s+available\s+for\s+interview/i',
            'by_phone' => '/(☒|☑|✓|✔|\[x\]|X)\s*(FDW\s+can\s+be\s+)?interviewed\s+by\s+(phone|telephone)/i',
            'by_video' => '/(☒|☑|✓|✔|\[x\]|X)\s*(FDW\s+can\s+be\s+)?interviewed\s+by\s+video(-conference)?/i',
            'in_person' => '/(☒|☑|✓|✔|\[x\]|X)\s*(FDW\s+can\s+be\s+)?interviewed\s+in\s+person/i',
        ];
        
        $result = [];
        foreach ($checkPatterns as $key => $pattern) {
            $result[$key] = preg_match($pattern, $availabilityText) === 1;
        }
        
        return $result;
    }

    private function cleanSectionText(string $text): string
    {
        $cleaned = preg_replace('/^\([A-Z]\d?\)[A-Z\s]+\n/i', '', $text);
        return trim($cleaned ?? $text);
    }

    private function formatSkillAreaName(string $key): string
    {
        $areaMap = [
            'care_of_infants' => 'Care of infants/children',
            'care_of_elderly' => 'Care of elderly',
            'care_of_disabled' => 'Care of disabled',
            'general_housework' => 'General housework',
            'cooking' => 'Cooking',
            'language_abilities' => 'Language abilities (spoken)',
            'other_skills' => 'Other skills',
        ];

        return $areaMap[$key] ?? ucwords(str_replace('_', ' ', $key));
    }
}
