import AppLayout from '@/layouts/app-layout';
import { index as masterIndex } from '@/routes/master';
import { index as quotationIndex } from '@/routes/quotation';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useCallback } from 'react';
import { QuotationForm } from './form';
import {
    paymentMethods,
    paymentPlans,
    QuotationSchema,
    statuses,
} from './schema';

interface EditQuotationProps {
    data: {
        data: QuotationSchema;
        maids: [];
        customers: [];
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Master',
        href: masterIndex().url,
    },
    {
        title: 'Quotation',
        href: quotationIndex().url,
    },
];

export default function EditQuotation({ data }: EditQuotationProps) {
    const handleCancel = useCallback(() => {
        window.history.back();
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Quotation" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">Quotation - Edit</h2>
                </div>

                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 md:min-h-min dark:border-sidebar-border">
                    <QuotationForm
                        mode="edit"
                        initialData={data.data}
                        paymentPlans={paymentPlans}
                        paymentMethods={paymentMethods}
                        statuses={statuses}
                        maids={data.maids}
                        customers={data.customers}
                        onCancel={handleCancel}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
