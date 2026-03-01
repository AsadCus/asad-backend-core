import {
    Command,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { OptionType } from '@/types';
import { CheckIcon, ChevronDown, X } from 'lucide-react';
import * as React from 'react';
import { Separator } from './ui/separator';

interface ProperInputSelectProps {
    options: OptionType[];
    value?: string | number;
    onValueChange: (value: string | number) => void;
    placeholder?: string;
    disabled?: boolean;
    className?: string;
    truncate?: number;
    searchable?: boolean;
    autoCloseOnSelect?: boolean;
}

export function ProperInputSelect({
    options,
    value = '',
    onValueChange,
    placeholder = 'Select...',
    disabled = false,
    className,
    truncate = 100,
    searchable = true,
    autoCloseOnSelect = true,
}: ProperInputSelectProps) {
    const [isPopoverOpen, setIsPopoverOpen] = React.useState(false);

    const selectedValue = value ?? '';
    const selected = options.find(
        (option) => String(option.value) === String(selectedValue),
    );

    const onOptionSelect = (option: string) => {
        onValueChange?.(option);
        if (autoCloseOnSelect) {
            setIsPopoverOpen(false);
        }
    };

    const onClearAllOptions = () => {
        onValueChange?.('');
    };

    const truncateLabel = (label: string, maxLength: number): string => {
        return label.length > maxLength
            ? label.slice(0, maxLength) + '…'
            : label;
    };

    const displayLabel = selected
        ? truncateLabel(selected.label, truncate)
        : placeholder;

    return (
        <Popover open={isPopoverOpen} onOpenChange={setIsPopoverOpen}>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    onClick={() => setIsPopoverOpen((prev) => !prev)}
                    role="combobox"
                    disabled={disabled}
                    aria-expanded={isPopoverOpen}
                    data-placeholder={!selected ? '' : undefined}
                    className={cn(
                        "flex h-9 w-full items-center justify-between rounded-md border border-input bg-transparent px-3 py-2 text-base whitespace-nowrap shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 data-[placeholder]:text-muted-foreground dark:bg-input/30 dark:hover:bg-input/50 dark:aria-invalid:ring-destructive/40 [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4 [&_svg:not([class*='text-'])]:text-muted-foreground",
                        className,
                    )}
                >
                    {selected ? (
                        <div className="flex w-full items-center gap-2">
                            <span className="line-clamp-1 flex-1 text-left">
                                {displayLabel}
                            </span>
                            <div className="flex items-center gap-1">
                                {selectedValue && (
                                    <>
                                        <X
                                            className="size-4 cursor-pointer text-muted-foreground"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                onClearAllOptions();
                                            }}
                                        />
                                        <Separator
                                            orientation="vertical"
                                            className="mx-1 flex h-full min-h-6"
                                        />
                                    </>
                                )}
                                <ChevronDown className="size-4 opacity-50" />
                            </div>
                        </div>
                    ) : (
                        <div className="flex w-full items-center justify-between gap-2">
                            <span className="line-clamp-1 text-left text-muted-foreground">
                                {placeholder}
                            </span>
                            <ChevronDown className="size-4 opacity-50" />
                        </div>
                    )}
                </button>
            </PopoverTrigger>
            <PopoverContent
                className={cn(
                    'relative z-50 max-h-96 w-[var(--radix-popover-trigger-width)] overflow-hidden rounded-md border bg-popover p-0 text-popover-foreground shadow-md',
                    className,
                )}
                align="start"
            >
                <Command>
                    {searchable && <CommandInput placeholder="Search..." />}
                    <CommandList className="max-h-96">
                        <CommandGroup className="max-h-80 overflow-y-auto p-1">
                            {options.map((option) => {
                                const isSelected =
                                    String(selectedValue) ===
                                    String(option.value);
                                return (
                                    <CommandItem
                                        key={option.value}
                                        onSelect={() =>
                                            onOptionSelect(option.value)
                                        }
                                        className="relative flex w-full cursor-default items-center gap-2 rounded-sm py-1.5 pr-8 pl-2 text-base outline-hidden select-none focus:bg-accent focus:text-accent-foreground"
                                    >
                                        <span className="line-clamp-1">
                                            {option.label}
                                        </span>
                                        <span
                                            className={cn(
                                                'absolute right-2 flex size-3.5 items-center justify-center',
                                                !isSelected && 'invisible',
                                            )}
                                        >
                                            <CheckIcon className="size-4" />
                                        </span>
                                    </CommandItem>
                                );
                            })}
                        </CommandGroup>
                        <CommandSeparator />
                        <CommandGroup>
                            <div className="flex items-center justify-between">
                                {selectedValue && (
                                    <>
                                        <CommandItem
                                            onSelect={onClearAllOptions}
                                            className="flex-1 cursor-pointer justify-center"
                                        >
                                            Clear
                                        </CommandItem>
                                        <Separator
                                            orientation="vertical"
                                            className="mx-2 flex h-full min-h-6"
                                        />
                                    </>
                                )}
                                <CommandItem
                                    onSelect={() => setIsPopoverOpen(false)}
                                    className="max-w-full flex-1 cursor-pointer justify-center"
                                >
                                    Close
                                </CommandItem>
                            </div>
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
