import AppLayout from '@/layouts/app-layout';
import { index as masterIndex } from '@/routes/master';
import { index } from '@/routes/master/user';
import { generatePdf } from '@/routes/sales';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { FileDown } from 'lucide-react';
import { useCallback } from 'react';
import { resolveUserRoleLabel } from './create';
import { UserForm } from './form';
import { UserSchema } from './schema';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Master',
        href: masterIndex().url,
    },
    {
        title: 'User',
        href: index().url,
    },
];

interface ViewUserProps {
    data: UserSchema[];
    dataRole: [];
    dataBranch: [];
    dataSales: [];
    isAdmin: boolean;
    isSales: boolean;
    isSupplier: boolean;
    isCustomer: boolean;
}

export default function ViewUser({
    data,
    dataRole,
    dataBranch,
    dataSales,
    isAdmin = false,
    isSales = false,
    isSupplier = false,
    isCustomer = false,
}: ViewUserProps) {
    const handleCancel = useCallback(() => {
        window.history.back();
    }, []);

    const roleLabel = resolveUserRoleLabel({
        isAdmin,
        isSales,
        isSupplier,
        isCustomer,
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="User" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">
                        {roleLabel} - View
                    </h2>
                    {isSales && (data as unknown as UserSchema)?.id && (
                        <a
                            href={generatePdf((data as unknown as UserSchema).id!).url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-2 rounded-md border border-input bg-background px-3 py-1.5 text-sm font-medium shadow-sm hover:bg-accent hover:text-accent-foreground"
                        >
                            <FileDown className="h-4 w-4" />
                            Download PDF
                        </a>
                    )}
                </div>
                <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                    <UserForm
                        mode="view"
                        initialData={data}
                        branches={dataBranch}
                        roles={dataRole}
                        salesList={dataSales}
                        onCancel={handleCancel}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
