import AppLayout from '@/layouts/app-layout';
import { index as masterIndex } from '@/routes/master';
import { index as quotationIndex } from '@/routes/quotation';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useCallback } from 'react';
import { QuotationForm } from './form';
import { paymentPlans, QuotationSchema, statuses } from './schema';

interface ViewQuotationProps {
    data: {
        data: QuotationSchema;
        customerConfirmations: [];
        activeCustomers?: Array<{
            value: number;
            label: string;
            name: string;
            contact?: string | null;
            address?: string | null;
            email?: string | null;
        }>;
        paymentMethods?: { label: string; value: string }[];
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

export default function ViewQuotation({ data }: ViewQuotationProps) {
    const handleCancel = useCallback(() => {
        window.history.back();
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Quotation" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">Quotation - View</h2>
                </div>

                <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                    <QuotationForm
                        mode="view"
                        initialData={data.data}
                        paymentPlans={paymentPlans}
                        paymentMethods={data.paymentMethods ?? []}
                        statuses={statuses}
                        customerConfirmations={data.customerConfirmations}
                        activeCustomers={data.activeCustomers ?? []}
                        onCancel={handleCancel}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
