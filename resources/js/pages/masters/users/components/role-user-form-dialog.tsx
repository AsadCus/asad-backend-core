import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { OptionType } from '@/types';
import { UserForm } from '../form';
import { UserSchema } from '../schema';

interface RoleUserFormDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    mode: 'create' | 'edit' | 'view';
    initialData?: UserSchema;
    roles?: OptionType[];
    branches?: OptionType[];
    salesList?: OptionType[];
    isAdmin?: boolean;
    isSales?: boolean;
    isSupplier?: boolean;
    isCustomer?: boolean;
    submitUrl: string;
}

export default function RoleUserFormDialog({
    open,
    onOpenChange,
    title,
    mode,
    initialData,
    roles = [],
    branches = [],
    salesList = [],
    isAdmin = false,
    isSales = false,
    isSupplier = false,
    isCustomer = false,
    submitUrl,
}: RoleUserFormDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[95%] max-w-[95%] min-w-[95%] flex-col">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription className="sr-only">
                        User role form dialog
                    </DialogDescription>
                </DialogHeader>

                <div className="overflow-y-auto pb-2">
                    <UserForm
                        mode={mode}
                        initialData={initialData}
                        roles={roles}
                        branches={branches}
                        salesList={salesList}
                        isAdmin={isAdmin}
                        isSales={isSales}
                        isSupplier={isSupplier}
                        isCustomer={isCustomer}
                        submitUrl={submitUrl}
                        onCancel={() => onOpenChange(false)}
                    />
                </div>
            </DialogContent>
        </Dialog>
    );
}
