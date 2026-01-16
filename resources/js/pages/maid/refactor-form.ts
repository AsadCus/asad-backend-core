/**
 * Public exports for Maid Form module
 * Centralizes all exports for clean imports
 */

// Main component
export { MaidForm } from './form';

// Types
export type { MaidSchema } from './schema';
export type { MaidFormData, SetDataFn, SkillRow } from './types';

// Schema for external validation
export { maidSchema } from './schema';

// Hooks (if needed externally)
export { useAutoSaveDraft } from './hooks/useAutoSaveDraft';
export { useFieldValidation } from './hooks/useFieldValidation';
export { useSectionStatus } from './hooks/useSectionStatus';

// Constants (if needed externally)
export {
    FIELD_LABELS,
    INITIAL_FORM_STATE,
    SECTION_FIELD_MAP,
} from './constants/formConfig';
