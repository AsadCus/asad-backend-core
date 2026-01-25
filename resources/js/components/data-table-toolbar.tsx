import { router } from '@inertiajs/react';
import { Row, Table } from '@tanstack/react-table';
import { X } from 'lucide-react';
import { toast } from 'sonner';
import { ActionType } from './action-column';
import useConfirmDialog from './confirm-popup';
import { DataTableExport } from './data-table-export';
import { DataTableSettings } from './data-table-settings';
import { Button } from './ui/button';

interface DataTableToolbarProps<TData> {
    table: Table<TData>;
    actions?: ActionType[];
    globalFilter: string;
    setGlobalFilter: (value: string) => void;
    density: string | undefined;
    setDensity: (value: string) => void;
    searchQuery: string;
    setSearchQuery: (value: string) => void;
    renderFilter?: (table: Table<TData>) => React.ReactNode;
    onAction?: (action: ActionType, row?: Row<TData>) => void;
    url?: string;
    exportFilename?: string;
    addButtonText?: string;
}

export function DataTableToolbar<TData>({
    table,
    actions,
    globalFilter,
    setGlobalFilter,
    density,
    setDensity,
    searchQuery,
    setSearchQuery,
    renderFilter,
    onAction,
    url,
    exportFilename = 'data',
    addButtonText = 'Add',
}: DataTableToolbarProps<TData>) {
    const isFiltered = table.getState().columnFilters.length > 0;
    const { confirm, ConfirmDialog } = useConfirmDialog();

    const deleteSelectedRecords = (selectedIds: number[]) => {
        confirm({
            title: 'Delete User',
            message: `Are you sure you want to delete ${selectedIds.length} data?`,
            confirmText: 'Delete',
            cancelText: 'Cancel',
            onConfirm: () => {
                router.delete(`${url}/0`, {
                    data: { ids: selectedIds },
                });
            },
        });
    };

    return (
        <>
            <div className="flex w-full justify-between">
                <div className="flex flex-1 items-center gap-2">
                    <DataTableSettings
                        table={table}
                        globalFilter={globalFilter}
                        setGlobalFilter={setGlobalFilter}
                        density={density}
                        setDensity={setDensity}
                        searchQuery={searchQuery}
                        setSearchQuery={setSearchQuery}
                        renderFilter={renderFilter}
                    />

                    {(isFiltered || globalFilter) && (
                        <Button
                            variant="ghost"
                            onClick={() => {
                                table.resetColumnFilters();
                                setGlobalFilter('');
                            }}
                        >
                            <span className="hidden sm:block">Reset</span>
                            <X />
                        </Button>
                    )}

                    {actions?.includes('delete') &&
                        table?.getSelectedRowModel().rows.length > 0 && (
                            <Button
                                onClick={() => {
                                    const selectedRows =
                                        table?.getSelectedRowModel().rows || [];

                                    const selectedIds = selectedRows
                                        .map(
                                            (r) =>
                                                (r.original as { id?: number })
                                                    .id,
                                        )
                                        .filter((id) => id !== undefined);

                                    if (selectedIds.length === 0) {
                                        toast('No items selected');
                                        return;
                                    }

                                    deleteSelectedRecords(selectedIds);
                                }}
                                variant={'destructive'}
                            >
                                Delete
                            </Button>
                        )}

                    {actions?.includes('add') && (
                        <Button onClick={() => onAction?.('add')}>
                            {addButtonText}
                        </Button>
                    )}
                </div>

                <DataTableExport table={table} filename={exportFilename} />
            </div>
            <ConfirmDialog />
        </>
    );
}
