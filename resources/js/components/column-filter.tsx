import { cn } from '@/lib/utils';
import { Table } from '@tanstack/react-table';
import { Check, PlusCircle } from 'lucide-react';
import { DataTableFacetedFilter } from './data-table-faceted-filter';
import { Badge } from './ui/badge';
import { Button } from './ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
} from './ui/command';
import { Popover, PopoverContent, PopoverTrigger } from './ui/popover';
import { Separator } from './ui/separator';

interface Option {
    value: string;
    label: string;
}

interface ColumnFilterProps<TData> {
    table?: Table<TData> | null;
    columnId?: string;
    title: string;
    options: Option[];

    value?: string[];
    onChange?: (value: string[]) => void;
}

export function ColumnFilter<TData>({
    table,
    columnId,
    title,
    options,
    value = [],
    onChange,
}: ColumnFilterProps<TData>) {
    if (table && columnId) {
        const column = table.getColumn(columnId);
        if (!column) return null;

        return (
            <div className="space-y-1">
                {/* <DropdownMenuLabel>{title}</DropdownMenuLabel> */}
                <DataTableFacetedFilter
                    column={column}
                    title={title}
                    options={options}
                />
            </div>
        );
    }

    const selectedValues = new Set(value);

    const handleSelect = (v: string) => {
        const newValues = new Set(selectedValues);
        if (newValues.has(v)) newValues.delete(v);
        else newValues.add(v);
        onChange?.(Array.from(newValues));
    };

    const handleClear = () => onChange?.([]);

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    className="justify-start border-dashed"
                >
                    <PlusCircle className="mr-2" />
                    {title}
                    {selectedValues.size > 0 && (
                        <>
                            <Separator
                                orientation="vertical"
                                className="mx-2 h-4"
                            />
                            <Badge
                                variant="secondary"
                                className="rounded-sm px-1 font-normal lg:hidden"
                            >
                                {selectedValues.size}
                            </Badge>
                            <div className="hidden gap-1 lg:flex">
                                {selectedValues.size > 1 ? (
                                    <Badge
                                        variant="secondary"
                                        className="rounded-sm px-1 font-normal"
                                    >
                                        {selectedValues.size} selected
                                    </Badge>
                                ) : (
                                    options
                                        .filter((option) =>
                                            selectedValues.has(option.value),
                                        )
                                        .map((option) => (
                                            <Badge
                                                variant="secondary"
                                                key={option.value}
                                                className="rounded-sm px-1 font-normal"
                                            >
                                                {option.label.length > 15
                                                    ? option.label.slice(
                                                          0,
                                                          15,
                                                      ) + '...'
                                                    : option.label}
                                            </Badge>
                                        ))
                                )}
                            </div>
                        </>
                    )}
                </Button>
            </PopoverTrigger>

            <PopoverContent className="w-[200px] p-0" align="start">
                <Command>
                    <CommandInput placeholder={title} />
                    <CommandList>
                        <CommandEmpty>No results found.</CommandEmpty>
                        <CommandGroup>
                            {options.map((option) => {
                                const isSelected = selectedValues.has(
                                    option.value,
                                );

                                return (
                                    <CommandItem
                                        key={option.value}
                                        onSelect={() =>
                                            handleSelect(option.value)
                                        }
                                    >
                                        <div
                                            className={cn(
                                                'flex size-4 items-center justify-center rounded-[4px] border',
                                                isSelected
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'border-input [&_svg]:invisible',
                                            )}
                                        >
                                            <Check className="size-3.5 text-primary-foreground" />
                                        </div>
                                        <span className="ml-2">
                                            {option.label}
                                        </span>
                                    </CommandItem>
                                );
                            })}
                        </CommandGroup>

                        {selectedValues.size > 0 && (
                            <>
                                <CommandSeparator />
                                <CommandGroup>
                                    <CommandItem
                                        onSelect={handleClear}
                                        className="justify-center text-center"
                                    >
                                        Clear filters
                                    </CommandItem>
                                </CommandGroup>
                            </>
                        )}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
