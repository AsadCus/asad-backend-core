import AppLayout from '@/layouts/app-layout';
import { index } from '@/routes/ops-movements';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useCallback } from 'react';
import { type OpsMovementSchema } from './schema';
import OpsMovementForm from './form';

interface ShowOpsMovementProps {
    data: OpsMovementSchema;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Ops Movements',
        href: index().url,
    },
];

export default function ShowOpsMovement({ data }: ShowOpsMovementProps) {
    const handleBack = useCallback(() => {
        window.history.back();
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Ops Movement - ${data.package_number ?? data.id}`} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">
                        Ops Movement Form -{' '}
                        <span className="text-primary">
                            {data.package_number ?? data.id}
                        </span>
                    </h2>
                </div>

                <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                    <OpsMovementForm initialData={data} onCancel={handleBack} />
                </div>
            </div>
        </AppLayout>
    );
}
