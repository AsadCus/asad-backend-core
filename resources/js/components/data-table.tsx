import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn, parseDisplayDate } from '@/lib/utils';
import {
    Cell,
    ColumnDef,
    ColumnFiltersState,
    ExpandedState,
    FilterFn,
    flexRender,
    getCoreRowModel,
    getExpandedRowModel,
    getFacetedRowModel,
    getFacetedUniqueValues,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    Row,
    RowData,
    RowSelectionState,
    SortingState,
    Table as TanStackTable,
    useReactTable,
    VisibilityState,
} from '@tanstack/react-table';
import {
    ArrowDown,
    ArrowUp,
    ChevronDown,
    ChevronRight,
    ChevronsUpDown,
} from 'lucide-react';
import React, { useState } from 'react';
import { ActionColumn, ActionType } from './action-column';
import { ActionMenuItems } from './action-menu-items';
import { DataTablePagination } from './data-table-pagination';
import { DataTableToolbar } from './data-table-toolbar';
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuTrigger,
} from './ui/context-menu';

interface DataTableProps<TData extends RowData, TValue = unknown> {
    columns: ColumnDef<TData, TValue>[];
    data: TData[];
    actions: ActionType[];
    url?: string;
    renderFilter?: (table: TanStackTable<TData>) => React.ReactNode;
    renderEmptyState?: (table: TanStackTable<TData>) => React.ReactNode;
    onAction?: (action: ActionType, row?: Row<TData>) => void;
    getRowActions?: (row: TData) => ActionType[];
    initialState?: {
        columnFilters?: ColumnFiltersState;
        columnVisibility?: VisibilityState;
        pagination?: {
            pageIndex?: number;
            pageSize?: number;
        };
    };
    exportFilename?: string;
    enableExpand?: boolean;
    renderSubComponent?: (row: Row<TData>) => React.ReactNode;
    onRowDoubleClick?: (row: TData) => void;
    addButtonText?: string;
}

export function DataTable<TData extends RowData, TValue = unknown>({
    columns,
    data,
    actions,
    url,
    renderFilter,
    onAction,
    getRowActions,
    initialState,
    exportFilename = 'data',
    enableExpand,
    renderSubComponent,
    renderEmptyState,
    onRowDoubleClick,
    addButtonText,
}: DataTableProps<TData, TValue>) {
    const [searchQuery, setSearchQuery] = useState<string>('');
    const [sorting, setSorting] = useState<SortingState>([]);
    const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>(
        initialState?.columnFilters ?? [],
    );
    const [columnVisibility, setColumnVisibility] = useState<VisibilityState>(
        initialState?.columnVisibility ?? {},
    );
    const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
    const [globalFilter, setGlobalFilter] = useState('');
    const [density, setDensity] = useState<string>('flexible');
    const [expanded, setExpanded] = useState<ExpandedState>({});

    const includesValue: FilterFn<TData> = (
        row,
        columnId,
        filterValue: string[],
    ) => {
        if (!filterValue || filterValue.length === 0) return true;

        const rawValue = row.getValue<unknown>(columnId);
        let values: string[] = [];

        if (typeof rawValue === 'string') {
            values = rawValue
                .split(',')
                .map((v) => v.trim())
                .filter(Boolean);
        } else if (typeof rawValue === 'number') {
            values = [String(rawValue)];
        } else if (Array.isArray(rawValue)) {
            values = rawValue.map((v) => String(v).trim());
        }

        if (values.length === 0) return false;

        return filterValue.some((f) => values.includes(String(f)));
    };

    const dateRangeFilter: FilterFn<TData> = (
        row,
        columnId,
        filterValue: { from?: string; to?: string },
    ) => {
        if (!filterValue || (!filterValue.from && !filterValue.to)) {
            return true;
        }

        const cellValue = row.getValue<string>(columnId);
        if (!cellValue) return false;

        const cellDate = parseDisplayDate(cellValue);
        if (!cellDate) return false;

        cellDate.setHours(0, 0, 0, 0);

        const fromDate = filterValue.from
            ? parseDisplayDate(filterValue.from)
            : null;
        const toDate = filterValue.to ? parseDisplayDate(filterValue.to) : null;

        if (fromDate) fromDate.setHours(0, 0, 0, 0);
        if (toDate) toDate.setHours(23, 59, 59, 999);

        if (fromDate && toDate) {
            return cellDate >= fromDate && cellDate <= toDate;
        } else if (fromDate) {
            return cellDate >= fromDate;
        } else if (toDate) {
            return cellDate <= toDate;
        }

        return true;
    };

    const expandColumn: ColumnDef<TData> = {
        id: 'expander',
        header: '',
        cell: ({ row }) => {
            return (
                <button
                    onClick={row.getToggleExpandedHandler()}
                    className="p-1 text-base"
                >
                    {row.getIsExpanded() ? (
                        <ChevronDown className="inline h-4 w-4" />
                    ) : (
                        <ChevronRight className="inline h-4 w-4" />
                    )}
                </button>
            );
        },
        enableSorting: false,
        enableHiding: false,
        meta: {
            exportable: false,
            className: 'w-[1%]',
        },
    };

    const hasActionsColumn = columns.some(
        (col) => 'id' in col && col.id === 'actions',
    );

    const finalColumns: ColumnDef<TData, TValue>[] = [
        ...(enableExpand ? [expandColumn] : []),
        ...columns,
        ...(!hasActionsColumn && actions.length > 0
            ? [
                  {
                      id: 'actions',
                      cell: ({ row }) => {
                          const rowActions = getRowActions
                              ? [...actions, ...getRowActions(row.original)]
                              : actions;
                          return (
                              <ActionColumn
                                  row={row}
                                  actions={rowActions}
                                  onAction={(action, payload) => {
                                      if (onAction) {
                                          if (
                                              payload &&
                                              typeof payload === 'object' &&
                                              'original' in payload
                                          ) {
                                              onAction(action, payload);
                                          } else {
                                              onAction(action, undefined);
                                          }
                                      } else {
                                          const item =
                                              payload &&
                                              typeof payload === 'object' &&
                                              'original' in payload
                                                  ? payload.original
                                                  : payload;

                                          if (action === 'view')
                                              console.log('View item', item);
                                          if (action === 'edit')
                                              console.log('Edit item', item);
                                          if (action === 'delete')
                                              console.log('Delete item', item);
                                      }
                                  }}
                              />
                          );
                      },
                      enableSorting: false,
                      enableHiding: false,
                      meta: { exportable: false },
                  } as ColumnDef<TData, TValue>,
              ]
            : []),
    ];

    const table = useReactTable<TData>({
        data,
        columns: finalColumns,
        filterFns: {
            includesValue,
            dateRangeFilter,
        },
        initialState: {
            pagination: {
                pageIndex: 0,
                pageSize: 10,
            },
            ...initialState,
        },
        state: {
            sorting,
            columnFilters,
            columnVisibility,
            rowSelection,
            globalFilter,
            expanded,
        },
        onExpandedChange: setExpanded,
        getExpandedRowModel: enableExpand ? getExpandedRowModel() : undefined,
        getRowCanExpand: () => !!enableExpand && !!renderSubComponent,
        globalFilterFn: 'includesString',
        onColumnVisibilityChange: setColumnVisibility,
        onColumnFiltersChange: setColumnFilters,
        onSortingChange: setSorting,
        onRowSelectionChange: setRowSelection,
        onGlobalFilterChange: setGlobalFilter,
        getCoreRowModel: getCoreRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        getFacetedRowModel: getFacetedRowModel(),
        getFacetedUniqueValues: getFacetedUniqueValues(),
    });

    const displayRows = table.getRowModel().rows;

    return (
        <div className="space-y-4">
            <div className="flex items-center gap-2">
                <div className="flex w-full justify-between">
                    <DataTableToolbar
                        table={table}
                        actions={actions}
                        globalFilter={globalFilter}
                        setGlobalFilter={setGlobalFilter}
                        density={density}
                        setDensity={setDensity}
                        searchQuery={searchQuery}
                        setSearchQuery={setSearchQuery}
                        renderFilter={renderFilter}
                        onAction={onAction}
                        url={url}
                        exportFilename={exportFilename}
                        addButtonText={addButtonText}
                    />
                </div>
            </div>

            {/* Table */}
            <div className="overflow-hidden rounded-md border">
                <Table
                    className={cn({
                        '[&_td]:py-1 [&_th]:py-1': density === 'compact',
                        '[&_td]:py-2 [&_th]:py-2': density === 'standard',
                        '[&_td]:py-3 [&_th]:py-3': density === 'flexible',
                    })}
                >
                    <TableHeader>
                        {table.getHeaderGroups().map((hg) => (
                            <TableRow key={hg.id}>
                                {hg.headers.map((header) => (
                                    <TableHead
                                        key={header.id}
                                        className={cn(
                                            header.column.columnDef.meta
                                                ?.className,
                                            header.column.getCanSort()
                                                ? 'cursor-pointer select-none'
                                                : '',
                                        )}
                                        onClick={header.column.getToggleSortingHandler()}
                                    >
                                        {header.isPlaceholder
                                            ? null
                                            : flexRender(
                                                  header.column.columnDef
                                                      .header,
                                                  header.getContext(),
                                              )}
                                        {{
                                            asc: (
                                                <ArrowUp className="ml-1 inline h-4 w-4" />
                                            ),
                                            desc: (
                                                <ArrowDown className="ml-1 inline h-4 w-4" />
                                            ),
                                        }[
                                            header.column.getIsSorted() as string
                                        ] ??
                                            (header.column.getCanSort() ? (
                                                <ChevronsUpDown className="ml-1 inline h-4 w-4 text-muted-foreground" />
                                            ) : null)}
                                    </TableHead>
                                ))}
                            </TableRow>
                        ))}
                    </TableHeader>

                    <TableBody>
                        {displayRows.length > 0 ? (
                            displayRows.map((row) => (
                                <React.Fragment key={row.id}>
                                    <ContextMenu>
                                        <ContextMenuTrigger asChild>
                                            <TableRow
                                                key={row.id}
                                                data-row-id={row.id}
                                                data-state={
                                                    row.getIsSelected() &&
                                                    'selected'
                                                }
                                                className={cn(
                                                    'relative align-top transition-colors odd:bg-muted/50 even:bg-background hover:bg-accent',
                                                    row.getIsSelected() &&
                                                        'bg-accent',
                                                    onRowDoubleClick &&
                                                        'cursor-pointer',
                                                )}
                                                onDoubleClick={() =>
                                                    onRowDoubleClick?.(
                                                        row.original,
                                                    )
                                                }
                                            >
                                                {row
                                                    .getVisibleCells()
                                                    .map(
                                                        (
                                                            cell: Cell<
                                                                TData,
                                                                unknown
                                                            >,
                                                        ) => (
                                                            <TableCell
                                                                key={cell.id}
                                                                className={cn(
                                                                    cell.column
                                                                        .columnDef
                                                                        .meta
                                                                        ?.className,
                                                                )}
                                                            >
                                                                {flexRender(
                                                                    cell.column
                                                                        .columnDef
                                                                        .cell,
                                                                    cell.getContext(),
                                                                )}
                                                            </TableCell>
                                                        ),
                                                    )}
                                            </TableRow>
                                        </ContextMenuTrigger>
                                        <ContextMenuContent className="w-48">
                                            <ActionMenuItems
                                                row={row}
                                                actions={
                                                    getRowActions
                                                        ? [
                                                              ...actions,
                                                              ...getRowActions(
                                                                  row.original,
                                                              ),
                                                          ]
                                                        : actions
                                                }
                                                onAction={(action, payload) => {
                                                    if (onAction) {
                                                        if (
                                                            payload &&
                                                            typeof payload ===
                                                                'object' &&
                                                            'original' in
                                                                payload
                                                        ) {
                                                            onAction(
                                                                action,
                                                                payload,
                                                            );
                                                        } else {
                                                            onAction(
                                                                action,
                                                                undefined,
                                                            );
                                                        }
                                                    } else {
                                                        const item =
                                                            payload &&
                                                            typeof payload ===
                                                                'object' &&
                                                            'original' in
                                                                payload
                                                                ? payload.original
                                                                : payload;

                                                        if (action === 'view')
                                                            console.log(
                                                                'View item',
                                                                item,
                                                            );
                                                        if (action === 'edit')
                                                            console.log(
                                                                'Edit item',
                                                                item,
                                                            );
                                                        if (action === 'delete')
                                                            console.log(
                                                                'Delete item',
                                                                item,
                                                            );
                                                    }
                                                }}
                                                mode="context"
                                            />
                                        </ContextMenuContent>
                                    </ContextMenu>

                                    {row.getIsExpanded() &&
                                        renderSubComponent && (
                                            <TableRow>
                                                <TableCell
                                                    colSpan={
                                                        row.getVisibleCells()
                                                            .length
                                                    }
                                                >
                                                    <div
                                                        data-expanded-row={
                                                            row.id
                                                        }
                                                    >
                                                        {renderSubComponent(
                                                            row,
                                                        )}
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        )}
                                </React.Fragment>
                            ))
                        ) : renderEmptyState ? (
                            <TableRow>
                                <TableCell
                                    colSpan={columns.length}
                                    className="h-24 text-center"
                                >
                                    {renderEmptyState(table)}
                                </TableCell>
                            </TableRow>
                        ) : (
                            <TableRow>
                                <TableCell
                                    colSpan={columns.length}
                                    className="h-24 text-center"
                                >
                                    No results.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            <DataTablePagination
                table={table}
                data={data}
                countTopLevelRows={!!renderSubComponent}
            />
        </div>
    );
}
