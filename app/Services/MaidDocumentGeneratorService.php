<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use App\Models\Maid;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MaidDocumentGeneratorService
{
    /**
     * Generate biodata HTML from template
     *
     * @param Maid $maid
     * @return string
     */
    public function generateBiodataHtml(Maid $maid): string
    {
        $templatePath = public_path('biodata_template.html');

        if (!file_exists($templatePath)) {
            Log::error('Template not found', ['path' => $templatePath]);
            throw new \Exception("Template file not found: {$templatePath}");
        }

        $template = file_get_contents($templatePath);
        $data = $this->prepareMaidData($maid);
        $html = $this->replacePlaceholders($template, $data);

        return $html;
    }

    /**
     * Generate PDF from maid biodata
     *
     * @param Maid $maid
     * @param bool $download
     * @return mixed
     */
    public function generatePdf(Maid $maid, bool $download = false)
    {
        $html = $this->generateBiodataHtml($maid);
        $html = $this->sanitizeHtmlForPdf($html);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true); // Enable to load local images
        $options->set('defaultFont', 'Arial');
        $options->set('debugPng', false);
        $options->set('debugKeepTemp', false);
        $options->set('debugCss', false);
        $options->set('debugLayout', false);
        $options->set('chroot', public_path()); // Set root for security

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');

        try {
            set_time_limit(60);
            $dompdf->render();
        } catch (\Exception $e) {
            Log::error('PDF render failed', [
                'maid_id' => $maid->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }

        $filename = $this->generateFileName($maid, 'pdf');
        $output = $dompdf->output();

        if ($download) {
            return response($output, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Content-Length', strlen($output))
                ->header('Cache-Control', 'private, max-age=0, must-revalidate')
                ->header('Pragma', 'public');
        }

        $path = 'documents/maids/' . $filename;
        Storage::disk('public')->put($path, $output);

        return $path;
    }

    /**
     * Prepare maid data for template
     *
     * @param Maid $maid
     * @return array
     */
    protected function prepareMaidData(Maid $maid): array
    {
        $age = $maid->date_of_birth
            ? \Carbon\Carbon::parse($maid->date_of_birth)->age
            : 'N/A';

        // Generate bio code if not exists
        $bioCode = $maid->bio_code ?? 'MBC-' . date('Y') . '-' . str_pad($maid->id, 4, '0', STR_PAD_LEFT);

        // Prepare employment history
        $employmentHistoryOverseas = $this->generateEmploymentHistoryRows($maid, 'overseas');
        $employmentFeedback = $this->generateEmploymentFeedbackRows($maid);

        // Prepare logo path - convert to base64 for PDF compatibility
        $logoPath = public_path('logo_agency.png');
        $logoData = '';

        if (file_exists($logoPath)) {
            $logoData = $this->imageToBase64($logoPath);
        }

        // Prepare photo - convert to base64 for PDF compatibility
        $photoData = '';

        if ($maid->photo_url) {
            $photoPath = $this->resolvePhotoPath($maid->photo_url);

            if ($photoPath && file_exists($photoPath)) {
                $photoData = $this->imageToBase64($photoPath);
            }
        }

        // Use placeholder if no photo
        if (empty($photoData)) {
            $placeholderPath = public_path('apple-touch-icon.png');
            if (file_exists($placeholderPath)) {
                $photoData = $this->imageToBase64($placeholderPath);
            }
        }

        return [
            'LOGO_PATH' => $logoData,
            'BIO_CODE' => $bioCode,
            'AVAILABILITY' => $maid->status === 'available' ? 'Yes' : 'No',
            'NAME' => strtoupper($maid->name ?? 'N/A'),
            'DATE_OF_BIRTH' => $maid->date_of_birth ? \Carbon\Carbon::parse($maid->date_of_birth)->format('d-M-Y') : 'N/A',
            'PLACE_OF_BIRTH' => strtoupper($maid->place_of_birth ?? 'N/A'),
            'AGE' => $age,
            'WEIGHT' => $maid->weight ?? 'N/A',
            'HEIGHT' => $maid->height ?? 'N/A',
            'NATIONALITY' => strtoupper($maid->country->adjective ?? $maid->country->name ?? 'N/A'),
            'ADDRESS' => $maid->address ?? 'N/A',
            'PORT_OF_EXIT' => $maid->repatriation_port_airport ?? 'N/A',
            'CONTACT_NUMBER' => $maid->contact_number_home_country ?? 'N/A',
            'RELIGION' => strtoupper($maid->religion->name ?? 'N/A'),
            'EDUCATION_LEVEL' => strtoupper($maid->educationLevel->name ?? 'N/A'),
            'MARITAL_STATUS' => ucfirst($maid->marital_status ?? 'N/A'),
            'NUMBER_OF_SIBLINGS' => $maid->number_of_siblings ?? '0',
            'NUMBER_OF_CHILDREN' => $maid->number_of_children ?? '0',
            'CHILDREN_AGES' => $maid->children_ages ?? 'N/A',
            'PHOTO_URL' => $photoData,

            // Medical History - Extract from attributes relation
            ...$this->extractMedicalData($maid),

            // Interview/Evaluation checkboxes (Section B)
            'INTERVIEW_PHONE_CHECKED' => $maid->eval_sg_phone ? 'checked' : '',
            'INTERVIEW_VIDEO_CHECKED' => $maid->eval_sg_video ? 'checked' : '',
            'INTERVIEW_IN_PERSON_CHECKED' => $maid->eval_sg_in_person ? 'checked' : '',
            'INTERVIEW_OBSERVED_CHECKED' => $maid->eval_sg_in_person_observed ? 'checked' : '',

            // Overseas Evaluation checkboxes
            'EVAL_OVERSEAS_NAME' => $maid->eval_overseas_name ?? '',
            'EVAL_OVERSEAS_CERT' => $maid->eval_overseas_cert ?? '',
            'EVAL_OVERSEAS_PHONE_CHECKED' => $maid->eval_overseas_phone ? 'checked' : '',
            'EVAL_OVERSEAS_VIDEO_CHECKED' => $maid->eval_overseas_video ? 'checked' : '',
            'EVAL_OVERSEAS_IN_PERSON_CHECKED' => $maid->eval_overseas_in_person ? 'checked' : '',
            'EVAL_OVERSEAS_OBSERVED_CHECKED' => $maid->eval_overseas_in_person_observed ? 'checked' : '',

            // Skills rows
            'SKILLS_ROWS_SINGAPORE' => $this->generateSkillsTable($maid->skills_assessment_singapore ?? []),
            'SKILLS_ROWS_OVERSEAS' => $this->generateSkillsTable($maid->skills_assessment_overseas ?? []),

            // Employment History
            'EMPLOYMENT_HISTORY_OVERSEAS' => $employmentHistoryOverseas,
            'SINGAPORE_EXPERIENCE_CHECKED' => $maid->singapore_experience === true ? 'checked' : '',
            'NO_SINGAPORE_EXPERIENCE_CHECKED' => $maid->singapore_experience === false ? 'checked' : '',
            'EMPLOYMENT_FEEDBACK' => $employmentFeedback,

            // Section D: Availability for Interview
            'AVAILABILITY_NOT_AVAILABLE_CHECKED' => $maid->interview_not_available ? 'checked' : '',
            'AVAILABILITY_BY_PHONE_CHECKED' => $maid->interview_by_phone ? 'checked' : '',
            'AVAILABILITY_BY_VIDEO_CHECKED' => $maid->interview_by_video ? 'checked' : '',
            'AVAILABILITY_IN_PERSON_CHECKED' => $maid->interview_in_person ? 'checked' : '',

            // Other remarks
            'OTHER_REMARKS' => nl2br(htmlspecialchars($maid->other_remarks ?? '')),
        ];
    }

    /**
     * Replace placeholders in template with actual data
     *
     * @param string $template
     * @param array $data
     * @return string
     */
    protected function replacePlaceholders(string $template, array $data): string
    {
        // Simple placeholder replacement
        // Format: {{placeholder}}
        foreach ($data as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $template = str_replace($placeholder, $value, $template);
        }

        return $template;
    }

    /**
     * Generate skills assessment table rows
     *
     * @param array $skillsData
     * @return string
     */
    protected function generateSkillsTable(array $skillsData): string
    {
        $areas = [
            'Care of infants/children',
            'Care of elderly',
            'Care of disabled',
            'General housework',
            'Cooking',
            'Language abilities (spoken)',
            'Other skills',
        ];

        $html = '';
        foreach ($areas as $area) {
            // Find data for this area
            $row = null;
            foreach ($skillsData as $item) {
                if (($item['area'] ?? '') === $area) {
                    $row = $item;
                    break;
                }
            }

            $willingness = $row['willingness'] ?? '';
            $experience = $row['experience'] ?? '';
            $assessment = $row['assessment'] ?? '';
            $observation = $row['observation'] ?? '';

            $assessmentDisplay = $assessment;
            if ($observation) {
                if ($assessmentDisplay)
                    $assessmentDisplay .= "\n";
                $assessmentDisplay .= $observation;
            }

            $html .= '<tr>';
            $html .= '<td style="border: 1px solid #000; padding: 1px 5px;">' . htmlspecialchars($area) . '</td>';
            $html .= '<td style="border: 1px solid #000; padding: 1px 5px; text-align: center;">' . htmlspecialchars($willingness) . '</td>';
            $html .= '<td style="border: 1px solid #000; padding: 1px 5px; text-align: center;">' . htmlspecialchars($experience) . '</td>';
            $html .= '<td style="border: 1px solid #000; padding: 1px 5px; max-width: 300px; word-wrap: break-word; white-space: pre-wrap; line-height: 1.4;">' . htmlspecialchars($assessmentDisplay) . '</td>';
            $html .= '</tr>';
        }
        return $html;
    }

    /**
     * Extract and merge skills data with SMART PRIORITY
     *
     * Data Structure: ARRAY of OBJECTS with "area" field
     * Merge Strategy (per client requirement):
     *   - Qualitative (Singapore EA) = PRIMARY
     *   - Numeric (Overseas Training) = FALLBACK
     *   - "Singapore ada yang kosong, ambil dari overseas"
     *
     * @param array $skillsData
     * @param array $numericData (Overseas/Training data)
     * @param array $qualitativeData (Singapore EA data)
     * @param string $source
     * @return array
     */
    protected function extractSkillsBySource(
        array $skillsData,
        array $numericData,
        array $qualitativeData,
        string $source
    ): array {
        // Map display names to internal keys
        $skillMapping = [
            'Care of infants/children' => 'care_of_baby',
            'Care of young children' => 'care_of_young_children',
            'Care of elderly' => 'care_of_elderly',
            'Care of disabled' => 'care_of_disabled',
            'General housework' => 'general_housework',
            'Cooking' => 'cooking',
            'Language abilities (spoken)' => 'language_abilities',
            'Other skills' => 'other_skills',
        ];

        $skillKeys = array_values($skillMapping);

        // Initialize all skills
        $extracted = [];
        foreach ($skillKeys as $key) {
            $extracted[$key] = [
                'willingness' => null,
                'years' => 0,
                'rating' => null,
                'observation' => null
            ];
        }

        // Step 1: Parse NUMERIC data (Overseas/Training) as FALLBACK
        $overseasData = [];
        if (!empty($numericData) && is_array($numericData)) {
            foreach ($numericData as $item) {
                if (!isset($item['area']))
                    continue;

                $key = $skillMapping[$item['area']] ?? null;
                if (!$key)
                    continue;

                // Handle both old format (assessment + observation) and new format (assessment_observation)
                $assessmentObservation = $item['assessment_observation'] ?? null;
                $rating = null;
                $observation = null;

                if ($assessmentObservation) {
                    // New format: assessment_observation contains the merged value
                    $observation = $assessmentObservation;
                } else {
                    // Old format: separate fields
                    $rating = ($item['assessment'] ?? null) !== 'N.A' && ($item['assessment'] ?? null) !== null ? $item['assessment'] : null;
                    $observation = $item['observation'] ?? null;
                }

                $overseasData[$key] = [
                    'willingness' => $item['willingness'] ?? null,
                    'years' => ($item['experience'] === 'Yes') ? 1 : 0,
                    'rating' => $rating,
                    'observation' => $observation
                ];
            }
        }

        // Step 2: Parse QUALITATIVE data (Singapore EA) as PRIMARY
        $singaporeData = [];
        if (!empty($qualitativeData) && is_array($qualitativeData)) {
            foreach ($qualitativeData as $item) {
                if (!isset($item['area']))
                    continue;

                $key = $skillMapping[$item['area']] ?? null;
                if (!$key)
                    continue;

                // Handle both old format (assessment + observation) and new format (assessment_observation)
                $assessmentObservation = $item['assessment_observation'] ?? null;
                $rating = null;
                $observation = null;

                if ($assessmentObservation) {
                    // New format: assessment_observation contains the merged value
                    $observation = $assessmentObservation;
                } else {
                    // Old format: separate fields
                    $rating = ($item['assessment'] ?? null) !== 'N.A' && ($item['assessment'] ?? null) !== null ? $item['assessment'] : null;
                    $observation = $item['observation'] ?? null;
                }

                $singaporeData[$key] = [
                    'willingness' => $item['willingness'] ?? null,
                    'years' => isset($item['experience_years']) && $item['experience_years'] !== null
                        ? (int) $item['experience_years']
                        : (($item['experience'] ?? null) === 'Yes' ? 1 : 0),
                    'rating' => $rating,
                    'observation' => $observation
                ];
            }
        }

        // Step 3: SMART MERGE - Singapore first, fallback to Overseas
        foreach ($skillKeys as $key) {
            // Willingness: Singapore → Overseas → "No"
            $extracted[$key]['willingness'] = $singaporeData[$key]['willingness']
                ?? $overseasData[$key]['willingness']
                ?? 'No';

            // Years: Singapore → Overseas → 0
            $extracted[$key]['years'] = $singaporeData[$key]['years'] ?? 0;
            if ($extracted[$key]['years'] === 0) {
                $extracted[$key]['years'] = $overseasData[$key]['years'] ?? 0;
            }

            // Rating: Singapore → Overseas → null
            $extracted[$key]['rating'] = $singaporeData[$key]['rating']
                ?? $overseasData[$key]['rating']
                ?? null;

            // Observation: Singapore → Overseas → empty
            $extracted[$key]['observation'] = $singaporeData[$key]['observation']
                ?? $overseasData[$key]['observation']
                ?? '';
        }

        return $extracted;
    }

    /**
     * Extract from legacy data format (without source field)
     * Tries to intelligently determine if data is from Singapore or Overseas
     *
     * @param array &$extracted
     * @param array $skillsData
     * @param array $numericData
     * @param array $qualitativeData
    /**
     * Generate employment history rows or complete tables
     * If multiple employment entries exist, generate multiple complete tables
     *
     * @param Maid $maid
     * @param string $type 'overseas' or 'singapore'
     * @return string
     */
    protected function generateEmploymentHistoryRows(Maid $maid, string $type = 'overseas'): string
    {
        $employmentHistory = $maid->employment_history ?? [];

        if (empty($employmentHistory)) {
            return $this->generateSingleEmploymentTable([], $type);
        }

        // For 'overseas' type, show ALL employment history (including Singapore)
        // For 'singapore' type, show only Singapore entries
        $filteredHistory = [];
        foreach ($employmentHistory as $employment) {
            if ($type === 'overseas') {
                // Show all entries for overseas section
                $filteredHistory[] = $employment;
            } elseif ($type === 'singapore') {
                // Only show Singapore entries for singapore section
                if (isset($employment['country']) && strtolower($employment['country']) === 'singapore') {
                    $filteredHistory[] = $employment;
                }
            }
        }

        if (empty($filteredHistory)) {
            return $this->generateSingleEmploymentTable([], $type);
        }

        // Always generate single table with all rows (no multiple tables)
        return $this->generateSingleEmploymentTable($filteredHistory, $type);
    }

    /**
     * Generate single employment history table (original format)
     * Used when there's 0 or 1 employment entry
     *
     * @param array $employments
     * @param string $type
     * @return string
     */
    protected function generateSingleEmploymentTable(array $employments, string $type): string
    {
        $html = '<table class="employment-table" style="width: 100%; border-collapse: collapse; margin-bottom: 15px; border: 1px solid #000;">';
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th style="border: 1px solid #000; padding: 1px 5px; background-color: #57acab; color: white; font-weight: bold;">Country (including FDW\'s home country)</th>';
        $html .= '<th colspan="2" style="border: 1px solid #000; padding: 1px 5px; background-color: #57acab; color: white; font-weight: bold;">Date</th>';
        $html .= '<th style="border: 1px solid #000; padding: 1px 5px; background-color: #57acab; color: white; font-weight: bold;">Employer</th>';
        $html .= '<th style="border: 1px solid #000; padding: 1px 5px; background-color: #57acab; color: white; font-weight: bold;">Work Duties</th>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<th style="border: 1px solid #000; padding: 1px 5px; background-color: #57acab; color: white; font-weight: bold;"></th>';
        $html .= '<th style="border: 1px solid #000; padding: 1px 5px; background-color: #57acab; color: white; font-weight: bold;">From</th>';
        $html .= '<th style="border: 1px solid #000; padding: 1px 5px; background-color: #57acab; color: white; font-weight: bold;">To</th>';
        $html .= '<th style="border: 1px solid #000; padding: 1px 5px; background-color: #57acab; color: white; font-weight: bold;"></th>';
        $html .= '<th style="border: 1px solid #000; padding: 1px 5px; background-color: #57acab; color: white; font-weight: bold;"></th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        if (empty($employments)) {
            $html .= '<tr><td colspan="5" style="border: 1px solid #000; padding: 12px; text-align: center; background-color: #f9f9f9;">No ' . $type . ' employment history</td></tr>';
        } else {
            foreach ($employments as $employment) {
                // Extract data with flexible field names
                $country = $employment['country'] ?? 'N/A';
                $employer = $employment['employer'] ?? 'N/A';

                // Handle both "period" and "date_from"/"date_to" formats
                if (isset($employment['period'])) {
                    // Format: "2014 - 2016" → split into from and to
                    $period = $employment['period'];
                    $dates = explode(' - ', $period);
                    $dateFrom = trim($dates[0] ?? 'N/A');
                    $dateTo = trim($dates[1] ?? 'N/A');
                } else {
                    $dateFrom = $employment['date_from'] ?? 'N/A';
                    $dateTo = $employment['date_to'] ?? 'N/A';
                }

                // Handle both "duties" and "work_duties" formats
                $duties = $employment['work_duties'] ?? $employment['duties'] ?? 'N/A';

                $html .= '<tr>';
                $html .= '<td style="border: 1px solid #000; padding: 1px 5px; vertical-align: top;">' . htmlspecialchars($country) . '</td>';
                $html .= '<td style="border: 1px solid #000; padding: 1px 5px; vertical-align: top;">' . htmlspecialchars($dateFrom) . '</td>';
                $html .= '<td style="border: 1px solid #000; padding: 1px 5px; vertical-align: top;">' . htmlspecialchars($dateTo) . '</td>';
                $html .= '<td style="border: 1px solid #000; padding: 1px 5px; vertical-align: top;">' . htmlspecialchars($employer) . '</td>';
                $html .= '<td style="border: 1px solid #000; padding: 1px 5px; vertical-align: top; word-wrap: break-word; white-space: pre-wrap; line-height: 1.4;">' . htmlspecialchars($duties) . '</td>';
                $html .= '</tr>';
            }
        }

        $html .= '</tbody>';
        $html .= '</table>';

        return $html;
    }

    /**
     * Generate employment feedback table rows (C3: Feedback from previous employers)
     *
     * @param Maid $maid
     * @return string
     */
    protected function generateEmploymentFeedbackRows(Maid $maid): string
    {
        // Use employer_feedback field (new structured data)
        $employerFeedback = $maid->employer_feedback ?? [];

        if (empty($employerFeedback)) {
            return '<tr><td colspan="2" style="border: 1px solid #000; padding: 12px; text-align: center; background-color: #f9f9f9;">No feedback available</td></tr>';
        }

        $html = '';
        foreach ($employerFeedback as $feedback) {
            $employer = $feedback['employer'] ?? 'N/A';
            $feedbackText = $feedback['feedback'] ?? 'N/A';

            $html .= '<tr>';
            $html .= '<td style="border: 1px solid #000; padding: 1px 5px; vertical-align: top;">' . htmlspecialchars($employer) . '</td>';
            $html .= '<td style="border: 1px solid #000; padding: 1px 5px; vertical-align: top; word-wrap: break-word; white-space: pre-wrap; line-height: 1.4;">' . htmlspecialchars($feedbackText) . '</td>';
            $html .= '</tr>';
        }

        return $html;
    }

    /**
     * Convert boolean to Yes/No
     *
     * @param mixed $value
     * @return string
     */
    protected function getYesNo($value): string
    {
        return $value ? 'Yes' : 'No';
    }

    /**
     * Extract medical data from maid attributes relation
     * Converts attributes to template-compatible format
     *
     * @param Maid $maid
     * @return array
     */
    protected function extractMedicalData(Maid $maid): array
    {
        // Initialize with default values
        $medicalData = [
            'MENTAL_ILLNESS' => 'No',
            'TUBERCULOSIS' => 'No',
            'EPILEPSY' => 'No',
            'MALARIA' => 'No',
            'ASTHMA' => 'No',
            'OPERATIONS' => 'No',
            'DIABETES' => 'No',
            'HYPERTENSION' => 'No',
            'HEART_DISEASE' => 'No',
            'OTHER_ILLNESSES' => '',
            'ALLERGIES' => 'No',
            'DIETARY_RESTRICTIONS' => 'No',
            'PHYSICAL_DISABILITIES' => 'No',
            'FOOD_PREFERENCES' => '',
        ];

        // Map illness names to placeholder keys
        $illnessMap = [
            'Mental Illness' => 'MENTAL_ILLNESS',
            'Tuberculosis' => 'TUBERCULOSIS',
            'Epilepsy' => 'EPILEPSY',
            'Malaria' => 'MALARIA',
            'Asthma' => 'ASTHMA',
            'Operations' => 'OPERATIONS',
            'Diabetes' => 'DIABETES',
            'Hypertension' => 'HYPERTENSION',
            'Heart Disease' => 'HEART_DISEASE',
        ];

        // Load attributes if not already loaded
        if (!$maid->relationLoaded('attributes')) {
            $maid->load('attributes');
        }

        // Group attributes by category
        $grouped = $maid->attributes->groupBy('attribute_category');

        // Process ILLNESS attributes
        $illnesses = $grouped->get('ILLNESS', collect());
        $illnessOthers = $grouped->get('ILLNESS_OTHERS', collect());

        foreach ($illnesses as $illness) {
            $attributeName = $illness->attribute_name;
            if (isset($illnessMap[$attributeName])) {
                $medicalData[$illnessMap[$attributeName]] = 'Yes';
            }
        }

        // Collect "Others" illnesses
        $otherIllnessList = [];
        foreach ($illnessOthers as $other) {
            if (!empty($other->attribute_name)) {
                $otherIllnessList[] = $other->attribute_name;
            }
        }
        if (!empty($otherIllnessList)) {
            $medicalData['OTHER_ILLNESSES'] = implode(', ', $otherIllnessList);
        }

        // Process ALLERGY attributes
        $allergies = $grouped->get('ALLERGY', collect());
        if ($allergies->isNotEmpty()) {
            $allergyList = $allergies->pluck('attribute_name')->filter()->toArray();
            $medicalData['ALLERGIES'] = !empty($allergyList) ? implode(', ', $allergyList) : 'No';
        }

        // Process PHYSICAL_DISABILITY attributes
        $disabilities = $grouped->get('PHYSICAL_DISABILITY', collect());
        if ($disabilities->isNotEmpty()) {
            $disabilityList = $disabilities->pluck('attribute_name')->filter()->toArray();
            $medicalData['PHYSICAL_DISABILITIES'] = !empty($disabilityList) ? implode(', ', $disabilityList) : 'No';
        }

        // Process DIET_RESTRICTION attributes
        $dietRestrictions = $grouped->get('DIET_RESTRICTION', collect());
        if ($dietRestrictions->isNotEmpty()) {
            $restrictionList = $dietRestrictions->pluck('attribute_name')->filter()->toArray();
            $medicalData['DIETARY_RESTRICTIONS'] = !empty($restrictionList) ? implode(', ', $restrictionList) : 'No';
        }

        // Process FOOD_PREFERENCE attributes - Format: "Beef - value, Pork - value, Others (optional)"
        // Logic: "No Beef" checked = cannot handle beef (No), unchecked = can handle beef (Yes)
        $foodPrefs = $grouped->get('FOOD_PREFERENCE', collect());
        $foodPrefOthers = $grouped->get('FOOD_PREFERENCE_OTHERS', collect());

        $foodPrefParts = [];

        // Check for Beef - if "No Beef" exists, they CANNOT handle beef
        $hasNoBeef = $foodPrefs->contains('attribute_name', 'No Beef');
        $foodPrefParts[] = 'Beef ' . ($hasNoBeef ? 'No' : 'Yes');

        // Check for Pork - if "No Pork" exists, they CANNOT handle pork
        $hasNoPork = $foodPrefs->contains('attribute_name', 'No Pork');
        $foodPrefParts[] = 'Pork ' . ($hasNoPork ? 'No' : 'Yes');

        // Add Others if exists
        if ($foodPrefOthers->isNotEmpty()) {
            $othersList = $foodPrefOthers->pluck('attribute_name')->filter()->toArray();
            if (!empty($othersList)) {
                $foodPrefParts[] = 'Others: ' . implode(', ', $othersList);
            }
        }

        $medicalData['FOOD_PREFERENCES'] = implode(', ', $foodPrefParts);

        return $medicalData;
    }

    /**
     * Resolve photo path from various possible locations
     * Priority order:
     * 1. storage/app/public/maids/photos/{filename} - manual upload
     * 2. storage/app/public/maid_photos/{filename} - document scan upload
     * 3. public/storage/maids/photos/{filename} - symlinked path
     * 4. public/storage/maid_photos/{filename} - symlinked path
     * 5. Full absolute path - if already absolute
     * 6. public/{path} - relative to public directory
     *
     * @param string $photoUrl
     * @return string|null
     */
    protected function resolvePhotoPath(string $photoUrl): ?string
    {
        // Remove leading slashes and 'storage/' prefix if present
        $cleanPath = ltrim($photoUrl, '/');
        $cleanPath = preg_replace('/^storage\//', '', $cleanPath);

        // Extract just the filename if it's a full path
        $filename = basename($photoUrl);

        // Priority 1: storage/app/public/maids/photos/ (manual upload)
        $path1 = Storage::disk('public')->path('maids/photos/' . $filename);
        if (file_exists($path1)) {
            return $path1;
        }

        // Priority 2: storage/app/public/maid_photos/ (document scan upload)
        $path2 = Storage::disk('public')->path('maid_photos/' . $filename);
        if (file_exists($path2)) {
            return $path2;
        }

        // Priority 3: Check if original path exists in storage
        if (Storage::disk('public')->exists($cleanPath)) {
            return Storage::disk('public')->path($cleanPath);
        }

        // Priority 4: public/storage/maids/photos/ (symlinked - manual)
        $path4 = public_path('storage/maids/photos/' . $filename);
        if (file_exists($path4)) {
            return $path4;
        }

        // Priority 5: public/storage/maid_photos/ (symlinked - scan)
        $path5 = public_path('storage/maid_photos/' . $filename);
        if (file_exists($path5)) {
            return $path5;
        }

        // Priority 6: Try as absolute path
        if (file_exists($photoUrl)) {
            return $photoUrl;
        }

        // Priority 7: Try relative to public directory
        $path7 = public_path($cleanPath);
        if (file_exists($path7)) {
            return $path7;
        }

        // Priority 8: Try with 'storage/' prefix in public
        $path8 = public_path('storage/' . $cleanPath);
        if (file_exists($path8)) {
            return $path8;
        }

        return null;
    }

    /**
     * Convert image file to base64 data URI
     *
     * @param string $imagePath
     * @return string
     */
    protected function imageToBase64(string $imagePath): string
    {
        if (!file_exists($imagePath)) {
            Log::warning('Image file not found', ['path' => $imagePath]);
            return '';
        }

        try {
            $imageData = file_get_contents($imagePath);
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $imagePath);
            finfo_close($finfo);

            $base64 = base64_encode($imageData);
            return "data:{$mimeType};base64,{$base64}";
        } catch (\Exception $e) {
            Log::error('Failed to convert image to base64', [
                'path' => $imagePath,
                'error' => $e->getMessage()
            ]);
            return '';
        }
    }

    /**
     * Generate filename for document
     *
     * @param Maid $maid
     * @param string $extension
     * @return string
     */
    protected function generateFileName(Maid $maid, string $extension): string
    {
        $name = str_replace(' ', '_', $maid->name ?? 'maid');
        $bioCode = 'MBC-' . date('Y') . '-' . str_pad($maid->id, 4, '0', STR_PAD_LEFT);
        $timestamp = date('Ymd_His');

        return "{$bioCode}_{$name}_{$timestamp}.{$extension}";
    }

    /**
     * Sanitize HTML for PDF generation
     * Remove problematic elements that cause Dompdf to hang
     *
     * @param string $html
     * @return string
     */
    protected function sanitizeHtmlForPdf(string $html): string
    {
        // Remove external resources that might cause hanging
        $html = preg_replace('/<link[^>]*href=["\']https?:\/\/[^"\'>]*["\'][^>]*>/i', '', $html);
        $html = preg_replace('/<script[^>]*src=["\']https?:\/\/[^"\'>]*["\'][^>]*>.*?<\/script>/is', '', $html);

        // Remove all script tags
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);

        // Images are already converted to base64 in prepareMaidData
        // No need to process image paths here

        return $html;
    }
}
