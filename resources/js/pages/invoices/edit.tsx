import AppLayout from '@/layouts/app-layout';
import { index as invoiceIndex } from '@/routes/invoice';
import { index as masterIndex } from '@/routes/master';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useCallback } from 'react';
import OrderForm from '../orders/form';
import { OrderSchema } from '../orders/schema';
import { paymentPlans, QuotationSchema } from '../quotations/schema';

interface EditInvoiceProps {
    data: {
        data: OrderSchema;
        quotation: QuotationSchema;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Master',
        href: masterIndex().url,
    },
    {
        title: 'Invoice',
        href: invoiceIndex().url,
    },
];

export default function EditInvoice({ data }: EditInvoiceProps) {
    const handleCancel = useCallback(() => {
        window.history.back();
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Invoice" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">Invoice -Edit</h2>
                </div>

                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 md:min-h-min dark:border-sidebar-border">
                    <OrderForm
                        mode="edit"
                        initialData={data.data}
                        quotation={data.quotation}
                        paymentPlans={paymentPlans}
                        onCancel={handleCancel}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
