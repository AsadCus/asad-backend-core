import { type TotalsSummaryExtensionMaster } from '@/components/totals-summary-card';
import AppLayout from '@/layouts/app-layout';
import { index as masterIndex } from '@/routes/master';
import { index as orderIndex } from '@/routes/order';
import { type BreadcrumbItem, type OptionType } from '@/types';
import { Head } from '@inertiajs/react';
import { useCallback } from 'react';
import { paymentPlans, QuotationSchema } from '../quotations/schema';
import OrderForm from './form';

interface CreateOrderProps {
    data: {
        quotation: QuotationSchema;
        paymentMethods: OptionType[];
        quotationExtensionMasters: TotalsSummaryExtensionMaster[];
        defaultPaymentMethod: string;
        invoiceNumberSeed?: {
            format_id?: number | null;
            numbers?: string[];
        };
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Master',
        href: masterIndex().url,
    },
    {
        title: 'Order',
        href: orderIndex().url,
    },
];

export default function CreateOrder({ data }: CreateOrderProps) {
    const handleCancel = useCallback(() => {
        window.history.back();
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Order" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">Order - Create</h2>
                </div>

                <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                    <OrderForm
                        mode="create"
                        quotation={data.quotation}
                        paymentPlans={paymentPlans}
                        paymentMethods={data.paymentMethods}
                        extensionMasters={data.quotationExtensionMasters}
                        defaultPaymentMethod={data.defaultPaymentMethod}
                        initialInvoiceNumberFormatId={
                            data.invoiceNumberSeed?.format_id ?? null
                        }
                        initialInvoiceNumbers={
                            data.invoiceNumberSeed?.numbers ?? []
                        }
                        onCancel={handleCancel}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
