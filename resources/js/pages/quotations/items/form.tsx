import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { Fragment, useCallback, useEffect, useMemo, useState } from 'react';
import { ProperInput } from '../../../components/proper-input';
import { ProperInputSelect } from '../../../components/proper-input-select';
import ExtensionMasterCombobox, {
    type ExtensionMasterComboboxOption,
} from '../components/extension-master-combobox';
import { QuotationSchema } from '../schema';
import ItemDescriptionCombobox, {
    type ItemDescriptionComboboxGroupOption,
} from './components/item-desc-combobox';
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

type TaxExtensionMasterOption = {
    id: number;
    name: string;
    type?: string | null;
    calculation_mode?: string | null;
    calculation_value?: string | number | null;
};

type TaxEditorDraft = {
    name: string;
    type: 'tax' | 'discount';
    calculation_mode: 'fixed' | 'percentage';
    amount: number | null;
};

const EMPTY_TAX_EXTENSION_MASTERS: TaxExtensionMasterOption[] = [];

const TAX_TYPE_OPTIONS: OptionType[] = [
    { label: 'Tax', value: 'tax' },
    { label: 'Discount', value: 'discount' },
];

const TAX_CALCULATION_MODE_OPTIONS: OptionType[] = [
    { label: 'Fixed Amount', value: 'fixed' },
    { label: 'Percentage', value: 'percentage' },
];

function normalizeTaxExtensionMasterOptions(
    options: TaxExtensionMasterOption[] = [],
): TaxExtensionMasterOption[] {
    const grouped = new Map<string, TaxExtensionMasterOption>();

    options.forEach((option) => {
        const id = Number(option.id ?? 0);
        const name = String(option.name ?? '')
            .trim()
            .toLowerCase();
        const type = String(option.type ?? 'tax')
            .trim()
            .toLowerCase();
        const mode = String(option.calculation_mode ?? 'fixed')
            .trim()
            .toLowerCase();
        const value = Number(option.calculation_value ?? 0);

        const key =
            mode === 'percentage'
                ? [name, type, mode, String(value)].join('|')
                : [name, type, mode].join('|');

        if (!grouped.has(key)) {
            grouped.set(key, {
                id,
                name: option.name,
                type,
                calculation_mode: option.calculation_mode ?? null,
                calculation_value: option.calculation_value ?? null,
            });
        }
    });

    return Array.from(grouped.values());
}

type QuotationItemTaxInput = {
    _key?: string;
    id?: number;
    quotation_item_id?: number | null;
    quotation_extension_master_id?: number | null;
    name?: string | null;
    calculation_mode?: string | null;
    calculation_value?: string | number | null;
    sort_order?: number;
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
    showTaxColumn?: boolean;
    showTotalFooter?: boolean;
    memberOptions?: OptionType[];
    taxExtensionMasters?: TaxExtensionMasterOption[];
    memberSharingPlanById?: Record<number, string | null | undefined>;
    itemDescriptionGroupOptions?: ItemDescriptionComboboxGroupOption[];
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
    showTaxColumn = false,
    showTotalFooter = true,
    memberOptions = [],
    taxExtensionMasters = EMPTY_TAX_EXTENSION_MASTERS,
    memberSharingPlanById = {},
    itemDescriptionGroupOptions = [],
}: QuotationItemTableFormProps<T>) {
    const [expanded, setExpanded] = useState<Record<string, boolean>>({});
    const [availableTaxExtensionMasters, setAvailableTaxExtensionMasters] =
        useState<TaxExtensionMasterOption[]>(
            normalizeTaxExtensionMasterOptions(taxExtensionMasters),
        );
    const [isTaxEditorOpen, setIsTaxEditorOpen] = useState(false);
    const [taxEditorTarget, setTaxEditorTarget] = useState<{
        itemKey: string;
        taxIndex: number;
    } | null>(null);
    const [taxDraft, setTaxDraft] = useState<TaxEditorDraft>({
        name: 'Extension',
        type: 'tax',
        calculation_mode: 'fixed',
        amount: null,
    });

    useEffect(() => {
        setAvailableTaxExtensionMasters(
            normalizeTaxExtensionMasterOptions(taxExtensionMasters),
        );
    }, [taxExtensionMasters]);

    const isLinkedMemberItem = (item: T): boolean =>
        Number(item.customer_confirmation_member_id ?? 0) > 0;

    const getItemKey = (item: T) => item._key;

    const getSourceIndex = (key: string) =>
        items.findIndex((i) => i._key === key);

    const isEmptyTax = (tax: QuotationItemTaxInput): boolean => {
        const hasMaster = Number(tax.quotation_extension_master_id ?? 0) > 0;
        const hasName = String(tax.name ?? '').trim() !== '';
        const mode = String(tax.calculation_mode ?? '');
        const hasMode = ['fixed', 'percentage'].includes(mode);
        const value = Number(tax.calculation_value ?? 0);
        const hasValue = value !== 0;

        return !(hasMaster || hasName || hasMode || hasValue);
    };

    const createEmptyTax = (sortOrder: number): QuotationItemTaxInput => ({
        _key: nanoid(),
        quotation_extension_master_id: null,
        name: null,
        calculation_mode: null,
        calculation_value: null,
        sort_order: sortOrder,
    });

    const normalizeItemTaxes = (taxes: unknown): QuotationItemTaxInput[] => {
        if (!Array.isArray(taxes)) {
            return [];
        }

        return taxes
            .filter((tax): tax is QuotationItemTaxInput => {
                return Boolean(tax && typeof tax === 'object');
            })
            .map((tax, index) => ({
                ...tax,
                _key: tax._key ?? nanoid(),
                sort_order: Number(tax.sort_order ?? index + 1),
            }));
    };

    const withTrailingEmptyTax = (
        taxes: QuotationItemTaxInput[],
    ): QuotationItemTaxInput[] => {
        const normalized = taxes.filter((tax, index) => {
            if (!isEmptyTax(tax)) {
                return true;
            }

            return index === taxes.length - 1;
        });

        const hasTrailingEmpty =
            normalized.length > 0 &&
            isEmptyTax(normalized[normalized.length - 1]);
        const withTrailing = hasTrailingEmpty
            ? normalized
            : [...normalized, createEmptyTax(normalized.length + 1)];

        return withTrailing.map((tax, index) => ({
            ...tax,
            sort_order: index + 1,
        }));
    };

    const getDisplayTaxes = (item: T): QuotationItemTaxInput[] => {
        const rawTaxes = normalizeItemTaxes(
            (item as T & { taxes?: unknown }).taxes,
        );

        return withTrailingEmptyTax(rawTaxes);
    };

    const clearTaxInput = (item: T, taxIndex: number): void => {
        const nextTaxes = getDisplayTaxes(item).map((currentTax, index) => {
            if (index !== taxIndex) {
                return currentTax;
            }

            return {
                ...currentTax,
                quotation_extension_master_id: null,
                name: null,
                calculation_mode: null,
                calculation_value: null,
            };
        });

        updateItemByKey(item._key, {
            taxes: withTrailingEmptyTax(nextTaxes),
        } as Partial<T>);
    };

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

    const isLockedLinkedMemberItem = (item: T): boolean => {
        if (!isLinkedMemberItem(item)) {
            return false;
        }

        return computeItemAmount(item) !== 0;
    };

    const hasLockedLinkedMemberChildren = (target: T): boolean => {
        return items.some(
            (item) =>
                isLockedLinkedMemberItem(item) &&
                (item.parent_key === target._key ||
                    (target.id && item.parent_id === target.id)),
        );
    };

    const isUmrahPackagesHeader = (item: T): boolean => {
        if (!item.is_header) {
            return false;
        }

        return (
            String(item.description ?? '')
                .trim()
                .toLowerCase() === 'umrah packages'
        );
    };

    const hasNonZeroChildAmount = (target: T): boolean => {
        return items.some(
            (item) =>
                !item.is_header &&
                (item.parent_key === target._key ||
                    (target.id && item.parent_id === target.id)) &&
                computeItemAmount(item) !== 0,
        );
    };

    const computeTaxRowAmount = (item: T, tax: QuotationItemTaxInput) => {
        const calculationMode = String(tax.calculation_mode ?? '');
        const calculationValue = Number(tax.calculation_value ?? 0);

        if (
            !['fixed', 'percentage'].includes(calculationMode) ||
            calculationValue === 0
        ) {
            return 0;
        }

        const lineAmount = computeItemAmount(item);

        return calculationMode === 'percentage'
            ? (lineAmount * calculationValue) / 100
            : calculationValue;
    };

    const normalizeSelectedExtensionValue = (
        option: ExtensionMasterComboboxOption | TaxExtensionMasterOption,
    ): number => {
        const rawValue = Number(option.calculation_value ?? 0);

        if (!Number.isFinite(rawValue)) {
            return 0;
        }

        const optionType = String(option.type ?? '').toLowerCase();

        if (optionType === 'discount') {
            return -Math.abs(rawValue);
        }

        return Math.abs(rawValue);
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
        rate: null,
        taxes: [createEmptyTax(1)],
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
            quantity: 1,
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
            quantity: 1,
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
        const sourceItem = items[index] as T & {
            taxes?: QuotationItemTaxInput[];
        };

        const next = [...items];
        next.splice(index + 1, 0, {
            ...sourceItem,
            _key: nanoid(),
            id: undefined,
            taxes: normalizeItemTaxes(sourceItem.taxes).map(
                (tax, taxIndex) => ({
                    ...tax,
                    _key: nanoid(),
                    id: undefined,
                    quotation_item_id: null,
                    sort_order: taxIndex + 1,
                }),
            ),
        });

        onChange(normalizeSortOrder(next));
    };

    const removeItem = (index: number) => {
        const target = items[index];
        const lockedByUmrahChildAmount =
            isUmrahPackagesHeader(target) && hasNonZeroChildAmount(target);

        if (isLockedLinkedMemberItem(target)) {
            return;
        }

        if (hasLockedLinkedMemberChildren(target)) {
            return;
        }

        if (lockedByUmrahChildAmount) {
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

    const getDescendantKeys = useCallback(
        (parent: T, pool: T[] = items): Set<string> => {
            const descendants = new Set<string>([parent._key]);
            const stack: string[] = [parent._key];

            while (stack.length > 0) {
                const currentKey = stack.pop();

                if (!currentKey) {
                    continue;
                }

                const currentItem = pool.find(
                    (candidate) => candidate._key === currentKey,
                );

                const children = pool.filter(
                    (candidate) =>
                        candidate.parent_key === currentKey ||
                        (currentItem?.id != null &&
                            candidate.parent_id === currentItem.id),
                );

                children.forEach((child) => {
                    if (descendants.has(child._key)) {
                        return;
                    }

                    descendants.add(child._key);
                    stack.push(child._key);
                });
            }

            return descendants;
        },
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
        if (!over || active.id === over.id) {
            return;
        }

        const activeKey = String(active.id);
        const overKey = String(over.id);

        const dragged = items.find((item) => item._key === activeKey);

        if (!dragged) {
            return;
        }

        const draggedKeys = dragged.is_header
            ? getDescendantKeys(dragged)
            : new Set<string>([dragged._key]);

        if (draggedKeys.has(overKey)) {
            return;
        }

        const draggedBlock = items.filter((item) => draggedKeys.has(item._key));
        const remaining = items.filter((item) => !draggedKeys.has(item._key));
        const target = remaining.find((item) => item._key === overKey);

        if (!target) {
            return;
        }

        let nextParentId = dragged.parent_id ?? null;
        let nextParentKey = dragged.parent_key ?? null;

        if (target.is_header) {
            nextParentId = target.id ?? null;
            nextParentKey = target._key;
        } else if (target.parent_key != null || target.parent_id != null) {
            const parentFromKey =
                target.parent_key != null
                    ? remaining.find(
                          (candidate) => candidate._key === target.parent_key,
                      )
                    : null;
            const parentFromId =
                parentFromKey ??
                (target.parent_id != null
                    ? remaining.find(
                          (candidate) => candidate.id === target.parent_id,
                      )
                    : null);

            nextParentId = parentFromId?.id ?? null;
            nextParentKey = parentFromId?._key ?? target.parent_key ?? null;
        } else {
            if (!dragged.is_header && !isLinkedMemberItem(dragged)) {
                return;
            }

            nextParentId = null;
            nextParentKey = null;
        }

        if (
            !dragged.is_header &&
            nextParentKey == null &&
            nextParentId == null
        ) {
            return;
        }

        if (nextParentKey && draggedKeys.has(nextParentKey)) {
            return;
        }

        if (nextParentKey) {
            const nextParent = remaining.find(
                (candidate) => candidate._key === nextParentKey,
            );

            if (!nextParent?.is_header) {
                return;
            }
        }

        let insertIndex = remaining.findIndex((item) => item._key === overKey);

        if (insertIndex === -1) {
            return;
        }

        if (target.is_header && nextParentKey === target._key) {
            const targetSubtreeKeys = getDescendantKeys(target, remaining);
            const lastSubtreeIndex = remaining.reduce(
                (lastIndex, item, index) => {
                    if (!targetSubtreeKeys.has(item._key)) {
                        return lastIndex;
                    }

                    return index;
                },
                insertIndex,
            );

            insertIndex = lastSubtreeIndex + 1;
        }

        const normalizedBlock = draggedBlock.map((entry) => {
            if (entry._key !== dragged._key) {
                return entry;
            }

            return {
                ...entry,
                parent_id: nextParentId,
                parent_key: nextParentKey,
            } as T;
        });

        const next = [...remaining];
        next.splice(insertIndex, 0, ...normalizedBlock);

        onChange(normalizeSortOrder(next));
    };

    const taxMasterById = useMemo(
        () =>
            new Map(
                availableTaxExtensionMasters.map((master) => [
                    Number(master.id),
                    master,
                ]),
            ),
        [availableTaxExtensionMasters],
    );

    const inferTaxType = useCallback(
        (tax: QuotationItemTaxInput): 'tax' | 'discount' => {
            const linkedMasterType =
                Number(tax.quotation_extension_master_id ?? 0) > 0
                    ? String(
                          taxMasterById.get(
                              Number(tax.quotation_extension_master_id),
                          )?.type ?? '',
                      )
                          .trim()
                          .toLowerCase()
                    : '';

            if (linkedMasterType === 'discount') {
                return 'discount';
            }

            return Number(tax.calculation_value ?? 0) < 0 ? 'discount' : 'tax';
        },
        [taxMasterById],
    );

    const formatTaxLabel = useCallback((tax: QuotationItemTaxInput): string => {
        const name = String(tax.name ?? '').trim() || 'Extension';
        const mode = String(tax.calculation_mode ?? 'fixed').toLowerCase();
        const value = Math.abs(Number(tax.calculation_value ?? 0));

        if (mode === 'percentage') {
            return `${name} ${value}%`;
        }

        return name;
    }, []);

    const openTaxEditor = useCallback(
        (item: T, taxIndex: number, seedTax?: QuotationItemTaxInput): void => {
            const displayTaxes = getDisplayTaxes(item);
            const targetTax =
                seedTax ??
                displayTaxes[taxIndex] ??
                createEmptyTax(taxIndex + 1);

            setTaxEditorTarget({ itemKey: item._key, taxIndex });
            setTaxDraft({
                name: String(targetTax.name ?? '').trim() || 'Extension',
                type: inferTaxType(targetTax),
                calculation_mode:
                    String(targetTax.calculation_mode ?? 'fixed') ===
                    'percentage'
                        ? 'percentage'
                        : 'fixed',
                amount: Math.abs(Number(targetTax.calculation_value ?? 0)),
            });
            setIsTaxEditorOpen(true);
        },
        [inferTaxType],
    );

    const closeTaxEditor = useCallback((open: boolean): void => {
        setIsTaxEditorOpen(open);

        if (!open) {
            setTaxEditorTarget(null);
        }
    }, []);

    const saveTaxEditor = useCallback((): void => {
        if (!taxEditorTarget) {
            return;
        }

        const targetItem = items.find(
            (item) => item._key === taxEditorTarget.itemKey,
        );

        if (!targetItem) {
            setIsTaxEditorOpen(false);
            setTaxEditorTarget(null);

            return;
        }

        const baseValue = Math.abs(Number(taxDraft.amount ?? 0));
        const signedValue =
            taxDraft.type === 'discount' ? -baseValue : baseValue;

        const nextTaxes = getDisplayTaxes(targetItem).map(
            (currentTax, index) => {
                if (index !== taxEditorTarget.taxIndex) {
                    return currentTax;
                }

                return {
                    ...currentTax,
                    quotation_extension_master_id: null,
                    name: taxDraft.name.trim() || 'Extension',
                    calculation_mode: taxDraft.calculation_mode,
                    calculation_value: signedValue,
                };
            },
        );

        updateItemByKey(targetItem._key, {
            taxes: withTrailingEmptyTax(nextTaxes),
        } as Partial<T>);

        setIsTaxEditorOpen(false);
        setTaxEditorTarget(null);
    }, [items, taxDraft, taxEditorTarget]);

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
                                disabled={disabled}
                                textarea={!item.is_header}
                                // size="compact"
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
                                disabled={disabled}
                                textarea
                                // size="compact"
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
                                isChild={false}
                                existingItemKeys={existingItemKeys}
                                groupOptions={itemDescriptionGroupOptions}
                                onSelect={(payload) => {
                                    if (payload.type === 'single') {
                                        updateItemByKey(item._key, {
                                            description:
                                                payload.item.description,
                                            quantity: payload.item.quantity,
                                            rate: payload.item.rate,
                                            is_header: payload.item.is_header,
                                            id: payload.item.id,
                                            taxes: getDisplayTaxes(
                                                payload.item as T,
                                            ),
                                        } as Partial<T>);
                                        return;
                                    }

                                    if (payload.type === 'group') {
                                        const parentKey = item._key;
                                        const currentParentId = item.parent_id;
                                        const currentParentKey =
                                            item.parent_key;

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
                                                      parent_id:
                                                          currentParentId ??
                                                          null,
                                                      parent_key:
                                                          currentParentKey ??
                                                          null,
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
                                                          rate: null,
                                                          taxes: [
                                                              createEmptyTax(1),
                                                          ],
                                                          is_header: false,
                                                          is_optional:
                                                              payload.parent
                                                                  .is_optional,
                                                      },
                                                  ];

                                        const children = childrenSource.map(
                                            (child) => {
                                                const normalizedChild =
                                                    child as Partial<QuotationItemSchema>;

                                                return {
                                                    _key: nanoid(),
                                                    id: normalizedChild.id,
                                                    parent_id:
                                                        payload.parent.id ??
                                                        null,
                                                    parent_key: parentKey,
                                                    description:
                                                        normalizedChild.description ??
                                                        '',
                                                    quantity:
                                                        normalizedChild.quantity ??
                                                        1,
                                                    rate:
                                                        normalizedChild.rate ??
                                                        null,
                                                    taxes: getDisplayTaxes(
                                                        normalizedChild as T,
                                                    ),
                                                    customer_confirmation_member_id:
                                                        Number(
                                                            normalizedChild.customer_confirmation_member_id ??
                                                                0,
                                                        ) > 0
                                                            ? Number(
                                                                  normalizedChild.customer_confirmation_member_id,
                                                              )
                                                            : null,
                                                    sharing_plan:
                                                        normalizedChild.sharing_plan ??
                                                        null,
                                                    is_header:
                                                        normalizedChild.is_header ??
                                                        false,
                                                    is_optional:
                                                        normalizedChild.is_optional ??
                                                        payload.parent
                                                            .is_optional,
                                                    sort_order: 0,
                                                };
                                            },
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
                                      //   size="compact"
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
                const parsedQuantity = Number(item.quantity ?? 0);
                const displayQuantity =
                    item.quantity == null ||
                    String(item.quantity).trim() === '' ||
                    !Number.isFinite(parsedQuantity) ||
                    parsedQuantity <= 0
                        ? ''
                        : item.quantity;

                if (item.is_header) {
                    return (
                        <div className="text-center text-muted-foreground"></div>
                    );
                }

                return (
                    <div className="relative flex w-full flex-col gap-1">
                        <ProperInput
                            value={displayQuantity}
                            type="number"
                            inputProps={{ step: 'any', min: 0 }}
                            disabled={disabled}
                            // size="compact"
                            onCommit={(value) => {
                                const nextQuantity = Number(value);

                                updateItemByKey(item._key, {
                                    quantity:
                                        Number.isFinite(nextQuantity) &&
                                        nextQuantity > 0
                                            ? nextQuantity
                                            : 1,
                                } as Partial<T>);
                            }}
                            placeholder="1"
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
                const parsedRate = Number(item.rate ?? 0);
                const displayRate =
                    item.rate == null ||
                    String(item.rate).trim() === '' ||
                    !Number.isFinite(parsedRate)
                        ? ''
                        : item.rate;

                if (item.is_header) {
                    return (
                        <div className="text-center text-muted-foreground"></div>
                    );
                }

                return (
                    <div className="relative flex w-full flex-col gap-1">
                        <ProperInput
                            value={displayRate}
                            type="number"
                            inputProps={{ step: 'any' }}
                            placeholder="0.00"
                            disabled={disabled}
                            // size="compact"
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
                        <div className="text-center text-muted-foreground"></div>
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
                const isLockedMemberItem = isLockedLinkedMemberItem(item);
                const hasLockedMemberChildren =
                    hasLockedLinkedMemberChildren(item);
                const lockedByUmrahChildAmount =
                    isUmrahPackagesHeader(item) && hasNonZeroChildAmount(item);
                const disableRemove =
                    isLockedMemberItem ||
                    hasLockedMemberChildren ||
                    lockedByUmrahChildAmount;
                const canMoveToAnotherInvoice =
                    item.is_header === true &&
                    item.parent_id == null &&
                    item.parent_key == null;

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
                                            'number' &&
                                        canMoveToAnotherInvoice && (
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
                                                            key={`${inv._key ?? 'invoice'}-${idx}`}
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

                        {!disableRemove && (
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
                        )}
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

    const quantityColumnIndex = columns.findIndex(
        (column) => column.id === 'quantity',
    );
    const descriptionColumnIndex = columns.findIndex(
        (column) => column.id === 'description',
    );
    const taxLeadingColSpanRaw =
        quantityColumnIndex > 0 ? quantityColumnIndex : 0;
    const hasDescriptionBeforeQuantity =
        descriptionColumnIndex >= 0 &&
        descriptionColumnIndex < quantityColumnIndex;
    const taxLeadingColSpan = Math.max(
        taxLeadingColSpanRaw - (hasDescriptionBeforeQuantity ? 1 : 0),
        0,
    );
    const taxColumnCount = Math.max(
        columns.length - (descriptionColumnIndex >= 0 ? 1 : 0),
        0,
    );
    const taxTrailingColSpan = Math.max(
        taxColumnCount - (taxLeadingColSpan + 3),
        0,
    );

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
                                            const displayTaxes =
                                                showTaxColumn && !item.is_header
                                                    ? getDisplayTaxes(item)
                                                    : [];
                                            const hasTaxSubRow =
                                                displayTaxes.length > 0;
                                            const isChild = level > 0;
                                            const isLastChild =
                                                isChild &&
                                                (!next ||
                                                    next.level <= level ||
                                                    (next.item.parent_id !==
                                                        item.parent_id &&
                                                        next.item.parent_key !==
                                                            item.parent_key));

                                            const sourceIndex = getSourceIndex(
                                                item._key,
                                            );

                                            return (
                                                <Fragment key={item._key}>
                                                    <SortableRow id={item._key}>
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
                                                                    hasTaxSubRow &&
                                                                        'border-b-0',
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
                                                                    .map(
                                                                        (
                                                                            cell,
                                                                        ) => {
                                                                            const isDescriptionCell =
                                                                                cell
                                                                                    .column
                                                                                    .id ===
                                                                                'description';
                                                                            const descriptionSpansTaxRow =
                                                                                hasTaxSubRow &&
                                                                                isDescriptionCell;

                                                                            return (
                                                                                <TableCell
                                                                                    key={`${row.id}-${cell.column.id}`}
                                                                                    rowSpan={
                                                                                        descriptionSpansTaxRow
                                                                                            ? displayTaxes.length +
                                                                                              1
                                                                                            : undefined
                                                                                    }
                                                                                    className={cn(
                                                                                        descriptionSpansTaxRow &&
                                                                                            'align-middle',
                                                                                    )}
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
                                                                            );
                                                                        },
                                                                    )}
                                                            </TableRow>
                                                        )}
                                                    </SortableRow>

                                                    {displayTaxes.map(
                                                        (tax, taxIndex) => (
                                                            <TableRow
                                                                key={`${item._key}-tax-${tax._key ?? taxIndex}`}
                                                                className="border-0 bg-transparent [&>td]:border-0"
                                                            >
                                                                {taxLeadingColSpan >
                                                                    0 && (
                                                                    <TableCell
                                                                        colSpan={
                                                                            taxLeadingColSpan
                                                                        }
                                                                        className="text-center text-muted-foreground"
                                                                    ></TableCell>
                                                                )}
                                                                <TableCell
                                                                    colSpan={2}
                                                                >
                                                                    <div className="relative flex w-full flex-col gap-1">
                                                                        <div className="flex items-center gap-2">
                                                                            <div className="flex-1">
                                                                                {isEmptyTax(
                                                                                    tax,
                                                                                ) ? (
                                                                                    <ExtensionMasterCombobox
                                                                                        value={
                                                                                            null
                                                                                        }
                                                                                        extensionType="item"
                                                                                        options={
                                                                                            availableTaxExtensionMasters as ExtensionMasterComboboxOption[]
                                                                                        }
                                                                                        disabled={
                                                                                            disabled
                                                                                        }
                                                                                        placeholder="Select extension"
                                                                                        onSelect={(
                                                                                            option,
                                                                                        ) => {
                                                                                            const selectedTaxMaster =
                                                                                                taxMasterById.get(
                                                                                                    Number(
                                                                                                        option.id,
                                                                                                    ),
                                                                                                ) ??
                                                                                                option;

                                                                                            const nextSelectedTax: QuotationItemTaxInput =
                                                                                                {
                                                                                                    ...tax,
                                                                                                    quotation_extension_master_id:
                                                                                                        null,
                                                                                                    name:
                                                                                                        selectedTaxMaster.name ??
                                                                                                        null,
                                                                                                    calculation_mode:
                                                                                                        selectedTaxMaster.calculation_mode ??
                                                                                                        null,
                                                                                                    calculation_value:
                                                                                                        normalizeSelectedExtensionValue(
                                                                                                            selectedTaxMaster,
                                                                                                        ),
                                                                                                };

                                                                                            const nextTaxes =
                                                                                                getDisplayTaxes(
                                                                                                    item,
                                                                                                ).map(
                                                                                                    (
                                                                                                        currentTax,
                                                                                                        currentTaxIndex,
                                                                                                    ) => {
                                                                                                        if (
                                                                                                            currentTaxIndex !==
                                                                                                            taxIndex
                                                                                                        ) {
                                                                                                            return currentTax;
                                                                                                        }

                                                                                                        return nextSelectedTax;
                                                                                                    },
                                                                                                );

                                                                                            updateItemByKey(
                                                                                                item._key,
                                                                                                {
                                                                                                    taxes: withTrailingEmptyTax(
                                                                                                        nextTaxes,
                                                                                                    ),
                                                                                                } as Partial<T>,
                                                                                            );
                                                                                        }}
                                                                                        onOptionsChange={(
                                                                                            nextOptions,
                                                                                        ) => {
                                                                                            setAvailableTaxExtensionMasters(
                                                                                                normalizeTaxExtensionMasterOptions(
                                                                                                    nextOptions.map(
                                                                                                        (
                                                                                                            option,
                                                                                                        ) => ({
                                                                                                            id: Number(
                                                                                                                option.id,
                                                                                                            ),
                                                                                                            name: option.name,
                                                                                                            type: option.type,
                                                                                                            calculation_mode:
                                                                                                                option.calculation_mode ??
                                                                                                                null,
                                                                                                            calculation_value:
                                                                                                                option.calculation_value ??
                                                                                                                null,
                                                                                                        }),
                                                                                                    ),
                                                                                                ),
                                                                                            );
                                                                                        }}
                                                                                    />
                                                                                ) : (
                                                                                    <button
                                                                                        type="button"
                                                                                        className="w-full cursor-pointer rounded-md border px-2 py-1 text-left text-base font-medium hover:bg-muted"
                                                                                        onClick={() =>
                                                                                            openTaxEditor(
                                                                                                item,
                                                                                                taxIndex,
                                                                                                tax,
                                                                                            )
                                                                                        }
                                                                                    >
                                                                                        {formatTaxLabel(
                                                                                            tax,
                                                                                        )}
                                                                                    </button>
                                                                                )}
                                                                            </div>
                                                                            {!disabled &&
                                                                                !isEmptyTax(
                                                                                    tax,
                                                                                ) && (
                                                                                    <Button
                                                                                        type="button"
                                                                                        variant="ghost"
                                                                                        size="icon"
                                                                                        className="h-7 w-7 text-red-600 hover:text-red-700"
                                                                                        onClick={() =>
                                                                                            clearTaxInput(
                                                                                                item,
                                                                                                taxIndex,
                                                                                            )
                                                                                        }
                                                                                    >
                                                                                        <X
                                                                                            size={
                                                                                                14
                                                                                            }
                                                                                        />
                                                                                    </Button>
                                                                                )}
                                                                        </div>
                                                                        {renderError?.(
                                                                            `items.${sourceIndex}.taxes.${taxIndex}.quotation_extension_master_id`,
                                                                        )}
                                                                    </div>
                                                                </TableCell>
                                                                <TableCell className="text-right font-medium">
                                                                    {isEmptyTax(
                                                                        tax,
                                                                    )
                                                                        ? ''
                                                                        : formatCurrency(
                                                                              computeTaxRowAmount(
                                                                                  item as T,
                                                                                  tax,
                                                                              ),
                                                                          )}
                                                                </TableCell>
                                                                {taxTrailingColSpan >
                                                                    0 && (
                                                                    <TableCell
                                                                        colSpan={
                                                                            taxTrailingColSpan
                                                                        }
                                                                    />
                                                                )}
                                                            </TableRow>
                                                        ),
                                                    )}
                                                </Fragment>
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
                            {showTotalFooter && (
                                <TableFooter>
                                    <TableRow>
                                        <TableCell
                                            colSpan={columns.length - 1}
                                            className="text-right font-semibold"
                                        >
                                            Total:
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {formatCurrency(totalAmount)}
                                        </TableCell>
                                    </TableRow>
                                </TableFooter>
                            )}
                        </Table>
                    </SortableContext>
                </DndContext>
            </div>

            <Dialog open={isTaxEditorOpen} onOpenChange={closeTaxEditor}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Configure Extension</DialogTitle>
                        <DialogDescription>
                            Update the selected item extension values.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-3">
                        <div className="space-y-1">
                            <label className="text-sm font-medium">
                                Extension Name
                            </label>
                            <ProperInput
                                value={taxDraft.name}
                                onCommit={(value) =>
                                    setTaxDraft((prev) => ({
                                        ...prev,
                                        name: value,
                                    }))
                                }
                            />
                        </div>

                        <div className="space-y-1">
                            <label className="text-sm font-medium">Type</label>
                            <ProperInputSelect
                                options={TAX_TYPE_OPTIONS}
                                value={taxDraft.type}
                                onValueChange={(value) =>
                                    setTaxDraft((prev) => ({
                                        ...prev,
                                        type:
                                            value === 'discount'
                                                ? 'discount'
                                                : 'tax',
                                    }))
                                }
                            />
                        </div>

                        <div className="space-y-1">
                            <label className="text-sm font-medium">
                                Calculation
                            </label>
                            <ProperInputSelect
                                options={TAX_CALCULATION_MODE_OPTIONS}
                                value={taxDraft.calculation_mode}
                                onValueChange={(value) =>
                                    setTaxDraft((prev) => ({
                                        ...prev,
                                        calculation_mode:
                                            value === 'percentage'
                                                ? 'percentage'
                                                : 'fixed',
                                    }))
                                }
                            />
                        </div>

                        <div className="space-y-1">
                            <label className="text-sm font-medium">
                                {taxDraft.calculation_mode === 'percentage'
                                    ? 'Value (%)'
                                    : 'Amount'}
                            </label>
                            <ProperInput
                                value={taxDraft.amount ?? ''}
                                type="number"
                                inputProps={{ step: 'any', min: 0 }}
                                placeholder="0"
                                onCommit={(value) =>
                                    setTaxDraft((prev) => ({
                                        ...prev,
                                        amount: Math.abs(Number(value ?? 0)),
                                    }))
                                }
                            />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => closeTaxEditor(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="button" onClick={saveTaxEditor}>
                            Save
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
