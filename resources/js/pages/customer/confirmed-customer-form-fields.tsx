import { FormField } from '@/components/form-field';
import { ProperInput } from '@/components/proper-input';
import { ProperInputSelect } from '@/components/proper-input-select';
import { sharingPlanOptions } from '@/pages/packages/schema';
import { type CustomerSchema } from './schema';

interface ConfirmedCustomerFormFieldsProps {
    customer: CustomerSchema;
    index?: number;
    fieldPrefix?: string;
    isView: boolean;
    processing: boolean;
    showStatusField?: boolean;
    getError: (path: string) => string | undefined;
    sharingPlanSelectOptions?: Array<{ label: string; value: string }>;
    onUpdateCustomer: (
        field: keyof CustomerSchema,
        value: string | boolean | File | null,
    ) => void;
}

const statusOptions = [
    { value: 'draft', label: 'Draft' },
    { value: 'pending_payment', label: 'Pending Payment' },
    { value: 'partially_paid', label: 'Partially Paid' },
    { value: 'confirmed', label: 'Confirmed' },
    { value: 'unavailable', label: 'Unavailable' },
    { value: 'cancelled', label: 'Cancelled' },
] as const;

export default function ConfirmedCustomerFormFields({
    customer,
    index,
    fieldPrefix,
    isView,
    processing,
    showStatusField = true,
    getError,
    sharingPlanSelectOptions,
    onUpdateCustomer,
}: ConfirmedCustomerFormFieldsProps) {
    const disabled = isView || processing;
    const prefix =
        fieldPrefix ?? (typeof index === 'number' ? `members.${index}` : '');
    const fieldPath = (field: keyof CustomerSchema): string => {
        return prefix ? `${prefix}.${field}` : String(field);
    };

    return (
        <div className="space-y-4">
            <div className="grid grid-cols-1 items-start gap-6 md:grid-cols-2">
                {showStatusField && (
                    <FormField
                        label="Status"
                        htmlFor={fieldPath('status')}
                        error={getError(fieldPath('status'))}
                        fieldRequirementsProps={{
                            required: true,
                            hint: 'Current booking status',
                            example: 'confirmed',
                            format: 'draft, pending payment, partially paid, confirmed, unavailable, or cancelled',
                        }}
                    >
                        <ProperInputSelect
                            id={fieldPath('status')}
                            mode="classic"
                            options={statusOptions.map((option) => ({
                                label: option.label,
                                value: option.value,
                            }))}
                            value={customer.status ?? 'draft'}
                            onValueChange={(value) =>
                                onUpdateCustomer('status', String(value))
                            }
                            placeholder="Select status"
                            disabled={disabled}
                        />
                    </FormField>
                )}

                <FormField
                    label="Sharing Plan"
                    htmlFor={fieldPath('sharing_plan')}
                    error={getError(fieldPath('sharing_plan'))}
                    fieldRequirementsProps={{
                        required: false,
                        hint: 'Room sharing arrangement',
                        example: 'double',
                        format: 'single, double, triple, or quad',
                    }}
                >
                    <ProperInputSelect
                        options={
                            sharingPlanSelectOptions &&
                            sharingPlanSelectOptions.length > 0
                                ? sharingPlanSelectOptions
                                : sharingPlanOptions
                        }
                        value={customer.sharing_plan ?? ''}
                        onValueChange={(value) =>
                            onUpdateCustomer(
                                'sharing_plan',
                                value ? String(value) : null,
                            )
                        }
                        placeholder="Select sharing plan"
                        disabled={disabled}
                        searchable={false}
                    />
                </FormField>

                <FormField
                    label="Relationship"
                    htmlFor={fieldPath('relationship')}
                    error={getError(fieldPath('relationship'))}
                    className="md:col-span-2"
                    fieldRequirementsProps={{
                        required: false,
                        hint: "Customer's relationship in the booking",
                        example: 'Parent, Child, or Family Member',
                        format: 'Up to 255 characters',
                    }}
                >
                    <ProperInput
                        id={fieldPath('relationship')}
                        value={customer.relationship ?? ''}
                        onCommit={(value) =>
                            onUpdateCustomer('relationship', value || null)
                        }
                        placeholder="Enter relationship"
                        disabled={disabled}
                    />
                </FormField>
            </div>
        </div>
    );
}
