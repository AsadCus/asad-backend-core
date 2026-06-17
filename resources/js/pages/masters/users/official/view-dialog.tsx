import { index } from '@/routes/master/user/official';
import { OptionType } from '@/types';
import RoleUserFormDialog from '../components/role-user-form-dialog';
import { UserSchema } from '../schema';

interface OfficialViewDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    mode: 'create' | 'edit' | 'view';
    initialData?: UserSchema;
    roles?: OptionType[];
}

export default function OfficialViewDialog({
    open,
    onOpenChange,
    mode,
    initialData,
    roles = [],
}: OfficialViewDialogProps) {
    const title =
        mode === 'create'
            ? 'Create Official'
            : mode === 'edit'
              ? 'Edit Official'
              : 'View Official';

    return (
        <RoleUserFormDialog
            open={open}
            onOpenChange={onOpenChange}
            mode={mode}
            title={title}
            initialData={initialData}
            roles={roles}
            isOfficial
            submitUrl={index().url}
        />
    );
}
