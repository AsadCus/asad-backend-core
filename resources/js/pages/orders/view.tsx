import AppLayout from '@/layouts/app-layout';
import { index as masterIndex } from '@/routes/master';
import { index as orderIndex } from '@/routes/order';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useCallback } from 'react';
import { paymentPlans, QuotationSchema } from '../quotations/schema';
import OrderForm from './form';
import { OrderSchema } from './schema';

interface ViewOrderProps {
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
        title: 'Order',
        href: orderIndex().url,
    },
];

export default function ViewOrder({ data }: ViewOrderProps) {
    const handleCancel = useCallback(() => {
        window.history.back();
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Order" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">Order - View</h2>
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
