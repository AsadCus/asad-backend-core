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
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { quotationItemsList } from '@/routes';
import { Check, FileText, Folder } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { ProperInput } from '../../../../components/proper-input';
import { QuotationItemSchema } from '../schema';

export type ItemSelectionPayload =
    | {
        type: 'single';
        item: QuotationItemSchema;
    }
    | {
        type: 'group';
        parent: QuotationItemSchema;
        children: QuotationItemSchema[];
    };

interface ItemDescriptionComboboxProps {
    value: string;
    disabled?: boolean;
    isHeader?: boolean;
    isChild?: boolean;
    existingItemKeys: string[];
    onSelect: (payload: ItemSelectionPayload) => void;
    onChange: (value: string) => void;
    className?: string;
}

export default function ItemDescriptionCombobox({
    value,
    disabled = false,
    isHeader = false,
    isChild = false,
    existingItemKeys,
    onSelect,
    onChange,
    className,
}: ItemDescriptionComboboxProps) {
    const [open, setOpen] = useState(false);
    const [options, setOptions] = useState<QuotationItemSchema[]>([]);
    const [loading, setLoading] = useState(false);
    const [searchValue, setSearchValue] = useState('');

    const fetchItems = useCallback(async () => {
        setLoading(true);
        try {
            const response = await fetch(quotationItemsList.url());
            const data = await response.json();
            const filtered = data.filter(
                (item: QuotationItemSchema) =>
                    !existingItemKeys.includes(`id-${item.id}`),
            );
            setOptions(filtered);
        } catch (error) {
            console.error('Failed to fetch items:', error);
        } finally {
            setLoading(false);
        }
    }, [existingItemKeys]);

    useEffect(() => {
        if (open && !disabled) {
            fetchItems();
        }
    }, [open, disabled, fetchItems]);

    const handleSelect = (payload: ItemSelectionPayload) => {
        onSelect(payload);
        setOpen(false);
        setSearchValue('');
    };

    const handleSearchChange = (search: string) => {
        setSearchValue(search);
    };

    const handleUseCustomText = () => {
        onChange(searchValue);
        setOpen(false);
        setSearchValue('');
    };

    const groupedItems = options.reduce(
        (acc, item) => {
            if (item.parent_id === null || item.parent_id === undefined) {
                if (!acc[item.id!]) {
                    acc[item.id!] = {
                        parent: item,
                        children: [],
                    };
                } else {
                    acc[item.id!].parent = item;
                }
            } else {
                if (!acc[item.parent_id]) {
                    acc[item.parent_id] = {
                        parent: null,
                        children: [],
                    };
                }
                acc[item.parent_id].children.push(item);
            }
            return acc;
        },
        {} as Record<
            number,
            {
                parent: QuotationItemSchema | null;
                children: QuotationItemSchema[];
            }
        >,
    );

    const filteredGroups = Object.entries(groupedItems).filter(([, group]) => {
        if (!searchValue) return true;

        const searchLower = searchValue.toLowerCase();
        const parentMatches = group.parent?.description
            ?.toLowerCase()
            .includes(searchLower);

        const childMatches = group.children.some((child) =>
            child.description?.toLowerCase().includes(searchLower),
        );

        return parentMatches || childMatches;
    });

    if (disabled) {
        return (
            <ProperInput
                value={value}
                disabled={disabled}
                textarea={!isHeader}
                size="compact"
                className={cn(isHeader && 'font-semibold', className)}
                onCommit={onChange}
            />
        );
    }

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <div 
                    onClick={(e) => {
                        if (!disabled && !open) {
                            e.preventDefault();
                            setOpen(true);
                        }
                    }}
                    onMouseDown={(e) => {
                        if (!disabled && !open) {
                            e.preventDefault();
                        }
                    }}
                >
                    <ProperInput
                        value={value}
                        disabled={disabled}
                        textarea={!isHeader}
                        size="compact"
                        className={cn(isHeader && 'font-semibold', className)}
                        placeholder="Select item or type..."
                        onCommit={(v) => {
                            if (v !== value) {
                                onChange(v);
                            }
                        }}
                    />
                </div>
            </PopoverTrigger>
            <PopoverContent className="w-[500px] p-0" align="start">
                <Command shouldFilter={false}>
                    <CommandInput
                        placeholder="Search or type new description..."
                        value={searchValue}
                        onValueChange={handleSearchChange}
                    />
                    <CommandList>
                        <CommandEmpty>
                            {loading ? (
                                <div className="py-6 text-center text-base">
                                    Loading items...
                                </div>
                            ) : (
                                <div className="py-6 text-center text-base">
                                    <p className="text-muted-foreground">
                                        {searchValue
                                            ? 'No matching items found.'
                                            : 'No items available.'}
                                    </p>
                                    {searchValue && (
                                        <p className="mt-2 text-sm">
                                            Click below to use custom text
                                        </p>
                                    )}
                                </div>
                            )}
                        </CommandEmpty>

                        {filteredGroups.length > 0 && (
                            <CommandGroup heading="Available Items">
                                {filteredGroups.map(([parentId, group]) => {
                                    const hasChildren =
                                        group.children.length > 0;

                                    const parent = group.parent;
                                    const parentMatches =
                                        !searchValue ||
                                        parent?.description
                                            ?.toLowerCase()
                                            .includes(
                                                searchValue.toLowerCase(),
                                            );

                                    return (
                                        <div key={parentId}>
                                            {parent &&
                                                parentMatches &&
                                                !isChild && (
                                                    <CommandItem
                                                        value={`parent-${parent.id}-${parent.description}`}
                                                        onSelect={() => {
                                                            return handleSelect(
                                                                {
                                                                    type: 'group',
                                                                    parent: parent,
                                                                    children:
                                                                        group.children,
                                                                },
                                                            );
                                                        }}
                                                        className="cursor-pointer"
                                                    >
                                                        <div className="flex w-full items-start gap-2">
                                                            {hasChildren ? (
                                                                <Folder className="h-4 w-4 shrink-0 text-blue-500" />
                                                            ) : (
                                                                <FileText className="h-4 w-4 shrink-0 text-muted-foreground" />
                                                            )}
                                                            <div className="flex flex-1 flex-col">
                                                                <span className="font-medium">
                                                                    {
                                                                        parent.description
                                                                    }
                                                                </span>

                                                                {(parent.quantity ||
                                                                    parent.rate) && (
                                                                        <span className="text-sm text-muted-foreground">
                                                                            Qty:{' '}
                                                                            {parent.quantity ||
                                                                                '-'}{' '}
                                                                            | Cost:
                                                                            $
                                                                            {parent.rate ||
                                                                                '-'}
                                                                            {parent.is_header &&
                                                                                ' | Header'}
                                                                            {parent.is_optional &&
                                                                                ' | Optional'}
                                                                        </span>
                                                                    )}
                                                            </div>
                                                            <Check
                                                                className={cn(
                                                                    'h-4 w-4 shrink-0',
                                                                    value ===
                                                                        parent.description
                                                                        ? 'opacity-100'
                                                                        : 'opacity-0',
                                                                )}
                                                            />
                                                        </div>
                                                    </CommandItem>
                                                )}

                                            {!isChild &&
                                                hasChildren &&
                                                group.children.map((child) => {
                                                    const childMatches =
                                                        !searchValue ||
                                                        child.description
                                                            ?.toLowerCase()
                                                            .includes(
                                                                searchValue.toLowerCase(),
                                                            );

                                                    if (!childMatches)
                                                        return null;

                                                    return (
                                                        <CommandItem
                                                            key={child.id}
                                                            value={`child-${child.id}-${child.description}`}
                                                            onSelect={() =>
                                                                handleSelect({
                                                                    type: 'single',
                                                                    item: child,
                                                                })
                                                            }
                                                            className="cursor-pointer pl-8"
                                                        >
                                                            <div className="flex w-full items-center gap-2">
                                                                <FileText className="h-3 w-3 shrink-0 text-muted-foreground" />
                                                                <div className="flex flex-1 flex-col">
                                                                    <span className="text-base">
                                                                        {
                                                                            child.description
                                                                        }
                                                                    </span>
                                                                    {(child.quantity ||
                                                                        child.rate) && (
                                                                            <span className="text-sm text-muted-foreground">
                                                                                Qty:{' '}
                                                                                {child.quantity ||
                                                                                    '-'}{' '}
                                                                                |
                                                                                Cost:
                                                                                $
                                                                                {child.rate ||
                                                                                    '-'}
                                                                                {child.is_optional &&
                                                                                    ' | Optional'}
                                                                            </span>
                                                                        )}
                                                                </div>
                                                                <Check
                                                                    className={cn(
                                                                        'h-4 w-4 shrink-0',
                                                                        value ===
                                                                            child.description
                                                                            ? 'opacity-100'
                                                                            : 'opacity-0',
                                                                    )}
                                                                />
                                                            </div>
                                                        </CommandItem>
                                                    );
                                                })}

                                            {isChild &&
                                                parent &&
                                                !hasChildren &&
                                                parentMatches && (
                                                    <CommandItem
                                                        value={`parent-${parent.id}-${parent.description}`}
                                                        onSelect={() =>
                                                            handleSelect({
                                                                type: 'single',
                                                                item: parent,
                                                            })
                                                        }
                                                        className="cursor-pointer"
                                                    >
                                                        <div className="flex w-full items-center gap-2">
                                                            <FileText className="h-4 w-4 shrink-0 text-muted-foreground" />
                                                            <div className="flex flex-1 flex-col">
                                                                <span className="font-medium">
                                                                    {
                                                                        parent.description
                                                                    }
                                                                </span>
                                                                {(parent.quantity ||
                                                                    parent.rate) && (
                                                                        <span className="text-sm text-muted-foreground">
                                                                            Qty:{' '}
                                                                            {parent.quantity ||
                                                                                '-'}{' '}
                                                                            | Cost:
                                                                            $
                                                                            {parent.rate ||
                                                                                '-'}
                                                                            {parent.is_optional &&
                                                                                ' | Optional'}
                                                                        </span>
                                                                    )}
                                                            </div>
                                                            <Check
                                                                className={cn(
                                                                    'h-4 w-4 shrink-0',
                                                                    value ===
                                                                        parent.description
                                                                        ? 'opacity-100'
                                                                        : 'opacity-0',
                                                                )}
                                                            />
                                                        </div>
                                                    </CommandItem>
                                                )}
                                        </div>
                                    );
                                })}
                            </CommandGroup>
                        )}

                        {searchValue && (
                            <>
                                <Separator className="my-1" />
                                <CommandGroup>
                                    <CommandItem
                                        value={`custom-${searchValue}`}
                                        onSelect={handleUseCustomText}
                                        className="cursor-pointer"
                                    >
                                        <div className="flex flex-1 items-center gap-2">
                                            <FileText className="h-4 w-4 text-primary" />
                                            <span className="text-base">
                                                Use custom:{' '}
                                                <strong>"{searchValue}"</strong>
                                            </span>
                                        </div>
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
