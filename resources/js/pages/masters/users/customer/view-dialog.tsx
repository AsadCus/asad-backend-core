import { index } from '@/routes/master/user/customer';
import { OptionType } from '@/types';
import RoleUserFormDialog from '../components/role-user-form-dialog';
import { UserSchema } from '../schema';

interface CustomerViewDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    mode: 'create' | 'edit' | 'view';
    initialData?: UserSchema;
    roles?: OptionType[];
    branches?: OptionType[];
    salesList?: OptionType[];
}

export default function CustomerViewDialog({
    open,
    onOpenChange,
    mode,
    initialData,
    roles = [],
    branches = [],
    salesList = [],
}: CustomerViewDialogProps) {
    const title =
        mode === 'create'
            ? 'Create Customer'
            : mode === 'edit'
              ? 'Edit Customer'
              : 'View Customer';

    return (
        <RoleUserFormDialog
            open={open}
            onOpenChange={onOpenChange}
            mode={mode}
            title={title}
            initialData={initialData}
            roles={roles}
            branches={branches}
            salesList={salesList}
            isCustomer
            submitUrl={index().url}
        />
    );
}
