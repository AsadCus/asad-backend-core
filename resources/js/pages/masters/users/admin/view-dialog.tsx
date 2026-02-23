import { index } from '@/routes/master/user/admin';
import { OptionType } from '@/types';
import RoleUserFormDialog from '../components/role-user-form-dialog';
import { UserSchema } from '../schema';

interface AdminViewDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    mode: 'create' | 'edit' | 'view';
    initialData?: UserSchema;
    roles?: OptionType[];
    branches?: OptionType[];
    salesList?: OptionType[];
}

export default function AdminViewDialog({
    open,
    onOpenChange,
    mode,
    initialData,
    roles = [],
    branches = [],
    salesList = [],
}: AdminViewDialogProps) {
    const title =
        mode === 'create'
            ? 'Create Administrator'
            : mode === 'edit'
              ? 'Edit Administrator'
              : 'View Administrator';

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
            isAdmin
            submitUrl={index().url}
        />
    );
}
