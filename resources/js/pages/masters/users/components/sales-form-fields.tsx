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
                    branch_id: data.branch_id,
                }}
                errors={errors}
                branches={branches}
                isView={isView || isSalesUser}
                onChange={(field, value) => onChange(field, value)}
            />
        </div>
    );
}
