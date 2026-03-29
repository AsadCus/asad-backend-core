import { type TotalsSummaryExtensionMaster } from '@/components/totals-summary-card';
import AppLayout from '@/layouts/app-layout';
import { index as invoiceIndex } from '@/routes/invoice';
import { index as masterIndex } from '@/routes/master';
import { type BreadcrumbItem, type OptionType } from '@/types';
import { Head } from '@inertiajs/react';
import { useCallback } from 'react';
import OrderForm from '../orders/form';
import { OrderSchema } from '../orders/schema';
import { paymentPlans, QuotationSchema } from '../quotations/schema';

interface EditInvoiceProps {
    data: {
        data: OrderSchema;
        quotation: QuotationSchema;
        paymentMethods: OptionType[];
        quotationExtensionMasters: TotalsSummaryExtensionMaster[];
        defaultPaymentMethod: string;
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

                <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                    <OrderForm
                        mode="edit"
                        initialData={data.data}
                        quotation={data.quotation}
                        paymentPlans={paymentPlans}
                        paymentMethods={data.paymentMethods}
                        extensionMasters={data.quotationExtensionMasters}
                        defaultPaymentMethod={data.defaultPaymentMethod}
                        onCancel={handleCancel}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
