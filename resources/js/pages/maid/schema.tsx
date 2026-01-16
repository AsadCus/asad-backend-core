import { z } from 'zod';

export const maidSchema = z
    .object({
        id: z.string().optional(),
        maid_number: z.string().optional(),
        passport_number: z.string().optional(),

        // Required fields
        name: z.string().min(1, 'Name is required'),
        date_of_birth: z.string().min(1, 'Date of birth is required'),
        place_of_birth: z.string().min(1, 'Place of birth is required'),
        country_id: z.string().min(1, 'Nationality is required'),
        nationality: z.string().optional(),
        height: z.string().min(1, 'Height is required'),
        weight: z.string().min(1, 'Weight is required'),
        address: z.string().min(1, 'Address is required'),
        contact_number_home_country: z.string().optional(),
        repatriation_port_airport: z
            .string()
            .min(1, 'Repatriation port is required'),
        religion_id: z.string().min(1, 'Religion is required'),
        religion: z.string().optional(),
        education_level_id: z.string().min(1, 'Education level is required'),
        education_level: z.string().optional(),
        marital_status: z.string().min(1, 'Marital status is required'),
        status: z.string().min(1, 'Status is required'),
        interview_date: z.string().nullable().optional(),
        interview_end_date: z.string().nullable().optional(),
        interview_date_formatted: z.string().nullable().optional(),
        pending_until: z.string().nullable().optional(),
        pending_reason: z.string().nullable().optional(),
        supplier_id: z.string().min(1, 'Supplier is required'),
        supplier: z.string().optional(),
        photo_url: z
            .union([
                z
                    .instanceof(File)
                    .refine(
                        (file) => file.size <= 2 * 1024 * 1024,
                        'Photo must be less than 2MB',
                    )
                    .refine(
                        (file) =>
                            [
                                'image/jpeg',
                                'image/png',
                                'image/jpg',
                                'image/webp',
                            ].includes(file.type),
                        'Only JPG, PNG, or WEBP images are allowed',
                    ),
                z.string(),
            ])
            .optional(),

        // Optional fields
        age: z.string().optional(),
        number_of_siblings: z.string().nullable().optional(),
        number_of_children: z.string().nullable().optional(),
        children_ages: z.string().nullable().optional(),
        rest_days_per_month: z.string().nullable().optional(),
        other_remarks: z.string().nullable().optional(),
        remaining_loan: z.string().nullable().optional(),
        monthly_salary: z.string().nullable().optional(),
        cost_of_maid: z.string().nullable().optional(),
        created_at: z.string().optional(),

        // A2. Medical History/Dietary Restrictions (New structure)
        allergies: z.string().nullable().optional(),
        physical_disabilities: z.string().nullable().optional(),
        dietary_restrictions: z.string().nullable().optional(),

        // Illness fields (checkbox + yes/no select)
        illness_mental_illness: z.boolean().optional(),
        illness_mental_illness_value: z.string().optional(),
        illness_epilepsy: z.boolean().optional(),
        illness_epilepsy_value: z.string().optional(),
        illness_asthma: z.boolean().optional(),
        illness_asthma_value: z.string().optional(),
        illness_diabetes: z.boolean().optional(),
        illness_diabetes_value: z.string().optional(),
        illness_hypertension: z.boolean().optional(),
        illness_hypertension_value: z.string().optional(),
        illness_tuberculosis: z.boolean().optional(),
        illness_tuberculosis_value: z.string().optional(),
        illness_heart_disease: z.boolean().optional(),
        illness_heart_disease_value: z.string().optional(),
        illness_malaria: z.boolean().optional(),
        illness_malaria_value: z.string().optional(),
        illness_operations: z.boolean().optional(),
        illness_operations_value: z.string().optional(),
        illness_others_value: z.string().optional(),

        // Food handling preferences fields (checkbox + yes/no select)
        food_handling_no_beef: z.boolean().optional(),
        food_handling_no_beef_value: z.string().optional(),
        food_handling_no_pork: z.boolean().optional(),
        food_handling_no_pork_value: z.string().optional(),
        food_handling_others_value: z.string().optional(),

        // Skills Assessment
        skills_assessment_singapore: z.array(z.any()).optional().default([]),
        skills_assessment_overseas: z.array(z.any()).optional().default([]),

        // Employment History
        employment_history: z.array(z.any()).optional().default([]),
        singapore_experience: z.boolean().nullable().optional().default(null),
        experience_years: z.coerce.number().nullable().optional(),
        employment_feedback: z.string().nullable().optional(),

        // Employer Feedback (C3: Feedback from previous employers in Singapore)
        employer_feedback: z.array(z.any()).optional().default([]),

        // Section D: AVAILABILITY OF FDW TO BE INTERVIEWED
        interview_not_available: z.boolean().optional().default(false),
        interview_by_phone: z.boolean().optional().default(false),
        interview_by_video: z.boolean().optional().default(false),
        interview_in_person: z.boolean().optional().default(false),

        // Section E: Availability Remarks
        availability_remarks: z.string().nullable().optional(),

        // Grouped attribute summaries
        allergy_attributes: z.string().optional(),
        illness_attributes: z.string().optional(),
        physical_disability_attributes: z.string().optional(),
        diet_restriction_attributes: z.string().optional(),
        food_preference_attributes: z.string().optional(),

        // Attributes array
        attributes: z
            .array(
                z.object({
                    attribute_category: z.string(),
                    attribute_name: z.string(),
                }),
            )
            .default([]),
    })
    .superRefine((data, ctx) => {
        // Validate employment history - only if any field is filled
        if (data.employment_history && data.employment_history.length > 0) {
            data.employment_history.forEach((emp, index) => {
                const hasAnyValue =
                    emp.country ||
                    emp.employer ||
                    emp.period ||
                    emp.duties ||
                    emp.remarks;

                // Only validate required fields if any field is filled
                // Note: Employer is NOT mandatory as per revision notes (Section C)
                if (hasAnyValue) {
                    if (!emp.country || emp.country.trim() === '') {
                        ctx.addIssue({
                            code: z.ZodIssueCode.custom,
                            message: 'Country is required',
                            path: ['employment_history', index, 'country'],
                        });
                    }
                }
            });
        }
    });

export type MaidSchema = z.infer<typeof maidSchema>;

export type AttributeItem = MaidSchema['attributes'][number];

export const maritalStatus = [
    { value: 'Single', label: 'Single' },
    { value: 'Married', label: 'Married' },
    { value: 'Widowed', label: 'Widowed' },
    { value: 'Divorced', label: 'Divorced' },
];

export const status = [
    { value: 'available', label: 'Available' },
    { value: 'interviewing', label: 'Interviewing' },
    { value: 'pending', label: 'Pending' },
    { value: 'assigned', label: 'Assigned' },
];

export const attributeCategories = [
    { value: 'ILLNESS', label: 'Illness' },
    { value: 'ILLNESS_OTHERS', label: 'Illness (Others)' },
    { value: 'ALLERGY', label: 'Allergy' },
    { value: 'PHYSICAL_DISABILITY', label: 'Physical Disability' },
    { value: 'DIET_RESTRICTION', label: 'Diet Restriction' },
    { value: 'FOOD_PREFERENCE', label: 'Food Preference' },
    { value: 'FOOD_PREFERENCE_OTHERS', label: 'Food Preference (Others)' },
];

export const illnessOptions = [
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

export const foodPreferenceOptions = ['No Beef', 'No Pork'];

// Default export for Fast Refresh compatibility
export default maidSchema;
