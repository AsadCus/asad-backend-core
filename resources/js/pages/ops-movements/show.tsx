import AppLayout from '@/layouts/app-layout';
import { index } from '@/routes/ops-movements';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useCallback } from 'react';
import { type OpsMovementSchema } from './schema';
import OpsMovementForm from './form';
import { Button } from '@/components/ui/button';
import { FileDown } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

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

    const handleExportReport = useCallback(() => {
        window.open(`/ops-movements/${data.id}/export-pdf`, '_blank');
    }, [data.id]);

    const handleExportPif = useCallback(() => {
        window.open(`/ops-movements/${data.id}/export-pif-pdf`, '_blank');
    }, [data.id]);

    const handleExportBudget = useCallback(() => {
        window.open(`/ops-movements/${data.id}/export-budget-pdf`, '_blank');
    }, [data.id]);

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
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline" size="sm">
                                <FileDown className="h-4 w-4" />
                                Export Report
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem onClick={handleExportReport}>
                                <FileDown className="mr-2 h-4 w-4" />
                                Ops Movement Report
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={handleExportPif}>
                                <FileDown className="mr-2 h-4 w-4" />
                                Ops Movement PIF
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={handleExportBudget}>
                                <FileDown className="mr-2 h-4 w-4" />
                                Ops Movement Budget
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                    <OpsMovementForm initialData={data} onCancel={handleBack} />
                </div>
            </div>
        </AppLayout>
    );
}
