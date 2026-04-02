import { type TotalsSummaryExtensionMaster } from '@/components/totals-summary-card';
import AppLayout from '@/layouts/app-layout';
import { index as invoiceIndex } from '@/routes/invoice';
import { index as masterIndex } from '@/routes/master';
import { type BreadcrumbItem, type OptionType } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useCallback } from 'react';
import { QuotationSchema } from '../quotations/schema';
import InvoiceForm from './form';

interface CreateInvoiceProps {
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
        title: 'Invoice',
        href: invoiceIndex().url,
    },
];

export default function CreateInvoice({ data }: CreateInvoiceProps) {
    const handleCancel = useCallback(() => {
        router.get(invoiceIndex().url);
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Invoice" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">Invoice - Create</h2>
                </div>

                <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                    <InvoiceForm
                        mode="create"
                        paymentMethods={data.paymentMethods}
                        extensionMasters={data.quotationExtensionMasters}
                        defaultPaymentMethod={data.defaultPaymentMethod}
                        initialInvoiceNumberFormatId={
                            data.invoiceNumberSeed?.format_id ?? null
                        }
                        initialInvoiceNumber={
                            data.invoiceNumberSeed?.numbers?.[0] ?? ''
                        }
                        onCancel={handleCancel}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
