import AppLayout from '@/layouts/app-layout';
import { index as receiptIndex } from '@/routes/receipt';
import { OptionType, type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useCallback } from 'react';
import { InvoiceSchema } from '../invoices/schema';
import ReceiptForm from './form';

interface CreateReceiptProps {
    data: {
        invoiceId?: number | undefined;
        invoiceData?: InvoiceSchema;
        invoiceOptions: OptionType[];
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Receipt', href: receiptIndex().url },
];

export default function CreateReceipt({ data }: CreateReceiptProps) {
    const handleCancel = useCallback(() => {
        window.history.back();
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Receipt" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">Receipt - Create</h2>
                </div>
                <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                    <ReceiptForm
                        mode="create"
                        invoiceId={data.invoiceId}
                        invoiceData={data.invoiceData}
                        invoiceOptions={data.invoiceOptions}
                        onCancel={handleCancel}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
