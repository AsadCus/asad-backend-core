import { FormField } from '@/components/form-field';
import { ProperInput } from '@/components/proper-input';
import { ProperInputSelect } from '@/components/proper-input-select';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { sharingPlanOptions } from '@/pages/packages/schema';
import { type CustomerSchema } from './schema';

interface ConfirmedCustomerFormFieldsProps {
    customer: CustomerSchema;
    index?: number;
    fieldPrefix?: string;
    isView: boolean;
    processing: boolean;
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
    { value: 'cancelled', label: 'Cancelled' },
] as const;

export default function ConfirmedCustomerFormFields({
    customer,
    index,
    fieldPrefix,
    isView,
    processing,
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
                <FormField
                    label="Status"
                    htmlFor={fieldPath('status')}
                    error={getError(fieldPath('status'))}
                >
                    <Select
                        value={customer.status ?? 'draft'}
                        onValueChange={(value) =>
                            onUpdateCustomer('status', value)
                        }
                        disabled={disabled}
                    >
                        <SelectTrigger id={fieldPath('status')}>
                            <SelectValue placeholder="Select status" />
                        </SelectTrigger>
                        <SelectContent>
                            {statusOptions.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FormField>

                <FormField
                    label="Sharing Plan"
                    htmlFor={fieldPath('sharing_plan')}
                    error={getError(fieldPath('sharing_plan'))}
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
                    label="Role"
                    htmlFor={fieldPath('role')}
                    error={getError(fieldPath('role'))}
                    className="md:col-span-2"
                >
                    <ProperInput
                        id={fieldPath('role')}
                        value={customer.role ?? ''}
                        onCommit={(value) =>
                            onUpdateCustomer('role', value || null)
                        }
                        placeholder="Enter role"
                        disabled={disabled}
                    />
                </FormField>
            </div>
        </div>
    );
}
