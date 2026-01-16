/**
 * Form configuration constants
 * Centralized configuration for form sections, field mappings, and validation
 */

import { MaidFormData } from '../types';

/**
 * Initial form state with all default values
 */
export const INITIAL_FORM_STATE: MaidFormData = {
    name: '',
    date_of_birth: '',
    place_of_birth: '',
    height: '',
    weight: '',
    country_id: '',
    address: '',
    repatriation_port_airport: '',
    contact_number_home_country: '',
    religion_id: '',
    education_level_id: '',
    marital_status: '',
    number_of_siblings: '',
    number_of_children: '',
    children_ages: '',
    photo_url: '',
    rest_days_per_month: '',
    other_remarks: '',
    status: '',
    supplier_id: '',
    remaining_loan: '',
    monthly_salary: '',
    cost_of_maid: '',
    
    // Medical fields
    allergies: '',
    physical_disabilities: '',
    dietary_restrictions: '',
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
    
    attributes: [],
    skills_assessment_singapore: [],
    skills_assessment_overseas: [],
    employment_history: [],
    singapore_experience: null,
    employment_feedback: '',
    employer_feedback: [],
    interview_not_available: false,
    interview_by_phone: false,
    interview_by_video: false,
    interview_in_person: false,
    availability_remarks: '',
    eval_declaration_no_eval: false,
    eval_sg_interview: false,
    eval_sg_phone: false,
    eval_sg_video: false,
    eval_sg_in_person: false,
    eval_sg_in_person_observed: false,
    eval_overseas_interview: false,
    eval_overseas_name: '',
    eval_overseas_cert: '',
    eval_overseas_phone: false,
    eval_overseas_video: false,
    eval_overseas_in_person: false,
    eval_overseas_in_person_observed: false,
    _method: 'POST',
};

/**
 * Field labels for better error messages
 */
export const FIELD_LABELS: Record<string, string> = {
    name: 'Name',
    date_of_birth: 'Date of Birth',
    place_of_birth: 'Place of Birth',
    height: 'Height',
    weight: 'Weight',
    country_id: 'Nationality',
    address: 'Address',
    repatriation_port_airport: 'Repatriation Port/Airport',
    contact_number_home_country: 'Contact Number',
    religion_id: 'Religion',
    education_level_id: 'Education Level',
    marital_status: 'Marital Status',
    photo_url: 'Photo',
    number_of_siblings: 'Number of Siblings',
    number_of_children: 'Number of Children',
    children_ages: 'Children Ages',
    attributes: 'Medical Attributes',
    rest_days_per_month: 'Rest Days per Month',
    other_remarks: 'Other Remarks',
    status: 'Status',
    supplier_id: 'Supplier',
    remaining_loan: 'Remaining Loan',
    cost_of_maid: 'Cost of Maid',
    skills_assessment_singapore: 'Skills Assessment (Singapore)',
    skills_assessment_overseas: 'Skills Assessment (Overseas)',
    employment_history: 'Employment History',
    singapore_experience: 'Singapore Experience',
    employment_feedback: 'Employment Feedback',
    employer_feedback: 'Employer Feedback',
    availability_remarks: 'Availability Remarks',
};

/**
 * Section to fields mapping for error handling
 */
export const SECTION_FIELD_MAP: Record<string, string[]> = {
    profile: [
        'name',
        'date_of_birth',
        'place_of_birth',
        'height',
        'weight',
        'country_id',
        'address',
        'repatriation_port_airport',
        'contact_number_home_country',
        'religion_id',
        'education_level_id',
        'marital_status',
        'photo_url',
    ],
    family: ['number_of_siblings', 'number_of_children', 'children_ages'],
    medical: ['attributes'],
    rest: ['rest_days_per_month', 'other_remarks'],
    status: ['status', 'supplier_id', 'remaining_loan', 'cost_of_maid'],
    skills: [
        'skills_assessment_singapore',
        'skills_assessment_overseas',
    ],
    employment: [
        'employment_history',
        'singapore_experience',
        'employment_feedback',
        'employer_feedback',
    ],
    availability: [
        'availability_remarks',
        'interview_not_available',
        'interview_by_phone',
        'interview_by_video',
        'interview_in_person',
        'eval_declaration_no_eval',
        'eval_sg_interview',
        'eval_sg_phone',
        'eval_sg_video',
        'eval_sg_in_person',
        'eval_sg_in_person_observed',
        'eval_overseas_interview',
        'eval_overseas_name',
        'eval_overseas_cert',
        'eval_overseas_phone',
        'eval_overseas_video',
        'eval_overseas_in_person',
        'eval_overseas_in_person_observed',
    ],
};

/**
 * Scroll offset for section navigation (accounts for sticky header)
 */
export const SCROLL_OFFSET = 200;

/**
 * Delay for scrolling after accordion opens
 */
export const SCROLL_DELAY = 100;

/**
 * Toast duration for error messages
 */
export const ERROR_TOAST_DURATION = 8000;

/**
 * Toast duration for validation errors
 */
export const VALIDATION_ERROR_DURATION = 6000;
