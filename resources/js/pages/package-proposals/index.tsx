import { ActionType } from '@/components/action-column';
import { ColumnFilter } from '@/components/column-filter';
import useConfirmDialog from '@/components/confirm-popup';
import { DataTable } from '@/components/data-table';
import { DateRangeFilter } from '@/components/date-range-filter';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { RejectDialog } from '@/pages/package-proposals/components/reject-dialog';
import { SubmitForApprovalDialog } from '@/pages/package-proposals/components/submit-dialog';
import {
    approve,
    create,
    destroy,
    edit,
    index,
    show,
} from '@/routes/package-proposals';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { useState } from 'react';
import { proposalStatusColors, proposalStatusLabels } from './schema';

interface ProposalDataTableSchema {
    id: number;
    proposal_number: string;
    name: string;
    status: string;
    status_label: string;
    country_name: string | null;
    currency_symbol: string | null;
    departure_date: string | null;
    return_date: string | null;
    total_seats: number;
    created_by_name: string | null;
    created_at: string | null;
    package_id: number | null;
}

interface ApproverOption {
    id: number;
    name: string;
    email: string;
}

interface Props {
    data: {
        proposalsForDatatable: ProposalDataTableSchema[];
    };
    approverOptions: ApproverOption[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Package PnL',
        href: index().url,
    },
];

const columns: ColumnDef<ProposalDataTableSchema>[] = [
    {
        accessorKey: 'id',
        header: 'ID',
        meta: { exportable: true },
    },
    {
        accessorKey: 'proposal_number',
        header: 'Proposal No.',
        meta: { exportable: true },
    },
    {
        accessorKey: 'name',
        header: 'Package Name',
        meta: { exportable: true },
    },
    {
        accessorKey: 'status',
        header: 'Status',
        meta: { exportable: true },
        cell: ({ row }) => {
            const status = String(row.original.status ?? '')
                .trim()
                .toLowerCase();
            if (!status)
                return <span className="text-muted-foreground">-</span>;
            return (
                <Badge
                    className={`${proposalStatusColors[status] ?? 'bg-gray-100 text-gray-800'} rounded-full px-3 py-1 text-base`}
                >
                    {proposalStatusLabels[status] ?? status}
                </Badge>
            );
        },
        filterFn: 'includesValue',
    },
    {
        accessorKey: 'country_name',
        header: 'Country',
        meta: { exportable: true },
        filterFn: 'includesValue',
        cell: ({ row }) =>
            row.original.country_name ?? (
                <span className="text-muted-foreground">-</span>
            ),
    },
    {
        accessorKey: 'departure_date',
        header: 'Departure Date',
        meta: { exportable: true },
        filterFn: 'dateRangeFilter',
    },
    {
        accessorKey: 'total_seats',
        header: 'Seats',
        meta: { exportable: true },
    },
    {
        accessorKey: 'created_by_name',
        header: 'Created By',
        meta: { exportable: true },
        cell: ({ row }) =>
            row.original.created_by_name ?? (
                <span className="text-muted-foreground">-</span>
            ),
    },
    {
        accessorKey: 'created_at',
        header: 'Created At',
        meta: { exportable: true },
        filterFn: 'dateRangeFilter',
    },
];

export default function PackageProposalsIndex({ data, approverOptions }: Props) {
    const { auth } = usePage<SharedData>().props;
    const userPermissions = auth.permissions || [];
    const canEdit = userPermissions.includes('package-proposal edit');
    const canDelete = userPermissions.includes('package-proposal delete');
    const canApprove = userPermissions.includes('package-proposal approve');

    const actions: ActionType[] = [];
    if (userPermissions.includes('package-proposal create')) actions.push('add');
    actions.push('view');

    const getRowActions = (row: ProposalDataTableSchema): ActionType[] => {
        const rowActions: ActionType[] = [];
        const status = String(row.status ?? '').toLowerCase();

        if (status === 'pending_approval') {
            if (canApprove) {
                rowActions.push('proposal-approve');
                rowActions.push('proposal-reject');
            }
            return rowActions;
        }

        if (status === 'draft') {
            if (canEdit) {
                rowActions.push('edit');
                rowActions.push('proposal-submit');
            }
            if (canDelete) rowActions.push('delete');
            return rowActions;
        }

        if (status === 'rejected') {
            if (canEdit) rowActions.push('edit');
            if (canDelete) rowActions.push('delete');
            return rowActions;
        }

        return rowActions;
    };

    const { proposalsForDatatable } = data;
    const { confirm, ConfirmDialog } = useConfirmDialog();
    const [submitDialogOpen, setSubmitDialogOpen] = useState(false);
    const [submitProposalId, setSubmitProposalId] = useState<number | undefined>();
    const [rejectDialogOpen, setRejectDialogOpen] = useState(false);
    const [rejectProposalId, setRejectProposalId] = useState<number | undefined>();

    const countryOptions = [
        ...new Set(
            proposalsForDatatable
                .map((r) => r.country_name)
                .filter((c): c is string => Boolean(c)),
        ),
    ].map((c) => ({ value: c, label: c }));

    return (
        <>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Package PnL" />
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">Package PnL</h2>
                    </div>

                    <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                        <DataTable
                            columns={columns}
                            data={proposalsForDatatable}
                            actions={actions}
                            getRowActions={getRowActions}
                            searchFilterMode="outside"
                            columnFilterMode="outside"
                            addButtonText="Create New Proposal"
                            url={index().url}
                            exportOptions={['excel', 'pdf']}
                            onAction={(action, row) => {
                                if (action === 'add') {
                                    router.get(create().url);
                                }

                                const proposalId = row?.original.id;

                                if (proposalId !== undefined) {
                                    if (action === 'view') {
                                        router.get(show(proposalId).url);
                                    } else if (action === 'edit') {
                                        router.get(edit(proposalId).url);
                                    } else if (action === 'proposal-submit') {
                                        if (row?.original.status === 'draft') {
                                            setSubmitProposalId(proposalId);
                                            setSubmitDialogOpen(true);
                                        }
                                    } else if (action === 'proposal-approve') {
                                        confirm({
                                            title: 'Approve Proposal',
                                            message: `Are you sure you want to approve proposal "${row?.original.name}"?`,
                                            confirmText: 'Approve',
                                            cancelText: 'Cancel',
                                            onConfirm: () => {
                                                router.post(
                                                    approve(proposalId).url,
                                                );
                                            },
                                        });
                                    } else if (action === 'proposal-reject') {
                                        setRejectProposalId(proposalId);
                                        setRejectDialogOpen(true);
                                    } else if (action === 'delete') {
                                        confirm({
                                            title: 'Delete Proposal',
                                            message: `Are you sure you want to delete proposal "${row?.original.name}"?`,
                                            confirmText: 'Delete',
                                            cancelText: 'Cancel',
                                            onConfirm: () => {
                                                router.delete(
                                                    destroy(proposalId).url,
                                                );
                                            },
                                        });
                                    }
                                }
                            }}
                            onRowDoubleClick={(row) => {
                                if (row.id) {
                                    router.get(show(row.id).url);
                                }
                            }}
                            initialState={{
                                pagination: {
                                    pageSize: 50,
                                    pageIndex: 0,
                                },
                                columnVisibility: {
                                    id: false,
                                    created_at: false,
                                },
                            }}
                            renderFilter={(table) => (
                                <>
                                    <ColumnFilter
                                        table={table}
                                        columnId="status"
                                        title="Status"
                                        options={Object.entries(
                                            proposalStatusLabels,
                                        ).map(([value, label]) => ({
                                            value,
                                            label,
                                        }))}
                                    />
                                    <ColumnFilter
                                        table={table}
                                        columnId="country_name"
                                        title="Country"
                                        options={countryOptions}
                                    />
                                    <DateRangeFilter
                                        table={table}
                                        columnId="departure_date"
                                        title="Departure Date"
                                        quickDate={true}
                                    />
                                </>
                            )}
                        />
                    </div>
                </div>
            </AppLayout>

            <ConfirmDialog />
            <SubmitForApprovalDialog
                open={submitDialogOpen}
                onOpenChange={(open) => {
                    setSubmitDialogOpen(open);
                    if (!open) setSubmitProposalId(undefined);
                }}
                proposalId={submitProposalId}
                approverOptions={approverOptions}
            />
            <RejectDialog
                open={rejectDialogOpen}
                onOpenChange={(open) => {
                    setRejectDialogOpen(open);
                    if (!open) setRejectProposalId(undefined);
                }}
                proposalId={rejectProposalId}
            />
        </>
    );
}
