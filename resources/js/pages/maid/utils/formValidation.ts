/**
 * Form validation utilities
 * Handles client-side validation and error scrolling
 */

import { toast } from 'sonner';
import { VALIDATION_ERROR_DURATION } from '../constants/formConfig';
import { maidSchema, MaidSchema } from '../schema';
import { MaidFormData } from '../types';
import { normalizeFormData } from './formDataProcessor';

interface ValidationResult {
    success: boolean;
    errors?: Partial<Record<keyof MaidSchema, string>>;
}

/**
 * Validates form data against schema
 */
export function validateForm(data: MaidFormData): ValidationResult {
    const normalizedData = normalizeFormData(data);
    const result = maidSchema.safeParse(normalizedData);

    if (!result.success) {
        const fieldErrors = result.error.issues.reduce(
            (acc, issue) => {
                const firstPath = issue.path[0];
                if (typeof firstPath === 'string') {
                    acc[firstPath as keyof MaidSchema] = issue.message;
                }
                return acc;
            },
            {} as Partial<Record<keyof MaidSchema, string>>,
        );

        return { success: false, errors: fieldErrors };
    }

    return { success: true };
}

/**
 * Shows validation error toast with details
 */
export function showValidationErrorToast(errorCount: number): void {
    toast.error(
        `Validation Failed: ${errorCount} field${errorCount > 1 ? 's' : ''} need${errorCount === 1 ? 's' : ''} attention`,
        {
            description:
                'Please check the highlighted fields and fix the errors before submitting.',
            duration: VALIDATION_ERROR_DURATION,
        },
    );
}

/**
 * Scrolls to the first error field in the form
 */
export function scrollToFirstError(firstErrorPath: unknown): void {
    setTimeout(() => {
        if (firstErrorPath) {
            const fieldId = String(firstErrorPath);
            const element =
                document.getElementById(fieldId) ||
                document.querySelector(`[name="${fieldId}"]`);

            if (element) {
                element.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center',
                });
                (element as HTMLElement).focus();
            }
        }
    }, 100);
}
