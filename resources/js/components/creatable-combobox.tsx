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
import { Check, ChevronsUpDown, Plus } from 'lucide-react';
import * as React from 'react';

export interface CreatableComboboxOption {
    value: string;
    label: string;
}

interface CreatableComboboxProps {
    options: CreatableComboboxOption[];
    value?: string;
    onChange: (value: string) => void;
    onCreateOption?: (option: CreatableComboboxOption) => void;
    placeholder?: string;
    searchPlaceholder?: string;
    disabled?: boolean;
    triggerId?: string;
    className?: string;
}

function toLabel(value: string): string {
    return value
        .split(/[_\s-]+/)
        .filter((part) => part !== '')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function toValue(raw: string): string {
    return raw
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '');
}

export function CreatableCombobox({
    options,
    value,
    onChange,
    onCreateOption,
    placeholder = 'Select option...',
    searchPlaceholder = 'Search...',
    disabled = false,
    triggerId,
    className,
}: CreatableComboboxProps) {
    const [open, setOpen] = React.useState(false);
    const [query, setQuery] = React.useState('');

    const normalizedQuery = toValue(query);

    const selected = options.find((option) => option.value === value);
    const canCreate =
        normalizedQuery !== '' &&
        !options.some((option) => option.value === normalizedQuery);

    const handleCreate = React.useCallback(() => {
        if (!canCreate) {
            return;
        }

        const option = {
            value: normalizedQuery,
            label: toLabel(query) || query,
        };

        onCreateOption?.(option);
        onChange(option.value);
        setOpen(false);
        setQuery('');
    }, [canCreate, normalizedQuery, onChange, onCreateOption, query]);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    id={triggerId}
                    variant="outline"
                    role="combobox"
                    disabled={disabled}
                    aria-expanded={open}
                    className={cn(
                        'w-full justify-between bg-transparent',
                        !selected && !value && 'text-muted-foreground',
                        className,
                    )}
                >
                    {selected?.label ?? (value ? toLabel(value) : placeholder)}
                    <ChevronsUpDown className="ml-2 h-4 w-4 opacity-50" />
                </Button>
            </PopoverTrigger>

            <PopoverContent
                align="start"
                className="w-[--radix-popover-trigger-width] p-0"
            >
                <Command>
                    <CommandInput
                        placeholder={searchPlaceholder}
                        className="h-9"
                        value={query}
                        onValueChange={setQuery}
                    />
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
                                        setQuery('');
                                    }}
                                >
                                    {option.label}
                                    <Check
                                        className={cn(
                                            'ml-auto h-4 w-4',
                                            option.value === value
                                                ? 'opacity-100'
                                                : 'opacity-0',
                                        )}
                                    />
                                </CommandItem>
                            ))}
                            {canCreate && (
                                <CommandItem
                                    value={`create ${normalizedQuery}`}
                                    onSelect={handleCreate}
                                >
                                    <Plus className="mr-2 h-4 w-4" />
                                    Create "{toLabel(query) || query}"
                                </CommandItem>
                            )}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
