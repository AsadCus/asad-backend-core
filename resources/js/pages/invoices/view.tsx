import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { index as invoiceIndex } from '@/routes/invoice';
import { index as masterIndex } from '@/routes/master';
import { create as createReceipt } from '@/routes/receipt';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useCallback } from 'react';
import OrderForm from '../orders/form';
import { OrderSchema } from '../orders/schema';
import { paymentPlans, QuotationSchema } from '../quotations/schema';
import InvoicePreviewModal from './components/invoice-preview-modal';

interface ViewInvoiceProps {
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

export default function ViewInvoice({ data }: ViewInvoiceProps) {
    const handleCancel = useCallback(() => {
        window.history.back();
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Invoice" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">Invoice - View</h2>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                router.get(
                                    createReceipt().url +
                                        `?invoice_id=${data.data.id}`,
                                )
                            }
                            className="gap-2"
                        >
                            <Plus className="h-4 w-4" />
                            Create Receipt
                        </Button>
                        <InvoicePreviewModal invoiceId={data.data.id} />
                    </div>
                </div>

                <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                    <OrderForm
                        mode="view"
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
