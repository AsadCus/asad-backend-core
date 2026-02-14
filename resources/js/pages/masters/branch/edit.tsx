import AppLayout from '@/layouts/app-layout';
import { index as masterIndex } from '@/routes/master';
import { index } from '@/routes/master/branch';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useCallback } from 'react';
import { BranchForm } from './form';
import { BranchSchema } from './schema';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Master',
        href: masterIndex().url,
    },
    {
        title: 'Branch',
        href: index().url,
    },
];

interface EditBranchProps {
    data: BranchSchema;
    dataCountry: [];
}

export default function EditBranch({ data, dataCountry }: EditBranchProps) {
    const handleCancel = useCallback(() => {
        window.history.back();
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Branch" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">Branch - Edit</h2>
                </div>
                <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                    <BranchForm
                        mode="edit"
                        initialData={data}
                        countries={dataCountry}
                        onCancel={handleCancel}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
