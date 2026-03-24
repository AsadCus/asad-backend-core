import { Button } from '@/components/ui/button';
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

    const opsMovementExportUrl = `/ops-movements/${data.id}/export-pdf`;
    const budgetExportUrl = `/ops-movements/${data.id}/export-budget-pdf`;
    const pifExportUrl = `/ops-movements/${data.id}/export-pif-pdf`;

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
                    <div className="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                window.open(
                                    opsMovementExportUrl,
                                    '_blank',
                                    'noopener,noreferrer',
                                )
                            }
                        >
                            Export Ops Movement PDF
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                window.open(
                                    budgetExportUrl,
                                    '_blank',
                                    'noopener,noreferrer',
                                )
                            }
                        >
                            Export Budget PDF
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                window.open(
                                    pifExportUrl,
                                    '_blank',
                                    'noopener,noreferrer',
                                )
                            }
                        >
                            Export PIF PDF
                        </Button>
                    </div>
                </div>

                <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                    <OpsMovementForm
                        initialData={data}
                        onCancel={handleBack}
                        opsMovementExportUrl={opsMovementExportUrl}
                        budgetExportUrl={budgetExportUrl}
                        pifExportUrl={pifExportUrl}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
