import { formatDateForDisplay, parseDisplayDate } from '@/lib/utils';
import { CalendarIcon } from 'lucide-react';
import { useState } from 'react';
import { Button } from './ui/button';
import { Calendar } from './ui/calendar';
import { Input } from './ui/input';
import { Popover, PopoverContent, PopoverTrigger } from './ui/popover';

export function DatePickerField({
    id,
    value,
    fromYear = new Date().getFullYear() - 3,
    toYear = new Date().getFullYear() + 3,
    disabled,
    disabledDates,
    quickDate = false,
    onChange,
}: {
    id: string;
    value?: string;
    fromYear?: number;
    toYear?: number;
    disabled: boolean;
    disabledDates?: (date: Date) => boolean;
    quickDate?: boolean;
    onChange: (value: string) => void;
}) {
    const [open, setOpen] = useState(false);
    const [selectedDate, setSelectedDate] = useState<Date | undefined>(() =>
        parseDisplayDate(value),
    );
    const [month, setMonth] = useState<Date | undefined>(selectedDate);

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
                value={value ?? ''}
                placeholder={formatDateForDisplay(new Date())}
                className="pr-10"
                disabled={disabled}
                onChange={(e) => {
                    onChange(e.target.value);
                    const parsed = parseDisplayDate(e.target.value);
                    setSelectedDate(parsed);
                    setMonth(parsed);
                }}
                onKeyDown={(e) => {
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        setOpen(true);
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
                                    onChange(
                                        formatDateForDisplay(date ?? undefined),
                                    );
                                    setOpen(false);
                                }}
                            />
                            {quickDate && (
                                <div className="flex min-w-[160px] flex-col gap-1 border-l p-3">
                                    <p className="mb-2 text-xs font-medium">
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
