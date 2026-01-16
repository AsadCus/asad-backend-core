import { useCallback } from 'react';
import { maidSchema } from '../schema';
import { MaidFormData } from '../types';
import { z } from 'zod';

interface UseFieldValidationProps {
    data: MaidFormData;
    setError: (key: keyof MaidFormData, message: string) => void;
    clearErrors: (...fields: (keyof MaidFormData)[]) => void;
}

export function useFieldValidation({
    data,
    setError,
    clearErrors,
}: UseFieldValidationProps) {
    const validateField = useCallback(
        (fieldName: keyof MaidFormData) => {
            // Clear existing error for this field (Inertia clearErrors uses rest params)
            clearErrors(fieldName);

            try {
                // Get the field schema
                const fieldSchema = (maidSchema as z.ZodObject<Record<string, z.ZodTypeAny>>).shape[fieldName];
                
                if (!fieldSchema) return true;

                // Normalize value for validation
                const value = data[fieldName];
                
                // Skip validation for empty optional fields
                if (value === '' || value === null || value === undefined) {
                    // Check if field is required
                    const isOptional = fieldSchema.isOptional();
                    if (isOptional) return true;
                }
                
                const normalizedValue = typeof value === 'number' ? String(value) : value;

                // Validate the field
                fieldSchema.parse(normalizedValue);
                return true;
            } catch (error) {
                if (error instanceof z.ZodError) {
                    const firstError = error.issues[0];
                    if (firstError) {
                        setError(fieldName, firstError.message);
                    }
                }
                return false;
            }
        },
        [data, setError, clearErrors]
    );

    const validateFieldAsync = useCallback(
        async (fieldName: keyof MaidFormData): Promise<boolean> => {
            return validateField(fieldName);
        },
        [validateField]
    );

    return { validateField, validateFieldAsync };
}
