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
    countries?: OptionType[];
    salesList?: OptionType[];
    isSuperadmin?: boolean;
    isAdmin?: boolean;
    isSales?: boolean;
    isOperations?: boolean;
    isCustomer?: boolean;
    submitUrl: string;
    scopeMode?: 'country' | 'branch';
}

export default function RoleUserFormDialog({
    open,
    onOpenChange,
    title,
    mode,
    initialData,
    roles = [],
    branches = [],
    countries = [],
    salesList = [],
    isSuperadmin = false,
    isAdmin = false,
    isSales = false,
    isOperations = false,
    isCustomer = false,
    submitUrl,
    scopeMode = 'country',
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
                        countries={countries}
                        salesList={salesList}
                        isSuperadmin={isSuperadmin}
                        isAdmin={isAdmin}
                        isSales={isSales}
                        isOperations={isOperations}
                        isCustomer={isCustomer}
                        submitUrl={submitUrl}
                        scopeMode={scopeMode}
                        onCancel={() => onOpenChange(false)}
                        onSuccess={() => onOpenChange(false)}
                    />
                </div>
            </DialogContent>
        </Dialog>
    );
}
