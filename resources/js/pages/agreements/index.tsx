import { DataTable } from '@/components/data-table';
import { createSelectColumn } from '@/components/select-column';
import AppLayout from '@/layouts/app-layout';
import { index } from '@/routes/agreement';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { useState } from 'react';
import AgreementPreviewModal from './components/agreement-preview-modal';
import { AgreementSchema } from './schema';

interface AgreementsProps {
    data: AgreementSchema[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Agreements',
        href: index().url,
    },
];

const columns: ColumnDef<AgreementSchema>[] = [
    createSelectColumn<AgreementSchema>(),
    {
        accessorKey: 'id',
        header: 'ID',
        meta: { exportable: true },
    },
    {
        accessorKey: 'customer_name',
        header: 'Customer Name',
        meta: { exportable: true },
    },
    {
        accessorKey: 'maid_name',
        header: 'Maid Name',
        meta: { exportable: true },
    },
    {
        accessorKey: 'agreement_number',
        header: 'Agreement No.',
        meta: { exportable: true },
    },
    {
        accessorKey: 'order',
        header: 'Order',
        cell: ({ row }) => row.original.quotation?.order?.order_number || '-',
        meta: { exportable: true },
    },
    {
        accessorKey: 'quotation',
        header: 'Quotation',
        cell: ({ row }) => {
            return row.original.quotation?.quotation_number || '-';
        },
        meta: { exportable: true },
    },
];

export default function Agreements({ data }: AgreementsProps) {
    const [previewAgreement, setPreviewAgreement] =
        useState<AgreementSchema | null>(null);
    const [isPreviewOpen, setIsPreviewOpen] = useState(false);

    const handleAction = (action: string, agreement: AgreementSchema) => {
        switch (action) {
            case 'preview':
                setPreviewAgreement(agreement);
                setIsPreviewOpen(true);
                break;
            case 'download':
                (async () => {
                    try {
                        const response = await fetch(
                            `/agreement/${agreement.id}/export-pdf`,
                        );
                        if (!response.ok)
                            throw new Error('Failed to generate PDF');
                        const blob = await response.blob();
                        const url = window.URL.createObjectURL(blob);

                        // Open in new tab
                        window.open(url, '_blank');

                        // Trigger download
                        const link = document.createElement('a');
                        link.href = url;
                        link.download = `agreement-${agreement.agreement_number}.pdf`;
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                    } catch (error) {
                        console.error('Error opening PDF:', error);
                    }
                })();
                break;
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Agreements" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">
                        Employment Agreements
                    </h2>
                    {/* <p className="text-base text-muted-foreground">
                        Agreements are generated from Quotations
                    </p> */}
                </div>

                <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                    <DataTable
                        columns={columns}
                        data={data}
                        actions={['preview', 'download']}
                        onAction={(action, row) => {
                            if (row && 'original' in row) {
                                handleAction(action as string, row.original);
                            }
                        }}
                        initialState={{
                            columnVisibility: {
                                id: false,
                            },
                        }}
                    />
                </div>
            </div>

            {previewAgreement && (
                <AgreementPreviewModal
                    agreement={previewAgreement}
                    open={isPreviewOpen}
                    onOpenChange={setIsPreviewOpen}
                />
            )}
        </AppLayout>
    );
}
