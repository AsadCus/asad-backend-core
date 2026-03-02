import AppLayout from '@/layouts/app-layout';
import { index as masterIndex } from '@/routes/master';
import { index as quotationIndex } from '@/routes/quotation';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useCallback } from 'react';
import { UserSchema } from '../masters/users/schema';
import { QuotationForm } from './form';
import { paymentMethods, paymentPlans, statuses } from './schema';

interface CreateQuotationProps {
    data: {
        customerConfirmations: [];
        quotationItems: [];
        quotationNotes: [];
    };
    prefilledCustomerId?: string;
    prefilledCustomerData?: UserSchema;
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

export default function CreateQuotation({
    data,
    prefilledCustomerId,
    prefilledCustomerData,
}: CreateQuotationProps) {
    const handleCancel = useCallback(() => {
        window.history.back();
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Quotation" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">
                        Quotation - Create
                    </h2>
                </div>

                <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                    <QuotationForm
                        mode="create"
                        paymentPlans={paymentPlans}
                        paymentMethods={paymentMethods}
                        statuses={statuses}
                        customerConfirmations={data.customerConfirmations}
                        quotationItems={data.quotationItems}
                        quotationNotes={data.quotationNotes}
                        prefilledCustomerId={prefilledCustomerId}
                        prefilledCustomerData={prefilledCustomerData}
                        onCancel={handleCancel}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
