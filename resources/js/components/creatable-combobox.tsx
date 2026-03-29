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
    onCreateOption?: (
        option: CreatableComboboxOption,
    ) =>
        | void
        | CreatableComboboxOption
        | Promise<void | CreatableComboboxOption>;
    onCreateRequest?: (payload: {
        option: CreatableComboboxOption;
        query: string;
    }) => void;
    placeholder?: string;
    searchPlaceholder?: string;
    disabled?: boolean;
    triggerId?: string;
    className?: string;
    createActionLabel?: string;
    createEmptyLabel?: string;
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
    onCreateRequest,
    placeholder = 'Select option...',
    searchPlaceholder = 'Search...',
    disabled = false,
    triggerId,
    className,
    createActionLabel,
    createEmptyLabel = 'Create new option',
}: CreatableComboboxProps) {
    const [open, setOpen] = React.useState(false);
    const [query, setQuery] = React.useState('');

    const normalizedQuery = toValue(query);

    const selected = options.find((option) => option.value === value);
    const canCreate =
        normalizedQuery !== '' &&
        !options.some((option) => option.value === normalizedQuery);
    const canRequestCreate = typeof onCreateRequest === 'function';
    const showCreateAction = canCreate || canRequestCreate;

    const handleCreate = React.useCallback(async () => {
        if (!canCreate && !canRequestCreate) {
            return;
        }

        const option: CreatableComboboxOption = {
            value: normalizedQuery,
            label: toLabel(query) || query,
        };

        if (onCreateRequest) {
            onCreateRequest({ option, query });
            setOpen(false);
            setQuery('');

            return;
        }

        const createdOption = await onCreateOption?.(option);
        const selectedOption = createdOption ?? option;

        if (selectedOption.value) {
            onChange(selectedOption.value);
        }

        setOpen(false);
        setQuery('');
    }, [
        canCreate,
        canRequestCreate,
        normalizedQuery,
        onChange,
        onCreateOption,
        onCreateRequest,
        query,
    ]);

    const createLabel =
        createActionLabel ??
        (normalizedQuery
            ? `Create "${toLabel(query) || query}"`
            : createEmptyLabel);

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
                            {showCreateAction && (
                                <CommandItem
                                    value={`create ${normalizedQuery || 'new'}`}
                                    onSelect={handleCreate}
                                >
                                    <Plus className="mr-2 h-4 w-4" />
                                    {createLabel}
                                </CommandItem>
                            )}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
