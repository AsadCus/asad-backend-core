import { index } from '@/routes/master/user/supplier';
import { OptionType } from '@/types';
import RoleUserFormDialog from '../components/role-user-form-dialog';
import { UserSchema } from '../schema';

interface SupplierViewDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    mode: 'create' | 'edit' | 'view';
    initialData?: UserSchema;
    roles?: OptionType[];
    branches?: OptionType[];
    salesList?: OptionType[];
}

export default function SupplierViewDialog({
    open,
    onOpenChange,
    mode,
    initialData,
    roles = [],
    branches = [],
    salesList = [],
}: SupplierViewDialogProps) {
    const title =
        mode === 'create'
            ? 'Create Supplier'
            : mode === 'edit'
              ? 'Edit Supplier'
              : 'View Supplier';

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
            isSupplier
            submitUrl={index().url}
        />
    );
}
