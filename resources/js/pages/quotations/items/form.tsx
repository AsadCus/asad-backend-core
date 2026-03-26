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
import { OptionType } from '@/types';
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
import { ProperInputSelect } from '../../../components/proper-input-select';
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
    showMemberColumn?: boolean;
    memberOptions?: OptionType[];
    memberSharingPlanById?: Record<number, string | null | undefined>;
}

export default function QuotationItemTableForm<T extends QuotationItemSchema>({
    mode = 'quotation',
    // quotation,
    items,
    onChange,
    renderError,
    disabled = false,
    invoices,
    currentInvoiceIndex,
    onMoveItem,
    showOptionalColumn = false,
    showMemberColumn = false,
    memberOptions = [],
    memberSharingPlanById = {},
}: QuotationItemTableFormProps<T>) {
    const [expanded, setExpanded] = useState<Record<string, boolean>>({});

    const isLinkedMemberItem = (item: T): boolean =>
        Number(item.customer_confirmation_member_id ?? 0) > 0;

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

        const quantity = Number(item.quantity ?? 0);
        const rate = Number(item.rate ?? 0);

        return quantity * rate;
    };

    const normalizeSortOrder = (list: T[]) =>
        list.map((item, i) => ({ ...item, sort_order: i + 1 })) as T[];

    const buildNewChildItem = (
        parentKey: string,
        parentId: number | null | undefined,
        optionalValue: boolean,
    ) => ({
        _key: nanoid(),
        id: undefined,
        description: '',
        parent_id: parentId ?? null,
        parent_key: parentKey,
        quantity: 1,
        rate: '',
        amount: '',
        is_header: false,
        is_optional: optionalValue,
        sort_order: 0,
    });

    // Actions
    const addItem = () => {
        const optionalValue = showOptionalColumn;
        const headerKey = nanoid();
        const newHeader = {
            _key: headerKey,
            id: undefined,
            description: '',
            parent_id: null,
            parent_key: null,
            quantity: null,
            rate: null,
            amount: null,
            is_header: true,
            is_optional: optionalValue,
            sort_order: items.length + 1,
        };

        const newChild = buildNewChildItem(headerKey, null, optionalValue);

        onChange(normalizeSortOrder([...items, newHeader as T, newChild as T]));
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

    const insertItem = (index: number) => {
        const parent = items[index];

        if (!parent.is_header) return;

        const child = buildNewChildItem(
            parent._key,
            parent.id,
            showOptionalColumn,
        );

        const next = [...items];
        next.splice(index + 1, 0, child as T);

        onChange(normalizeSortOrder(next));
    };

    const insertItemWithHeader = (index: number) => {
        const parent = items[index];

        if (!parent.is_header) return;

        const optionalValue = showOptionalColumn;
        const childHeaderKey = nanoid();
        const childHeader = {
            _key: childHeaderKey,
            id: undefined,
            description: '',
            parent_id: parent.id ?? null,
            parent_key: parent._key,
            quantity: null,
            rate: null,
            amount: null,
            is_header: true,
            is_optional: optionalValue,
            sort_order: 0,
        };
        const nestedItem = buildNewChildItem(
            childHeaderKey,
            null,
            optionalValue,
        );

        const next = [...items];
        next.splice(index + 1, 0, childHeader as T, nestedItem as T);

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

        if (isLinkedMemberItem(target)) {
            return;
        }

        const hasLinkedMemberChild = items.some(
            (item) =>
                isLinkedMemberItem(item) &&
                (item.parent_key === target._key ||
                    (target.id && item.parent_id === target.id)),
        );

        if (hasLinkedMemberChild) {
            return;
        }

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
            if (hasChildren(item)) {
                next[getItemKey(item)] = true;
            }
        });
        setExpanded(next);
    };

    const collapseAll = () => {
        const next: Record<string, boolean> = {};
        items.forEach((item) => {
            if (hasChildren(item)) {
                next[getItemKey(item)] = false;
            }
        });
        setExpanded(next);
    };

    useEffect(() => {
        const next: Record<string, boolean> = {};
        items.forEach((i) => {
            if (hasChildren(i)) next[i._key] = true;
        });
        setExpanded((p) => ({ ...next, ...p }));
    }, [items, hasChildren]);

    // Visible items
    const visibleItems = useMemo<VisibleItem<T>[]>(() => {
        const out: VisibleItem<T>[] = [];

        const getChildren = (parent: T): T[] =>
            items.filter(
                (child) =>
                    child.parent_key === parent._key ||
                    (parent.id != null && child.parent_id === parent.id),
            );

        const appendWithChildren = (item: T, level: number) => {
            out.push({ item, level });

            if (expanded[item._key] === false) {
                return;
            }

            getChildren(item).forEach((child) => {
                appendWithChildren(child, level + 1);
            });
        };

        items
            .filter((item) => item.parent_id == null && item.parent_key == null)
            .forEach((rootItem) => {
                appendWithChildren(rootItem, 0);
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

        const next = [...items];
        next.splice(fromIndex, 1);

        let nextParentId = dragged.parent_id;
        let nextParentKey = dragged.parent_key;

        if (target.is_header) {
            if (dragged._key === target._key) {
                return;
            }

            nextParentId = target.id ?? null;
            nextParentKey = target._key;
        } else {
            if (target.parent_id == null && target.parent_key == null) {
                if (!dragged.is_header && !isLinkedMemberItem(dragged)) {
                    return;
                }

                nextParentId = null;
                nextParentKey = null;
            } else {
                if (target.parent_id != null) {
                    nextParentId = target.parent_id;
                    nextParentKey =
                        items.find((i) => i.id === target.parent_id)?._key ??
                        null;
                } else if (target.parent_key != null) {
                    nextParentKey = target.parent_key;
                    const parentItem = items.find(
                        (i) => i._key === target.parent_key,
                    );
                    nextParentId = parentItem?.id ?? null;
                }
            }
        }

        if (nextParentKey) {
            if (nextParentKey === dragged._key || nextParentId === dragged.id) {
                return;
            }

            const parentItem = items.find(
                (item) => item._key === nextParentKey,
            );

            if (!parentItem?.is_header) {
                return;
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
            header: 'Items',
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
                                disabled={disabled || isLinkedMemberItem(item)}
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
                        ) : !item.is_header ? (
                            <ProperInput
                                value={item.description ?? ''}
                                disabled
                                textarea
                                size="compact"
                                onCommit={() => {}}
                            />
                        ) : (
                            <ItemDescriptionCombobox
                                value={item.description ?? ''}
                                disabled={disabled || isLinkedMemberItem(item)}
                                isHeader={item.is_header ?? false}
                                isChild={Boolean(
                                    !item.is_header &&
                                        (item.parent_id || item.parent_key),
                                )}
                                existingItemKeys={existingItemKeys}
                                onSelect={(payload) => {
                                    if (payload.type === 'single') {
                                        updateItemByKey(item._key, {
                                            description:
                                                payload.item.description,
                                            quantity: payload.item.quantity,
                                            rate: payload.item.rate,
                                            is_header: payload.item.is_header,
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
                                                  } as T)
                                                : i,
                                        );

                                        const parentIndex = updated.findIndex(
                                            (i) => i._key === parentKey,
                                        );

                                        const childrenSource =
                                            payload.children.length > 0
                                                ? payload.children
                                                : [
                                                      {
                                                          id: undefined,
                                                          description: '',
                                                          quantity: 1,
                                                          rate: '',
                                                          is_header: false,
                                                          is_optional:
                                                              payload.parent
                                                                  .is_optional,
                                                      },
                                                  ];

                                        const children = childrenSource.map(
                                            (child) => ({
                                                _key: nanoid(),
                                                id: child.id,
                                                parent_id: payload.parent.id,
                                                parent_key: parentKey,
                                                description:
                                                    child.description ?? '',
                                                quantity: child.quantity ?? 1,
                                                rate: child.rate ?? '',
                                                is_header:
                                                    child.is_header ?? false,
                                                is_optional:
                                                    child.is_optional ??
                                                    payload.parent.is_optional,
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
                                onChange={() => {}}
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
        ...(showMemberColumn
            ? [
                  {
                      id: 'member',
                      header: 'Member',
                      meta: { className: 'sm:table-cell w-[18%]' },
                      cell: ({
                          row,
                      }: {
                          row: { original: VisibleItem<T> };
                      }) => {
                          const { item } = row.original;
                          const index = getSourceIndex(item._key);

                          return (
                              <div className="relative flex w-full flex-col gap-1">
                                  <ProperInputSelect
                                      options={memberOptions}
                                      disabled={disabled}
                                      size="compact"
                                      truncate={16}
                                      value={
                                          item.customer_confirmation_member_id ??
                                          ''
                                      }
                                      onValueChange={(value) => {
                                          const numericValue = Number(value);
                                          const nextMemberId = Number.isNaN(
                                              numericValue,
                                          )
                                              ? null
                                              : numericValue;

                                          updateItemByKey(item._key, {
                                              customer_confirmation_member_id:
                                                  nextMemberId,
                                              sharing_plan: nextMemberId
                                                  ? (memberSharingPlanById[
                                                        nextMemberId
                                                    ] ?? null)
                                                  : null,
                                          } as Partial<T>);
                                      }}
                                      placeholder="Select member"
                                  />
                                  {renderError?.(
                                      `items.${index}.customer_confirmation_member_id`,
                                  )}
                              </div>
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
                            inputProps={{ step: 'any' }}
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
                            inputProps={{ step: 'any' }}
                            placeholder="0.00"
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
            header: () => <div className="text-right">Amount</div>,
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
                const isLockedMemberItem = isLinkedMemberItem(item);
                const hasLockedMemberChildren = items.some(
                    (child) =>
                        isLinkedMemberItem(child) &&
                        (child.parent_key === item._key ||
                            (item.id && child.parent_id === item.id)),
                );
                const disableRemove =
                    isLockedMemberItem || hasLockedMemberChildren;

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
                                                    className="text-sm opacity-60"
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

                                    {item.is_header && (
                                        <DropdownMenuItem
                                            disabled={isLockedMemberItem}
                                            onClick={() => {
                                                if (isLockedMemberItem) {
                                                    return;
                                                }

                                                insertItem(index);
                                            }}
                                        >
                                            <CornerDownRight
                                                size={14}
                                                className="mr-2"
                                            />
                                            Insert Item
                                        </DropdownMenuItem>
                                    )}

                                    {item.is_header && (
                                        <DropdownMenuItem
                                            disabled={isLockedMemberItem}
                                            onClick={() => {
                                                if (isLockedMemberItem) {
                                                    return;
                                                }

                                                insertItemWithHeader(index);
                                            }}
                                        >
                                            <CornerDownRight
                                                size={14}
                                                className="mr-2"
                                            />
                                            Insert Item with Header
                                        </DropdownMenuItem>
                                    )}

                                    <DropdownMenuItem
                                        disabled={isLockedMemberItem}
                                        onClick={() => {
                                            if (isLockedMemberItem) {
                                                return;
                                            }

                                            duplicateItem(index);
                                        }}
                                    >
                                        <Copy size={14} className="mr-2" />
                                        Duplicate
                                    </DropdownMenuItem>

                                    <DropdownMenuItem
                                        className="text-red-600"
                                        disabled={disableRemove}
                                        onClick={() => {
                                            if (disableRemove) {
                                                return;
                                            }

                                            removeItem(index);
                                        }}
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
                                        disabled={disableRemove}
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
                                'text-base sm:text-base md:text-base',
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
                                                    next.level <= level ||
                                                    (next.item.parent_id !==
                                                        item.parent_id &&
                                                        next.item.parent_key !==
                                                            item.parent_key));

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
                                    <TableCell className="font-medium">
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
