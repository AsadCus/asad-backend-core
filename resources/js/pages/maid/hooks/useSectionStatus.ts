import { useCallback, useMemo } from 'react';
import { SectionProgress } from '../../../components/form-progress-header';
import { MaidFormData } from '../types';

interface SectionField {
    [key: string]: (keyof MaidFormData)[];
}

const SECTION_FIELDS: SectionField = {
    // Section A: Profile Of FDW (A1 + A2 + A3)
    profile: [
        // A1. Personal Information
        'name',
        'date_of_birth',
        'age',
        'place_of_birth',
        'height',
        'weight',
        'country_id',
        'address',
        'repatriation_port_airport',
        'contact_number_home_country',
        'religion_id',
        'education_level_id',
        'number_of_siblings',
        'marital_status',
        'number_of_children',
        'children_ages',
        'photo_url',
        // A2. Medical History/Dietary Restrictions
        'allergies',
        'physical_disabilities',
        'dietary_restrictions',
        'illness_mental_illness',
        'illness_epilepsy',
        'illness_asthma',
        'illness_diabetes',
        'illness_hypertension',
        'illness_tuberculosis',
        'illness_heart_disease',
        'illness_malaria',
        'illness_operations',
        'food_handling_no_beef',
        'food_handling_no_pork',
        // A3. Others
        'rest_days_per_month',
        'other_remarks',
    ],
    // Section B: Skills Of FDW
    skills: ['skills_assessment_singapore', 'skills_assessment_overseas'],
    // Section C: Employment History Of The FDW
    employment: [
        'employment_history',
        'singapore_experience',
        'employment_feedback',
        'employer_feedback',
    ],
    // Section D: Availability Of FDW To Be Interviewed
    availability: [
        'interview_not_available',
        'interview_by_phone',
        'interview_by_video',
        'interview_in_person',
    ],
    // Section E: Other Remarks
    'other-remarks': ['availability_remarks'],
    // Status & Financial (moved to bottom)
    status: ['status', 'supplier_id', 'remaining_loan', 'cost_of_maid'],
};

const REQUIRED_FIELDS = new Set<keyof MaidFormData>([
    // Required fields from Section A (Profile)
    'name',
    'date_of_birth',
    'place_of_birth',
    'height',
    'weight',
    'country_id',
    'address',
    'repatriation_port_airport',
    'religion_id',
    'education_level_id',
    'marital_status',
    // Required fields from Status & Financial section
    'status',
    'supplier_id',
]);

interface UseSectionStatusProps {
    data: MaidFormData;
    errors: Partial<Record<keyof MaidFormData, string>>;
}

export function useSectionStatus({ data, errors }: UseSectionStatusProps) {
    // Helper to check if a value is meaningful (not empty, not default)
    const hasValue = useCallback((value: unknown): boolean => {
        // String must have content
        if (typeof value === 'string') {
            return value.trim().length > 0;
        }
        // Number is always meaningful
        if (typeof value === 'number') {
            return true;
        }
        // Boolean false is NOT meaningful (it's default)
        if (typeof value === 'boolean') {
            return value === true;
        }
        // File object is meaningful
        if (value instanceof File) {
            return true;
        }
        // Array must have items with actual data
        if (Array.isArray(value)) {
            if (value.length === 0) return false;
            // Check if array items have any filled fields
            return value.some((item: unknown) => {
                if (typeof item === 'object' && item !== null) {
                    return Object.values(item).some((val: unknown) => {
                        if (typeof val === 'string')
                            return val.trim().length > 0;
                        if (typeof val === 'number') return true;
                        if (typeof val === 'boolean') return val === true;
                        return false;
                    });
                }
                return false;
            });
        }
        return false;
    }, []);

    const getSectionStatus = useCallback(
        (sectionId: string): 'incomplete' | 'complete' | 'error' => {
            const fields = SECTION_FIELDS[sectionId] || [];

            // Check if any field has errors
            const hasError = fields.some((field) => errors[field]);
            if (hasError) return 'error';

            // Check if required fields are filled
            const requiredFields = fields.filter((field) =>
                REQUIRED_FIELDS.has(field),
            );

            if (requiredFields.length > 0) {
                const allRequiredFilled = requiredFields.every((field) => {
                    return hasValue(data[field]);
                });

                return allRequiredFilled ? 'complete' : 'incomplete';
            }

            // For non-required sections, check if any field has meaningful data
            const anyFieldFilled = fields.some((field) => {
                return hasValue(data[field]);
            });

            return anyFieldFilled ? 'complete' : 'incomplete';
        },
        [data, errors, hasValue],
    );

    const sections: SectionProgress[] = useMemo(
        () => [
            {
                id: 'profile',
                title: 'Section A: Profile',
                status: getSectionStatus('profile'),
            },
            {
                id: 'skills',
                title: 'Section B: Skills',
                status: getSectionStatus('skills'),
            },
            {
                id: 'employment',
                title: 'Section C: Employment',
                status: getSectionStatus('employment'),
            },
            {
                id: 'availability',
                title: 'Section D: Availability',
                status: getSectionStatus('availability'),
            },
            {
                id: 'other-remarks',
                title: 'Section E: Remarks',
                status: getSectionStatus('other-remarks'),
            },
            {
                id: 'status',
                title: 'Status & Financial',
                status: getSectionStatus('status'),
            },
        ],
        [getSectionStatus],
    );

    return { sections, getSectionStatus };
}
