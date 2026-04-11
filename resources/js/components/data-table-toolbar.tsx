import { router } from '@inertiajs/react';
import { Row, Table } from '@tanstack/react-table';
import { Plus, Trash, X } from 'lucide-react';
import { toast } from 'sonner';
import { ActionType } from './action-column';
import useConfirmDialog from './confirm-popup';
import { DataTableExport } from './data-table-export';
import { DataTableSettings } from './data-table-settings';
import { ProperInput } from './proper-input';
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
    searchFilterMode?: 'inside' | 'outside';
    columnFilterMode?: 'inside' | 'outside';
    showSettings?: boolean;
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
    searchFilterMode = 'inside',
    columnFilterMode = 'inside',
    showSettings = true,
}: DataTableToolbarProps<TData>) {
    const isFiltered = table.getState().columnFilters.length > 0;
    const showOutsideSearch = searchFilterMode === 'outside';
    const showOutsideColumnFilters =
        columnFilterMode === 'outside' && Boolean(renderFilter);
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
            <div className="flex w-full flex-col gap-3">
                <div className="flex w-full justify-between">
                    <div className="flex flex-1 items-center gap-2">
                        {showSettings && (
                            <DataTableSettings
                                table={table}
                                globalFilter={globalFilter}
                                setGlobalFilter={setGlobalFilter}
                                density={density}
                                setDensity={setDensity}
                                searchQuery={searchQuery}
                                setSearchQuery={setSearchQuery}
                                renderFilter={renderFilter}
                                searchFilterMode={searchFilterMode}
                                columnFilterMode={columnFilterMode}
                            />
                        )}

                        {showSettings && (isFiltered || globalFilter) && (
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
                                            table?.getSelectedRowModel().rows ||
                                            [];

                                        const selectedIds = selectedRows
                                            .map(
                                                (r) =>
                                                    (
                                                        r.original as {
                                                            id?: number;
                                                        }
                                                    ).id,
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
                                    <Trash className="h-4 w-4" />
                                    <span className="hidden sm:block">
                                        Delete
                                    </span>
                                </Button>
                            )}

                        {actions?.includes('add') && (
                            <Button onClick={() => onAction?.('add')}>
                                <Plus className="h-4 w-4" />
                                <span className="hidden sm:block">
                                    {addButtonText}
                                </span>
                            </Button>
                        )}
                    </div>

                    <DataTableExport table={table} filename={exportFilename} />
                </div>

                {(showOutsideSearch || showOutsideColumnFilters) && (
                    <div className="flex w-full flex-col gap-3">
                        {showOutsideSearch && (
                            <div className="w-full max-w-sm">
                                <ProperInput
                                    value={globalFilter ?? ''}
                                    onCommit={setGlobalFilter}
                                    placeholder="Search..."
                                    inputProps={{
                                        onChange: (e) =>
                                            setGlobalFilter(e.target.value),
                                    }}
                                />
                            </div>
                        )}

                        {showOutsideColumnFilters && renderFilter && (
                            <div className="flex flex-wrap items-center gap-3">
                                {renderFilter(table)}
                            </div>
                        )}
                    </div>
                )}
            </div>
            <ConfirmDialog />
        </>
    );
}
