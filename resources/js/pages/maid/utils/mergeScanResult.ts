import { MaidFormData, SkillRow } from '../types';

type RawScanResult = {
    data?: Record<string, unknown>;
    metadata?: {
        original_name?: string;
        file_size?: number;
    };
    photos?: {
        uploaded?: Array<{ url: string }>;
        total_found?: number;
    };
    success?: boolean;
};

function hasValue(value: unknown): boolean {
    if (value === null || value === undefined) return false;
    if (typeof value === 'string') return value.trim() !== '';
    if (typeof value === 'number') return true;
    if (typeof value === 'boolean') return true;
    if (Array.isArray(value)) return value.length > 0;
    return false;
}

export function mergeScanResultIntoFormData(
    current: MaidFormData,
    result: RawScanResult,
): MaidFormData {
    if (!result?.data) {
        return current;
    }

    const extracted = result.data;

    const skillsSingapore =
        (extracted.skills_assessment_singapore as SkillRow[]) ??
        current.skills_assessment_singapore ??
        [];
    const skillsOverseas =
        (extracted.skills_assessment_overseas as SkillRow[]) ??
        current.skills_assessment_overseas ??
        [];

    const photoData = result.photos;
    let photoUrl = current.photo_url ?? undefined;

    if (photoData?.uploaded && photoData.uploaded.length > 0) {
        photoUrl = photoData.uploaded[0].url ?? photoUrl;
    } else if (hasValue(extracted.photo_url)) {
        photoUrl = extracted.photo_url as string;
    }

    // Derive checkbox fields (illnesses, food handling) from extracted data
    const derivedCheckboxes = deriveCheckboxFieldsFromExtraction(extracted);

    return {
        ...current,
        name: hasValue(extracted.name)
            ? (extracted.name as string)
            : current.name,
        date_of_birth: hasValue(extracted.date_of_birth)
            ? (extracted.date_of_birth as string)
            : current.date_of_birth,
        place_of_birth: hasValue(extracted.place_of_birth)
            ? (extracted.place_of_birth as string)
            : current.place_of_birth,
        height: hasValue(extracted.height)
            ? (extracted.height as string)
            : current.height,
        weight: hasValue(extracted.weight)
            ? (extracted.weight as string)
            : current.weight,
        country_id: hasValue(extracted.country_id)
            ? (extracted.country_id as string)
            : current.country_id,
        address: hasValue(extracted.address)
            ? (extracted.address as string)
            : current.address,
        repatriation_port_airport: hasValue(extracted.repatriation_port_airport)
            ? (extracted.repatriation_port_airport as string)
            : current.repatriation_port_airport,
        contact_number_home_country: hasValue(
            extracted.contact_number_home_country,
        )
            ? (extracted.contact_number_home_country as string)
            : current.contact_number_home_country,
        religion_id: hasValue(extracted.religion_id)
            ? (extracted.religion_id as string)
            : current.religion_id,
        education_level_id: hasValue(extracted.education_level_id)
            ? (extracted.education_level_id as string)
            : current.education_level_id,
        marital_status: hasValue(extracted.marital_status)
            ? (extracted.marital_status as string)
            : current.marital_status,
        number_of_siblings: hasValue(extracted.number_of_siblings)
            ? (extracted.number_of_siblings as string)
            : current.number_of_siblings,
        number_of_children: hasValue(extracted.number_of_children)
            ? (extracted.number_of_children as string)
            : current.number_of_children,
        children_ages: hasValue(extracted.children_ages)
            ? (extracted.children_ages as string)
            : current.children_ages,
        photo_url: photoUrl,
        rest_days_per_month: hasValue(extracted.rest_days_per_month)
            ? (extracted.rest_days_per_month as string)
            : current.rest_days_per_month,
        other_remarks: hasValue(extracted.other_remarks)
            ? (extracted.other_remarks as string)
            : current.other_remarks,
        skills_assessment_singapore: skillsSingapore,
        skills_assessment_overseas: skillsOverseas,
        employment_history: cleanupEmptyEmploymentHistory(
            hasValue(extracted.employment_history)
                ? (extracted.employment_history as MaidFormData['employment_history'])
                : current.employment_history,
        ),
        singapore_experience:
            typeof extracted.singapore_experience === 'boolean'
                ? extracted.singapore_experience
                : current.singapore_experience,
        employment_feedback: hasValue(extracted.employment_feedback)
            ? (extracted.employment_feedback as string)
            : current.employment_feedback,
        availability_remarks: hasValue(extracted.interview_availability)
            ? (extracted.interview_availability as string)
            : hasValue(extracted.availability_remarks)
              ? (extracted.availability_remarks as string)
              : current.availability_remarks,
        attributes: cleanupEmptyAttributes(
            convertMedicalHistoryToAttributes(
                extracted,
                hasValue(extracted.attributes)
                    ? (extracted.attributes as MaidFormData['attributes'])
                    : current.attributes,
            ),
        ),

        // Medical fields
        allergies: hasValue(extracted.allergies)
            ? (extracted.allergies as string)
            : current.allergies,
        physical_disabilities: hasValue(extracted.physical_disabilities)
            ? (extracted.physical_disabilities as string)
            : current.physical_disabilities,
        dietary_restrictions: hasValue(extracted.dietary_restrictions)
            ? (extracted.dietary_restrictions as string)
            : current.dietary_restrictions,

        // Evaluation fields - Singapore
        eval_declaration_no_eval:
            typeof extracted.eval_declaration_no_eval === 'boolean'
                ? extracted.eval_declaration_no_eval
                : current.eval_declaration_no_eval,
        eval_sg_interview:
            typeof extracted.eval_sg_interview === 'boolean'
                ? extracted.eval_sg_interview
                : current.eval_sg_interview,
        eval_sg_phone:
            typeof extracted.eval_sg_phone === 'boolean'
                ? extracted.eval_sg_phone
                : current.eval_sg_phone,
        eval_sg_video:
            typeof extracted.eval_sg_video === 'boolean'
                ? extracted.eval_sg_video
                : current.eval_sg_video,
        eval_sg_in_person:
            typeof extracted.eval_sg_in_person === 'boolean'
                ? extracted.eval_sg_in_person
                : current.eval_sg_in_person,
        eval_sg_in_person_observed:
            typeof extracted.eval_sg_in_person_observed === 'boolean'
                ? extracted.eval_sg_in_person_observed
                : current.eval_sg_in_person_observed,

        // Evaluation fields - Overseas
        eval_overseas_interview:
            typeof extracted.eval_overseas_interview === 'boolean'
                ? extracted.eval_overseas_interview
                : current.eval_overseas_interview,
        eval_overseas_name: hasValue(extracted.eval_overseas_name)
            ? (extracted.eval_overseas_name as string)
            : current.eval_overseas_name,
        eval_overseas_cert: hasValue(extracted.eval_overseas_cert)
            ? (extracted.eval_overseas_cert as string)
            : current.eval_overseas_cert,
        eval_overseas_phone:
            typeof extracted.eval_overseas_phone === 'boolean'
                ? extracted.eval_overseas_phone
                : current.eval_overseas_phone,
        eval_overseas_video:
            typeof extracted.eval_overseas_video === 'boolean'
                ? extracted.eval_overseas_video
                : current.eval_overseas_video,
        eval_overseas_in_person:
            typeof extracted.eval_overseas_in_person === 'boolean'
                ? extracted.eval_overseas_in_person
                : current.eval_overseas_in_person,
        eval_overseas_in_person_observed:
            typeof extracted.eval_overseas_in_person_observed === 'boolean'
                ? extracted.eval_overseas_in_person_observed
                : current.eval_overseas_in_person_observed,

        // Interview availability fields
        interview_not_available:
            typeof extracted.interview_not_available === 'boolean'
                ? extracted.interview_not_available
                : current.interview_not_available,
        interview_by_phone:
            typeof extracted.interview_by_phone === 'boolean'
                ? extracted.interview_by_phone
                : current.interview_by_phone,
        interview_by_video:
            typeof extracted.interview_by_video === 'boolean'
                ? extracted.interview_by_video
                : current.interview_by_video,
        interview_in_person:
            typeof extracted.interview_in_person === 'boolean'
                ? extracted.interview_in_person
                : current.interview_in_person,

        status: hasValue(extracted.status)
            ? (extracted.status as string)
            : current.status,

        // Apply derived illness and food handling checkbox fields (booleans + values)
        ...derivedCheckboxes,
    };
}

function convertMedicalHistoryToAttributes(
    extracted: Record<string, unknown>,
    currentAttributes: MaidFormData['attributes'],
): MaidFormData['attributes'] {
    // Start with extracted attributes (ILLNESS from backend)
    let newAttributes: MaidFormData['attributes'] = [];

    // If backend provided attributes (e.g., ILLNESS), use them
    if (hasValue(extracted.attributes) && Array.isArray(extracted.attributes)) {
        newAttributes = [
            ...(extracted.attributes as MaidFormData['attributes']),
        ];
    } else {
        // Otherwise, keep current attributes
        newAttributes = [...(currentAttributes || [])];
    }

    // Convert allergies to attributes (only if not "NO")
    if (hasValue(extracted.allergies)) {
        const allergiesText = (extracted.allergies as string).trim();
        if (allergiesText && allergiesText.toUpperCase() !== 'NO') {
            newAttributes.push({
                attribute_category: 'ALLERGY',
                attribute_name: allergiesText,
            });
        }
    }

    // Convert physical_disabilities to attributes (only if not "NO")
    if (hasValue(extracted.physical_disabilities)) {
        const disabilitiesText = (
            extracted.physical_disabilities as string
        ).trim();
        if (disabilitiesText && disabilitiesText.toUpperCase() !== 'NO') {
            newAttributes.push({
                attribute_category: 'PHYSICAL_DISABILITY',
                attribute_name: disabilitiesText,
            });
        }
    }

    // Convert dietary_restrictions to attributes (only if not "NO")
    if (hasValue(extracted.dietary_restrictions)) {
        const dietText = (extracted.dietary_restrictions as string).trim();
        if (dietText && dietText.toUpperCase() !== 'NO') {
            newAttributes.push({
                attribute_category: 'DIET_RESTRICTION',
                attribute_name: dietText,
            });
        }
    }

    // Convert food_preferences to attributes - parse checkbox format
    if (hasValue(extracted.food_preferences)) {
        const foodText = (extracted.food_preferences as string).trim();
        if (foodText && foodText.toUpperCase() !== 'NO') {
            // const foodLower = foodText.toLowerCase();

            // Parse "No Beef" checkbox
            if (/no\s*beef/i.test(foodText)) {
                newAttributes.push({
                    attribute_category: 'FOOD_PREFERENCE',
                    attribute_name: 'No Beef',
                });
            }

            // Parse "No Pork" checkbox
            if (/no\s*pork/i.test(foodText)) {
                newAttributes.push({
                    attribute_category: 'FOOD_PREFERENCE',
                    attribute_name: 'No Pork',
                });
            }

            // Parse "Others:" text field
            const othersMatch = foodText.match(/others\s*:\s*(.+?)(?:,|$)/i);
            if (
                othersMatch &&
                othersMatch[1].trim() &&
                othersMatch[1].trim() !== '_________________________'
            ) {
                newAttributes.push({
                    attribute_category: 'FOOD_PREFERENCE_OTHERS',
                    attribute_name: othersMatch[1].trim(),
                });
            }
        }
    }

    return newAttributes;
}

function cleanupEmptyAttributes(
    attributes: MaidFormData['attributes'],
): MaidFormData['attributes'] {
    if (!attributes || !Array.isArray(attributes)) {
        return [];
    }

    return attributes.filter((attr) => {
        // Remove if both category and name are empty
        if (!attr.attribute_category && !attr.attribute_name) {
            return false;
        }

        // Remove if category is empty (even if name exists)
        if (!attr.attribute_category || attr.attribute_category.trim() === '') {
            return false;
        }

        // Remove if name is empty (even if category exists)
        if (!attr.attribute_name || attr.attribute_name.trim() === '') {
            return false;
        }

        return true;
    });
}

function cleanupEmptyEmploymentHistory(
    history: MaidFormData['employment_history'],
): MaidFormData['employment_history'] {
    if (!history || !Array.isArray(history)) {
        return [];
    }

    return history.filter((emp) => {
        // Check if any meaningful field has value
        const hasCountry = emp.country && emp.country.trim() !== '';
        const hasEmployer = emp.employer && emp.employer.trim() !== '';
        const hasDuties = emp.duties && emp.duties.trim() !== '';
        const hasRemarks = emp.remarks && emp.remarks.trim() !== '';

        // Keep if has at least ONE of: country, employer, duties, or remarks
        // Lebih fleksibel untuk ekstraksi dokumen yang tidak sempurna
        return hasCountry || hasEmployer || hasDuties || hasRemarks;
    });
}

export function formatParsedFileMeta(metadata?: RawScanResult['metadata']) {
    if (!metadata?.original_name || !metadata?.file_size) {
        return null;
    }

    return `${metadata.original_name} (${(metadata.file_size / 1024).toFixed(2)} KB)`;
}

export type { RawScanResult };

// Helpers
function normalizeIllnessName(name: string): string {
    return name.trim().toLowerCase().replace(/\s+/g, ' ');
}

function illnessKeyFromLabel(label: string): keyof MaidFormData {
    const base = `illness_${label.trim().toLowerCase().replace(/\s+/g, '_')}`;
    return base as keyof MaidFormData;
}

function foodKeyFromLabel(label: string): keyof MaidFormData {
    const base = `food_handling_${label.trim().toLowerCase().replace(/\s+/g, '_')}`;
    return base as keyof MaidFormData;
}

function deriveCheckboxFieldsFromExtraction(
    extracted: Record<string, unknown>,
): Partial<MaidFormData> {
    const updates: Partial<MaidFormData> = {};

    // 1) Illnesses from attributes array (category: ILLNESS)
    const attrs = (extracted.attributes as MaidFormData['attributes']) || [];
    const illnessAttrNames = new Set(
        attrs
            .filter(
                (a) =>
                    a && a.attribute_category === 'ILLNESS' && a.attribute_name,
            )
            .map((a) => normalizeIllnessName(a.attribute_name)),
    );

    // Known illness option labels in the UI
    const knownIllnesses = [
        'Mental Illness',
        'Epilepsy',
        'Asthma',
        'Diabetes',
        'Hypertension',
        'Tuberculosis',
        'Heart Disease',
        'Malaria',
        'Operations',
    ];

    for (const label of knownIllnesses) {
        const normalized = normalizeIllnessName(label);
        const hasIt = illnessAttrNames.has(normalized);
        const boolKey = illnessKeyFromLabel(label);
        const valKey = `${String(boolKey)}_value` as keyof MaidFormData;

        if (hasIt) {
            // Set checkbox true and default value 'Yes' if found in document
            (updates as Record<string, unknown>)[String(boolKey)] = true;
            (updates as Record<string, unknown>)[String(valKey)] = 'Yes';
        } else {
            // If not found or empty, set value to 'No'
            (updates as Record<string, unknown>)[String(valKey)] = 'No';
        }
    }

    // 2) Food handling preferences from food_preferences string
    const prefs = (extracted.food_preferences as string) || '';
    const prefsLower = prefs.toLowerCase();

    const foodMap: Array<{ match: RegExp; label: string }> = [
        { match: /no\s*pork/, label: 'No Pork' },
        { match: /no\s*beef/, label: 'No Beef' },
    ];

    for (const item of foodMap) {
        const boolKey = foodKeyFromLabel(item.label);
        const valKey = `${String(boolKey)}_value` as keyof MaidFormData;
        
        if (item.match.test(prefsLower)) {
            // If found in document, set to 'Yes'
            (updates as Record<string, unknown>)[String(boolKey)] = true;
            (updates as Record<string, unknown>)[String(valKey)] = 'Yes';
        } else {
            // If not found or empty, set value to 'No'
            (updates as Record<string, unknown>)[String(valKey)] = 'No';
        }
    }

    // Parse "Others:" text field
    const othersMatch = prefs.match(/others\s*:\s*(.+?)(?:,|$)/i);
    if (
        othersMatch &&
        othersMatch[1].trim() &&
        othersMatch[1].trim() !== '_________________________'
    ) {
        (updates as Record<string, unknown>)['food_handling_others_value'] =
            othersMatch[1].trim();
    }

    return updates;
}
