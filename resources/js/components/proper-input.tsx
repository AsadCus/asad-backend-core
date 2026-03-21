import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { Clock3 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type TimeFormat = 12 | 24;

const HOURS_24_OPTIONS = Array.from({ length: 24 }, (_, i) => i);
const HOURS_12_OPTIONS = Array.from({ length: 12 }, (_, i) => i + 1);
const MINUTE_OPTIONS = Array.from({ length: 60 }, (_, i) => i);

function parseTimeValue(value: string): {
    hours: number;
    minutes: number;
    meridiem: 'AM' | 'PM';
} | null {
    if (!value) {
        return null;
    }

    const parsedValue = value.trim();
    const twelveHourMatch = parsedValue.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i);

    if (twelveHourMatch) {
        let hours = Number(twelveHourMatch[1]);
        const minutes = Number(twelveHourMatch[2]);
        const meridiem = twelveHourMatch[3].toUpperCase() as 'AM' | 'PM';

        if (hours < 1 || hours > 12 || minutes < 0 || minutes > 59) {
            return null;
        }

        if (meridiem === 'AM' && hours === 12) {
            hours = 0;
        } else if (meridiem === 'PM' && hours !== 12) {
            hours += 12;
        }

        return { hours, minutes, meridiem };
    }

    const twentyFourHourMatch = parsedValue.match(/^(\d{1,2}):(\d{2})$/);

    if (!twentyFourHourMatch) {
        return null;
    }

    const hours = Number(twentyFourHourMatch[1]);
    const minutes = Number(twentyFourHourMatch[2]);

    if (hours < 0 || hours > 23 || minutes < 0 || minutes > 59) {
        return null;
    }

    return {
        hours,
        minutes,
        meridiem: hours >= 12 ? 'PM' : 'AM',
    };
}

function formatTimeValue(
    hours24: number,
    minutes: number,
    timeFormat: TimeFormat,
    meridiemOverride?: 'AM' | 'PM',
): string {
    const safeMinutes = String(minutes).padStart(2, '0');

    if (timeFormat === 24) {
        return `${String(hours24).padStart(2, '0')}:${safeMinutes}`;
    }

    let hours12 = hours24 % 12;

    if (hours12 === 0) {
        hours12 = 12;
    }

    const meridiem = meridiemOverride ?? (hours24 >= 12 ? 'PM' : 'AM');

    return `${String(hours12).padStart(2, '0')}:${safeMinutes} ${meridiem}`;
}

export function ProperInput({
    id,
    value,
    onCommit,
    placeholder,
    disabled,
    textarea = false,
    type = 'text',
    timeFormat = 24,
    size = 'default',
    className,
    inputProps,
}: {
    id?: string;
    value: string | number | null;
    onCommit: (v: string) => void;
    placeholder?: string;
    disabled?: boolean;
    textarea?: boolean;
    type?: 'text' | 'number' | 'time';
    timeFormat?: TimeFormat;
    size?: 'compact' | 'default';
    className?: string;
    inputProps?: React.InputHTMLAttributes<HTMLInputElement>;
    rows?: number;
}) {
    const [local, setLocal] = useState(String(value ?? ''));
    const [timePickerOpen, setTimePickerOpen] = useState(false);
    const [selectedHours24, setSelectedHours24] = useState(0);
    const [selectedMinutes, setSelectedMinutes] = useState(0);
    const [selectedMeridiem, setSelectedMeridiem] = useState<'AM' | 'PM'>('AM');
    const latestLocalRef = useRef(local);
    const latestValueRef = useRef(String(value ?? ''));
    const latestOnCommitRef = useRef(onCommit);

    useEffect(() => {
        latestLocalRef.current = local;
    }, [local]);

    useEffect(() => {
        latestValueRef.current = String(value ?? '');
    }, [value]);

    useEffect(() => {
        latestOnCommitRef.current = onCommit;
    }, [onCommit]);

    useEffect(() => {
        return () => {
            const valueToCommit = latestLocalRef.current;

            if (latestValueRef.current !== valueToCommit) {
                latestOnCommitRef.current(valueToCommit);
            }
        };
    }, []);

    useEffect(() => {
        const nextLocal = String(value ?? '');
        setLocal(nextLocal);

        if (type !== 'time') {
            return;
        }

        const parsed = parseTimeValue(nextLocal);

        if (!parsed) {
            return;
        }

        setSelectedHours24(parsed.hours);
        setSelectedMinutes(parsed.minutes);
        setSelectedMeridiem(parsed.meridiem);
    }, [type, value]);

    const commit = (nextValue?: string) => {
        const valueToCommit = nextValue ?? local;
        if (String(value ?? '') !== valueToCommit) {
            onCommit(valueToCommit);
        }
    };

    const emitSelectedTime = (
        hours24: number,
        minutes: number,
        meridiem: 'AM' | 'PM',
    ) => {
        const nextValue = formatTimeValue(
            hours24,
            minutes,
            timeFormat,
            meridiem,
        );
        setLocal(nextValue);
        commit(nextValue);
    };

    if (textarea) {
        return (
            <Textarea
                id={id}
                value={local}
                placeholder={placeholder}
                disabled={disabled}
                className={cn(
                    size === 'compact'
                        ? 'min-h-[36px] px-2 py-1 text-base sm:min-h-[48px]'
                        : '',
                    className,
                )}
                onChange={(e) => setLocal(e.target.value)}
                onBlur={() => commit()}
            />
        );
    }

    if (type === 'time') {
        const hourOptions =
            timeFormat === 24 ? HOURS_24_OPTIONS : HOURS_12_OPTIONS;
        const selectedHourForDropdown =
            timeFormat === 24
                ? selectedHours24
                : selectedHours24 % 12 === 0
                  ? 12
                  : selectedHours24 % 12;

        return (
            <div className="relative">
                <Input
                    id={id}
                    type="text"
                    value={local}
                    placeholder={timeFormat === 24 ? 'HH:mm' : 'hh:mm AM/PM'}
                    disabled={disabled}
                    className={cn(
                        'pr-10',
                        size === 'compact'
                            ? 'h-6 px-2 py-1 text-base sm:h-7'
                            : '',
                        className,
                    )}
                    onChange={(e) => {
                        const nextValue = e.target.value;
                        setLocal(nextValue);

                        const parsed = parseTimeValue(nextValue);
                        if (parsed) {
                            setSelectedHours24(parsed.hours);
                            setSelectedMinutes(parsed.minutes);
                            setSelectedMeridiem(parsed.meridiem);
                        }
                    }}
                    onBlur={() => commit()}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            commit();
                        }

                        if (e.key === 'ArrowDown' && !disabled) {
                            e.preventDefault();
                            setTimePickerOpen(true);
                        }
                    }}
                    {...inputProps}
                />

                {!disabled && (
                    <Popover
                        open={timePickerOpen}
                        onOpenChange={setTimePickerOpen}
                    >
                        <PopoverTrigger asChild>
                            <Button
                                variant="ghost"
                                type="button"
                                className="absolute top-1/2 right-2 size-6 -translate-y-1/2"
                            >
                                <Clock3 className="size-3.5" />
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent
                            className="w-auto p-3"
                            align="end"
                            sideOffset={10}
                        >
                            <div className="flex items-center gap-2">
                                <Select
                                    value={String(selectedHourForDropdown)}
                                    onValueChange={(nextHourRaw) => {
                                        const nextHourValue =
                                            Number(nextHourRaw);
                                        if (Number.isNaN(nextHourValue)) {
                                            return;
                                        }

                                        if (timeFormat === 24) {
                                            setSelectedHours24(nextHourValue);
                                            emitSelectedTime(
                                                nextHourValue,
                                                selectedMinutes,
                                                selectedMeridiem,
                                            );

                                            return;
                                        }

                                        const normalizedHour =
                                            nextHourValue % 12;
                                        const nextHours24 =
                                            selectedMeridiem === 'PM'
                                                ? normalizedHour + 12
                                                : normalizedHour;

                                        setSelectedHours24(nextHours24 % 24);
                                        emitSelectedTime(
                                            nextHours24 % 24,
                                            selectedMinutes,
                                            selectedMeridiem,
                                        );
                                    }}
                                >
                                    <SelectTrigger className="w-[76px]">
                                        <SelectValue placeholder="Hour" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {hourOptions.map((hourValue) => (
                                            <SelectItem
                                                key={hourValue}
                                                value={String(hourValue)}
                                            >
                                                {String(hourValue).padStart(
                                                    2,
                                                    '0',
                                                )}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                <span className="text-sm font-medium">:</span>

                                <Select
                                    value={String(selectedMinutes)}
                                    onValueChange={(nextMinuteRaw) => {
                                        const nextMinuteValue =
                                            Number(nextMinuteRaw);
                                        if (Number.isNaN(nextMinuteValue)) {
                                            return;
                                        }

                                        setSelectedMinutes(nextMinuteValue);
                                        emitSelectedTime(
                                            selectedHours24,
                                            nextMinuteValue,
                                            selectedMeridiem,
                                        );
                                    }}
                                >
                                    <SelectTrigger className="w-[76px]">
                                        <SelectValue placeholder="Min" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {MINUTE_OPTIONS.map((minuteValue) => (
                                            <SelectItem
                                                key={minuteValue}
                                                value={String(minuteValue)}
                                            >
                                                {String(minuteValue).padStart(
                                                    2,
                                                    '0',
                                                )}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                {timeFormat === 12 && (
                                    <Select
                                        value={selectedMeridiem}
                                        onValueChange={(nextMeridiemRaw) => {
                                            const nextMeridiem =
                                                nextMeridiemRaw === 'PM'
                                                    ? 'PM'
                                                    : 'AM';
                                            setSelectedMeridiem(nextMeridiem);

                                            const baseHour =
                                                selectedHours24 % 12;
                                            const nextHours24 =
                                                nextMeridiem === 'PM'
                                                    ? baseHour + 12
                                                    : baseHour;

                                            setSelectedHours24(
                                                nextHours24 % 24,
                                            );
                                            emitSelectedTime(
                                                nextHours24 % 24,
                                                selectedMinutes,
                                                nextMeridiem,
                                            );
                                        }}
                                    >
                                        <SelectTrigger className="w-[82px]">
                                            <SelectValue placeholder="AM/PM" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="AM">
                                                AM
                                            </SelectItem>
                                            <SelectItem value="PM">
                                                PM
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                )}
                            </div>
                        </PopoverContent>
                    </Popover>
                )}
            </div>
        );
    }

    return (
        <Input
            id={id}
            type={type}
            value={local}
            placeholder={placeholder}
            disabled={disabled}
            className={cn(
                size === 'compact' ? 'h-6 px-2 py-1 text-base sm:h-7' : '',
                className,
            )}
            inputMode={type === 'number' ? 'decimal' : undefined}
            onChange={(e) => setLocal(e.target.value)}
            onBlur={() => commit()}
            onKeyDown={(e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    commit();
                }
            }}
            {...inputProps}
        />
    );
}
