import { index } from '@/routes/master/user/superadmin';
import { OptionType } from '@/types';
import RoleUserFormDialog from '../components/role-user-form-dialog';
import { UserSchema } from '../schema';

interface SuperadminViewDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    mode: 'create' | 'edit' | 'view';
    initialData?: UserSchema;
    roles?: OptionType[];
    branches?: OptionType[];
    countries?: OptionType[];
    salesList?: OptionType[];
    scopeMode?: 'country' | 'branch';
}

export default function SuperadminViewDialog({
    open,
    onOpenChange,
    mode,
    initialData,
    roles = [],
    branches = [],
    countries = [],
    salesList = [],
    scopeMode = 'country',
}: SuperadminViewDialogProps) {
    const title =
        mode === 'create'
            ? 'Create Superadmin'
            : mode === 'edit'
              ? 'Edit Superadmin'
              : 'View Superadmin';

    return (
        <RoleUserFormDialog
            open={open}
            onOpenChange={onOpenChange}
            mode={mode}
            title={title}
            initialData={initialData}
            roles={roles}
            branches={branches}
            countries={countries}
            salesList={salesList}
            isSuperadmin
            submitUrl={index().url}
            scopeMode={scopeMode}
        />
    );
}
