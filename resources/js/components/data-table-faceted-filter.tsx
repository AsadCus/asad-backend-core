import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
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
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { Column } from '@tanstack/react-table';
import { Check, PlusCircle } from 'lucide-react';
import * as React from 'react';

interface DataTableFacetedFilterProps<TData> {
    column?: Column<TData>;
    title?: string;
    options: {
        label: string;
        value: string;
        icon?: React.ComponentType<{ className?: string }>;
    }[];
}

export function DataTableFacetedFilter<TData>({
    column,
    title,
    options,
}: DataTableFacetedFilterProps<TData>) {
    const selectedValues = new Set(
        ((column?.getFilterValue() as (string | number)[]) ?? []).map(String),
    );

    const facetCounts: Record<string, number> = {};
    const tableRows = column?.getFacetedRowModel()?.rows ?? [];

    for (const row of tableRows) {
        const rawValue = column ? row.getValue(column.id) : null;

        if (rawValue === undefined || rawValue === null) continue;

        const parts = Array.isArray(rawValue)
            ? rawValue.map((v) => String(v).trim()).filter(Boolean)
            : String(rawValue)
                  .split(',')
                  .map((v) => v.trim())
                  .filter(Boolean);

        for (const part of parts) {
            facetCounts[part] = (facetCounts[part] || 0) + 1;
        }
    }

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    className="w-full justify-start border-dashed"
                >
                    <PlusCircle />
                    {title}
                    {selectedValues?.size > 0 && (
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
                                            selectedValues.has(
                                                String(option.value),
                                            ),
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

            <PopoverContent className="w-50 p-0" align="start">
                <Command>
                    <CommandInput placeholder={title} />
                    <CommandList>
                        <CommandEmpty>No results found.</CommandEmpty>
                        <CommandGroup>
                            {options.map((option) => {
                                const valueStr = String(option.value);
                                const isSelected = selectedValues.has(valueStr);
                                const count = facetCounts[valueStr] || 0;

                                return (
                                    <CommandItem
                                        key={valueStr}
                                        onSelect={() => {
                                            if (isSelected) {
                                                selectedValues.delete(valueStr);
                                            } else {
                                                selectedValues.add(valueStr);
                                            }
                                            const filterValues =
                                                Array.from(selectedValues);
                                            column?.setFilterValue(
                                                filterValues.length
                                                    ? filterValues
                                                    : undefined,
                                            );
                                        }}
                                    >
                                        <div
                                            className={cn(
                                                'flex size-4 items-center justify-center rounded-lg border',
                                                isSelected
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'border-input [&_svg]:invisible',
                                            )}
                                        >
                                            <Check className="size-3.5 text-primary-foreground" />
                                        </div>
                                        {option.icon && (
                                            <option.icon className="size-4 text-muted-foreground" />
                                        )}
                                        <span>{option.label}</span>
                                        {count > 0 && (
                                            <span className="ml-auto flex size-4 items-center justify-center font-mono text-sm text-muted-foreground">
                                                {count}
                                            </span>
                                        )}
                                    </CommandItem>
                                );
                            })}
                        </CommandGroup>

                        {selectedValues.size > 0 && (
                            <>
                                <CommandSeparator />
                                <CommandGroup>
                                    <CommandItem
                                        onSelect={() =>
                                            column?.setFilterValue(undefined)
                                        }
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
