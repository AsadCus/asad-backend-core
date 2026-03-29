import { index } from '@/routes/master/user/operations';
import { OptionType } from '@/types';
import RoleUserFormDialog from '../components/role-user-form-dialog';
import { UserSchema } from '../schema';

interface OperationsViewDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    mode: 'create' | 'edit' | 'view';
    initialData?: UserSchema;
    roles?: OptionType[];
    branches?: OptionType[];
    salesList?: OptionType[];
}

export default function OperationsViewDialog({
    open,
    onOpenChange,
    mode,
    initialData,
    roles = [],
    branches = [],
    salesList = [],
}: OperationsViewDialogProps) {
    const title =
        mode === 'create'
            ? 'Create Operations User'
            : mode === 'edit'
              ? 'Edit Operations User'
              : 'View Operations User';

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
            isOperations
            submitUrl={index().url}
        />
    );
}
