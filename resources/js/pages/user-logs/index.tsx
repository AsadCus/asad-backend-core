import { type ActionType } from '@/components/action-column';
import { DataTable } from '@/components/data-table';
import { createSelectColumn } from '@/components/select-column';
import AppLayout from '@/layouts/app-layout';
import { index } from '@/routes/user-logs';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

import { ColumnDef } from '@tanstack/react-table';

interface ActivityLog {
    id: number;
    description: string;
    subject_type: string;
    subject_id: number;
    causer_id: number | null;
    properties: Record<string, unknown>;
    created_at: string;
    updated_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'User Logs',
        href: index().url,
    },
];

const actions: ActionType[] = [];

const columns: ColumnDef<ActivityLog>[] = [
    createSelectColumn<ActivityLog>(),
    {
        accessorKey: 'id',
        header: 'Id',
        meta: { exportable: true },
    },
    {
        accessorKey: 'description',
        header: 'Description',
        meta: { exportable: true },
    },

    {
        accessorKey: 'causer.name',
        header: 'User ID',
        meta: { exportable: true },
    },
    {
        accessorKey: 'created_at',
        header: 'Date',
        meta: { exportable: true },
        cell: ({ row }) => {
            const date = new Date(row.getValue('created_at'));
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        },
    },
];

interface UserLogsProps {
    activities: ActivityLog[];
}

export default function UserLogs({ activities }: UserLogsProps) {
    console.log(activities);
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="User Logs" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">
                        User Activity Logs
                    </h2>
                </div>
                <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                    <DataTable
                        columns={columns}
                        data={activities}
                        actions={actions}
                        initialState={{
                            columnVisibility: { id: false },
                        }}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
