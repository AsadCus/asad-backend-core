/**
 * Form error handling utilities
 * Handles error grouping, toast notifications, and error navigation
 */

import { toast } from 'sonner';
import {
    ERROR_TOAST_DURATION,
    FIELD_LABELS,
    SECTION_FIELD_MAP,
} from '../constants/formConfig';
import { MaidFormData } from '../types';
import { scrollToFirstError } from './formValidation';

interface SectionError {
    field: string;
    message: string;
}

interface ErrorsBySectionId {
    [sectionId: string]: SectionError[];
}

interface Section {
    id: string;
    title: string;
}

/**
 * Groups errors by their respective sections
 */
export function groupErrorsBySection(
    errors: Record<string, string>,
): ErrorsBySectionId {
    const errorsBySectionId: ErrorsBySectionId = {};

    Object.entries(errors).forEach(([fieldKey, errorMessage]) => {
        // Find which section this field belongs to
        for (const [sectionId, fields] of Object.entries(SECTION_FIELD_MAP)) {
            if (fields.includes(fieldKey)) {
                if (!errorsBySectionId[sectionId]) {
                    errorsBySectionId[sectionId] = [];
                }
                errorsBySectionId[sectionId].push({
                    field: FIELD_LABELS[fieldKey] || fieldKey,
                    message: errorMessage,
                });
                break;
            }
        }
    });

    return errorsBySectionId;
}

/**
 * Formats error list for toast description
 */
function formatErrorList(errors: SectionError[]): string {
    return errors.map((err) => `• ${err.field}: ${err.message}`).join('\n');
}

/**
 * Formats error summary (field names only)
 */
function formatErrorSummary(errors: SectionError[]): string {
    return errors.map((err) => err.field).join(', ');
}

/**
 * Shows error toast for a specific section
 */
function showSectionErrorToast(
    sectionName: string,
    errors: SectionError[],
): void {
    const errorCount = errors.length;
    const errorList = formatErrorList(errors);
    const errorSummary = formatErrorSummary(errors);

    toast.error(
        `${errorCount} Error${errorCount > 1 ? 's' : ''} in ${sectionName}`,
        {
            description:
                errorCount <= 3
                    ? errorList
                    : `Fields with errors: ${errorSummary}`,
            duration: ERROR_TOAST_DURATION,
        },
    );
}

/**
 * Shows error toasts for all sections with errors
 */
export function showErrorToasts(
    errorsBySectionId: ErrorsBySectionId,
    sections: Section[],
): void {
    Object.entries(errorsBySectionId).forEach(([sectionId, sectionErrors]) => {
        const sectionName =
            sections.find((s) => s.id === sectionId)?.title || sectionId;
        showSectionErrorToast(sectionName, sectionErrors);
    });
}

/**
 * Handles form errors: groups by section, shows toasts, and scrolls to first error
 */
export function handleFormErrors(
    errors: Record<string, string>,
    sections: Section[],
    setError: (errors: Partial<Record<keyof MaidFormData, string>>) => void,
): void {
    setError(errors);

    const errorsBySectionId = groupErrorsBySection(errors);
    showErrorToasts(errorsBySectionId, sections);

    const firstErrorKey = Object.keys(errors)[0];
    if (firstErrorKey) {
        scrollToFirstError(firstErrorKey);
    }
}
