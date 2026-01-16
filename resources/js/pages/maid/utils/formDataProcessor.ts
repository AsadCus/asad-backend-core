/**
 * Form data processing utilities
 * Handles form data transformations and merging
 */

import { INITIAL_FORM_STATE } from '../constants/formConfig';
import { AttributeItem, illnessOptions, MaidSchema } from '../schema';
import { MaidFormData } from '../types';

/**
 * Parse attributes array to individual illness and food handling fields
 */
function parseAttributesToFields(
    attributes: AttributeItem[] | undefined,
): Partial<MaidFormData> {
    const fields: Partial<MaidFormData> = {
        illness_mental_illness_value: 'No',
        illness_epilepsy_value: 'No',
        illness_asthma_value: 'No',
        illness_diabetes_value: 'No',
        illness_hypertension_value: 'No',
        illness_tuberculosis_value: 'No',
        illness_heart_disease_value: 'No',
        illness_malaria_value: 'No',
        illness_operations_value: 'No',
        illness_others_value: '',

        food_handling_no_beef_value: 'No',
        food_handling_no_pork_value: 'No',
        food_handling_others_value: '',
    };

    if (!Array.isArray(attributes)) return fields;

    attributes.forEach((attr) => {
        const category = attr.attribute_category;
        const name = attr.attribute_name;

        // Illness attributes
        if (category === 'ILLNESS') {
            const fieldKey =
                `illness_${name.toLowerCase().replace(/\s+/g, '_')}_value` as keyof MaidFormData;
            (fields[fieldKey] as string | undefined) = 'Yes';
        }

        // Illness others
        else if (category === 'ILLNESS_OTHERS') {
            fields.illness_others_value = name;
        }

        // Food preferences
        else if (category === 'FOOD_PREFERENCE') {
            const nameLower = name.toLowerCase();

            // Combined format
            if (nameLower.includes(',') || nameLower.includes('others:')) {
                if (/no\s*beef/i.test(name))
                    fields.food_handling_no_beef_value = 'Yes';
                if (/no\s*pork/i.test(name))
                    fields.food_handling_no_pork_value = 'Yes';

                const othersMatch = name.match(/others\s*:\s*(.+?)(?:,|$)/i);
                if (
                    othersMatch &&
                    othersMatch[1].trim() &&
                    othersMatch[1].trim() !== '_________________________'
                ) {
                    fields.food_handling_others_value = othersMatch[1].trim();
                }
            } else {
                // Individual format
                if (nameLower === 'no beef')
                    fields.food_handling_no_beef_value = 'Yes';
                if (nameLower === 'no pork')
                    fields.food_handling_no_pork_value = 'Yes';
            }
        }

        // Food preference others
        else if (category === 'FOOD_PREFERENCE_OTHERS') {
            if (name && name.trim() !== '_________________________') {
                fields.food_handling_others_value = name;
            }
        }

        // Allergy
        else if (category === 'ALLERGY') {
            fields.allergies = name;
        }

        // Physical disability
        else if (category === 'PHYSICAL_DISABILITY') {
            fields.physical_disabilities = name;
        }

        // Dietary restrictions
        else if (category === 'DIET_RESTRICTION') {
            fields.dietary_restrictions = name;
        }
    });

    return fields;
}

/**
 * Remove trailing zeros from decimal string
 */
function cleanDecimal(value: unknown): string {
    if (value === null || value === undefined) return '';
    const num = parseFloat(String(value));
    return Number.isNaN(num) ? '' : String(num);
}

/**
 * Merges initial data with default form state
 */
export function mergeInitialData(
    initialData?: MaidSchema,
    isEdit = false,
): MaidFormData {
    const parsedFields = initialData?.attributes
        ? parseAttributesToFields(initialData.attributes)
        : {};

    return {
        ...INITIAL_FORM_STATE,
        ...(initialData as Partial<MaidFormData>),

        ...parsedFields,

        height: cleanDecimal(initialData?.height),
        weight: cleanDecimal(initialData?.weight),
        remaining_loan: cleanDecimal(initialData?.remaining_loan),
        monthly_salary: cleanDecimal(initialData?.monthly_salary),
        cost_of_maid: cleanDecimal(initialData?.cost_of_maid),

        attributes: [],

        skills_assessment_singapore: Array.isArray(
            initialData?.skills_assessment_singapore,
        )
            ? initialData.skills_assessment_singapore.map((row) => ({
                  ...row,
                  area: row.area ?? '',
                  willingness: row.willingness ?? '',
                  experience: row.experience ?? '',
                  assessment: row.assessment ?? '',
                  observation: row.observation ?? '',
              }))
            : INITIAL_FORM_STATE.skills_assessment_singapore,

        skills_assessment_overseas: Array.isArray(
            initialData?.skills_assessment_overseas,
        )
            ? initialData.skills_assessment_overseas.map((row) => ({
                  ...row,
                  area: row.area ?? '',
                  willingness: row.willingness ?? '',
                  experience: row.experience ?? '',
                  assessment: row.assessment ?? '',
                  observation: row.observation ?? '',
              }))
            : INITIAL_FORM_STATE.skills_assessment_overseas,

        employment_history: Array.isArray(initialData?.employment_history)
            ? initialData.employment_history
            : INITIAL_FORM_STATE.employment_history,

        // Preserve supplier_id from initialData if provided (for prefilled supplier from context)
        supplier_id: initialData?.supplier_id ?? INITIAL_FORM_STATE.supplier_id,

        _method: isEdit ? 'PUT' : 'POST',
    };
}

/**
 * Calculate total experience years from employment history
 */
function calculateExperienceYears(employmentHistory: unknown[]): number {
    if (!Array.isArray(employmentHistory)) return 0;

    return employmentHistory.reduce((total: number, emp: unknown) => {
        if (typeof emp !== 'object' || emp === null || !('period' in emp))
            return total;
        if (typeof emp.period !== 'string') return total;

        const period = emp.period.trim();
        
        // Format: "2015-2018" or "2015 - 2018"
        const yearRangeMatch = period.match(/(\d{4})\s*-\s*(\d{4})/);
        if (yearRangeMatch) {
            const startYear = parseInt(yearRangeMatch[1]);
            const endYear = parseInt(yearRangeMatch[2]);
            return total + (endYear - startYear);
        }
        
        // Format: "2 years", "6 months"
        const periodLower = period.toLowerCase();
        const yearMatch = periodLower.match(/(\d+(?:\.\d+)?)\s*(?:year|yr|y)/i);
        const monthMatch = periodLower.match(/(\d+(?:\.\d+)?)\s*(?:month|mon|m)/i);

        let years = 0;
        if (yearMatch) years += parseFloat(yearMatch[1]);
        if (monthMatch) years += parseFloat(monthMatch[1]) / 12;

        return total + years;
    }, 0);
}

/**
 * Normalizes form data for zod validation
 */
export function normalizeFormData(data: MaidFormData): Record<string, unknown> {
    const normalized = Object.fromEntries(
        Object.entries(data).map(([key, value]) => [
            key,
            typeof value === 'number' ? String(value) : value,
        ]),
    );

    // Auto-calculate experience_years from employment_history
    if (data.employment_history && data.employment_history.length > 0) {
        const totalYears = calculateExperienceYears(data.employment_history);
        normalized.experience_years = totalYears > 0 ? totalYears : null;
    }

    return normalized;
}

/**
 * Convert individual illness + food handling fields back to attributes[]
 */
export function convertFieldsToAttributes(data: MaidFormData): AttributeItem[] {
    const attributes: AttributeItem[] = [];

    // Illness
    illnessOptions.forEach((illness) => {
        const fieldKey =
            `illness_${illness.toLowerCase().replace(/\s+/g, '_')}_value` as keyof MaidFormData;

        if (data[fieldKey] === 'Yes') {
            attributes.push({
                attribute_category: 'ILLNESS',
                attribute_name: illness,
            });
        }
    });

    // Illness others
    if (data.illness_others_value && data.illness_others_value.trim()) {
        attributes.push({
            attribute_category: 'ILLNESS_OTHERS',
            attribute_name: data.illness_others_value.trim(),
        });
    }

    // Allergies
    if (data.allergies && data.allergies.trim()) {
        attributes.push({
            attribute_category: 'ALLERGY',
            attribute_name: data.allergies.trim(),
        });
    }

    // Physical disabilities
    if (data.physical_disabilities && data.physical_disabilities.trim()) {
        attributes.push({
            attribute_category: 'PHYSICAL_DISABILITY',
            attribute_name: data.physical_disabilities.trim(),
        });
    }

    // Dietary restrictions
    if (data.dietary_restrictions && data.dietary_restrictions.trim()) {
        attributes.push({
            attribute_category: 'DIET_RESTRICTION',
            attribute_name: data.dietary_restrictions.trim(),
        });
    }

    // Food preferences
    if (data.food_handling_no_beef_value === 'Yes') {
        attributes.push({
            attribute_category: 'FOOD_PREFERENCE',
            attribute_name: 'No Beef',
        });
    }

    if (data.food_handling_no_pork_value === 'Yes') {
        attributes.push({
            attribute_category: 'FOOD_PREFERENCE',
            attribute_name: 'No Pork',
        });
    }

    // Food preference others
    if (
        data.food_handling_others_value &&
        data.food_handling_others_value.trim()
    ) {
        attributes.push({
            attribute_category: 'FOOD_PREFERENCE_OTHERS',
            attribute_name: data.food_handling_others_value.trim(),
        });
    }

    return attributes;
}

/**
 * Handles photo URL logic for form submission
 */
export function handlePhotoUrl(
    data: MaidFormData,
    initialData?: MaidSchema,
    isEdit = false,
    isCreate = false,
): {
    shouldRemove: boolean;
    originalUrl: string | File | undefined;
} {
    const originalPhotoUrl = data.photo_url;

    if (typeof data.photo_url === 'string') {
        if (isEdit && initialData?.photo_url === data.photo_url) {
            return { shouldRemove: true, originalUrl: originalPhotoUrl };
        }

        if (isCreate && data.photo_url.startsWith('/storage/')) {
            return { shouldRemove: false, originalUrl: originalPhotoUrl };
        }
    }

    return { shouldRemove: false, originalUrl: originalPhotoUrl };
}
