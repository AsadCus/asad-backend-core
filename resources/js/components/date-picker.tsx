import { formatDateForDisplay, parseDisplayDate } from '@/lib/utils';
import { CalendarIcon } from 'lucide-react';
import { useState } from 'react';
import { Button } from './ui/button';
import { Calendar } from './ui/calendar';
import { Input } from './ui/input';
import { Popover, PopoverContent, PopoverTrigger } from './ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from './ui/select';

function formatWithTime(dateStr: string, h: number, m: number): string {
    if (!dateStr) return '';
    return `${dateStr} ${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
}

function parseTimeFromValue(value?: string): {
    datePart: string;
    hours: number;
    minutes: number;
} {
    if (!value) return { datePart: '', hours: 0, minutes: 0 };
    const match = value.match(/\s(\d{1,2}):(\d{1,2})\s*$/);
    if (match) {
        return {
            datePart: value.slice(0, match.index).trim(),
            hours: parseInt(match[1], 10),
            minutes: parseInt(match[2], 10),
        };
    }
    return { datePart: value, hours: 0, minutes: 0 };
}

const HOUR_OPTIONS = Array.from({ length: 24 }, (_, i) => i);
const MINUTE_OPTIONS = Array.from({ length: 60 }, (_, i) => i);

export function DatePickerField({
    id,
    value,
    fromYear = new Date().getFullYear() - 3,
    toYear = new Date().getFullYear() + 3,
    disabled,
    disabledDates,
    quickDate = false,
    useTime = false,
    onChange,
}: {
    id: string;
    value?: string;
    fromYear?: number;
    toYear?: number;
    disabled: boolean;
    disabledDates?: (date: Date) => boolean;
    quickDate?: boolean;
    useTime?: boolean;
    onChange: (value: string) => void;
}) {
    const initialTime = parseTimeFromValue(value);

    const [open, setOpen] = useState(false);
    const [selectedDate, setSelectedDate] = useState<Date | undefined>(() =>
        parseDisplayDate(useTime ? initialTime.datePart : value),
    );
    const [month, setMonth] = useState<Date | undefined>(selectedDate);
    const [hours, setHours] = useState(initialTime.hours);
    const [minutes, setMinutes] = useState(initialTime.minutes);
    const resolvedReadOnlyValue =
        disabled && (!value || value.trim().length === 0) ? '-' : (value ?? '');

    function emitDateTime(date: Date | undefined, h: number, m: number) {
        const dateStr = formatDateForDisplay(date);
        onChange(dateStr ? formatWithTime(dateStr, h, m) : '');
    }

    function getQuickDate(type: string) {
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
        }

        return date;
    }

    return (
        <div className="relative">
            <Input
                id={id}
                value={resolvedReadOnlyValue}
                placeholder={
                    useTime
                        ? `${formatDateForDisplay(new Date())} 00:00`
                        : formatDateForDisplay(new Date())
                }
                className={`pr-10 ${disabled ? 'bg-muted/40 text-muted-foreground' : ''}`}
                readOnly={disabled}
                onChange={(e) => {
                    onChange(e.target.value);
                    if (useTime) {
                        const parsed = parseTimeFromValue(e.target.value);
                        const d = parseDisplayDate(parsed.datePart);
                        setSelectedDate(d);
                        setMonth(d);
                        setHours(parsed.hours);
                        setMinutes(parsed.minutes);
                    } else {
                        const parsed = parseDisplayDate(e.target.value);
                        setSelectedDate(parsed);
                        setMonth(parsed);
                    }
                }}
                onKeyDown={(e) => {
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        setOpen(true);
                    }
                }}
                onFocus={(e) => {
                    if (disabled) {
                        e.currentTarget.select();
                    }
                }}
            />

            {!disabled && (
                <Popover open={open} onOpenChange={setOpen}>
                    <PopoverTrigger asChild>
                        <Button
                            variant="ghost"
                            className="absolute top-1/2 right-2 size-6 -translate-y-1/2"
                        >
                            <CalendarIcon className="size-3.5" />
                        </Button>
                    </PopoverTrigger>

                    <PopoverContent
                        className="w-auto overflow-hidden p-0"
                        align="end"
                        alignOffset={-8}
                        sideOffset={10}
                    >
                        <div className="flex justify-between">
                            <div>
                                <Calendar
                                    mode="single"
                                    selected={selectedDate}
                                    captionLayout="dropdown"
                                    month={month}
                                    fromYear={fromYear}
                                    toYear={toYear}
                                    disabled={disabledDates}
                                    onMonthChange={setMonth}
                                    onSelect={(date) => {
                                        setSelectedDate(date ?? undefined);
                                        if (useTime) {
                                            emitDateTime(
                                                date ?? undefined,
                                                hours,
                                                minutes,
                                            );
                                        } else {
                                            onChange(
                                                formatDateForDisplay(
                                                    date ?? undefined,
                                                ),
                                            );
                                            setOpen(false);
                                        }
                                    }}
                                />
                                {useTime && (
                                    <div className="flex items-center gap-2 border-t px-3 py-2">
                                        <span className="text-sm font-medium text-muted-foreground">
                                            Time
                                        </span>
                                        <Select
                                            value={String(hours)}
                                            onValueChange={(v) => {
                                                const h = Number(v);
                                                setHours(h);
                                                emitDateTime(
                                                    selectedDate,
                                                    h,
                                                    minutes,
                                                );
                                            }}
                                        >
                                            <SelectTrigger className="w-[70px]">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {HOUR_OPTIONS.map((i) => (
                                                    <SelectItem
                                                        key={i}
                                                        value={String(i)}
                                                    >
                                                        {String(i).padStart(
                                                            2,
                                                            '0',
                                                        )}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <span className="text-sm font-medium">
                                            :
                                        </span>
                                        <Select
                                            value={String(minutes)}
                                            onValueChange={(v) => {
                                                const m = Number(v);
                                                setMinutes(m);
                                                emitDateTime(
                                                    selectedDate,
                                                    hours,
                                                    m,
                                                );
                                            }}
                                        >
                                            <SelectTrigger className="w-[70px]">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {MINUTE_OPTIONS.map((i) => (
                                                    <SelectItem
                                                        key={i}
                                                        value={String(i)}
                                                    >
                                                        {String(i).padStart(
                                                            2,
                                                            '0',
                                                        )}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                )}
                            </div>
                            {quickDate && (
                                <div className="flex min-w-[160px] flex-col gap-1 border-l p-3">
                                    <p className="mb-2 text-sm font-medium">
                                        Quick Select
                                    </p>

                                    {[
                                        { label: 'In 3 days', value: '3days' },
                                        { label: 'In 1 week', value: '1week' },
                                        {
                                            label: 'In 1 month',
                                            value: '1month',
                                        },
                                        {
                                            label: 'In 3 months',
                                            value: '3months',
                                        },
                                        {
                                            label: 'End of month',
                                            value: 'endmonth',
                                        },
                                        {
                                            label: 'End of next month',
                                            value: 'endnextmonth',
                                        },
                                    ].map((item) => (
                                        <Button
                                            key={item.value}
                                            variant="ghost"
                                            size="sm"
                                            className="justify-start"
                                            onClick={() => {
                                                const date = getQuickDate(
                                                    item.value,
                                                );
                                                setSelectedDate(date);
                                                setMonth(date);
                                                onChange(
                                                    formatDateForDisplay(date),
                                                );
                                            }}
                                        >
                                            {item.label}
                                        </Button>
                                    ))}
                                </div>
                            )}
                        </div>
                    </PopoverContent>
                </Popover>
            )}
        </div>
    );
}
