'use client';

import { formatDateForDisplay, parseDisplayDate } from '@/lib/utils';
import { Table } from '@tanstack/react-table';
import { CalendarIcon, X } from 'lucide-react';
import { useState } from 'react';
import { DateRange } from 'react-day-picker';
import { ProperInput } from './proper-input';
import { Button } from './ui/button';
import { Calendar } from './ui/calendar';
import { Label } from './ui/label';
import { Popover, PopoverContent, PopoverTrigger } from './ui/popover';

interface DateRangeFilterProps<TData> {
    table?: Table<TData> | null;
    columnId?: string;
    title: string;
    fromYear?: number;
    toYear?: number;
    quickDate?: boolean;
    compact?: boolean;

    value?: { from?: string; to?: string };
    onChange?: (value: { from?: string; to?: string }) => void;
}

const QUICK_SINGLE = [
    { label: 'Today', value: 'today' },
    // { label: 'Tomorrow', value: '3days' },
    // { label: 'In 1 week', value: '1week' },
    // { label: 'In 1 month', value: '1month' },
    // { label: 'In 3 months', value: '3months' },
    // { label: 'End of month', value: 'endmonth' },
    // { label: 'End of next month', value: 'endnextmonth' },
];

const QUICK_RANGE = [
    { label: 'Last 7 days', value: 'last7days' },
    { label: 'Last 30 days', value: 'last30days' },
    { label: 'Last 3 months', value: 'last3months' },
    { label: 'Last 6 months', value: 'last6months' },
    { label: 'Last 1 year', value: 'last1year' },
];

export function DateRangeFilter<TData>({
    table,
    columnId,
    title,
    fromYear = new Date().getFullYear() - 3,
    toYear = new Date().getFullYear() + 3,
    quickDate = false,
    compact = false,
    value,
    onChange,
}: DateRangeFilterProps<TData>) {
    const column = table && columnId ? table.getColumn(columnId) : null;

    const filterValue = column
        ? (column.getFilterValue() as
              | { from?: string; to?: string }
              | undefined)
        : value;

    const [open, setOpen] = useState(false);
    const [dateRange, setDateRange] = useState<DateRange | undefined>(
        filterValue?.from
            ? {
                  from: parseDisplayDate(filterValue.from)!,
                  to: filterValue?.to
                      ? parseDisplayDate(filterValue.to)
                      : undefined,
              }
            : undefined,
    );

    const handleDateChange = (type: 'from' | 'to', dateStr: string) => {
        const parsed = parseDisplayDate(dateStr);

        let newRange: DateRange | undefined;

        if (type === 'from' && parsed) {
            newRange = { from: parsed, to: dateRange?.to };
        }

        if (type === 'to' && dateRange?.from) {
            newRange = { from: dateRange.from, to: parsed };
        }

        setDateRange(newRange);

        const filterObj = {
            from: type === 'from' ? dateStr : filterValue?.from,
            to: type === 'to' ? dateStr : filterValue?.to,
        };

        if (column) {
            column.setFilterValue(filterObj);
        } else {
            onChange?.(filterObj);
        }
    };

    const handleCalendarSelect = (range?: DateRange) => {
        setDateRange(range);

        const filterObj = {
            from: range?.from ? formatDateForDisplay(range.from) : undefined,
            to: range?.to ? formatDateForDisplay(range.to) : undefined,
        };

        if (column) {
            column.setFilterValue(filterObj);
        } else {
            onChange?.(filterObj);
        }
    };

    const handleClear = () => {
        setDateRange(undefined);

        if (column) {
            column.setFilterValue(undefined);
        } else {
            onChange?.({});
        }

        setOpen(false);
    };

    const getQuickDate = (type: string) => {
        const date = new Date();

        switch (type) {
            case '3days':
                date.setDate(date.getDate() + 3);
                break;
            case '1week':
                date.setDate(date.getDate() + 7);
                break;
            case '1month':
                date.setMonth(date.getMonth() + 1);
                break;
            case '3months':
                date.setMonth(date.getMonth() + 3);
                break;
            case 'endmonth':
                date.setMonth(date.getMonth() + 1, 0);
                break;
            case 'endnextmonth':
                date.setMonth(date.getMonth() + 2, 0);
                break;
            case 'today':
                break;
            case 'yesterday':
                date.setDate(date.getDate() - 1);
                break;
            case 'last7days':
                date.setDate(date.getDate() - 7);
                break;
            case 'last30days':
                date.setDate(date.getDate() - 30);
                break;
            case 'thismonth':
                date.setDate(1);
                break;
            case 'last3months':
                date.setMonth(date.getMonth() - 3);
                break;
            case 'last6months':
                date.setMonth(date.getMonth() - 6);
                break;
            case 'last1year':
                date.setFullYear(date.getFullYear() - 1);
                break;
        }

        return date;
    };

    const applyQuickDate = (type: string) => {
        const fromDate = getQuickDate(type);
        const toDate =
            type.startsWith('last') || type === 'thismonth'
                ? new Date()
                : undefined;

        setDateRange({ from: fromDate, to: toDate });

        const filterObj = {
            from: formatDateForDisplay(fromDate),
            to: toDate ? formatDateForDisplay(toDate) : undefined,
        };

        if (column) {
            column.setFilterValue(filterObj);
        } else {
            onChange?.(filterObj);
        }
    };

    const hasFilter = filterValue?.from || filterValue?.to;

    const renderContent = () => (
        <div className="space-y-1">
            {/* <DropdownMenuLabel>{title}</DropdownMenuLabel> */}
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button
                        variant="outline"
                        size={compact ? 'sm' : 'default'}
                        className={
                            compact
                                ? 'h-9 justify-start gap-1.5 border-dashed'
                                : 'w-full justify-start border-dashed'
                        }
                    >
                        <CalendarIcon className="h-4 w-4 shrink-0" />
                        {!hasFilter && title}
                        {hasFilter && (
                            <span className="text-sm font-normal">
                                {filterValue?.from && filterValue?.to
                                    ? `${filterValue.from} – ${filterValue.to}`
                                    : filterValue?.from || filterValue?.to}
                            </span>
                        )}
                    </Button>
                </PopoverTrigger>

                <PopoverContent
                    className="w-auto p-3"
                    align="end"
                    side="bottom"
                    sideOffset={4}
                >
                    <div className="space-y-3">
                        {!compact && (
                            <div className="grid grid-cols-2 gap-2">
                                <div className="grid w-full gap-1">
                                    <Label>From</Label>
                                    <div className="relative">
                                        <ProperInput
                                            placeholder={formatDateForDisplay(
                                                new Date(),
                                            )}
                                            value={filterValue?.from ?? ''}
                                            onCommit={(e) =>
                                                handleDateChange('from', e)
                                            }
                                            type="text"
                                        />
                                    </div>
                                </div>

                                <div className="grid w-full gap-1">
                                    <Label>To</Label>
                                    <div className="relative">
                                        <ProperInput
                                            placeholder={formatDateForDisplay(
                                                new Date(),
                                            )}
                                            value={filterValue?.to ?? ''}
                                            onCommit={(e) =>
                                                handleDateChange('to', e)
                                            }
                                            type="text"
                                        />
                                    </div>
                                </div>
                            </div>
                        )}

                        <div className="flex gap-3">
                            <div className="space-y-2">
                                <Calendar
                                    mode="range"
                                    numberOfMonths={1}
                                    defaultMonth={dateRange?.from}
                                    selected={dateRange}
                                    captionLayout="dropdown"
                                    fromYear={fromYear}
                                    toYear={toYear}
                                    onSelect={handleCalendarSelect}
                                    className="rounded-lg border shadow-sm"
                                />

                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="w-full"
                                    onClick={handleClear}
                                >
                                    <X className="h-4 w-4" />
                                    Clear Filter
                                </Button>

                                {quickDate && (
                                    <div className="mt-3 flex flex-col gap-1 border-t pt-3 md:hidden">
                                        <p className="mb-2 text-sm font-medium">
                                            Quick Select
                                        </p>

                                        {[...QUICK_SINGLE, ...QUICK_RANGE].map(
                                            (item) => (
                                                <Button
                                                    key={item.value}
                                                    variant="ghost"
                                                    size="sm"
                                                    className="justify-start"
                                                    onClick={() =>
                                                        applyQuickDate(
                                                            item.value,
                                                        )
                                                    }
                                                >
                                                    {item.label}
                                                </Button>
                                            ),
                                        )}
                                    </div>
                                )}
                            </div>

                            {quickDate && (
                                <div className="hidden min-w-[160px] flex-col gap-1 border-l pl-4 md:flex">
                                    <p className="mb-2 text-sm font-medium">
                                        Quick Select
                                    </p>

                                    {[...QUICK_SINGLE, ...QUICK_RANGE].map(
                                        (item) => (
                                            <Button
                                                key={item.value}
                                                variant="ghost"
                                                size="sm"
                                                className="justify-start"
                                                onClick={() =>
                                                    applyQuickDate(item.value)
                                                }
                                            >
                                                {item.label}
                                            </Button>
                                        ),
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </PopoverContent>
            </Popover>
        </div>
    );

    return renderContent();
}
