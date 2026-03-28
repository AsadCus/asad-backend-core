import AppLayout from '@/layouts/app-layout';
import { index as receiptIndex } from '@/routes/receipt';
import { OptionType, type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useCallback } from 'react';
import { InvoiceSchema } from '../invoices/schema';
import ReceiptForm from './form';
import { ReceiptSchema } from './schema';

interface EditReceiptProps {
    data: {
        data: ReceiptSchema;
        invoiceOptions: OptionType[];
        invoiceData?: InvoiceSchema;
        paymentMethods: OptionType[];
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Receipt', href: receiptIndex().url },
];

export default function EditReceipt({ data }: EditReceiptProps) {
    const handleCancel = useCallback(() => {
        window.history.back();
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit Receipt" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">Receipt - Edit</h2>
                </div>
                <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                    <ReceiptForm
                        mode="edit"
                        initialData={data.data}
                        invoiceData={data.invoiceData}
                        invoiceOptions={data.invoiceOptions}
                        paymentMethods={data.paymentMethods}
                        onCancel={handleCancel}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
