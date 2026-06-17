import { Check, ChevronsUpDown, X } from 'lucide-react';
import * as React from 'react';

import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

export interface ComboboxOption {
    value: string | number;
    label: string;
}

interface ComboboxProps {
    options: ComboboxOption[];
    value?: string | number;
    onChange: (value: string | number) => void;
    placeholder?: string;
    disabled?: boolean;
    className?: string;
    truncate?: number;
    clearable?: boolean;
}

export function Combobox({
    options,
    value,
    onChange,
    placeholder = 'Select option...',
    disabled = false,
    className,
    truncate = 100,
    clearable = false,
}: ComboboxProps) {
    const [open, setOpen] = React.useState(false);

    const selected = options.find(
        (option) => String(option.value) === String(value),
    );

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    role="combobox"
                    disabled={disabled}
                    aria-expanded={open}
                    className={cn(
                        'w-full justify-between bg-transparent',
                        !selected && 'text-muted-foreground',
                        className,
                    )}
                >
                    {selected
                        ? selected.label.length > truncate
                            ? selected.label.slice(0, truncate) + '…'
                            : selected.label
                        : placeholder}
                    <span className="ml-2 flex items-center gap-1">
                        {clearable && selected && !disabled && (
                            <span
                                role="button"
                                tabIndex={0}
                                aria-label="Clear selection"
                                className="rounded-sm opacity-50 hover:opacity-100"
                                onPointerDown={(e) => e.preventDefault()}
                                onClick={(e) => {
                                    e.stopPropagation();
                                    onChange('');
                                }}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter' || e.key === ' ') {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        onChange('');
                                    }
                                }}
                            >
                                <X className="h-4 w-4" />
                            </span>
                        )}
                        <ChevronsUpDown className="h-4 w-4 opacity-50" />
                    </span>
                </Button>
            </PopoverTrigger>

            <PopoverContent align="start" className="w-auto p-0">
                <Command>
                    <CommandInput placeholder="Search..." className="h-9" />
                    <CommandList>
                        <CommandEmpty>No results found.</CommandEmpty>
                        <CommandGroup>
                            {options.map((option) => (
                                <CommandItem
                                    key={option.value}
                                    value={`${option.label} ${option.value}`}
                                    onSelect={() => {
                                        onChange(option.value);
                                        setOpen(false);
                                    }}
                                >
                                    {option.label}
                                    <Check
                                        className={cn(
                                            'ml-auto h-4 w-4',
                                            String(option.value) ===
                                                String(value)
                                                ? 'opacity-100'
                                                : 'opacity-0',
                                        )}
                                    />
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
