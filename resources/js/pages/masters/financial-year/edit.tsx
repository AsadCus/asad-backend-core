import AppLayout from '@/layouts/app-layout';
import { index as masterIndex } from '@/routes/master';
import { index } from '@/routes/master/financial-year';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useCallback } from 'react';
import { FinancialYearForm } from './form';
import { FinancialYearFormSchema } from './schema';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Master',
        href: masterIndex().url,
    },
    {
        title: 'Financial Year',
        href: index().url,
    },
];

interface EditFinancialYearProps {
    data: FinancialYearFormSchema;
}

export default function EditFinancialYear({ data }: EditFinancialYearProps) {
    const handleCancel = useCallback(() => {
        window.history.back();
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Financial Year" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">
                        Financial Year - Edit
                    </h2>
                </div>
                <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                    <FinancialYearForm
                        mode="edit"
                        initialData={data}
                        onCancel={handleCancel}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
