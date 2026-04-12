import { OptionType } from '@/types';
import { UserSchema } from '../schema';
import { AdminFormFields } from './admin-form-fields';

interface SalesFormFieldsProps {
    data: Pick<UserSchema, 'name' | 'email' | 'contact' | 'scope_ids'>;
    errors: Partial<Record<keyof UserSchema, string>>;
    scopeMode?: 'country' | 'branch';
    scopeOptions: OptionType[];
    isView: boolean;
    isSalesUser: boolean;
    onChange: (
        field: 'name' | 'email' | 'contact' | 'scope_ids',
        value: string | string[],
    ) => void;
}

export function SalesFormFields({
    data,
    errors,
    scopeMode = 'country',
    scopeOptions,
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
                    scope_ids: data.scope_ids,
                }}
                errors={errors}
                scopeMode={scopeMode}
                scopeOptions={scopeOptions}
                isView={isView || isSalesUser}
                onChange={(field, value) => onChange(field, value)}
            />
        </div>
    );
}
