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
    forceStatusDisabled?: boolean;
    getError: (path: string) => string | undefined;
    sharingPlanSelectOptions?: Array<{ label: string; value: string }>;
    onUpdateCustomer: (
        field: keyof CustomerSchema,
        value: string | boolean | File | null,
    ) => void;
}

const statusOptions = [
    { value: 'pending_payment', label: 'Pending Payment' },
    { value: 'partially_paid', label: 'Partially Paid' },
    { value: 'fully_paid', label: 'Fully Paid' },
    { value: 'overpaid', label: 'Overpaid' },
    { value: 'cancelled', label: 'Cancelled' },
] as const;

export default function ConfirmedCustomerFormFields({
    customer,
    index,
    fieldPrefix,
    isView,
    processing,
    showStatusField = true,
    forceStatusDisabled = false,
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
                        label="Payment Status"
                        htmlFor={fieldPath('status')}
                        error={getError(fieldPath('status'))}
                        fieldRequirementsProps={{
                            required: true,
                            hint: 'Current member payment status',
                            example: 'fully_paid',
                            format: 'pending payment, partially paid, fully paid, overpaid, or cancelled',
                        }}
                    >
                        <ProperInputSelect
                            id={fieldPath('status')}
                            mode="classic"
                            options={statusOptions.map((option) => ({
                                label: option.label,
                                value: option.value,
                            }))}
                            value={customer.status ?? 'pending_payment'}
                            onValueChange={(value) =>
                                onUpdateCustomer('status', String(value))
                            }
                            placeholder="Select payment status"
                            disabled={disabled || forceStatusDisabled}
                        />
                    </FormField>
                )}

                <FormField
                    label="Pricing Plan"
                    htmlFor={fieldPath('sharing_plan')}
                    error={getError(fieldPath('sharing_plan'))}
                    fieldRequirementsProps={{
                        required: false,
                        hint: 'Selected package pricing plan',
                        example: 'double or child_with_bed',
                        format: 'single, double, triple, quad, child_with_bed, child_no_bed, or infant',
                    }}
                >
                    <ProperInputSelect
                        id={fieldPath('sharing_plan')}
                        mode="classic"
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
                        placeholder="Select pricing plan"
                        disabled={disabled}
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
