import { index } from '@/routes/master/user/sales';
import { OptionType } from '@/types';
import RoleUserFormDialog from '../components/role-user-form-dialog';
import { UserSchema } from '../schema';

interface SalesViewDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    mode: 'create' | 'edit' | 'view';
    initialData?: UserSchema;
    roles?: OptionType[];
    branches?: OptionType[];
    salesList?: OptionType[];
}

export default function SalesViewDialog({
    open,
    onOpenChange,
    mode,
    initialData,
    roles = [],
    branches = [],
    salesList = [],
}: SalesViewDialogProps) {
    const title =
        mode === 'create'
            ? 'Create Salesperson'
            : mode === 'edit'
              ? 'Edit Salesperson'
              : 'View Salesperson';

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
            isSales
            submitUrl={index().url}
        />
    );
}
