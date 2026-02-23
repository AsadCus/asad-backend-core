import { FormField } from '@/components/form-field';
import { ProperInputSelect } from '@/components/proper-input-select';
import { OptionType } from '@/types';
import { UserSchema } from '../schema';
import { AdminFormFields } from './admin-form-fields';

interface SalesFormFieldsProps {
    data: Pick<UserSchema, 'name' | 'email' | 'contact' | 'branch_id'>;
    errors: Partial<Record<keyof UserSchema, string>>;
    branches: OptionType[];
    isView: boolean;
    isSalesUser: boolean;
    onChange: (
        field: 'name' | 'email' | 'contact' | 'branch_id',
        value: string,
    ) => void;
}

export function SalesFormFields({
    data,
    errors,
    branches,
    isView,
    isSalesUser,
    onChange,
}: SalesFormFieldsProps) {
    return (
        <div className="space-y-6">
            <AdminFormFields
                data={{
                    name: data.name,
                    email: data.email,
                    contact: data.contact,
                }}
                errors={errors}
                isView={isView}
                onChange={(field, value) => onChange(field, value)}
            />

            <div className="space-y-4">
                <h3 className="text-xl font-semibold">Sales</h3>
                <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-3">
                    <FormField
                        label="Branch"
                        fieldRequirementsProps={{ required: true }}
                        error={errors.branch_id}
                    >
                        <ProperInputSelect
                            disabled={isView || isSalesUser}
                            options={branches}
                            value={data.branch_id}
                            onValueChange={(value) =>
                                onChange('branch_id', String(value))
                            }
                            placeholder="Select branch"
                        />
                    </FormField>
                </div>
            </div>
        </div>
    );
}
