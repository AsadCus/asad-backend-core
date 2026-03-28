import { FormField } from '@/components/form-field';
import ModelNumberInput from '@/components/model-number-input';
import { ProperInput } from '@/components/proper-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatCurrency } from '@/lib/utils';
import { show as showCustomerConfirmation } from '@/routes/customer-confirmations';
import { OptionType } from '@/types';
import { useForm } from '@inertiajs/react';
import { AlertCircle, Trash } from 'lucide-react';
import { nanoid } from 'nanoid';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { InvoiceHeader } from '../invoices/components/invoice-header';
import {
    calculateInvoicesTotal,
    calculateTotal,
    collectWithChildren,
    normalizeItems,
} from '../invoices/lib/utils';
import { InvoiceSchema, statusColors, statuses } from '../invoices/schema';
import QuotationItemTableForm from '../quotations/items/form';
import { QuotationSchema } from '../quotations/schema';
import { PaymentPlanSection } from './components/payment-plan-section';
import {
    autoFillInvoiceDates,
    buildInvoices,
    buildInvoicesFromItems,
    quotationItemsToInvoiceItems,
} from './lib/invoice-builders';
import { OrderSchema } from './schema';
import { orderValidationSchema } from './validation';

interface OrderFormProps {
    mode: 'create' | 'edit' | 'view';
    initialData?: OrderSchema;
    quotation?: QuotationSchema;
    paymentPlans?: OptionType[];
    onCancel?: () => void;
}

type InvoiceExtensionInput = {
    _key?: string;
    id?: number;
    quotation_extension_master_id?: number | null;
    name?: string | null;
    type?: string | null;
    calculation_mode?: string | null;
    calculation_value?: number | string | null;
    amount?: number | string | null;
    sort_order?: number;
};

function calculateItemTaxTotal(items: InvoiceSchema['items'] = []): number {
    return items.reduce((sum, item) => {
        if (item.is_header) {
            return sum;
        }

        const lineAmount = Number(item.quantity ?? 0) * Number(item.rate ?? 0);
        const itemTaxTotal = (item.taxes ?? []).reduce((taxSum, tax) => {
            const calculationMode = String(tax.calculation_mode ?? '');
            const calculationValue = Number(tax.calculation_value ?? 0);

            if (
                !['fixed', 'percentage'].includes(calculationMode) ||
                calculationValue <= 0
            ) {
                return taxSum;
            }

            const taxAmount =
                calculationMode === 'percentage'
                    ? (lineAmount * calculationValue) / 100
                    : calculationValue;

            return taxSum + taxAmount;
        }, 0);

        return sum + itemTaxTotal;
    }, 0);
}

function normalizeInvoiceExtensions(
    extensions: InvoiceExtensionInput[] = [],
): InvoiceExtensionInput[] {
    return extensions.map((extension, index) => {
        const calculationMode =
            String(extension.calculation_mode ?? 'fixed') === 'percentage'
                ? 'percentage'
                : 'fixed';

        return {
            ...extension,
            id:
                typeof extension.id === 'number' &&
                Number.isFinite(extension.id)
                    ? extension.id
                    : undefined,
            _key:
                extension._key ??
                (extension.id ? `id-${extension.id}` : nanoid()),
            name: String(extension.name ?? 'Extension'),
            type: String(extension.type ?? 'discount'),
            calculation_mode: calculationMode,
            calculation_value: Number(extension.calculation_value ?? 0),
            amount: Number(extension.amount ?? 0),
            sort_order: Number(extension.sort_order ?? index + 1),
        };
    });
}

function computeInvoiceExtensionAmount(
    extension: InvoiceExtensionInput,
    subtotalAmount: number,
): number {
    const calculationMode = String(extension.calculation_mode ?? 'fixed');
    const calculationValue = Number(extension.calculation_value ?? 0);
    const extensionType = String(extension.type ?? 'discount');

    if (calculationMode === 'percentage') {
        const rawAmount = (subtotalAmount * calculationValue) / 100;

        return extensionType === 'discount' ? -Math.abs(rawAmount) : rawAmount;
    }

    const fallbackAmount = Number(extension.amount ?? 0);
    const rawAmount =
        calculationValue !== 0 || fallbackAmount === 0
            ? calculationValue
            : fallbackAmount;

    return extensionType === 'discount' ? -Math.abs(rawAmount) : rawAmount;
}

function formatExtensionLabel(
    name: string,
    calculationMode?: string | null,
    calculationValue?: number | null,
): string {
    if (String(calculationMode ?? 'fixed') !== 'percentage') {
        return name;
    }

    return `${name} ${Number(calculationValue ?? 0)}%`;
}

function buildItemTaxSummaries(items: InvoiceSchema['items'] = []): Array<{
    name: string;
    calculation_mode: string;
    calculation_value: number;
    amount: number;
}> {
    const grouped = new Map<
        string,
        {
            name: string;
            calculation_mode: string;
            calculation_value: number;
            amount: number;
        }
    >();

    items.forEach((item) => {
        if (item.is_header) {
            return;
        }

        const lineAmount = Number(item.quantity ?? 0) * Number(item.rate ?? 0);

        (item.taxes ?? []).forEach((tax) => {
            const calculationMode = String(tax.calculation_mode ?? '');
            const calculationValue = Number(tax.calculation_value ?? 0);

            if (
                !['fixed', 'percentage'].includes(calculationMode) ||
                calculationValue <= 0
            ) {
                return;
            }

            const key = [
                Number(tax.quotation_extension_master_id ?? 0),
                String(tax.name ?? 'Tax').toLowerCase(),
                calculationMode,
                calculationValue,
            ].join('|');

            const current = grouped.get(key) ?? {
                name: String(tax.name ?? 'Tax'),
                calculation_mode: calculationMode,
                calculation_value: calculationValue,
                amount: 0,
            };

            current.amount +=
                calculationMode === 'percentage'
                    ? (lineAmount * calculationValue) / 100
                    : calculationValue;

            grouped.set(key, current);
        });
    });

    return Array.from(grouped.values());
}

function recalculateInvoice(invoice: InvoiceSchema): InvoiceSchema {
    const subtotalAmount = calculateTotal(invoice.items ?? []);
    const itemTaxTotal = calculateItemTaxTotal(invoice.items ?? []);
    const normalizedExtensions = normalizeInvoiceExtensions(
        (invoice.extensions ?? []) as InvoiceExtensionInput[],
    );

    const extensionsWithAmount = normalizedExtensions.map((extension) => ({
        ...extension,
        amount: computeInvoiceExtensionAmount(extension, subtotalAmount),
    }));

    const extensionTotal = extensionsWithAmount.reduce(
        (sum, extension) => sum + Number(extension.amount ?? 0),
        0,
    );

    return {
        ...invoice,
        extensions: extensionsWithAmount,
        amount: Number(
            (subtotalAmount + itemTaxTotal + extensionTotal).toFixed(2),
        ),
    };
}

function normalizeInvoices(invoices: InvoiceSchema[] = []): InvoiceSchema[] {
    const itemKeyMap = new Map<number, string>();

    const normalizedInvoices = invoices.map((invoice) => ({
        ...invoice,
        _key: invoice._key ?? (invoice.id ? `id-${invoice.id}` : nanoid()),
    }));

    normalizedInvoices.forEach((invoice) => {
        invoice.items?.forEach((item) => {
            if (item.id) {
                const key = item._key ?? `id-${item.id}`;
                itemKeyMap.set(item.id, key);
            }
        });
    });

    normalizedInvoices.forEach((invoice) => {
        invoice.items = invoice.items?.map((item) => ({
            ...item,
            _key: item._key ?? (item.id ? `id-${item.id}` : nanoid()),
            parent_key: item.parent_id
                ? (itemKeyMap.get(item.parent_id) ?? item.parent_key ?? null)
                : (item.parent_key ?? null),
        }));
    });

    return normalizedInvoices;
}

function cleanMessage(message: string) {
    return message
        .replace(/^The\s.+?\s/, '')
        .replace(/when\s.+?\.is_header\sis\sfalse\.?/i, 'when header is false')
        .replace(/\.$/, '');
}

function createEmptyInvoice(): InvoiceSchema {
    return {
        _key: nanoid(),
        invoice_number: '',
        number_format_id: null,
        description: '',
        invoice_date: '',
        due_date: '',
        extensions: [],
        items: [],
        amount: 0,
    };
}

const depositTypes = [
    { label: 'Percentage (%)', value: 'percentage' },
    { label: 'Fixed Amount ($)', value: 'fixed' },
];

export default function OrderForm({
    mode,
    initialData,
    quotation,
    paymentPlans = [],
    onCancel,
}: OrderFormProps) {
    const isView = mode === 'view';
    const isEdit = mode === 'edit';
    const isCreate = mode === 'create';

    const initialItems = quotation?.items.map((item) => ({
        ...item,
        _key: item.id ? `id-${item.id}` : nanoid(),
    }));

    const initialFormState: OrderSchema = {
        order_number: '',
        number_format_id: null,
        payment_plan: quotation?.payment_plan ?? 'direct',
        deposit_type: 'fixed',
        deposit_value: 500,
        invoices: [],
        items: initialItems ?? [],

        quotation_id: quotation?.id,
        quotation_number: quotation?.quotation_number,
    };

    const defaultData: OrderSchema = initialData
        ? {
              ...initialData,
              deposit_type: initialData.deposit_type ?? 'fixed',
              deposit_value:
                  initialData.deposit_value === null ||
                  initialData.deposit_value === undefined ||
                  initialData.deposit_value === ''
                      ? 500
                      : initialData.deposit_value,
              invoices: normalizeInvoices(initialData.invoices ?? []),
          }
        : initialFormState;

    const {
        data,
        setData,
        post,
        put,
        processing,
        errors,
        reset,
        setError,
        clearErrors,
    } = useForm<OrderSchema>(defaultData);

    const rebuildInvoicesFromSource = useCallback(
        (
            paymentPlan: string,
            depositType?: string | null,
            depositValue?: number | string | null,
            currentInvoices: InvoiceSchema[] = [],
        ): InvoiceSchema[] => {
            if (quotation) {
                const rebuiltInvoices = normalizeInvoices(
                    autoFillInvoiceDates(
                        buildInvoicesFromItems(
                            paymentPlan,
                            quotationItemsToInvoiceItems(quotation),
                            Number(quotation.total_amount ?? 0),
                            depositType,
                            depositValue,
                            quotation.extensions ?? [],
                        ),
                        {
                            defaultDate: quotation.quotation_date ?? '',
                            paymentPlan,
                            hasCustomerConfirmationMemberItem: (
                                quotation.items ?? []
                            ).some(
                                (item) =>
                                    Number(
                                        item.customer_confirmation_member_id ??
                                            0,
                                    ) > 0,
                            ),
                            packageDepartureDate:
                                quotation.package_departure_date ?? null,
                        },
                    ),
                );

                const totalSubtotal = rebuiltInvoices.reduce(
                    (sum, invoice) => sum + calculateTotal(invoice.items ?? []),
                    0,
                );

                const quotationExtensions = (quotation.extensions ??
                    []) as InvoiceExtensionInput[];

                return normalizeInvoices(
                    rebuiltInvoices.map((invoice, index) => {
                        const existingInvoice = currentInvoices[index];
                        const subtotalAmount = calculateTotal(
                            invoice.items ?? [],
                        );

                        const inheritedExtensions = normalizeInvoiceExtensions(
                            quotationExtensions.map(
                                (extension, extensionIndex) => {
                                    const ratio =
                                        totalSubtotal > 0
                                            ? subtotalAmount / totalSubtotal
                                            : 0;

                                    const calculationMode =
                                        String(
                                            extension.calculation_mode ??
                                                'fixed',
                                        ) === 'percentage'
                                            ? 'percentage'
                                            : 'fixed';

                                    const sourceAmount = Number(
                                        extension.amount ?? 0,
                                    );
                                    const calculationValue = Number(
                                        extension.calculation_value ??
                                            (calculationMode === 'fixed'
                                                ? sourceAmount
                                                : 0),
                                    );

                                    return {
                                        _key:
                                            extension._key ??
                                            `ext-${index + 1}-${extensionIndex + 1}`,
                                        id: undefined,
                                        quotation_extension_master_id:
                                            extension.quotation_extension_master_id ??
                                            null,
                                        name: extension.name ?? 'Extension',
                                        type: extension.type ?? 'discount',
                                        calculation_mode: calculationMode,
                                        calculation_value:
                                            calculationMode === 'percentage'
                                                ? calculationValue
                                                : Number(
                                                      (
                                                          sourceAmount * ratio
                                                      ).toFixed(2),
                                                  ),
                                        amount: 0,
                                        sort_order:
                                            extension.sort_order ??
                                            extensionIndex + 1,
                                    };
                                },
                            ),
                        );

                        const mergedInvoice = {
                            ...invoice,
                            extensions: inheritedExtensions,
                        } as InvoiceSchema;

                        if (!existingInvoice) {
                            return recalculateInvoice(mergedInvoice);
                        }

                        return recalculateInvoice({
                            ...mergedInvoice,
                            id: existingInvoice.id ?? invoice.id,
                            invoice_number:
                                existingInvoice.invoice_number ??
                                invoice.invoice_number,
                            number_format_id:
                                existingInvoice.number_format_id ??
                                invoice.number_format_id ??
                                null,
                            status:
                                existingInvoice.status ??
                                invoice.status ??
                                'issued',
                            invoice_date:
                                existingInvoice.invoice_date ??
                                invoice.invoice_date,
                            due_date:
                                existingInvoice.due_date ?? invoice.due_date,
                        });
                    }),
                );
            }

            return normalizeInvoices(
                buildInvoices(
                    paymentPlan,
                    currentInvoices,
                    depositType,
                    depositValue,
                ).map((invoice) => recalculateInvoice(invoice)),
            );
        },
        [quotation],
    );

    const serializeInvoicesForComparison = useCallback(
        (invoices: InvoiceSchema[]): string => {
            return JSON.stringify(
                invoices.map((invoice) => ({
                    invoice_date: invoice.invoice_date ?? '',
                    due_date: invoice.due_date ?? '',
                    description: invoice.description ?? '',
                    amount: Number(invoice.amount ?? 0),
                    items: (invoice.items ?? []).map((item) => ({
                        id: item.id ?? null,
                        parent_id: item.parent_id ?? null,
                        customer_confirmation_member_id:
                            item.customer_confirmation_member_id ?? null,
                        description: item.description ?? '',
                        is_header: Boolean(item.is_header),
                        quantity: Number(item.quantity ?? 0),
                        rate: Number(item.rate ?? 0),
                        sort_order: Number(item.sort_order ?? 0),
                    })),
                })),
            );
        },
        [],
    );

    const didInitializeQuotationInvoicesRef = useRef(false);

    useEffect(() => {
        if (didInitializeQuotationInvoicesRef.current) {
            return;
        }

        if (initialData || !quotation) {
            didInitializeQuotationInvoicesRef.current = true;
            return;
        }

        const paymentPlan =
            data.payment_plan ?? quotation.payment_plan ?? 'full';

        const nextInvoices = rebuildInvoicesFromSource(
            paymentPlan,
            data.deposit_type,
            data.deposit_value,
            data.invoices,
        );

        const currentHash = serializeInvoicesForComparison(data.invoices);
        const nextHash = serializeInvoicesForComparison(nextInvoices);

        if (currentHash !== nextHash) {
            setData('invoices', nextInvoices);
        }

        didInitializeQuotationInvoicesRef.current = true;
    }, [
        initialData,
        quotation,
        data.payment_plan,
        data.deposit_type,
        data.deposit_value,
        data.invoices,
        setData,
        rebuildInvoicesFromSource,
        serializeInvoicesForComparison,
    ]);

    function addInvoice() {
        const newInvoices = [...data.invoices, createEmptyInvoice()];
        setData('invoices', newInvoices);
    }

    function removeInvoice(index: number) {
        if (data.invoices.length <= 1) return;

        const next = [...data.invoices];
        next.splice(index, 1);
        setData('invoices', next);
    }

    function moveItemsBetweenInvoices(
        fromIndex: number,
        toIndex: number,
        itemKeys: string[],
    ) {
        if (fromIndex === toIndex) return;

        const invoices = [...data.invoices];

        const fromItems = invoices[fromIndex].items;
        const toItems = invoices[toIndex].items;

        const isRootHeader = (key: string): boolean => {
            const candidate = fromItems.find((item) => item._key === key);

            if (!candidate) {
                return false;
            }

            return (
                candidate.is_header === true &&
                candidate.parent_id == null &&
                candidate.parent_key == null
            );
        };

        if (!itemKeys.length || !itemKeys.every((key) => isRootHeader(key))) {
            return;
        }

        const movingItems = collectWithChildren(fromItems, itemKeys);
        const movingKeys = new Set(movingItems.map((i) => i._key));

        invoices[fromIndex] = {
            ...invoices[fromIndex],
            items: normalizeItems(
                fromItems.filter((i) => !movingKeys.has(i._key)),
            ),
            amount: 0,
        };

        invoices[toIndex] = {
            ...invoices[toIndex],
            items: normalizeItems([...toItems, ...movingItems]),
            amount: 0,
        };

        invoices[fromIndex] = recalculateInvoice(invoices[fromIndex]);
        invoices[toIndex] = recalculateInvoice(invoices[toIndex]);

        setData('invoices', invoices);
    }

    // validation
    function validateClientSide(): boolean {
        clearErrors();

        const result = orderValidationSchema.safeParse(data);

        if (!result.success) {
            result.error.issues.forEach((issue) => {
                const path = issue.path.join('.');
                setError(path as keyof OrderSchema, issue.message);
            });

            window.scrollTo({ top: 0, behavior: 'smooth' });
            return false;
        }

        return true;
    }

    // action
    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (!validateClientSide()) return;

        const url = '/order';

        if (isCreate) {
            post(url, {
                preserveState: true,
                preserveScroll: true,
                onError: (errors) => {
                    setError(errors);
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },
            });
        } else if (isEdit) {
            put(`${url}/${data.id}`, {
                preserveState: true,
                preserveScroll: true,
                onError: (errors) => {
                    setError(errors);
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },
            });
        }
    }

    // error
    function formatError(path: string, message: string) {
        const parts = path.split('.');

        if (parts[0] === 'invoices' && parts.length === 3) {
            const invoiceIndex = Number(parts[1]) + 1;
            const field = parts[2];

            const fieldLabelMap: Record<string, string> = {
                invoice_date: 'invoice date',
                due_date: 'due date',
            };

            return `Invoice #${invoiceIndex} ${fieldLabelMap[field] ?? field} ${message.replace(
                /^The\s.+?\s/,
                '',
            )}`;
        }

        if (
            parts[0] === 'invoices' &&
            parts[2] === 'items' &&
            parts.length >= 5
        ) {
            const invoiceIndex = Number(parts[1]) + 1;
            const itemIndex = Number(parts[3]) + 1;
            const field = parts[4];

            return `Invoice #${invoiceIndex}, Item #${itemIndex} ${field} ${cleanMessage(message)}`;
        }

        return message;
    }

    const renderError = (path: string) => {
        const errorMap = errors as Record<string, string | undefined>;
        const message = errorMap[path];

        if (!message) return null;

        return (
            <p className="mt-1 text-sm text-red-500">
                {formatError(path, message)}
            </p>
        );
    };

    const modelNumberError =
        (errors as Record<string, string | undefined>).order_number ??
        (errors as Record<string, string | undefined>).number_format_id;

    const hasInvoiceErrors = (invoiceIndex: number) => {
        const errorMap = errors as Record<string, string | undefined>;
        const prefix = `invoices.${invoiceIndex}.`;
        return Object.keys(errorMap).some((key) => key.startsWith(prefix));
    };

    const getInvoiceErrors = (invoiceIndex: number) => {
        const errorMap = errors as Record<string, string | undefined>;
        const prefix = `invoices.${invoiceIndex}.`;
        return Object.entries(errorMap)
            .filter(([key]) => key.startsWith(prefix))
            .map(([key, message]) => ({
                key,
                message: message as string,
            }));
    };

    const handleReset = () => {
        reset();
    };

    // expand & collapse invoice
    const [collapsedInvoices, setCollapsedInvoices] = useState<
        Record<string, boolean>
    >({});
    const [memberOptions, setMemberOptions] = useState<OptionType[]>([]);
    const [memberSharingPlanById, setMemberSharingPlanById] = useState<
        Record<number, string | null>
    >({});

    useEffect(() => {
        const confirmationId = Number(quotation?.customer_confirmation_id ?? 0);

        if (!quotation || confirmationId <= 0) {
            setMemberOptions([]);
            setMemberSharingPlanById({});
            return;
        }

        let isUnmounted = false;

        const loadMembers = async () => {
            try {
                const response = await fetch(
                    showCustomerConfirmation(confirmationId).url,
                );

                if (!response.ok) {
                    throw new Error('Failed to load customer confirmation');
                }

                const confirmation = await response.json();
                const members = (confirmation.members ?? []) as Array<{
                    member_id?: number;
                    id?: number;
                    name?: string;
                    sharing_plan?: string | null;
                }>;

                const normalizedMembers = members
                    .map((member) => ({
                        id: Number(member.member_id ?? member.id ?? 0),
                        name: member.name ?? `Member #${member.id ?? '-'}`,
                        sharing_plan: member.sharing_plan ?? null,
                    }))
                    .filter((member) => member.id > 0);

                if (isUnmounted) {
                    return;
                }

                setMemberOptions(
                    normalizedMembers.map((member) => ({
                        value: String(member.id),
                        label: member.name,
                    })),
                );
                setMemberSharingPlanById(
                    Object.fromEntries(
                        normalizedMembers.map((member) => [
                            member.id,
                            member.sharing_plan,
                        ]),
                    ),
                );
            } catch {
                if (isUnmounted) {
                    return;
                }

                setMemberOptions([]);
                setMemberSharingPlanById({});
            }
        };

        loadMembers();

        return () => {
            isUnmounted = true;
        };
    }, [quotation]);

    useEffect(() => {
        if (!data.invoices.length) return;

        setCollapsedInvoices((prev) => {
            const next: Record<string, boolean> = {};

            data.invoices.forEach((invoice) => {
                next[invoice._key] = prev[invoice._key] ?? true;
            });

            const prevKeys = Object.keys(prev);
            const nextKeys = Object.keys(next);

            if (prevKeys.length === nextKeys.length) {
                const isSame = nextKeys.every((key) => prev[key] === next[key]);

                if (isSame) {
                    return prev;
                }
            }

            return next;
        });
    }, [data.invoices]);

    function toggleInvoice(invoiceKey: string) {
        setCollapsedInvoices((prev) => ({
            ...prev,
            [invoiceKey]: !prev[invoiceKey],
        }));
    }

    function collapseAllInvoices() {
        setCollapsedInvoices(
            Object.fromEntries(data.invoices.map((inv) => [inv._key, true])),
        );
    }

    function expandAllInvoices() {
        setCollapsedInvoices(
            Object.fromEntries(data.invoices.map((inv) => [inv._key, false])),
        );
    }

    // expand & collapse quote ref
    const [quotationCollapsed, setQuotationCollapsed] = useState(true);

    function toggleQuotation() {
        setQuotationCollapsed((prev) => !prev);
    }

    const quotationSubtotalAmount = Number(
        quotation?.subtotal_amount ??
            quotation?.items?.reduce((sum, item) => {
                if (item.is_header) {
                    return sum;
                }

                return (
                    sum + Number(item.quantity ?? 0) * Number(item.rate ?? 0)
                );
            }, 0) ??
            0,
    );

    const quotationExtensions = quotation?.extensions ?? [];
    const quotationExtensionTotalAmount = Number(
        quotation?.extension_total_amount ??
            quotationExtensions.reduce(
                (sum, extension) => sum + Number(extension.amount ?? 0),
                0,
            ) ??
            0,
    );

    const quotationTotalAmount = Number(
        quotation?.total_amount ??
            quotationSubtotalAmount + quotationExtensionTotalAmount,
    );

    const hasQuotationCustomerConfirmation =
        Number(quotation?.customer_confirmation_id ?? 0) > 0;

    const taxExtensionMasters = useMemo(() => {
        const grouped = new Map<
            string,
            {
                id: number;
                name: string;
                calculation_mode: string;
                calculation_value: number;
            }
        >();

        const collectTaxesFromItems = (items: InvoiceSchema['items'] = []) => {
            items.forEach((item) => {
                (item.taxes ?? []).forEach((tax) => {
                    const calculationMode = String(tax.calculation_mode ?? '');
                    const calculationValue = Number(tax.calculation_value ?? 0);

                    if (
                        !['fixed', 'percentage'].includes(calculationMode) ||
                        calculationValue <= 0
                    ) {
                        return;
                    }

                    const key = [
                        Number(tax.quotation_extension_master_id ?? 0),
                        String(tax.name ?? 'Tax').toLowerCase(),
                        calculationMode,
                        calculationValue,
                    ].join('|');

                    if (!grouped.has(key)) {
                        grouped.set(key, {
                            id: Number(tax.quotation_extension_master_id ?? 0),
                            name: String(tax.name ?? 'Tax'),
                            calculation_mode: calculationMode,
                            calculation_value: calculationValue,
                        });
                    }
                });
            });
        };

        collectTaxesFromItems(
            data.invoices.flatMap((invoice) => invoice.items ?? []),
        );

        (quotation?.items ?? []).forEach((item) => {
            (item.taxes ?? []).forEach((tax) => {
                const calculationMode = String(tax.calculation_mode ?? '');
                const calculationValue = Number(tax.calculation_value ?? 0);

                if (
                    !['fixed', 'percentage'].includes(calculationMode) ||
                    calculationValue <= 0
                ) {
                    return;
                }

                const key = [
                    Number(tax.quotation_extension_master_id ?? 0),
                    String(tax.name ?? 'Tax').toLowerCase(),
                    calculationMode,
                    calculationValue,
                ].join('|');

                if (!grouped.has(key)) {
                    grouped.set(key, {
                        id: Number(tax.quotation_extension_master_id ?? 0),
                        name: String(tax.name ?? 'Tax'),
                        calculation_mode: calculationMode,
                        calculation_value: calculationValue,
                    });
                }
            });
        });

        return Array.from(grouped.values()).sort((left, right) =>
            left.name.localeCompare(right.name),
        );
    }, [data.invoices, quotation]);

    // preview
    // const [previewInvoice, setPreviewInvoice] = useState<InvoiceSchema | null>(
    //     null,
    // );

    console.log(errors);

    return (
        <>
            <div className="mx-auto w-full">
                <form onSubmit={submit} className="space-y-6">
                    {/* Error Summary Banner */}
                    {Object.keys(errors).length > 0 && (
                        <div className="rounded-lg border border-red-200 bg-red-50 p-4">
                            <div className="flex items-start gap-3">
                                <AlertCircle className="mt-0.5 h-5 w-5 flex-shrink-0 text-red-600" />
                                <div className="flex-1">
                                    <h3 className="font-semibold text-red-900">
                                        Please fix the following errors:
                                    </h3>
                                    <ul className="mt-2 space-y-1 text-base text-red-800">
                                        {Object.entries(errors).map(
                                            ([key, message]) => (
                                                <li key={key}>
                                                    •{' '}
                                                    {formatError(key, message)}
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                </div>
                            </div>
                        </div>
                    )}

                    {isView && data.order_number && (
                        <div className="mb-2 rounded-lg border border-primary/20 bg-primary/5 p-4">
                            <p className="text-base text-muted-foreground">
                                Order No.
                            </p>
                            <p className="text-2xl font-bold text-primary">
                                {data.order_number}
                            </p>
                        </div>
                    )}

                    {/* Invoices Breakdown */}
                    <Card>
                        <CardContent className="space-y-4 px-6">
                            <div className="space-y-4">
                                <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-3">
                                    {!isView && (
                                        <ModelNumberInput
                                            modelKey="order"
                                            label="Order Number"
                                            value={data.order_number ?? ''}
                                            formatId={
                                                data.number_format_id ?? null
                                            }
                                            onValueChange={(nextValue) =>
                                                setData(
                                                    'order_number',
                                                    nextValue,
                                                )
                                            }
                                            onFormatIdChange={(nextFormatId) =>
                                                setData(
                                                    'number_format_id',
                                                    nextFormatId,
                                                )
                                            }
                                            disabled={processing}
                                            error={modelNumberError}
                                            hint="Select a format from the number input to auto-generate order number."
                                        />
                                    )}

                                    {/* Payment Plan */}
                                    <PaymentPlanSection
                                        value={data.payment_plan ?? ''}
                                        plans={paymentPlans}
                                        disabled={isView}
                                        renderError={renderError}
                                        onChange={(v) => {
                                            const nextDepositType =
                                                v === 'installment'
                                                    ? (data.deposit_type ??
                                                      'fixed')
                                                    : data.deposit_type;
                                            const nextDepositValue =
                                                v === 'installment' &&
                                                (data.deposit_value ===
                                                    undefined ||
                                                    data.deposit_value ===
                                                        null ||
                                                    data.deposit_value === '')
                                                    ? 500
                                                    : data.deposit_value;

                                            setData({
                                                ...data,
                                                payment_plan: v,
                                                deposit_type: nextDepositType,
                                                deposit_value: nextDepositValue,
                                                invoices:
                                                    rebuildInvoicesFromSource(
                                                        v,
                                                        nextDepositType,
                                                        nextDepositValue,
                                                        data.invoices,
                                                    ),
                                            });
                                        }}
                                    />

                                    {data.payment_plan === 'installment' &&
                                        hasQuotationCustomerConfirmation && (
                                            <>
                                                <FormField label="Deposit Type">
                                                    <Select
                                                        value={String(
                                                            data.deposit_type ??
                                                                '',
                                                        )}
                                                        onValueChange={(v) => {
                                                            setData({
                                                                ...data,
                                                                deposit_type: v,
                                                                invoices:
                                                                    rebuildInvoicesFromSource(
                                                                        data.payment_plan ??
                                                                            'installment',
                                                                        v,
                                                                        data.deposit_value,
                                                                        data.invoices,
                                                                    ),
                                                            });
                                                        }}
                                                        disabled={isView}
                                                    >
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="Select type" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {depositTypes.map(
                                                                (dt) => (
                                                                    <SelectItem
                                                                        key={
                                                                            dt.value
                                                                        }
                                                                        value={
                                                                            dt.value
                                                                        }
                                                                    >
                                                                        {
                                                                            dt.label
                                                                        }
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                    {renderError(
                                                        'deposit_type',
                                                    )}
                                                </FormField>

                                                <FormField label="Deposit Value">
                                                    <ProperInput
                                                        value={
                                                            data.deposit_value ??
                                                            ''
                                                        }
                                                        type="number"
                                                        inputProps={{
                                                            step: 'any',
                                                            min: '0',
                                                            ...(data.deposit_type ===
                                                            'percentage'
                                                                ? {
                                                                      max: '100',
                                                                  }
                                                                : {}),
                                                        }}
                                                        placeholder={
                                                            data.deposit_type ===
                                                            'percentage'
                                                                ? 'Enter %'
                                                                : 'Enter amount'
                                                        }
                                                        disabled={isView}
                                                        onCommit={(v) => {
                                                            setData({
                                                                ...data,
                                                                deposit_value:
                                                                    v,
                                                                invoices:
                                                                    rebuildInvoicesFromSource(
                                                                        data.payment_plan ??
                                                                            'installment',
                                                                        data.deposit_type,
                                                                        v,
                                                                        data.invoices,
                                                                    ),
                                                            });
                                                        }}
                                                    />
                                                    {renderError(
                                                        'deposit_value',
                                                    )}
                                                </FormField>
                                            </>
                                        )}
                                </div>
                            </div>

                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <h2 className="text-lg font-semibold">
                                        Invoices Breakdown
                                    </h2>

                                    <div className="rounded-md bg-primary/10 px-3 py-1 text-base font-semibold text-primary">
                                        Total&nbsp;
                                        <span className="tabular-nums">
                                            $
                                            {calculateInvoicesTotal(
                                                data.invoices,
                                            )}
                                        </span>
                                    </div>
                                </div>

                                <div className="flex items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={expandAllInvoices}
                                    >
                                        Expand All
                                    </Button>

                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={collapseAllInvoices}
                                    >
                                        Collapse All
                                    </Button>
                                    {!isView && (
                                        <Button
                                            type="button"
                                            onClick={addInvoice}
                                            variant="outline"
                                        >
                                            + Add Invoice
                                        </Button>
                                    )}
                                </div>
                            </div>

                            {data.invoices.map((invoice, idx) => {
                                const invoiceHasErrors = hasInvoiceErrors(idx);
                                const invoiceErrors = getInvoiceErrors(idx);
                                const invoiceSubtotal = calculateTotal(
                                    invoice.items,
                                );
                                const itemTaxSummaries = buildItemTaxSummaries(
                                    invoice.items,
                                );
                                const itemTaxTotal = itemTaxSummaries.reduce(
                                    (sum, tax) => sum + Number(tax.amount ?? 0),
                                    0,
                                );
                                const extensionsWithAmount =
                                    normalizeInvoiceExtensions(
                                        (invoice.extensions ??
                                            []) as InvoiceExtensionInput[],
                                    ).map((extension) => ({
                                        ...extension,
                                        amount: computeInvoiceExtensionAmount(
                                            extension,
                                            invoiceSubtotal,
                                        ),
                                    }));
                                const nonDiscountExtensions =
                                    extensionsWithAmount.filter(
                                        (extension) =>
                                            String(
                                                extension.type ?? 'discount',
                                            ) !== 'discount',
                                    );
                                const nonDiscountTaxExtensions =
                                    nonDiscountExtensions.filter(
                                        (extension) =>
                                            String(extension.type ?? '') ===
                                            'tax',
                                    );
                                const nonDiscountOtherExtensions =
                                    nonDiscountExtensions.filter(
                                        (extension) =>
                                            String(extension.type ?? '') !==
                                            'tax',
                                    );
                                const nonDiscountExtensionTotal =
                                    nonDiscountExtensions.reduce(
                                        (sum, extension) =>
                                            sum + Number(extension.amount ?? 0),
                                        0,
                                    );
                                const discountExtension =
                                    extensionsWithAmount.find(
                                        (extension) =>
                                            String(
                                                extension.type ?? 'discount',
                                            ) === 'discount',
                                    ) ?? null;
                                const discountAmount = Number(
                                    discountExtension?.amount ?? 0,
                                );
                                const invoiceExtensionTotal =
                                    itemTaxTotal +
                                    nonDiscountExtensionTotal +
                                    discountAmount;
                                const invoiceGrandTotal =
                                    invoiceSubtotal + invoiceExtensionTotal;

                                return (
                                    <Card
                                        key={invoice._key}
                                        className={`py-4 shadow-sm transition-shadow hover:shadow ${
                                            invoiceHasErrors
                                                ? 'border-red-300 bg-red-50/30'
                                                : 'border-muted/80'
                                        }`}
                                    >
                                        <CardContent className="space-y-2 px-4">
                                            {invoiceHasErrors && (
                                                <div className="rounded-md border border-red-200 bg-red-50 p-3">
                                                    <div className="flex items-start gap-2">
                                                        <AlertCircle className="mt-0.5 h-4 w-4 flex-shrink-0 text-red-600" />
                                                        <div className="flex-1">
                                                            <p className="text-base font-semibold text-red-900">
                                                                Invoice #
                                                                {idx + 1} has
                                                                validation
                                                                errors:
                                                            </p>
                                                            <ul className="mt-1 space-y-0.5 text-sm text-red-800">
                                                                {invoiceErrors.map(
                                                                    (err) => (
                                                                        <li
                                                                            key={
                                                                                err.key
                                                                            }
                                                                        >
                                                                            •{' '}
                                                                            {formatError(
                                                                                err.key,
                                                                                err.message,
                                                                            )}
                                                                        </li>
                                                                    ),
                                                                )}
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                            )}

                                            <div className="flex items-center justify-between gap-4">
                                                <div className="flex gap-4">
                                                    <div className="space-y-0.5">
                                                        <div className="flex items-center gap-2">
                                                            <span className="text-base font-medium text-muted-foreground">
                                                                {invoice.invoice_number ||
                                                                    `Invoice ${idx + 1}`}
                                                            </span>
                                                        </div>

                                                        <p className="text-base font-semibold">
                                                            {invoice.description ||
                                                                '—'}
                                                        </p>
                                                    </div>
                                                    <div className="flex items-center gap-3">
                                                        <Badge className="rounded-md bg-emerald-50 px-3 py-1 text-base font-semibold text-emerald-700">
                                                            <span className="tabular-nums">
                                                                $
                                                                {calculateTotal(
                                                                    invoice.items,
                                                                )}
                                                            </span>
                                                        </Badge>

                                                        <Badge
                                                            className={`${
                                                                statusColors[
                                                                    (invoice.status ??
                                                                        'draft') as keyof typeof statusColors
                                                                ]
                                                            } px-3 py-1 text-base`}
                                                        >
                                                            {statuses.find(
                                                                (s) =>
                                                                    s.value ===
                                                                    (invoice.status ??
                                                                        'draft'),
                                                            )?.label ??
                                                                invoice.status ??
                                                                'draft'}
                                                        </Badge>
                                                    </div>
                                                </div>

                                                <div className="flex gap-2">
                                                    {/* <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            setPreviewInvoice(
                                                                invoice,
                                                            )
                                                        }
                                                    >
                                                        Preview
                                                    </Button> */}

                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            toggleInvoice(
                                                                invoice._key,
                                                            )
                                                        }
                                                    >
                                                        {collapsedInvoices[
                                                            invoice._key
                                                        ]
                                                            ? 'Expand'
                                                            : 'Collapse'}
                                                    </Button>

                                                    {!isView && (
                                                        <div className="flex justify-end">
                                                            <Button
                                                                type="button"
                                                                variant="destructive"
                                                                size="sm"
                                                                onClick={() =>
                                                                    removeInvoice(
                                                                        idx,
                                                                    )
                                                                }
                                                                disabled={
                                                                    data
                                                                        .invoices
                                                                        .length <=
                                                                    1
                                                                }
                                                            >
                                                                <Trash />
                                                            </Button>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>

                                            {!collapsedInvoices[
                                                invoice._key
                                            ] && (
                                                <>
                                                    <InvoiceHeader
                                                        invoice={invoice}
                                                        disabled={
                                                            processing || isView
                                                        }
                                                        isView={isView}
                                                        renderError={(path) =>
                                                            renderError(
                                                                `invoices.${idx}.${path}`,
                                                            )
                                                        }
                                                        onChange={(patch) => {
                                                            const next = [
                                                                ...data.invoices,
                                                            ];
                                                            next[idx] = {
                                                                ...next[idx],
                                                                ...patch,
                                                            };
                                                            setData(
                                                                'invoices',
                                                                next,
                                                            );
                                                        }}
                                                    />

                                                    <QuotationItemTableForm
                                                        items={invoice.items}
                                                        disabled={isView}
                                                        renderError={(path) =>
                                                            renderError(
                                                                `invoices.${idx}.${path}`,
                                                            )
                                                        }
                                                        onChange={(items) => {
                                                            const next = [
                                                                ...data.invoices,
                                                            ];
                                                            next[idx] =
                                                                recalculateInvoice(
                                                                    {
                                                                        ...invoice,
                                                                        items: normalizeItems(
                                                                            items,
                                                                        ),
                                                                    },
                                                                );
                                                            setData(
                                                                'invoices',
                                                                next,
                                                            );
                                                        }}
                                                        invoices={data.invoices}
                                                        currentInvoiceIndex={
                                                            idx
                                                        }
                                                        onMoveItem={
                                                            moveItemsBetweenInvoices
                                                        }
                                                        showOptionalColumn={
                                                            false
                                                        }
                                                        showMemberColumn={false}
                                                        showTaxColumn
                                                        memberOptions={
                                                            memberOptions
                                                        }
                                                        taxExtensionMasters={
                                                            taxExtensionMasters
                                                        }
                                                        memberSharingPlanById={
                                                            memberSharingPlanById
                                                        }
                                                    />

                                                    <div className="mt-3 ml-auto w-full rounded-md border p-4 md:w-1/3">
                                                        <table className="w-full table-fixed text-base">
                                                            <tbody className="[&>tr>td]:py-1.5">
                                                                <tr>
                                                                    <td className="w-2/3 text-right font-semibold">
                                                                        Sub
                                                                        Total
                                                                    </td>
                                                                    <td className="w-1/3 text-right font-medium">
                                                                        {formatCurrency(
                                                                            invoiceSubtotal,
                                                                        )}
                                                                    </td>
                                                                </tr>

                                                                {nonDiscountOtherExtensions.map(
                                                                    (
                                                                        extension,
                                                                        extensionIndex,
                                                                    ) => (
                                                                        <tr
                                                                            key={
                                                                                extension._key ??
                                                                                `invoice-${idx}-other-${extensionIndex}`
                                                                            }
                                                                        >
                                                                            <td className="text-right">
                                                                                {formatExtensionLabel(
                                                                                    String(
                                                                                        extension.name ??
                                                                                            'Extension',
                                                                                    ),
                                                                                    String(
                                                                                        extension.calculation_mode ??
                                                                                            'fixed',
                                                                                    ),
                                                                                    Number(
                                                                                        extension.calculation_value ??
                                                                                            0,
                                                                                    ),
                                                                                )}
                                                                            </td>
                                                                            <td className="text-right">
                                                                                {formatCurrency(
                                                                                    Number(
                                                                                        extension.amount ??
                                                                                            0,
                                                                                    ),
                                                                                )}
                                                                            </td>
                                                                        </tr>
                                                                    ),
                                                                )}

                                                                {discountExtension && (
                                                                    <tr>
                                                                        <td className="text-right">
                                                                            {formatExtensionLabel(
                                                                                String(
                                                                                    discountExtension.name ??
                                                                                        'Discount',
                                                                                ),
                                                                                String(
                                                                                    discountExtension.calculation_mode ??
                                                                                        'fixed',
                                                                                ),
                                                                                Number(
                                                                                    discountExtension.calculation_value ??
                                                                                        0,
                                                                                ),
                                                                            )}
                                                                        </td>
                                                                        <td className="text-right">
                                                                            {formatCurrency(
                                                                                discountAmount,
                                                                            )}
                                                                        </td>
                                                                    </tr>
                                                                )}

                                                                {itemTaxSummaries.map(
                                                                    (
                                                                        tax,
                                                                        taxIndex,
                                                                    ) => (
                                                                        <tr
                                                                            key={`invoice-${idx}-item-tax-${taxIndex}`}
                                                                        >
                                                                            <td className="text-right">
                                                                                {formatExtensionLabel(
                                                                                    String(
                                                                                        tax.name ??
                                                                                            'Tax',
                                                                                    ),
                                                                                    tax.calculation_mode,
                                                                                    Number(
                                                                                        tax.calculation_value ??
                                                                                            0,
                                                                                    ),
                                                                                )}
                                                                            </td>
                                                                            <td className="text-right">
                                                                                {formatCurrency(
                                                                                    Number(
                                                                                        tax.amount ??
                                                                                            0,
                                                                                    ),
                                                                                )}
                                                                            </td>
                                                                        </tr>
                                                                    ),
                                                                )}

                                                                {nonDiscountTaxExtensions.map(
                                                                    (
                                                                        extension,
                                                                        extensionIndex,
                                                                    ) => (
                                                                        <tr
                                                                            key={
                                                                                extension._key ??
                                                                                `invoice-${idx}-tax-ext-${extensionIndex}`
                                                                            }
                                                                        >
                                                                            <td className="text-right">
                                                                                {formatExtensionLabel(
                                                                                    String(
                                                                                        extension.name ??
                                                                                            'Tax',
                                                                                    ),
                                                                                    String(
                                                                                        extension.calculation_mode ??
                                                                                            'fixed',
                                                                                    ),
                                                                                    Number(
                                                                                        extension.calculation_value ??
                                                                                            0,
                                                                                    ),
                                                                                )}
                                                                            </td>
                                                                            <td className="text-right">
                                                                                {formatCurrency(
                                                                                    Number(
                                                                                        extension.amount ??
                                                                                            0,
                                                                                    ),
                                                                                )}
                                                                            </td>
                                                                        </tr>
                                                                    ),
                                                                )}

                                                                <tr>
                                                                    <td className="text-right font-semibold">
                                                                        Extension
                                                                        Total
                                                                    </td>
                                                                    <td className="text-right font-medium">
                                                                        {formatCurrency(
                                                                            invoiceExtensionTotal,
                                                                        )}
                                                                    </td>
                                                                </tr>

                                                                <tr>
                                                                    <td className="border-t pt-2 text-right text-base font-bold">
                                                                        Grand
                                                                        Total
                                                                    </td>
                                                                    <td className="border-t pt-2 text-right text-lg font-bold text-primary">
                                                                        {formatCurrency(
                                                                            Number(
                                                                                invoice.amount ??
                                                                                    invoiceGrandTotal,
                                                                            ),
                                                                        )}
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </>
                                            )}
                                        </CardContent>
                                    </Card>
                                );
                            })}

                            <div className="flex justify-end">
                                {!isView && data.invoices.length > 0 && (
                                    <Button
                                        type="button"
                                        onClick={addInvoice}
                                        variant="outline"
                                    >
                                        + Add Invoice
                                    </Button>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {quotation && (
                        <>
                            {/* Quotation Number Box */}
                            {data.quotation_number && (
                                <div className="mb-2 rounded-lg border border-primary/20 bg-primary/5 p-4">
                                    <p className="text-base text-muted-foreground">
                                        Quotation No.
                                    </p>
                                    <p className="text-2xl font-bold text-primary">
                                        {data.quotation_number}
                                    </p>
                                </div>
                            )}
                            <Card>
                                <CardContent className="space-y-4 px-6">
                                    <div className="flex items-center justify-between">
                                        <h2 className="text-lg font-semibold">
                                            Quotation Reference
                                        </h2>

                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={toggleQuotation}
                                        >
                                            {quotationCollapsed
                                                ? 'Expand'
                                                : 'Collapse'}
                                        </Button>
                                    </div>

                                    {!quotationCollapsed && (
                                        <>
                                            {quotation.package_name && (
                                                <div className="grid w-full items-center gap-3 rounded-md border p-3">
                                                    <Label>
                                                        Package & Sharing Plan
                                                        Costs
                                                    </Label>
                                                    <div className="space-y-1 text-sm">
                                                        <div className="flex items-center justify-between gap-3 border-b pb-2 font-medium">
                                                            <span className="text-muted-foreground">
                                                                Package
                                                            </span>
                                                            <span>
                                                                {
                                                                    quotation.package_name
                                                                }
                                                            </span>
                                                        </div>

                                                        <div className="flex items-center justify-between gap-3">
                                                            <span className="text-muted-foreground">
                                                                Single
                                                            </span>
                                                            <span>
                                                                $
                                                                {Number(
                                                                    quotation.package_price_single ??
                                                                        0,
                                                                ).toFixed(2)}
                                                            </span>
                                                        </div>

                                                        <div className="flex items-center justify-between gap-3">
                                                            <span className="text-muted-foreground">
                                                                Double
                                                            </span>
                                                            <span>
                                                                $
                                                                {Number(
                                                                    quotation.package_price_double ??
                                                                        0,
                                                                ).toFixed(2)}
                                                            </span>
                                                        </div>

                                                        <div className="flex items-center justify-between gap-3">
                                                            <span className="text-muted-foreground">
                                                                Triple
                                                            </span>
                                                            <span>
                                                                $
                                                                {Number(
                                                                    quotation.package_price_triple ??
                                                                        0,
                                                                ).toFixed(2)}
                                                            </span>
                                                        </div>

                                                        <div className="flex items-center justify-between gap-3">
                                                            <span className="text-muted-foreground">
                                                                Quad
                                                            </span>
                                                            <span>
                                                                $
                                                                {Number(
                                                                    quotation.package_price_quad ??
                                                                        0,
                                                                ).toFixed(2)}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            )}

                                            <QuotationItemTableForm
                                                items={initialItems ?? []}
                                                onChange={(next) =>
                                                    setData('items', next)
                                                }
                                                disabled
                                                showMemberColumn={false}
                                                showTaxColumn
                                                memberOptions={memberOptions}
                                                memberSharingPlanById={
                                                    memberSharingPlanById
                                                }
                                            />

                                            <div className="space-y-3 rounded-md border p-4">
                                                <div className="flex items-center justify-between text-sm">
                                                    <span className="text-muted-foreground">
                                                        Sub Total
                                                    </span>
                                                    <span className="font-semibold">
                                                        {formatCurrency(
                                                            quotationSubtotalAmount,
                                                        )}
                                                    </span>
                                                </div>

                                                {quotationExtensions.map(
                                                    (extension, index) => (
                                                        <div
                                                            key={
                                                                extension._key ??
                                                                `quotation-extension-${index}`
                                                            }
                                                            className="flex items-center justify-between gap-3 text-sm"
                                                        >
                                                            <span className="text-muted-foreground">
                                                                {extension.name ||
                                                                    'Extension'}
                                                            </span>
                                                            <span className="font-semibold">
                                                                {formatCurrency(
                                                                    Number(
                                                                        extension.amount ??
                                                                            0,
                                                                    ),
                                                                )}
                                                            </span>
                                                        </div>
                                                    ),
                                                )}

                                                <div className="flex items-center justify-between border-t pt-3 text-sm">
                                                    <span className="text-muted-foreground">
                                                        Extension Total
                                                    </span>
                                                    <span className="font-semibold">
                                                        {formatCurrency(
                                                            quotationExtensionTotalAmount,
                                                        )}
                                                    </span>
                                                </div>

                                                <div className="flex items-center justify-between text-base">
                                                    <span className="font-semibold">
                                                        Total Amount
                                                    </span>
                                                    <span className="text-lg font-bold text-primary">
                                                        {formatCurrency(
                                                            quotationTotalAmount,
                                                        )}
                                                    </span>
                                                </div>
                                            </div>
                                        </>
                                    )}
                                </CardContent>
                            </Card>
                        </>
                    )}

                    <div className="flex justify-end gap-4">
                        {onCancel && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={onCancel}
                            >
                                Back
                            </Button>
                        )}
                        {!isView && (
                            <>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={handleReset}
                                    disabled={processing}
                                >
                                    Reset
                                </Button>
                                <Button
                                    type="submit"
                                    className="min-w-[140px]"
                                    disabled={processing}
                                >
                                    {isEdit ? 'Update' : 'Create'}
                                </Button>
                            </>
                        )}
                    </div>
                </form>
            </div>
        </>
    );
}
