import { SectionProgress } from '@/components/form-progress-header';
import { useCallback, useMemo } from 'react';
import { QuotationSchema } from '../schema';

export type QuotationFormData = QuotationSchema;
type QuotationErrors = Partial<Record<string, string>>;

interface UseQuotationSectionStatusProps {
    data: QuotationFormData;
    errors: QuotationErrors;
}

const SECTION_FIELDS: Record<string, string[]> = {
    customer_and_quotation_information: [
        'quotation_date',
        'expiry_date',
        'customer_id',
        'nric_number',
        'customer_contact',
        'customer_address',
        'customer_email',
        'commencement_date',
    ],

    maid_and_quotation_details: [
        'description',
        'monthly_salary',
        'loan_duration',
        'payment_plan',
        'payment_method',
        // 'rest_day_of_the_week',
        'rest_days_per_month',
        'compensation_off_in_lieu',
        'items',
    ],

    status: ['status', 'reason'],
};

const REQUIRED_FIELDS = new Set<string>([
    'quotation_date',
    'expiry_date',
    'customer_id',
    'description',
    'monthly_salary',
    'loan_duration',
    // 'rest_day_of_the_week',
    'rest_days_per_month',
    'compensation_off_in_lieu',
    'commencement_date',
    'payment_plan',
    'payment_method',
    'status',
]);

export function useQuotationSectionStatus({
    data,
    errors,
}: UseQuotationSectionStatusProps) {
    const hasValue = useCallback((value: unknown): boolean => {
        if (typeof value === 'string') return value.trim().length > 0;
        if (typeof value === 'number') return true;
        if (typeof value === 'boolean') return value === true;
        if (value instanceof File) return true;

        if (Array.isArray(value)) {
            if (value.length === 0) return false;
            return value.some((item) => hasValue(item));
        }

        if (typeof value === 'object' && value !== null) {
            return Object.values(value).some((v) => hasValue(v));
        }

        return false;
    }, []);

    const hasItemsError = useCallback(() => {
        return Object.keys(errors || {}).some((key) =>
            key.startsWith('items.'),
        );
    }, [errors]);

    const getQuotationSectionStatus = useCallback(
        (sectionId: string): 'incomplete' | 'complete' | 'error' => {
            if (sectionId === 'quotation_items') {
                if (hasItemsError()) return 'error';

                const items = data.items ?? [];
                return items.length > 0 ? 'complete' : 'incomplete';
            }

            const fields = SECTION_FIELDS[sectionId] || [];

            const hasError = fields.some((f) => Boolean(errors?.[f]));
            if (hasError) return 'error';

            const requiredFields = fields.filter((f) => REQUIRED_FIELDS.has(f));

            if (requiredFields.length > 0) {
                const allRequiredFilled = requiredFields.every((f) =>
                    hasValue(data?.[f as keyof QuotationSchema]),
                );
                return allRequiredFilled ? 'complete' : 'incomplete';
            }

            const anyFieldFilled = fields.some((f) =>
                hasValue(data?.[f as keyof QuotationSchema]),
            );

            return anyFieldFilled ? 'complete' : 'incomplete';
        },
        [data, errors, hasValue, hasItemsError],
    );

    const sections: SectionProgress[] = useMemo(
        () => [
            {
                id: 'customer_and_quotation_information',
                title: 'Customer',
                status: getQuotationSectionStatus(
                    'customer_and_quotation_information',
                ),
            },
            {
                id: 'maid_and_quotation_details',
                title: 'Quotation Details',
                status: getQuotationSectionStatus('maid_and_quotation_details'),
            },
            {
                id: 'status',
                title: 'Status',
                status: getQuotationSectionStatus('status'),
            },
        ],
        [getQuotationSectionStatus],
    );

    return { sections, getQuotationSectionStatus };
}
