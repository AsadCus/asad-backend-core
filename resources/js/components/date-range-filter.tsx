'use client';

import {
    QUICK_DATE_RANGE_OPTIONS,
    QUICK_DATE_SINGLE_OPTIONS,
    QuickDateKey,
    resolveQuickDateRange,
} from '@/lib/quick-date';
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
    dash?: boolean;
    align?: 'end' | 'center' | 'start' | undefined;

    value?: { from?: string; to?: string };
    onChange?: (value: { from?: string; to?: string }) => void;
}

export function DateRangeFilter<TData>({
    table,
    columnId,
    title,
    fromYear = new Date().getFullYear() - 3,
    toYear = new Date().getFullYear() + 3,
    quickDate = false,
    compact = false,
    dash = true,
    align = 'end',
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

    const applyQuickDate = (type: QuickDateKey) => {
        const { from: fromDate, to: toDate } = resolveQuickDateRange(type);

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
                                ? `h-9 justify-start gap-1.5 ${dash ? 'border-dashed' : ''}`
                                : `w-full justify-start ${dash ? 'border-dashed' : ''}`
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
                    align={align}
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

                                        {[
                                            ...QUICK_DATE_SINGLE_OPTIONS,
                                            ...QUICK_DATE_RANGE_OPTIONS,
                                        ].map((item) => (
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
                                        ))}
                                    </div>
                                )}
                            </div>

                            {quickDate && (
                                <div className="hidden min-w-[160px] flex-col gap-1 border-l pl-4 md:flex">
                                    <p className="mb-2 text-sm font-medium">
                                        Quick Select
                                    </p>

                                    {[
                                        ...QUICK_DATE_SINGLE_OPTIONS,
                                        ...QUICK_DATE_RANGE_OPTIONS,
                                    ].map((item) => (
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
                                    ))}
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
