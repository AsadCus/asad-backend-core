import { type ActionType } from '@/components/action-column';
import useConfirmDialog from '@/components/confirm-popup';
import { DataTable } from '@/components/data-table';
import { createSelectColumn } from '@/components/select-column';
import AppLayout from '@/layouts/app-layout';
import { index as masterIndex } from '@/routes/master';
import { index as userIndex } from '@/routes/master/user';
import { destroy, index } from '@/routes/master/user/sales';
import { OptionType, type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { useState } from 'react';
import { UserSchema } from '../schema';
import SalesViewDialog from './view-dialog';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Master',
        href: masterIndex().url,
    },
    {
        title: 'User',
        href: userIndex().url,
    },
    {
        title: 'Salesperson',
        href: index().url,
    },
];

const actions: ActionType[] = ['add', 'view', 'edit', 'delete'];

const columns: ColumnDef<UserSchema>[] = [
    createSelectColumn<UserSchema>(),
    {
        accessorKey: 'id',
        header: 'Id',
        meta: { exportable: true },
    },
    {
        accessorKey: 'name',
        header: 'Name',
        meta: { exportable: true },
    },
    {
        accessorKey: 'email',
        header: 'Email',
        meta: { exportable: true },
    },
    {
        accessorKey: 'branch_name',
        header: 'Branch',
        meta: { exportable: true },
    },
    {
        accessorKey: 'country_name',
        header: 'Country',
        meta: { exportable: true },
    },
    {
        accessorKey: 'role',
        header: 'Role',
        meta: { exportable: true },
        cell: ({ row }) => (
            <span className="capitalize">{row.getValue('role')}</span>
        ),
    },
];

interface SalesProps {
    dataUser: UserSchema[];
    dataRole: OptionType[];
    dataBranch: OptionType[];
    dataCountry: OptionType[];
    dataSales: OptionType[];
    scopeMode?: 'country' | 'branch';
}

export default function Sales({
    dataUser,
    dataRole,
    dataBranch,
    dataCountry,
    dataSales,
    scopeMode = 'country',
}: SalesProps) {
    const { confirm, ConfirmDialog } = useConfirmDialog();
    const [dialogOpen, setDialogOpen] = useState(false);
    const [dialogMode, setDialogMode] = useState<'create' | 'edit' | 'view'>(
        'view',
    );
    const [selectedUser, setSelectedUser] = useState<UserSchema | undefined>();

    const openDialog = (
        mode: 'create' | 'edit' | 'view',
        user?: UserSchema,
    ) => {
        setDialogMode(mode);
        setSelectedUser(user);
        setDialogOpen(true);
    };

    return (
        <>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Salesperson" />
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">Salesperson</h2>
                    </div>
                    <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                        <DataTable
                            columns={columns}
                            data={dataUser}
                            actions={actions}
                            searchFilterMode="outside"
                            columnFilterMode="outside"
                            url={index().url}
                            onAction={(action, row) => {
                                if (action === 'add') {
                                    openDialog('create');
                                    return;
                                }

                                const userId = row?.original.id;

                                if (userId !== undefined) {
                                    if (action === 'view') {
                                        openDialog('view', row?.original);
                                    } else if (action === 'edit') {
                                        openDialog('edit', row?.original);
                                    } else if (action === 'delete') {
                                        confirm({
                                            title: 'Delete Sales',
                                            message: `Are you sure you want to delete sales "${row?.original.name}"?`,
                                            confirmText: 'Delete',
                                            cancelText: 'Cancel',
                                            onConfirm: () => {
                                                router.delete(
                                                    destroy(userId).url,
                                                );
                                            },
                                        });
                                    }
                                }
                            }}
                            onRowDoubleClick={(row) => {
                                openDialog('edit', row as UserSchema);
                            }}
                            initialState={{
                                columnVisibility: { id: false },
                            }}
                        />
                    </div>
                </div>
            </AppLayout>
            <SalesViewDialog
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                mode={dialogMode}
                initialData={selectedUser}
                roles={dataRole}
                branches={dataBranch}
                countries={dataCountry}
                salesList={dataSales}
                scopeMode={scopeMode}
            />
            <ConfirmDialog />
        </>
    );
}
