import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn, formatCurrency } from '@/lib/utils';
import { InvoiceSchema } from '@/pages/invoices/schema';
import {
    closestCenter,
    DndContext,
    DragEndEvent,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    SortableContext,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import {
    flexRender,
    getCoreRowModel,
    useReactTable,
    type ColumnDef,
} from '@tanstack/react-table';
import {
    ChevronDown,
    ChevronRight,
    Copy,
    CornerDownRight,
    GripVertical,
    MoreVertical,
    Plus,
    Trash2,
    X,
} from 'lucide-react';
import { nanoid } from 'nanoid';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { ProperInput } from '../../../components/proper-input';
import { QuotationSchema } from '../schema';
import ItemDescriptionCombobox from './components/item-desc-combobox';
import { QuotationItemSchema } from './schema';

type VisibleItem<T> = {
    item: T;
    level: number;
};

type SortableProps = {
    ref: (el: HTMLElement | null) => void;
    style: React.CSSProperties;
    attributes: React.HTMLAttributes<HTMLElement>;
    listeners: React.HTMLAttributes<HTMLElement>;
};

interface QuotationItemTableFormProps<T extends QuotationItemSchema> {
    mode?: 'master' | 'quotation';
    quotation?: QuotationSchema;
    items: T[];
    onChange: (items: T[]) => void;
    renderError?: (path: string) => React.ReactNode;
    disabled?: boolean;
    invoices?: InvoiceSchema[];
    currentInvoiceIndex?: number;
    onMoveItem?: (
        fromInvoiceIndex: number,
        toInvoiceIndex: number,
        itemKeys: string[],
    ) => void;
    showOptionalColumn?: boolean;
    showPlacementFeeColumn?: boolean;
}

export default function QuotationItemTableForm<T extends QuotationItemSchema>({
    mode = 'quotation',
    quotation,
    items,
    onChange,
    renderError,
    disabled = false,
    invoices,
    currentInvoiceIndex,
    onMoveItem,
    showOptionalColumn = false,
    showPlacementFeeColumn = false,
}: QuotationItemTableFormProps<T>) {
    const [expanded, setExpanded] = useState<Record<string, boolean>>({});

    const getItemKey = (item: T) => item._key;

    const getSourceIndex = (key: string) =>
        items.findIndex((i) => i._key === key);

    const updateItemByKey = (key: string, patch: Partial<T>) => {
        onChange(
            items.map((item) =>
                item._key === key ? { ...item, ...patch } : item,
            ),
        );
    };

    const computeItemAmount = (item: T) => {
        if (item.is_header) return 0;

        const salary = Number(quotation?.monthly_salary ?? item.rate ?? 0);
        const loanDuration = Number(
            quotation?.loan_duration ?? item.quantity ?? 0,
        );

        if (item.is_placement_fee) {
            return salary * loanDuration;
        }

        const quantity = Number(item.quantity ?? 0);
        const rate = Number(item.rate ?? 0);

        return quantity * rate;
    };

    const normalizeSortOrder = (list: T[]) =>
        list.map((item, i) => ({ ...item, sort_order: i + 1 })) as T[];

    // Handle upfront item for quotation
    useEffect(() => {
        if (!quotation) return;

        const salary = Number(quotation.monthly_salary);
        const loan = Number(quotation.loan_duration);

        if (Number.isNaN(salary) || Number.isNaN(loan)) return;

        const existingIndex = items.findIndex(
            (i) => i.is_placement_fee === true,
        );

        if (existingIndex !== -1) {
            const updatedItem = { ...items[existingIndex] };
            let changed = false;

            if (Number(updatedItem.rate) !== salary) {
                updatedItem.rate = String(salary);
                changed = true;
            }

            if (Number(updatedItem.quantity) !== loan) {
                updatedItem.quantity = loan;
                changed = true;
            }

            if (changed) {
                const next = [...items];
                next[existingIndex] = updatedItem;
                onChange(next);
            }
        } else {
            const newItem = {
                _key: `upfront-${nanoid()}`,
                id: undefined,
                description: 'Placement Fee',
                quantity: loan,
                rate: String(salary),
                amount: String(salary * loan),
                is_header: false,
                is_placement_fee: true,
                is_optional: false,
                parent_id: null,
                sort_order: items.length + 1,
            } as T;
            onChange([...items, newItem]);
        }
    }, [quotation, items, onChange, showOptionalColumn]);

    // Actions
    const addItem = () => {
        const baseNewItem = {
            _key: nanoid(),
            id: undefined,
            description: '',
            parent_id: null,
            quantity: 1,
            rate: '',
            amount: '',
            is_header: false,
            is_optional: false,
            sort_order: items.length + 1,
        };

        const newItem = showOptionalColumn
            ? { ...baseNewItem, is_optional: false, parent_key: null }
            : showPlacementFeeColumn
              ? {
                    ...baseNewItem,
                    is_placement_fee: false,
                    parent_key: null,
                    invoice_key: null,
                    invoice_id: undefined,
                }
              : baseNewItem;

        onChange(normalizeSortOrder([...items, newItem as T]));
    };

    // const insertBelow = (index: number, asHeader = false) => {
    //     const current = items[index];

    //     const next = [...items];
    //     next.splice(index + 1, 0, {
    //         _key: nanoid(),
    //         id: undefined,
    //         description: '',
    //         parent_id: current.parent_id,
    //         quantity: asHeader ? null : 1,
    //         rate: asHeader ? null : '',
    //         amount: asHeader ? null : '',
    //         is_header: asHeader,
    //         is_optional: false,
    //         sort_order: 0,
    //     });

    //     onChange(normalizeSortOrder(next));
    // };

    const insertChild = (index: number) => {
        const parent = items[index];

        if (!parent.id && parent.parent_key) return;

        const baseChild = {
            _key: nanoid(),
            id: undefined,
            description: '',
            parent_id: parent.id,
            parent_key: parent._key,
            quantity: 1,
            rate: '',
            amount: '',
            is_header: false,
            is_optional: false,
            sort_order: 0,
        };

        const child = showOptionalColumn
            ? { ...baseChild, is_optional: false }
            : showPlacementFeeColumn
              ? {
                    ...baseChild,
                    is_placement_fee: false,
                    invoice_key: null,
                    invoice_id: undefined,
                }
              : baseChild;

        const next = [...items];
        next.splice(index + 1, 0, child as T);

        onChange(normalizeSortOrder(next));
    };

    const duplicateItem = (index: number) => {
        const next = [...items];
        next.splice(index + 1, 0, {
            ...items[index],
            _key: nanoid(),
            id: undefined,
        });

        onChange(normalizeSortOrder(next));
    };

    const removeItem = (index: number) => {
        const target = items[index];

        onChange(
            normalizeSortOrder(
                items.filter(
                    (item, i) =>
                        i !== index &&
                        item.parent_key !== target._key &&
                        item.parent_id !== target.id,
                ),
            ),
        );
    };

    const hasChildren = useCallback(
        (item: T) =>
            items.some(
                (i) =>
                    i.parent_key === item._key ||
                    (item.id && i.parent_id === item.id),
            ),
        [items],
    );

    // Expand & collapse
    const toggleExpanded = (key: string) =>
        setExpanded((p) => ({ ...p, [key]: !p[key] }));

    const expandAll = () => {
        const next: Record<string, boolean> = {};
        items.forEach((item) => {
            if (!item.id) return;
            const hasChild = items.some((child) => child.parent_id === item.id);
            if (hasChild) {
                next[getItemKey(item)] = true;
            }
        });
        setExpanded(next);
    };

    const collapseAll = () => {
        const next: Record<string, boolean> = {};
        items.forEach((item) => {
            if (!item.id) return;
            const hasChild = items.some((child) => child.parent_id === item.id);
            if (hasChild) {
                next[getItemKey(item)] = false;
            }
        });
        setExpanded(next);
    };

    useEffect(() => {
        const next: Record<string, boolean> = {};
        items.forEach((i) => {
            if (hasChildren(i)) next[i._key] = false;
        });
        setExpanded((p) => ({ ...next, ...p }));
    }, [items, hasChildren]);

    // Visible items
    const visibleItems = useMemo<VisibleItem<T>[]>(() => {
        const out: VisibleItem<T>[] = [];

        items.forEach((item) => {
            const isRoot = item.parent_id == null && item.parent_key == null;

            if (!isRoot) return;

            out.push({ item, level: 0 });

            if (expanded[item._key] === false) return;

            items.forEach((child) => {
                const isChild =
                    (child.parent_key != null &&
                        child.parent_key === item._key) ||
                    (child.parent_id != null && child.parent_id === item.id);

                if (isChild) {
                    out.push({ item: child, level: 1 });
                }
            });
        });

        return out;
    }, [items, expanded]);

    // Sortable
    function SortableRow({
        id,
        children,
    }: {
        id: string;
        children: (props: SortableProps) => React.ReactNode;
    }) {
        const { setNodeRef, attributes, listeners, transform, transition } =
            useSortable({ id });

        return children({
            ref: setNodeRef,
            style: {
                transform: CSS.Transform.toString(transform),
                transition,
            },
            attributes: attributes as React.HTMLAttributes<HTMLElement>,
            listeners: listeners as React.HTMLAttributes<HTMLElement>,
        });
    }

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
    );

    const handleDragEnd = (event: DragEndEvent) => {
        const { active, over } = event;
        if (!over || active.id === over.id) return;

        const fromIndex = items.findIndex((i) => i._key === active.id);
        const toIndex = items.findIndex((i) => i._key === over.id);
        if (fromIndex === -1 || toIndex === -1) return;

        const dragged = items[fromIndex];
        const target = items[toIndex];

        if (dragged.is_header && (target.parent_id || target.parent_key))
            return;

        const next = [...items];
        next.splice(fromIndex, 1);

        let nextParentId = dragged.parent_id;
        let nextParentKey = dragged.parent_key;

        if (target.parent_id == null && target.parent_key == null) {
            nextParentId = null;
            nextParentKey = null;
        } else {
            if (target.parent_id != null) {
                nextParentId = target.parent_id;
                nextParentKey =
                    items.find((i) => i.id === target.parent_id)?._key ?? null;
            } else if (target.parent_key != null) {
                nextParentKey = target.parent_key;
                const parentItem = items.find(
                    (i) => i._key === target.parent_key,
                );
                nextParentId = parentItem?.id ?? null;
            }
        }

        next.splice(toIndex, 0, {
            ...dragged,
            parent_id: nextParentId,
            parent_key: nextParentKey,
        } as T);

        onChange(normalizeSortOrder(next));
    };

    // Columns
    const columns: ColumnDef<VisibleItem<T>>[] = [
        ...(!disabled
            ? [
                  {
                      id: 'drag',
                      meta: { className: 'sm:table-cell w-fit' },
                      cell: () => (
                          <Button
                              type="button"
                              variant="ghost"
                              size="icon"
                              className="h-6 w-6 cursor-grab active:cursor-grabbing"
                          >
                              <GripVertical size={14} />
                          </Button>
                      ),
                  },
              ]
            : []),
        {
            id: 'expander',
            meta: { className: 'sm:table-cell w-fit' },
            cell: ({ row }) => {
                const { item } = row.original;
                if (!hasChildren(item)) return null;

                return (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="h-6 w-6"
                        onClick={() => toggleExpanded(item._key)}
                    >
                        {expanded[item._key] !== false ? (
                            <ChevronDown size={16} />
                        ) : (
                            <ChevronRight size={16} />
                        )}
                    </Button>
                );
            },
        },
        {
            id: 'description',
            header: 'Item Details',
            meta: { className: 'sm:table-cell w-[50%]' },
            cell: ({ row }) => {
                const { item, level } = row.original;
                const index = getSourceIndex(item._key);

                const existingItemKeys = items
                    .filter((i) => i.id)
                    .map((i) => `id-${i.id}`);

                return (
                    <div
                        className="relative w-full"
                        style={{ paddingLeft: level * 32 }}
                    >
                        {mode === 'master' ? (
                            <ProperInput
                                value={item.description ?? ''}
                                disabled={disabled}
                                textarea={!item.is_header}
                                size="compact"
                                className={
                                    item.is_header ? 'font-semibold' : ''
                                }
                                onCommit={(v) =>
                                    updateItemByKey(item._key, {
                                        description: v,
                                    } as Partial<T>)
                                }
                            />
                        ) : (
                            <ItemDescriptionCombobox
                                value={item.description ?? ''}
                                disabled={disabled}
                                isHeader={item.is_header ?? false}
                                isChild={!!(item.parent_id || item.parent_key)}
                                existingItemKeys={existingItemKeys}
                                onSelect={(payload) => {
                                    if (payload.type === 'single') {
                                        updateItemByKey(item._key, {
                                            description:
                                                payload.item.description,
                                            quantity: payload.item.quantity,
                                            rate: payload.item.rate,
                                            is_header: payload.item.is_header,
                                            is_placement_fee:
                                                payload.item.is_placement_fee,
                                            id: payload.item.id,
                                        } as Partial<T>);
                                        return;
                                    }

                                    if (payload.type === 'group') {
                                        const parentKey = item._key;

                                        const cleaned = items.filter(
                                            (i) =>
                                                i._key === item._key ||
                                                (i.parent_key !== parentKey &&
                                                    i.parent_id !== item.id),
                                        );

                                        const updated = cleaned.map((i) =>
                                            i._key === parentKey
                                                ? ({
                                                      ...i,
                                                      id: payload.parent.id,
                                                      parent_id: null,
                                                      parent_key: null,
                                                      description:
                                                          payload.parent
                                                              .description,
                                                      quantity:
                                                          payload.parent
                                                              .quantity,
                                                      rate: payload.parent.rate,
                                                      is_header:
                                                          payload.parent
                                                              .is_header,
                                                      is_optional:
                                                          payload.parent
                                                              .is_optional,
                                                      is_placement_fee:
                                                          payload.parent
                                                              .is_placement_fee,
                                                  } as T)
                                                : i,
                                        );

                                        const parentIndex = updated.findIndex(
                                            (i) => i._key === parentKey,
                                        );

                                        const children = payload.children.map(
                                            (child) => ({
                                                _key: nanoid(),
                                                id: child.id,
                                                parent_id: payload.parent.id,
                                                parent_key: parentKey,
                                                description: child.description,
                                                quantity: child.quantity,
                                                rate: child.rate,
                                                is_header: false,
                                                is_optional:
                                                    payload.parent.is_optional,
                                                is_placement_fee:
                                                    child.is_placement_fee,
                                                sort_order: 0,
                                            }),
                                        ) as T[];

                                        updated.splice(
                                            parentIndex + 1,
                                            0,
                                            ...children,
                                        );

                                        onChange(normalizeSortOrder(updated));
                                        return;
                                    }
                                }}
                                onChange={(value) => {
                                    updateItemByKey(item._key, {
                                        description: value,
                                    } as Partial<T>);
                                }}
                                className={
                                    item.is_header ? 'font-semibold' : ''
                                }
                            />
                        )}
                        {renderError?.(`items.${index}.description`)}
                    </div>
                );
            },
        },
        {
            id: 'is_header',
            header: 'Header',
            meta: { className: 'sm:table-cell' },
            cell: ({ row }) => {
                const { item } = row.original;
                return (
                    <Checkbox
                        checked={item.is_header ?? false}
                        disabled={disabled}
                        onCheckedChange={(v) =>
                            updateItemByKey(item._key, {
                                is_header: Boolean(v),
                                amount: v ? null : '',
                            } as Partial<T>)
                        }
                    />
                );
            },
        },
        ...(showOptionalColumn
            ? [
                  {
                      id: 'optional',
                      header: 'Optional',
                      meta: { className: 'sm:table-cell' },
                      cell: ({
                          row,
                      }: {
                          row: { original: VisibleItem<T> };
                      }) => {
                          const { item } = row.original;
                          return (
                              <Checkbox
                                  checked={item.is_optional ?? false}
                                  disabled={disabled}
                                  onCheckedChange={(v) =>
                                      updateItemByKey(item._key, {
                                          is_optional: Boolean(v),
                                      } as Partial<T>)
                                  }
                              />
                          );
                      },
                  },
              ]
            : []),
        ...(showPlacementFeeColumn
            ? [
                  {
                      id: 'is_placement_fee',
                      header: 'Fee',
                      meta: { className: 'sm:table-cell' },
                      cell: ({
                          row,
                      }: {
                          row: { original: VisibleItem<T> };
                      }) => {
                          const { item } = row.original;
                          return (
                              <Checkbox
                                  checked={item.is_placement_fee ?? false}
                                  disabled={disabled}
                                  onCheckedChange={(v) =>
                                      updateItemByKey(item._key, {
                                          is_placement_fee: Boolean(v),
                                      } as Partial<T>)
                                  }
                              />
                          );
                      },
                  },
              ]
            : []),
        {
            id: 'quantity',
            header: 'Qty',
            meta: { className: 'sm:table-cell w-[8%]' },
            cell: ({ row }) => {
                const { item } = row.original;
                const index = getSourceIndex(item._key);

                if (item.is_header) {
                    return (
                        <div className="text-center text-muted-foreground">
                            —
                        </div>
                    );
                }

                return (
                    <div className="relative flex w-full flex-col gap-1">
                        <ProperInput
                            value={item.quantity ?? ''}
                            type="number"
                            inputProps={{ step: 'any', min: 0 }}
                            disabled={disabled}
                            size="compact"
                            onCommit={(v) =>
                                updateItemByKey(item._key, {
                                    quantity: Number(v),
                                } as Partial<T>)
                            }
                        />
                        {renderError?.(`items.${index}.quantity`)}
                    </div>
                );
            },
        },
        {
            id: 'rate',
            header: 'Cost',
            meta: { className: 'sm:table-cell w-[12%]' },
            cell: ({ row }) => {
                const { item } = row.original;
                const index = getSourceIndex(item._key);

                if (item.is_header) {
                    return (
                        <div className="text-center text-muted-foreground">
                            —
                        </div>
                    );
                }

                return (
                    <div className="relative flex w-full flex-col gap-1">
                        <ProperInput
                            value={item.rate ?? ''}
                            type="number"
                            inputProps={{ step: '1' }}
                            disabled={disabled}
                            size="compact"
                            onCommit={(v) =>
                                updateItemByKey(item._key, {
                                    rate: Number(v),
                                } as Partial<T>)
                            }
                        />
                        {renderError?.(`items.${index}.rate`)}
                    </div>
                );
            },
        },
        {
            id: 'amount',
            header: 'Amount',
            meta: { className: 'sm:table-cell w-[12%]' },
            cell: ({ row }) => {
                const { item } = row.original;

                if (item.is_header) {
                    return (
                        <div className="text-center text-muted-foreground">
                            —
                        </div>
                    );
                }

                const amount = computeItemAmount(item as T);

                return (
                    <div className="text-right font-medium">
                        {formatCurrency(amount)}
                    </div>
                );
            },
        },
        {
            id: 'actions',
            meta: { className: 'sm:table-cell' },
            cell: ({ row }) => {
                if (disabled) return null;

                const index = getSourceIndex(row.original.item._key);
                const item = row.original.item;

                return (
                    <div className="flex items-center gap-1">
                        <TooltipProvider>
                            <DropdownMenu>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <DropdownMenuTrigger asChild>
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                className="h-6 w-6"
                                            >
                                                <MoreVertical size={16} />
                                            </Button>
                                        </DropdownMenuTrigger>
                                    </TooltipTrigger>
                                    <TooltipContent side="left">
                                        Actions
                                    </TooltipContent>
                                </Tooltip>

                                <DropdownMenuContent align="end">
                                    {/* <DropdownMenuItem
                                    onClick={() => insertBelow(index, false)}
                                >
                                    <Plus size={14} className="mr-2" />
                                    Insert below
                                </DropdownMenuItem>

                                <DropdownMenuItem
                                    onClick={() => insertBelow(index, true)}
                                >
                                    <Heading size={14} className="mr-2" />
                                    Insert header below
                                </DropdownMenuItem> */}

                                    {onMoveItem &&
                                        invoices &&
                                        typeof currentInvoiceIndex ===
                                            'number' && (
                                            <>
                                                <DropdownMenuItem
                                                    disabled
                                                    className="text-xs opacity-60"
                                                >
                                                    Move to invoice
                                                </DropdownMenuItem>

                                                {invoices.map((inv, idx) =>
                                                    idx !==
                                                    currentInvoiceIndex ? (
                                                        <DropdownMenuItem
                                                            key={inv._key}
                                                            onClick={() =>
                                                                onMoveItem(
                                                                    currentInvoiceIndex,
                                                                    idx,
                                                                    [item._key],
                                                                )
                                                            }
                                                        >
                                                            {inv.description ||
                                                                `Invoice ${idx + 1}`}
                                                        </DropdownMenuItem>
                                                    ) : null,
                                                )}

                                                <DropdownMenuItem disabled />
                                            </>
                                        )}

                                    {(item.id || item._key) &&
                                        !item.parent_id &&
                                        !item.parent_key && (
                                            <DropdownMenuItem
                                                onClick={() =>
                                                    insertChild(index)
                                                }
                                            >
                                                <CornerDownRight
                                                    size={14}
                                                    className="mr-2"
                                                />
                                                Insert child
                                            </DropdownMenuItem>
                                        )}

                                    <DropdownMenuItem
                                        onClick={() => duplicateItem(index)}
                                    >
                                        <Copy size={14} className="mr-2" />
                                        Duplicate
                                    </DropdownMenuItem>

                                    <DropdownMenuItem
                                        className="text-red-600"
                                        onClick={() => removeItem(index)}
                                    >
                                        <Trash2 size={14} className="mr-2" />
                                        Remove
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </TooltipProvider>

                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        className="h-6 w-6 text-red-600 hover:text-red-700"
                                        onClick={() => removeItem(index)}
                                    >
                                        <X size={14} />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent side="left">
                                    Remove
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                );
            },
        },
    ];

    const table = useReactTable({
        data: visibleItems,
        columns,
        getCoreRowModel: getCoreRowModel(),
        getRowId: (row) => row.item._key,
    });

    const totalAmount = items.reduce((sum, item) => {
        return sum + computeItemAmount(item as T);
    }, 0);

    return (
        <div className="space-y-4">
            {!disabled && (
                <div className="flex justify-end gap-2">
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={expandAll}
                    >
                        Expand All
                    </Button>

                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={collapseAll}
                    >
                        Collapse All
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="default"
                        onClick={addItem}
                    >
                        <Plus size={14} />
                        Add Item
                    </Button>
                </div>
            )}

            <div className="overflow-x-auto rounded-md border">
                <DndContext
                    sensors={sensors}
                    collisionDetection={closestCenter}
                    onDragEnd={handleDragEnd}
                >
                    <SortableContext
                        items={visibleItems.map((v) => v.item._key)}
                        strategy={verticalListSortingStrategy}
                    >
                        <Table
                            className={cn(
                                'min-w-[900px]',
                                'text-sm sm:text-sm md:text-base',
                                '[&_td]:align-top [&_th]:align-middle',
                                '[&_td]:p-1 [&_th]:px-1 [&_th]:py-2',
                                '[&_tfoot_td]:p-2',
                            )}
                        >
                            <TableHeader>
                                {table.getHeaderGroups().map((headerGroup) => (
                                    <TableRow key={headerGroup.id}>
                                        {headerGroup.headers.map((header) => (
                                            <TableHead
                                                key={header.id}
                                                className={
                                                    header.column.columnDef.meta
                                                        ?.className
                                                }
                                            >
                                                {header.isPlaceholder
                                                    ? null
                                                    : flexRender(
                                                          header.column
                                                              .columnDef.header,
                                                          header.getContext(),
                                                      )}
                                            </TableHead>
                                        ))}
                                    </TableRow>
                                ))}
                            </TableHeader>
                            <TableBody>
                                {table.getRowModel().rows?.length ? (
                                    table
                                        .getRowModel()
                                        .rows.map((row, index, rows) => {
                                            const { item, level } =
                                                row.original;
                                            const next =
                                                rows[index + 1]?.original;
                                            const itemKey = getItemKey(item);
                                            const isExpanded =
                                                expanded[itemKey] !== false;
                                            const isParent =
                                                level === 0 &&
                                                hasChildren(item);
                                            const isChild = level > 0;
                                            const isLastChild =
                                                isChild &&
                                                (!next ||
                                                    next.level === 0 ||
                                                    next.item.parent_id !==
                                                        item.parent_id);

                                            return (
                                                <SortableRow
                                                    key={item._key}
                                                    id={item._key}
                                                >
                                                    {({
                                                        ref,
                                                        style,
                                                        attributes,
                                                        listeners,
                                                    }) => (
                                                        <TableRow
                                                            ref={ref}
                                                            style={style}
                                                            {...attributes}
                                                            className={cn(
                                                                item.is_header &&
                                                                    'bg-muted/30',
                                                                isParent &&
                                                                    isExpanded &&
                                                                    'border-b-0',
                                                                isChild &&
                                                                    !isLastChild &&
                                                                    'border-b-0',
                                                            )}
                                                        >
                                                            {row
                                                                .getVisibleCells()
                                                                .map((cell) => (
                                                                    <TableCell
                                                                        key={`${row.id}-${cell.column.id}`}
                                                                        {...(cell
                                                                            .column
                                                                            .id ===
                                                                        'drag'
                                                                            ? listeners
                                                                            : {})}
                                                                    >
                                                                        {flexRender(
                                                                            cell
                                                                                .column
                                                                                .columnDef
                                                                                .cell,
                                                                            cell.getContext(),
                                                                        )}
                                                                    </TableCell>
                                                                ))}
                                                        </TableRow>
                                                    )}
                                                </SortableRow>
                                            );
                                        })
                                ) : (
                                    <TableRow>
                                        <TableCell
                                            colSpan={columns.length}
                                            className="text-center text-muted-foreground"
                                        >
                                            No items yet
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                            <TableFooter>
                                <TableRow>
                                    <TableCell
                                        colSpan={columns.length - 1}
                                        className="text-right font-semibold"
                                    >
                                        Total Amount:
                                    </TableCell>
                                    <TableCell className="font-semibold">
                                        {formatCurrency(totalAmount)}
                                    </TableCell>
                                </TableRow>
                            </TableFooter>
                        </Table>
                    </SortableContext>
                </DndContext>
            </div>
        </div>
    );
}
