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
    PaginationState,
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
import React, {
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import { ActionColumn, ActionType } from './action-column';
import { ActionMenuItems } from './action-menu-items';
import { CustomExport } from './data-table-export';
import { DataTablePagination } from './data-table-pagination';
import { DataTableToolbar } from './data-table-toolbar';
import { Button } from './ui/button';
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
            pageSize?: number | 'all';
        };
    };
    exportFilename?: string;
    enableExpand?: boolean;
    renderSubComponent?: (row: Row<TData>) => React.ReactNode;
    onRowDoubleClick?: (row: TData) => void;
    addButtonText?: string;
    searchFilterMode?: 'inside' | 'outside';
    columnFilterMode?: 'inside' | 'outside';
    settingsKey?: string;
    groupByRowColorKey?: string;
    inheritExpandedRowBackground?: boolean;
    showSettings?: boolean;
    showExport?: boolean;
    customExports?: CustomExport[];
    exportOptions?: ('csv' | 'excel' | 'pdf' | 'json')[];
    showImport?: boolean;
    onImport?: () => void;
}

const ROW_CLICK_IGNORE_SELECTOR = [
    'a',
    'button',
    'input',
    'textarea',
    'select',
    'option',
    'label',
    '[role="button"]',
    '[role="checkbox"]',
    '[role="menuitem"]',
    '[data-prevent-row-open="true"]',
].join(',');

function shouldIgnoreRowOpen(
    event: React.MouseEvent<HTMLTableRowElement>,
): boolean {
    const target = event.target;

    if (!(target instanceof Element)) {
        return false;
    }

    if (target.closest(ROW_CLICK_IGNORE_SELECTOR)) {
        return true;
    }

    if (window.getSelection()?.toString()) {
        return true;
    }

    return false;
}

interface DataTablePersistedState {
    version: 1;
    sorting: SortingState;
    columnFilters: ColumnFiltersState;
    columnVisibility: VisibilityState;
    globalFilter: string;
    density: string;
    expanded: ExpandedState;
    pagination: PaginationState;
    searchQuery: string;
    pageSizeMode?: 'fixed' | 'all';
}

const DATATABLE_STORAGE_PREFIX = 'datatable-settings';
const DATATABLE_RESET_EVENT = 'datatable:reset-settings';

export function clearAllDataTableSettings(): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        const keysToDelete: string[] = [];

        for (let index = 0; index < window.localStorage.length; index += 1) {
            const key = window.localStorage.key(index);

            if (key && key.startsWith(`${DATATABLE_STORAGE_PREFIX}::`)) {
                keysToDelete.push(key);
            }
        }

        keysToDelete.forEach((key) => {
            window.localStorage.removeItem(key);
        });
    } catch {
        // Ignore storage errors and still broadcast reset to mounted tables.
    }

    window.dispatchEvent(new Event(DATATABLE_RESET_EVENT));
}

function readPersistedState(key: string): DataTablePersistedState | undefined {
    if (typeof window === 'undefined') {
        return undefined;
    }

    try {
        const raw = window.localStorage.getItem(key);

        if (!raw) {
            return undefined;
        }

        const parsed = JSON.parse(raw) as DataTablePersistedState;

        if (parsed?.version !== 1) {
            return undefined;
        }

        return {
            ...parsed,
            pageSizeMode: parsed.pageSizeMode === 'all' ? 'all' : 'fixed',
        };
    } catch {
        return undefined;
    }
}

function writePersistedState(
    key: string,
    state: DataTablePersistedState,
): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(key, JSON.stringify(state));
    } catch {
        // Ignore storage errors (quota/private mode) and keep UI usable.
    }
}

function columnStorageId<TData extends RowData>(
    column: ColumnDef<TData, unknown>,
    index: number,
): string {
    if ('id' in column && typeof column.id === 'string') {
        return column.id;
    }

    if (
        'accessorKey' in column &&
        typeof column.accessorKey === 'string' &&
        column.accessorKey.length > 0
    ) {
        return column.accessorKey;
    }

    return `col-${index}`;
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
    searchFilterMode = 'inside',
    columnFilterMode = 'inside',
    settingsKey,
    groupByRowColorKey,
    inheritExpandedRowBackground = false,
    showSettings = true,
    showExport = true,
    customExports,
    exportOptions,
    showImport = false,
    onImport,
}: DataTableProps<TData, TValue>) {
    const hasActionsColumn = columns.some(
        (col) => 'id' in col && col.id === 'actions',
    );

    const defaultStorageKey = useMemo(() => {
        const pathname =
            typeof window !== 'undefined' ? window.location.pathname : 'ssr';
        const baseColumns = columns.map((column, index) =>
            columnStorageId(column as ColumnDef<TData, unknown>, index),
        );
        const signature = [
            ...(enableExpand ? ['expander'] : []),
            ...baseColumns,
            ...(!hasActionsColumn && actions.length > 0 ? ['actions'] : []),
        ].join('|');

        return `${DATATABLE_STORAGE_PREFIX}::${pathname}::${url ?? 'no-url'}::${signature}`;
    }, [actions.length, columns, enableExpand, hasActionsColumn, url]);

    const storageKey = settingsKey ?? defaultStorageKey;
    const persistedState = useMemo(
        () => readPersistedState(storageKey),
        [storageKey],
    );

    const initialPageSizeSetting = initialState?.pagination?.pageSize;
    const initialPageSizeMode: 'fixed' | 'all' =
        initialPageSizeSetting === 'all' ? 'all' : 'fixed';
    const initialResolvedPageSize =
        initialPageSizeSetting === 'all' ? 1 : (initialPageSizeSetting ?? 10);

    const initialDefaultsRef = useRef({
        columnFilters: initialState?.columnFilters ?? [],
        columnVisibility: initialState?.columnVisibility ?? {},
        pagination: {
            pageIndex: initialState?.pagination?.pageIndex ?? 0,
            pageSize: initialResolvedPageSize,
        },
        pageSizeMode: initialPageSizeMode,
    });

    const [searchQuery, setSearchQuery] = useState<string>(
        persistedState?.searchQuery ?? '',
    );
    const [sorting, setSorting] = useState<SortingState>(
        persistedState?.sorting ?? [],
    );
    const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>(
        persistedState?.columnFilters ??
            initialDefaultsRef.current.columnFilters,
    );
    const [columnVisibility, setColumnVisibility] = useState<VisibilityState>(
        persistedState?.columnVisibility ??
            initialDefaultsRef.current.columnVisibility ??
            {},
    );
    const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
    const [globalFilter, setGlobalFilter] = useState(
        persistedState?.globalFilter ?? '',
    );
    const [density, setDensity] = useState<string>(
        persistedState?.density ?? 'flexible',
    );
    const [expanded, setExpanded] = useState<ExpandedState>(
        persistedState?.expanded ?? {},
    );
    const [pagination, setPagination] = useState<PaginationState>(
        persistedState?.pagination ?? {
            pageIndex: initialDefaultsRef.current.pagination.pageIndex,
            pageSize: initialDefaultsRef.current.pagination.pageSize,
        },
    );
    const [pageSizeMode, setPageSizeMode] = useState<'fixed' | 'all'>(
        persistedState?.pageSizeMode ?? initialDefaultsRef.current.pageSizeMode,
    );

    const resetToDefaultState = useCallback(() => {
        setSearchQuery('');
        setSorting([]);
        setColumnFilters(initialDefaultsRef.current.columnFilters);
        setColumnVisibility(initialDefaultsRef.current.columnVisibility);
        setRowSelection({});
        setGlobalFilter('');
        setDensity('flexible');
        setExpanded({});
        setPagination(initialDefaultsRef.current.pagination);
        setPageSizeMode(initialDefaultsRef.current.pageSizeMode);
    }, []);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const handleReset = () => {
            resetToDefaultState();
        };

        window.addEventListener(DATATABLE_RESET_EVENT, handleReset);

        return () => {
            window.removeEventListener(DATATABLE_RESET_EVENT, handleReset);
        };
    }, [resetToDefaultState]);

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

    const tableInitialState = {
        ...initialState,
        pagination: {
            pageIndex: initialState?.pagination?.pageIndex ?? 0,
            pageSize: initialResolvedPageSize,
        },
    };

    const table = useReactTable<TData>({
        data,
        columns: finalColumns,
        filterFns: {
            includesValue,
            dateRangeFilter,
        },
        initialState: tableInitialState,
        state: {
            sorting,
            columnFilters,
            columnVisibility,
            rowSelection,
            globalFilter,
            expanded,
            pagination,
        },
        onExpandedChange: setExpanded,
        getExpandedRowModel: enableExpand ? getExpandedRowModel() : undefined,
        getRowCanExpand: () => !!enableExpand && !!renderSubComponent,
        globalFilterFn: 'includesString',
        onPaginationChange: setPagination,
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

    useEffect(() => {
        if (pageSizeMode !== 'all') {
            return;
        }

        const filteredRows = table.getFilteredRowModel().rows;
        const visibleRowCount = renderSubComponent
            ? filteredRows.filter((row) => row.depth === 0).length
            : filteredRows.length;
        const nextPageSize = Math.max(1, visibleRowCount);

        setPagination((prev) => {
            if (prev.pageSize === nextPageSize && prev.pageIndex === 0) {
                return prev;
            }

            return {
                ...prev,
                pageIndex: 0,
                pageSize: nextPageSize,
            };
        });
    }, [
        table,
        pagination.pageSize,
        pagination.pageIndex,
        pageSizeMode,
        renderSubComponent,
        data,
        globalFilter,
        columnFilters,
    ]);

    useEffect(() => {
        writePersistedState(storageKey, {
            version: 1,
            sorting,
            columnFilters,
            columnVisibility,
            globalFilter,
            density,
            expanded,
            pagination,
            searchQuery,
            pageSizeMode,
        });
    }, [
        storageKey,
        sorting,
        columnFilters,
        columnVisibility,
        globalFilter,
        density,
        expanded,
        pagination,
        searchQuery,
        pageSizeMode,
    ]);

    const displayRows = table.getRowModel().rows;
    const shouldShowExpandControls = Boolean(
        enableExpand && renderSubComponent,
    );
    const canExpandAnyRows = shouldShowExpandControls
        ? displayRows.some((row) => row.getCanExpand())
        : false;
    const canExpandAllRows = shouldShowExpandControls
        ? displayRows.some((row) => row.getCanExpand() && !row.getIsExpanded())
        : false;
    const canCollapseAllRows = shouldShowExpandControls
        ? displayRows.some((row) => row.getCanExpand() && row.getIsExpanded())
        : false;

    const rowToneClassById = useMemo(() => {
        const classByRowId = new Map<string, string>();

        if (groupByRowColorKey) {
            const classByValue = new Map<string, string>();
            const colorClasses = [
                'bg-orange-50 dark:bg-gray-900',
                'bg-orange-100 dark:bg-gray-900/50',
            ];

            let colorIndex = 0;

            displayRows.forEach((row) => {
                const rowValue = String(
                    (row.original as Record<string, unknown>)?.[
                        groupByRowColorKey
                    ] ?? '',
                ).trim();

                if (rowValue.length === 0) {
                    classByRowId.set(row.id, 'bg-background');

                    return;
                }

                if (!classByValue.has(rowValue)) {
                    classByValue.set(
                        rowValue,
                        colorClasses[colorIndex % colorClasses.length],
                    );
                    colorIndex += 1;
                }

                classByRowId.set(row.id, classByValue.get(rowValue)!);
            });

            return classByRowId;
        }

        displayRows.forEach((row, index) => {
            classByRowId.set(
                row.id,
                index % 2 === 0 ? 'bg-muted/50' : 'bg-background',
            );
        });

        return classByRowId;
    }, [displayRows, groupByRowColorKey]);

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
                        searchFilterMode={searchFilterMode}
                        columnFilterMode={columnFilterMode}
                        showSettings={showSettings}
                        showExport={showExport}
                        customExports={customExports}
                        exportOptions={exportOptions}
                        showImport={showImport}
                        onImport={onImport}
                    />
                </div>
            </div>

            {/* Table */}
            <div className="overflow-hidden rounded-md border">
                {shouldShowExpandControls && canExpandAnyRows && (
                    <div className="flex items-center justify-end gap-2 border-b bg-muted/20 px-3 py-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => table.toggleAllRowsExpanded(true)}
                            disabled={!canExpandAllRows}
                        >
                            Expand All
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => table.toggleAllRowsExpanded(false)}
                            disabled={!canCollapseAllRows}
                        >
                            Collapse All
                        </Button>
                    </div>
                )}
                <div className="always-scrollbars max-h-[80vh] overflow-auto [&_[data-slot=table-container]]:overflow-visible">
                    <Table
                        className={cn(
                            'min-w-full [&_thead_th]:sticky [&_thead_th]:top-0 [&_thead_th]:z-10 [&_thead_th]:bg-background',
                            {
                                '[&_td]:py-1 [&_th]:py-1':
                                    density === 'compact',
                                '[&_td]:py-2 [&_th]:py-2':
                                    density === 'standard',
                                '[&_td]:py-3 [&_th]:py-3':
                                    density === 'flexible',
                            },
                        )}
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
                                displayRows.map((row) => {
                                    const rowToneClass =
                                        rowToneClassById.get(row.id) ??
                                        'bg-background';

                                    return (
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
                                                            'relative align-top transition-colors hover:bg-accent',
                                                            rowToneClass,
                                                            row.getIsSelected() &&
                                                                'bg-accent',
                                                            onRowDoubleClick &&
                                                                'cursor-pointer',
                                                        )}
                                                        onClick={(event) => {
                                                            if (
                                                                !onRowDoubleClick
                                                            ) {
                                                                return;
                                                            }

                                                            if (
                                                                shouldIgnoreRowOpen(
                                                                    event,
                                                                )
                                                            ) {
                                                                return;
                                                            }

                                                            onRowDoubleClick(
                                                                row.original,
                                                            );
                                                        }}
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
                                                                        key={
                                                                            cell.id
                                                                        }
                                                                        className={cn(
                                                                            cell
                                                                                .column
                                                                                .columnDef
                                                                                .meta
                                                                                ?.className,
                                                                        )}
                                                                    >
                                                                        {flexRender(
                                                                            cell
                                                                                .column
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
                                                        onAction={(
                                                            action,
                                                            payload,
                                                        ) => {
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

                                                                if (
                                                                    action ===
                                                                    'view'
                                                                )
                                                                    console.log(
                                                                        'View item',
                                                                        item,
                                                                    );
                                                                if (
                                                                    action ===
                                                                    'edit'
                                                                )
                                                                    console.log(
                                                                        'Edit item',
                                                                        item,
                                                                    );
                                                                if (
                                                                    action ===
                                                                    'delete'
                                                                )
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
                                                    <TableRow
                                                        className={cn(
                                                            inheritExpandedRowBackground &&
                                                                rowToneClass,
                                                        )}
                                                    >
                                                        <TableCell
                                                            colSpan={
                                                                row.getVisibleCells()
                                                                    .length
                                                            }
                                                            className={cn(
                                                                inheritExpandedRowBackground &&
                                                                    rowToneClass,
                                                            )}
                                                        >
                                                            <div
                                                                data-expanded-row={
                                                                    row.id
                                                                }
                                                                className={cn(
                                                                    inheritExpandedRowBackground &&
                                                                        rowToneClass,
                                                                )}
                                                            >
                                                                {renderSubComponent(
                                                                    row,
                                                                )}
                                                            </div>
                                                        </TableCell>
                                                    </TableRow>
                                                )}
                                        </React.Fragment>
                                    );
                                })
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
            </div>

            <DataTablePagination
                table={table}
                pageSizeMode={pageSizeMode}
                onPageSizeModeChange={setPageSizeMode}
                countTopLevelRows={!!renderSubComponent}
            />
        </div>
    );
}
