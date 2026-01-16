import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Table } from '@tanstack/react-table';
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
} from 'lucide-react';

interface DataTablePaginationProps<TData> {
    table: Table<TData>;
    data: TData[];
    countTopLevelRows?: boolean;
}

export function DataTablePagination<TData>({
    table,
    data,
    countTopLevelRows = false,
}: DataTablePaginationProps<TData>) {
    const filteredRows = table.getFilteredRowModel().rows;
    const filteredSelected = table.getFilteredSelectedRowModel().rows;
    const topLevelFilteredRows = countTopLevelRows
        ? filteredRows.filter((r) => r.depth === 0)
        : filteredRows;
    const filteredSelectedRows = countTopLevelRows
        ? filteredSelected.filter((r) => r.depth === 0)
        : filteredSelected;

    const pageSize = table.getState().pagination.pageSize;
    const pageIndex = table.getState().pagination.pageIndex;
    const totalTopRows = topLevelFilteredRows.length;
    const allRowsLength = countTopLevelRows ? totalTopRows : data.length;
    const totalPages = totalTopRows === 0 && countTopLevelRows ? 1 : pageSize === allRowsLength ? 1 : Math.ceil(totalTopRows / pageSize);

    const canPrev = pageIndex > 0;
    const canNext = pageIndex < totalPages - 1;

    return (
        <div className="flex flex-col gap-3 px-2 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-center justify-between md:justify-baseline">
                <div className="text-sm text-muted-foreground">
                    {filteredSelectedRows.length} of {topLevelFilteredRows.length} row(s) selected.
                </div>
                <div className="flex items-center space-x-2 md:hidden">
                    <p className="text-sm font-medium">Rows</p>
                    <Select
                        value={table.getState().pagination.pageSize === allRowsLength ? 'all' : `${table.getState().pagination.pageSize}`}
                        onValueChange={(value) => {
                            table.setPageSize(value === 'all' ? allRowsLength : Number(value));
                        }}
                    >
                        <SelectTrigger className="h-8 w-[80px]">
                            <SelectValue placeholder="Rows" />
                        </SelectTrigger>
                        <SelectContent side="top">
                            {[10, 20, 30, 40, 50, 'all'].map((pageSize) => (
                                <SelectItem
                                    key={pageSize}
                                    value={pageSize.toString()}
                                >
                                    {pageSize === 'all' ? 'All' : pageSize}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div className="flex flex-col items-center gap-3 sm:flex-row sm:gap-6 lg:gap-8">
                <div className="hidden items-center space-x-2 md:flex">
                    <p className="text-sm font-medium">Rows</p>
                    <Select
                        value={
                            table.getState().pagination.pageSize === allRowsLength
                                ? 'all'
                                : `${table.getState().pagination.pageSize}`
                        }
                        onValueChange={(value) => {
                            table.setPageSize(
                                value === 'all' ? allRowsLength : Number(value),
                            );
                        }}
                    >
                        <SelectTrigger className="h-8 w-[80px]">
                            <SelectValue placeholder="Rows" />
                        </SelectTrigger>
                        <SelectContent side="top">
                            {[10, 20, 30, 40, 50, 'all'].map((pageSize) => (
                                <SelectItem
                                    key={pageSize}
                                    value={pageSize.toString()}
                                >
                                    {pageSize === 'all' ? 'All' : pageSize}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="text-sm font-medium">
                    Page {pageIndex + 1} of {totalPages}
                </div>

                <div className="flex items-center space-x-1 md:space-x-3">
                    <Button variant="outline" size="icon" className="flex size-8" onClick={() => table.setPageIndex(0)} disabled={!canPrev}>
                        <span className="sr-only">Go to first page</span>
                        <ChevronsLeft />
                    </Button>
                    <Button variant="outline" size="icon" className="size-8" onClick={() => table.setPageIndex(Math.max(0, pageIndex - 1))} disabled={!canPrev}>
                        <span className="sr-only">Go to previous page</span>
                        <ChevronLeft />
                    </Button>
                    <Button variant="outline" size="icon" className="size-8" onClick={() => table.setPageIndex(Math.min(totalPages - 1, pageIndex + 1))} disabled={!canNext}>
                        <span className="sr-only">Go to next page</span>
                        <ChevronRight />
                    </Button>
                    <Button variant="outline" size="icon" className="flex size-8" onClick={() => table.setPageIndex(Math.max(0, totalPages - 1))} disabled={!canNext}>
                        <span className="sr-only">Go to last page</span>
                        <ChevronsRight />
                    </Button>
                </div>
            </div>
        </div>
    );
}
