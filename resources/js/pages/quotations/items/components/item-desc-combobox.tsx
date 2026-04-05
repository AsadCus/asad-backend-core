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
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { quotationItemsList } from '@/routes';
import { quickCreate as quickCreateQuotationItem } from '@/routes/quotation-items';
import { router } from '@inertiajs/react';
import { Check, FileText, Folder, Plus } from 'lucide-react';
import { nanoid } from 'nanoid';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
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

export type ItemDescriptionComboboxGroupOption = {
    key: string;
    label: string;
    parent: Partial<QuotationItemSchema>;
    children: Array<Partial<QuotationItemSchema>>;
};

interface ItemDescriptionComboboxProps {
    value: string;
    disabled?: boolean;
    isHeader?: boolean;
    isChild?: boolean;
    existingItemKeys: string[];
    groupOptions?: ItemDescriptionComboboxGroupOption[];
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
    groupOptions = [],
    onSelect,
    onChange,
    className,
}: ItemDescriptionComboboxProps) {
    const [open, setOpen] = useState(false);
    const [openCreateDialog, setOpenCreateDialog] = useState(false);
    const [options, setOptions] = useState<QuotationItemSchema[]>([]);
    const [loading, setLoading] = useState(false);
    const [isCreating, setIsCreating] = useState(false);
    const [searchValue, setSearchValue] = useState('');
    const [newItemName, setNewItemName] = useState('');
    const [newItemDescription, setNewItemDescription] = useState('');
    const [newItemQuantity, setNewItemQuantity] = useState('1');
    const [newItemRate, setNewItemRate] = useState('0');

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

    const handleOpenCreateDialog = () => {
        setNewItemName(searchValue || '');
        setNewItemDescription(searchValue || '');
        setNewItemQuantity('1');
        setNewItemRate('0');
        setOpenCreateDialog(true);
    };

    const handleCreateItem = () => {
        if (!newItemName.trim()) {
            toast.error('Name is required.');

            return;
        }

        if (!newItemDescription.trim()) {
            toast.error('Description is required.');

            return;
        }

        setIsCreating(true);

        router.post(
            quickCreateQuotationItem.url(),
            {
                name: newItemName.trim(),
                description: newItemDescription.trim(),
                quantity: Number(newItemQuantity || 1),
                rate: Number(newItemRate || 0),
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: async (page) => {
                    const flash = (page.props as Record<string, unknown>)
                        .flash as Record<string, unknown> | undefined;
                    const result = flash?.result as
                        | {
                              parent?: QuotationItemSchema;
                              children?: QuotationItemSchema[];
                          }
                        | undefined;

                    if (!result?.parent) {
                        toast.error('Failed to create product/service item.');

                        return;
                    }

                    await fetchItems();

                    handleSelect({
                        type: 'group',
                        parent: result.parent,
                        children: result.children ?? [],
                    });

                    setOpenCreateDialog(false);
                    toast.success('Product/service item created.');
                },
                onError: (errors) => {
                    const firstError = Object.values(errors)[0];
                    toast.error(
                        String(
                            firstError ??
                                'Failed to create product/service item.',
                        ),
                    );
                },
                onFinish: () => {
                    setIsCreating(false);
                },
            },
        );
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

    const toSelectableItem = useCallback(
        (
            item: Partial<QuotationItemSchema>,
            fallbackDescription: string,
            isHeaderItem: boolean,
        ): QuotationItemSchema => ({
            _key: String(item._key ?? `dynamic-${nanoid()}`),
            id:
                typeof item.id === 'number' && Number.isFinite(item.id)
                    ? item.id
                    : undefined,
            parent_id:
                typeof item.parent_id === 'number' &&
                Number.isFinite(item.parent_id)
                    ? item.parent_id
                    : null,
            parent_key:
                typeof item.parent_key === 'string' && item.parent_key !== ''
                    ? item.parent_key
                    : null,
            description: String(item.description ?? fallbackDescription),
            is_header: item.is_header ?? isHeaderItem,
            is_optional: item.is_optional ?? false,
            quantity: item.quantity ?? 1,
            rate: item.rate ?? null,
            customer_confirmation_member_id:
                typeof item.customer_confirmation_member_id === 'number' &&
                Number.isFinite(item.customer_confirmation_member_id)
                    ? item.customer_confirmation_member_id
                    : null,
            sharing_plan: item.sharing_plan ?? null,
            taxes: Array.isArray(item.taxes) ? item.taxes : [],
            amount: item.amount ?? null,
            sort_order: Number(item.sort_order ?? 0),
        }),
        [],
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

    const filteredDynamicGroups = groupOptions.filter((groupOption) => {
        if (!searchValue) {
            return true;
        }

        const search = searchValue.toLowerCase();
        const parentDescription = String(
            groupOption.parent.description ?? groupOption.label,
        ).toLowerCase();
        const label = String(groupOption.label ?? '').toLowerCase();
        const childMatches = (groupOption.children ?? []).some((child) =>
            String(child.description ?? '')
                .toLowerCase()
                .includes(search),
        );

        return (
            parentDescription.includes(search) ||
            label.includes(search) ||
            childMatches
        );
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
        <>
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
                            // size="compact"
                            className={cn(
                                isHeader && 'font-semibold',
                                className,
                            )}
                            placeholder="Select item"
                            onCommit={() => {
                                setOpen(true);
                            }}
                        />
                    </div>
                </PopoverTrigger>
                <PopoverContent className="w-[500px] p-0" align="start">
                    <Command shouldFilter={false}>
                        <CommandInput
                            placeholder="Search item..."
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
                                    </div>
                                )}
                            </CommandEmpty>

                            {!isChild && filteredDynamicGroups.length > 0 && (
                                <CommandGroup heading="Customer Confirmation Items">
                                    {filteredDynamicGroups.map(
                                        (groupOption) => (
                                            <CommandItem
                                                key={groupOption.key}
                                                value={`dynamic-group-${groupOption.key}-${groupOption.label}`}
                                                onSelect={() => {
                                                    handleSelect({
                                                        type: 'group',
                                                        parent: toSelectableItem(
                                                            {
                                                                ...groupOption.parent,
                                                                is_header: true,
                                                            },
                                                            groupOption.label,
                                                            true,
                                                        ),
                                                        children: (
                                                            groupOption.children ??
                                                            []
                                                        ).map(
                                                            (
                                                                child,
                                                                childIndex,
                                                            ) =>
                                                                toSelectableItem(
                                                                    {
                                                                        ...child,
                                                                        is_header: false,
                                                                        sort_order:
                                                                            child.sort_order ??
                                                                            childIndex +
                                                                                1,
                                                                    },
                                                                    String(
                                                                        child.description ??
                                                                            `Member ${childIndex + 1}`,
                                                                    ),
                                                                    false,
                                                                ),
                                                        ),
                                                    });
                                                }}
                                                className="cursor-pointer"
                                            >
                                                <div className="flex w-full items-center gap-2">
                                                    <Folder className="h-4 w-4 shrink-0 text-primary" />
                                                    <div className="flex flex-1 flex-col">
                                                        <span className="font-medium">
                                                            {groupOption.label}
                                                        </span>
                                                        <span className="text-sm text-muted-foreground">
                                                            {
                                                                groupOption
                                                                    .children
                                                                    .length
                                                            }{' '}
                                                            member item
                                                            {groupOption
                                                                .children
                                                                .length === 1
                                                                ? ''
                                                                : 's'}
                                                        </span>
                                                    </div>
                                                    <Check
                                                        className={cn(
                                                            'h-4 w-4 shrink-0',
                                                            value ===
                                                                groupOption
                                                                    .parent
                                                                    .description
                                                                ? 'opacity-100'
                                                                : 'opacity-0',
                                                        )}
                                                    />
                                                </div>
                                            </CommandItem>
                                        ),
                                    )}
                                </CommandGroup>
                            )}

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
                                                            <div className="flex w-full items-center gap-2">
                                                                {hasChildren ? (
                                                                    <Folder className="h-4 w-4 shrink-0 text-primary" />
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
                                                                            |
                                                                            Cost:
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
                                                    group.children.map(
                                                        (child) => {
                                                            const childMatches =
                                                                !searchValue ||
                                                                child.description
                                                                    ?.toLowerCase()
                                                                    .includes(
                                                                        searchValue.toLowerCase(),
                                                                    );

                                                            if (!childMatches) {
                                                                return null;
                                                            }

                                                            return (
                                                                <CommandItem
                                                                    key={
                                                                        child.id
                                                                    }
                                                                    value={`child-${child.id}-${child.description}`}
                                                                    onSelect={() => {
                                                                        if (
                                                                            parent
                                                                        ) {
                                                                            handleSelect(
                                                                                {
                                                                                    type: 'group',
                                                                                    parent,
                                                                                    children:
                                                                                        [
                                                                                            child,
                                                                                        ],
                                                                                },
                                                                            );

                                                                            return;
                                                                        }

                                                                        handleSelect(
                                                                            {
                                                                                type: 'single',
                                                                                item: child,
                                                                            },
                                                                        );
                                                                    }}
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
                                                        },
                                                    )}

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
                                                                            |
                                                                            Cost:
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

                            <Separator className="my-1" />
                            <CommandGroup>
                                <CommandItem
                                    value={`create-item-${searchValue || 'new'}`}
                                    onSelect={handleOpenCreateDialog}
                                    className="cursor-pointer"
                                >
                                    <div className="flex flex-1 items-center gap-2">
                                        <Plus className="h-4 w-4 text-primary" />
                                        <span className="text-base">
                                            Create product/service
                                        </span>
                                    </div>
                                </CommandItem>
                            </CommandGroup>
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>

            <Dialog
                open={openCreateDialog}
                onOpenChange={(nextOpen) => {
                    if (!isCreating) {
                        setOpenCreateDialog(nextOpen);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Create Product/Service</DialogTitle>
                        <DialogDescription>
                            This creates one item header and one child item.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-3">
                        <div className="space-y-1">
                            <Label htmlFor="new-item-name">Name</Label>
                            <Input
                                id="new-item-name"
                                value={newItemName}
                                onChange={(event) =>
                                    setNewItemName(event.target.value)
                                }
                                placeholder="Item header"
                                disabled={isCreating}
                            />
                        </div>

                        <div className="space-y-1">
                            <Label htmlFor="new-item-description">
                                Description
                            </Label>
                            <Input
                                id="new-item-description"
                                value={newItemDescription}
                                onChange={(event) =>
                                    setNewItemDescription(event.target.value)
                                }
                                placeholder="Child item description"
                                disabled={isCreating}
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1">
                                <Label htmlFor="new-item-quantity">
                                    Quantity
                                </Label>
                                <Input
                                    id="new-item-quantity"
                                    type="number"
                                    step="any"
                                    value={newItemQuantity}
                                    onChange={(event) =>
                                        setNewItemQuantity(event.target.value)
                                    }
                                    disabled={isCreating}
                                />
                            </div>

                            <div className="space-y-1">
                                <Label htmlFor="new-item-rate">Cost</Label>
                                <Input
                                    id="new-item-rate"
                                    type="number"
                                    step="any"
                                    value={newItemRate}
                                    onChange={(event) =>
                                        setNewItemRate(event.target.value)
                                    }
                                    disabled={isCreating}
                                />
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={isCreating}
                            onClick={() => setOpenCreateDialog(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            disabled={isCreating}
                            onClick={handleCreateItem}
                        >
                            {isCreating ? 'Creating...' : 'Create'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
