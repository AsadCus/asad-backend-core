/**
 * Custom hook for form submission logic
 * Separates submission concerns from main form component
 */

import { FormEvent, useCallback } from 'react';
import { toast } from 'sonner';
import { MaidSchema } from '../schema';
import { MaidFormData, SetDataFn } from '../types';
import { handleFormErrors } from '../utils/errorHandler';
import { handlePhotoUrl } from '../utils/formDataProcessor';
import {
    scrollToFirstError,
    showValidationErrorToast,
    validateForm,
} from '../utils/formValidation';

interface UseFormSubmissionProps {
    data: MaidFormData;
    initialData?: MaidSchema;
    isCreate: boolean;
    isEdit: boolean;
    setData: SetDataFn;
    post: (
        url: string,
        options?: {
            forceFormData?: boolean;
            onSuccess?: () => void;
            onError?: (errors: Record<string, string>) => void;
        },
    ) => void;
    setError: (errors: Partial<Record<keyof MaidFormData, string>>) => void;
    clearDraft: () => void;
    sections: Array<{ id: string; title: string }>;
}

interface UseFormSubmissionReturn {
    handleSubmit: (event: FormEvent) => void;
}

export function useFormSubmission({
    data,
    initialData,
    isCreate,
    isEdit,
    setData,
    post,
    setError,
    clearDraft,
    sections,
}: UseFormSubmissionProps): UseFormSubmissionReturn {
    const handleSubmit = useCallback(
        (event: FormEvent) => {
            event.preventDefault();

            // Client-side validation
            const validationResult = validateForm(data);
            if (!validationResult.success && validationResult.errors) {
                setError(validationResult.errors);
                const errorCount = Object.keys(validationResult.errors).length;
                showValidationErrorToast(errorCount);
                scrollToFirstError(Object.keys(validationResult.errors)[0]);
                return;
            }

            const url = '/maid';

            // Handle photo URL logic
            const { shouldRemove, originalUrl } = handlePhotoUrl(
                data,
                initialData,
                isEdit,
                isCreate,
            );

            if (shouldRemove) {
                setData('photo_url', '');
            }

            const onSuccess = () => {
                if (isCreate) {
                    clearDraft();
                    toast.success('Maid created successfully');
                } else {
                    toast.success('Maid updated successfully');
                    // Restore photo_url after successful update
                    if (shouldRemove && originalUrl) {
                        setData('photo_url', originalUrl);
                    }
                }
            };

            const onError = (errors: Record<string, string>) => {
                handleFormErrors(errors, sections, setError);
                // Restore photo_url after error
                if (shouldRemove && originalUrl) {
                    setData('photo_url', originalUrl);
                }
            };

            if (isCreate) {
                post(url, {
                    forceFormData: true,
                    onSuccess,
                    onError,
                });
            } else if (isEdit) {
                if (!data.id) {
                    toast.error(
                        'Maid identifier missing. Please reload the page and try again.',
                    );
                    return;
                }
                post(`${url}/${data.id}`, {
                    forceFormData: true,
                    onSuccess,
                    onError,
                });
            }
        },
        [
            data,
            initialData,
            isCreate,
            isEdit,
            setData,
            post,
            setError,
            clearDraft,
            sections,
        ],
    );

    return { handleSubmit };
}
