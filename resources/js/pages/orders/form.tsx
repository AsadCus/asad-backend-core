import { FormField } from '@/components/form-field';
import ModelNumberInput from '@/components/model-number-input';
import PackageSharingPlanInfo from '@/components/package-sharing-plan-info';
import PaymentMethodMasterCombobox from '@/components/payment-method-master-combobox';
import { ProperInput } from '@/components/proper-input';
import TotalsSummaryCard, {
    type TotalsSummaryExtensionMaster,
} from '@/components/totals-summary-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { show as showCustomerConfirmation } from '@/routes/customer-confirmations';
import { OptionType } from '@/types';
import { useForm } from '@inertiajs/react';
import { AlertCircle, Trash } from 'lucide-react';
import { nanoid } from 'nanoid';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { InvoiceHeader } from '../invoices/components/invoice-header';
import {
    calculateTotal,
    collectWithChildren,
    normalizeItems,
} from '../invoices/lib/utils';
import { InvoiceSchema, statusColors, statuses } from '../invoices/schema';
import QuotationItemTableForm from '../quotations/items/form';
import { QuotationSchema } from '../quotations/schema';
import { PaymentPlanSection } from './components/payment-plan-section';
import {
    applyInvoiceNumberingSequence,
    applyInvoicePaymentMethodExtensions,
    autoFillInvoiceDates,
    buildInvoices,
    buildInvoicesFromItems,
    buildSequentialInvoiceNumbersFromSeed,
    computeInvoiceExtensionAmount,
    type InvoiceExtensionInput,
    type InvoicePaymentMethodExtensionMaster,
    normalizeInvoiceExtensions,
    quotationItemsToInvoiceItems,
    recalculateInvoice,
} from './lib/invoice-builders';
import { OrderSchema } from './schema';
import { orderValidationSchema } from './validation';

interface OrderFormProps {
    mode: 'create' | 'edit' | 'view';
    initialData?: OrderSchema;
    quotation?: QuotationSchema;
    paymentPlans?: OptionType[];
    paymentMethods?: OptionType[];
    extensionMasters?: TotalsSummaryExtensionMaster[];
    defaultPaymentMethod?: string;
    initialInvoiceNumberFormatId?: number | null;
    initialInvoiceNumbers?: string[];
    onCancel?: () => void;
}

type OrderExtensionMasterInput = TotalsSummaryExtensionMaster &
    InvoicePaymentMethodExtensionMaster;

function sanitizeInstallmentInvoiceCount(
    value?: number | string | null,
): number {
    const parsed = Number(value ?? 3);

    if (!Number.isFinite(parsed)) {
        return 3;
    }

    return Math.max(3, Math.floor(parsed));
}

function aggregateSourceExtensionsForSplit(
    currentInvoices: InvoiceSchema[],
    fallbackExtensions: InvoiceExtensionInput[],
): InvoiceExtensionInput[] {
    const normalizedFallbackExtensions = normalizeInvoiceExtensions(
        fallbackExtensions,
    ).filter((extension) => String(extension.type ?? 'discount') !== 'tax');

    const normalizedCurrentExtensions = currentInvoices
        .flatMap((invoice) =>
            normalizeInvoiceExtensions(
                (invoice.extensions ?? []) as InvoiceExtensionInput[],
            ),
        )
        .filter((extension) => String(extension.type ?? 'discount') !== 'tax');

    if (normalizedCurrentExtensions.length === 0) {
        return normalizedFallbackExtensions;
    }

    if (currentInvoices.length <= 1) {
        return normalizedCurrentExtensions;
    }

    const grouped = new Map<string, InvoiceExtensionInput>();

    normalizedCurrentExtensions.forEach((extension, index) => {
        const masterId = Number(extension.quotation_extension_master_id ?? 0);
        const groupKey =
            masterId > 0
                ? `master:${masterId}`
                : [
                      String(extension.name ?? '')
                          .trim()
                          .toLowerCase(),
                      String(extension.type ?? 'discount')
                          .trim()
                          .toLowerCase(),
                  ].join('|');

        const calculationMode =
            String(extension.calculation_mode ?? 'fixed') === 'percentage'
                ? 'percentage'
                : 'fixed';

        if (!grouped.has(groupKey)) {
            grouped.set(groupKey, {
                ...extension,
                _key: extension._key ?? `split-extension-${index + 1}`,
                calculation_mode: calculationMode,
                calculation_value: Number(extension.calculation_value ?? 0),
                amount: Number(extension.amount ?? 0),
            });

            return;
        }

        const current = grouped.get(groupKey);

        if (!current) {
            return;
        }

        if (calculationMode === 'percentage') {
            grouped.set(groupKey, {
                ...current,
                calculation_mode: 'percentage',
                calculation_value: Number(
                    current.calculation_value ??
                        extension.calculation_value ??
                        0,
                ),
            });

            return;
        }

        grouped.set(groupKey, {
            ...current,
            calculation_mode: 'fixed',
            amount: Number(current.amount ?? 0) + Number(extension.amount ?? 0),
            calculation_value:
                Number(current.calculation_value ?? current.amount ?? 0) +
                Number(extension.calculation_value ?? extension.amount ?? 0),
        });
    });

    return Array.from(grouped.values()).map((extension, index) => ({
        ...extension,
        sort_order: index + 1,
    }));
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

function isPaidInvoice(invoice: InvoiceSchema): boolean {
    return String(invoice.status ?? '').toLowerCase() === 'paid';
}

function isInvoiceLockedForRemoval(invoice: InvoiceSchema): boolean {
    return (
        isPaidInvoice(invoice) ||
        Boolean(invoice.has_receipt) ||
        Number(invoice.receipt_id ?? 0) > 0
    );
}

function resolvePrimaryValidationMessage(
    errors: Record<string, string | undefined>,
): string | null {
    if (errors.payment_plan) {
        return errors.payment_plan;
    }

    if (errors.invoices) {
        return errors.invoices;
    }

    return (
        Object.values(errors).find((message) => typeof message === 'string') ??
        null
    );
}

type TaxLineItem = {
    is_header?: boolean | null;
    quantity?: number | string | null;
    rate?: number | string | null;
    taxes?: Array<{
        quotation_extension_master_id?: number | null;
        name?: string | null;
        calculation_mode?: string | null;
        calculation_value?: number | string | null;
    }>;
};

function buildItemTaxSummaries(items: TaxLineItem[] = []): Array<{
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

function calculateInvoiceGrandTotal(invoice: InvoiceSchema): number {
    const invoiceSubtotal = calculateTotal(invoice.items);
    const itemTaxTotal = buildItemTaxSummaries(invoice.items).reduce(
        (sum, tax) => sum + Number(tax.amount ?? 0),
        0,
    );
    const extensionAmountFromExtensions = normalizeInvoiceExtensions(
        (invoice.extensions ?? []) as InvoiceExtensionInput[],
    )
        .map((extension) =>
            computeInvoiceExtensionAmount(extension, invoiceSubtotal),
        )
        .reduce((sum, amount) => sum + Number(amount ?? 0), 0);

    return invoiceSubtotal + itemTaxTotal + extensionAmountFromExtensions;
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

function createEmptyInvoice(defaultPaymentMethod = ''): InvoiceSchema {
    return {
        _key: nanoid(),
        invoice_number: '',
        number_format_id: null,
        payment_method: defaultPaymentMethod,
        description: '',
        invoice_date: '',
        due_date: '',
        extensions: [],
        items: [],
        amount: 0,
    };
}

function applySeededInvoiceNumbering(
    invoices: InvoiceSchema[],
    seededNumbers: string[] = [],
    preferredFormatId: number | null = null,
    fallbackSourceInvoices: InvoiceSchema[] = [],
): InvoiceSchema[] {
    const normalizedSeededNumbers = seededNumbers.map((number) =>
        String(number ?? '').trim(),
    );
    const hasSeededNumber = normalizedSeededNumbers.some(
        (number) => number !== '' && number !== '-',
    );

    console.log('[applySeededInvoiceNumbering] START', {
        invoiceCount: invoices.length,
        seededNumbers: normalizedSeededNumbers,
        hasSeededNumber,
    });

    if (hasSeededNumber) {
        // If we have seeded numbers that match invoice count, use them directly
        if (normalizedSeededNumbers.length === invoices.length) {
            const result = invoices.map((invoice, index) => ({
                ...invoice,
                invoice_number: normalizedSeededNumbers[index] ?? '',
                number_format_id:
                    invoice.number_format_id ?? preferredFormatId ?? null,
            }));

            console.log(
                '[applySeededInvoiceNumbering] Using seeded numbers directly',
                result.map((inv) => inv.invoice_number),
            );

            return result;
        }

        // If we have a single seed or partial seeds, build complete sequence
        const firstSeedNumber = normalizedSeededNumbers.find(
            (number) => number !== '' && number !== '-',
        );

        if (firstSeedNumber) {
            const sequentialSeedNumbers = buildSequentialInvoiceNumbersFromSeed(
                firstSeedNumber,
                invoices.length,
            );

            console.log(
                '[applySeededInvoiceNumbering] Built sequential from seed',
                {
                    firstSeedNumber,
                    sequentialSeedNumbers,
                },
            );

            if (sequentialSeedNumbers.length === invoices.length) {
                const result = invoices.map((invoice, index) => ({
                    ...invoice,
                    invoice_number: sequentialSeedNumbers[index] ?? '',
                    number_format_id:
                        invoice.number_format_id ?? preferredFormatId ?? null,
                }));

                console.log(
                    '[applySeededInvoiceNumbering] Returning built sequence',
                    result.map((inv) => inv.invoice_number),
                );

                return result;
            }
        }
    }

    const baseInvoices = hasSeededNumber
        ? invoices.map((invoice) => ({
              ...invoice,
              invoice_number: '',
              number_format_id:
                  invoice.number_format_id ?? preferredFormatId ?? null,
          }))
        : invoices;

    console.log(
        '[applySeededInvoiceNumbering] Falling back to applyInvoiceNumberingSequence',
    );

    return applyInvoiceNumberingSequence(baseInvoices, {
        sourceInvoices: hasSeededNumber ? [] : fallbackSourceInvoices,
        seededNumbers: normalizedSeededNumbers,
        preferredFormatId,
    });
}

const depositTypes = [
    { label: 'Percentage (%)', value: 'percentage' },
    { label: 'Fixed Amount ($)', value: 'fixed' },
];

const EMPTY_OPTION_TYPES: OptionType[] = [];
const EMPTY_EXTENSION_MASTERS: TotalsSummaryExtensionMaster[] = [];

export default function OrderForm({
    mode,
    initialData,
    quotation,
    paymentPlans = EMPTY_OPTION_TYPES,
    paymentMethods = EMPTY_OPTION_TYPES,
    extensionMasters = EMPTY_EXTENSION_MASTERS,
    defaultPaymentMethod = '',
    initialInvoiceNumberFormatId = null,
    initialInvoiceNumbers = [],
    onCancel,
}: OrderFormProps) {
    const isView = mode === 'view';
    const isEdit = mode === 'edit';
    const isCreate = mode === 'create';

    const initialItems = quotation?.items.map((item) => ({
        ...item,
        _key: item.id ? `id-${item.id}` : nanoid(),
    }));

    const normalizedOrderExtensionMasters = useMemo(
        () =>
            extensionMasters.map((master) => ({
                ...master,
                payment_methods: Array.isArray(
                    (master as OrderExtensionMasterInput).payment_methods,
                )
                    ? ((master as OrderExtensionMasterInput).payment_methods ??
                      [])
                    : [],
            })) as OrderExtensionMasterInput[],
        [extensionMasters],
    );

    const normalizedInitialInvoiceNumbers = useMemo(() => {
        const normalized = initialInvoiceNumbers.map((number) =>
            String(number ?? '').trim(),
        );

        console.log('[OrderForm.normalizedInitialInvoiceNumbers] Normalized:', {
            input: initialInvoiceNumbers,
            output: normalized,
            length: normalized.length,
            hasAny: normalized.some((n) => n !== '' && n !== '-'),
        });

        return normalized;
    }, [initialInvoiceNumbers]);

    const createInitialInvoices = useMemo((): InvoiceSchema[] => {
        if (!isCreate || !quotation) {
            return [];
        }

        const paymentPlan = quotation.payment_plan ?? 'direct';
        const installmentInvoiceCount =
            paymentPlan === 'installment'
                ? sanitizeInstallmentInvoiceCount(
                      normalizedInitialInvoiceNumbers.length > 0
                          ? normalizedInitialInvoiceNumbers.length
                          : 3,
                  )
                : sanitizeInstallmentInvoiceCount(3);

        const baseInvoices = normalizeInvoices(
            autoFillInvoiceDates(
                buildInvoicesFromItems(
                    paymentPlan,
                    quotationItemsToInvoiceItems(quotation),
                    Number(quotation.total_amount ?? 0),
                    'fixed',
                    500,
                    quotation.extensions ?? [],
                    installmentInvoiceCount,
                ),
                {
                    defaultDate: quotation.quotation_date ?? '',
                    paymentPlan,
                    hasCustomerConfirmationMemberItem: (
                        quotation.items ?? []
                    ).some(
                        (item) =>
                            Number(item.customer_confirmation_member_id ?? 0) >
                            0,
                    ),
                    packageDepartureDate:
                        quotation.package_departure_date ?? null,
                },
            ),
        );

        // Convert quotation extensions to invoice extensions
        const sourceExtensions = aggregateSourceExtensionsForSplit(
            [],
            (quotation.extensions ?? []) as InvoiceExtensionInput[],
        );

        const withExtensions = normalizeInvoices(
            baseInvoices.map((invoice, index) => {
                const inheritedExtensions = normalizeInvoiceExtensions(
                    sourceExtensions
                        .filter((extension) => {
                            const calculationMode =
                                String(
                                    extension.calculation_mode ?? 'fixed',
                                ) === 'percentage'
                                    ? 'percentage'
                                    : 'fixed';

                            return (
                                calculationMode === 'percentage' || index === 0
                            );
                        })
                        .map((extension, extensionIndex) => {
                            const calculationMode =
                                String(
                                    extension.calculation_mode ?? 'fixed',
                                ) === 'percentage'
                                    ? 'percentage'
                                    : 'fixed';

                            const sourceAmount = Number(
                                extension.amount ??
                                    extension.calculation_value ??
                                    0,
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
                                calculation_value: calculationValue,
                                amount:
                                    calculationMode === 'fixed'
                                        ? sourceAmount
                                        : 0,
                                sort_order:
                                    extension.sort_order ?? extensionIndex + 1,
                            };
                        }),
                );

                const mergedInvoice = {
                    ...invoice,
                    extensions: inheritedExtensions,
                } as InvoiceSchema;

                const resolvedPaymentMethod = String(
                    invoice.payment_method ?? defaultPaymentMethod ?? '',
                );

                return recalculateInvoice({
                    ...mergedInvoice,
                    payment_method: resolvedPaymentMethod,
                });
            }),
        );

        return applySeededInvoiceNumbering(
            withExtensions,
            normalizedInitialInvoiceNumbers,
            initialInvoiceNumberFormatId,
            [],
        );
    }, [
        defaultPaymentMethod,
        initialInvoiceNumberFormatId,
        isCreate,
        normalizedInitialInvoiceNumbers,
        quotation,
    ]);

    const createInstallmentInvoiceCount =
        isCreate && quotation?.payment_plan === 'installment'
            ? sanitizeInstallmentInvoiceCount(
                  createInitialInvoices.length > 0
                      ? createInitialInvoices.length
                      : 3,
              )
            : 3;

    const initialFormState: OrderSchema = {
        order_number: '',
        number_format_id: null,
        payment_plan: quotation?.payment_plan ?? 'direct',
        installment_invoice_count: createInstallmentInvoiceCount,
        deposit_type: 'fixed',
        deposit_value: 500,
        invoices: createInitialInvoices,
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
              installment_invoice_count:
                  initialData.payment_plan === 'installment'
                      ? sanitizeInstallmentInvoiceCount(
                            initialData.installment_invoice_count ??
                                initialData.invoices?.length ??
                                3,
                        )
                      : sanitizeInstallmentInvoiceCount(
                            initialData.installment_invoice_count ?? 3,
                        ),
              invoices: normalizeInvoices(initialData.invoices ?? []).map(
                  (invoice) => ({
                      ...invoice,
                      payment_method:
                          String(invoice.payment_method ?? '') ||
                          String(defaultPaymentMethod ?? ''),
                  }),
              ),
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

    const invoicesGrandTotal = useMemo(
        () =>
            (data.invoices ?? []).reduce(
                (sum, invoice) => sum + calculateInvoiceGrandTotal(invoice),
                0,
            ),
        [data.invoices],
    );

    const updateInvoiceAtIndex = useCallback(
        (
            invoiceIndex: number,
            updater: (invoice: InvoiceSchema) => InvoiceSchema,
        ) => {
            setData((currentData) => {
                const currentInvoices = [...(currentData.invoices ?? [])];
                const currentInvoice = currentInvoices[invoiceIndex];

                if (!currentInvoice) {
                    return currentData;
                }

                currentInvoices[invoiceIndex] = updater(currentInvoice);

                return {
                    ...currentData,
                    invoices: currentInvoices,
                };
            });
        },
        [setData],
    );

    const rebuildInvoicesFromSource = useCallback(
        (
            paymentPlan: string,
            depositType?: string | null,
            depositValue?: number | string | null,
            currentInvoices: InvoiceSchema[] = [],
            installmentInvoiceCount?: number | string | null,
        ): InvoiceSchema[] => {
            const normalizedInstallmentCount = sanitizeInstallmentInvoiceCount(
                installmentInvoiceCount,
            );

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
                            normalizedInstallmentCount,
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

                const sourceExtensions = aggregateSourceExtensionsForSplit(
                    currentInvoices,
                    (quotation.extensions ?? []) as InvoiceExtensionInput[],
                );

                const normalizedRebuiltInvoices = normalizeInvoices(
                    rebuiltInvoices.map((invoice, index) => {
                        const existingInvoice = currentInvoices[index];

                        const inheritedExtensions = normalizeInvoiceExtensions(
                            sourceExtensions
                                .filter((extension) => {
                                    const calculationMode =
                                        String(
                                            extension.calculation_mode ??
                                                'fixed',
                                        ) === 'percentage'
                                            ? 'percentage'
                                            : 'fixed';

                                    return (
                                        calculationMode === 'percentage' ||
                                        index === 0
                                    );
                                })
                                .map((extension, extensionIndex) => {
                                    const calculationMode =
                                        String(
                                            extension.calculation_mode ??
                                                'fixed',
                                        ) === 'percentage'
                                            ? 'percentage'
                                            : 'fixed';

                                    const sourceAmount = Number(
                                        extension.amount ??
                                            extension.calculation_value ??
                                            0,
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
                                        calculation_value: calculationValue,
                                        amount:
                                            calculationMode === 'fixed'
                                                ? sourceAmount
                                                : 0,
                                        sort_order:
                                            extension.sort_order ??
                                            extensionIndex + 1,
                                    };
                                }),
                        );

                        const mergedInvoice = {
                            ...invoice,
                            extensions: inheritedExtensions,
                        } as InvoiceSchema;

                        const resolvedPaymentMethod = String(
                            existingInvoice?.payment_method ??
                                invoice.payment_method ??
                                defaultPaymentMethod ??
                                '',
                        );

                        if (!existingInvoice) {
                            return recalculateInvoice({
                                ...mergedInvoice,
                                payment_method: resolvedPaymentMethod,
                            });
                        }

                        return recalculateInvoice({
                            ...mergedInvoice,
                            payment_method: resolvedPaymentMethod,
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

                if (isCreate) {
                    return applySeededInvoiceNumbering(
                        normalizedRebuiltInvoices,
                        normalizedInitialInvoiceNumbers,
                        initialInvoiceNumberFormatId,
                        currentInvoices,
                    );
                }

                return applyInvoiceNumberingSequence(
                    normalizedRebuiltInvoices,
                    {
                        sourceInvoices: currentInvoices,
                    },
                );
            }

            const normalizedRebuiltInvoices = normalizeInvoices(
                buildInvoices(
                    paymentPlan,
                    currentInvoices,
                    depositType,
                    depositValue,
                    normalizedInstallmentCount,
                ).map((invoice) => {
                    const resolvedPaymentMethod = String(
                        invoice.payment_method ?? defaultPaymentMethod ?? '',
                    );

                    return recalculateInvoice(
                        applyInvoicePaymentMethodExtensions(
                            {
                                ...invoice,
                                payment_method: resolvedPaymentMethod,
                            },
                            resolvedPaymentMethod,
                            normalizedOrderExtensionMasters,
                        ),
                    );
                }),
            );

            if (isCreate) {
                return applySeededInvoiceNumbering(
                    normalizedRebuiltInvoices,
                    normalizedInitialInvoiceNumbers,
                    initialInvoiceNumberFormatId,
                    currentInvoices,
                );
            }

            return applyInvoiceNumberingSequence(normalizedRebuiltInvoices, {
                sourceInvoices: currentInvoices,
            });
        },
        [
            defaultPaymentMethod,
            initialInvoiceNumberFormatId,
            isCreate,
            normalizedInitialInvoiceNumbers,
            normalizedOrderExtensionMasters,
            quotation,
        ],
    );

    function addInvoice() {
        if (data.payment_plan === 'installment') {
            const nextInstallmentCount = sanitizeInstallmentInvoiceCount(
                Number(data.invoices.length ?? 0) + 1,
            );

            setData({
                ...data,
                installment_invoice_count: nextInstallmentCount,
                invoices: rebuildInvoicesFromSource(
                    data.payment_plan,
                    data.deposit_type,
                    data.deposit_value,
                    data.invoices,
                    nextInstallmentCount,
                ),
            });

            return;
        }

        const newInvoices = applyInvoiceNumberingSequence(
            [
                ...data.invoices,
                createEmptyInvoice(String(defaultPaymentMethod ?? '')),
            ],
            {
                sourceInvoices: data.invoices,
            },
        );
        setData('invoices', newInvoices);
    }

    function removeInvoice(index: number) {
        if (data.invoices.length <= 1) return;

        if (data.payment_plan === 'installment') {
            const nextCurrentInvoices = data.invoices.filter(
                (_, invoiceIndex) => invoiceIndex !== index,
            );
            const nextInstallmentCount = sanitizeInstallmentInvoiceCount(
                Number(nextCurrentInvoices.length ?? 0),
            );

            setData({
                ...data,
                installment_invoice_count: nextInstallmentCount,
                invoices: rebuildInvoicesFromSource(
                    data.payment_plan,
                    data.deposit_type,
                    data.deposit_value,
                    nextCurrentInvoices,
                    nextInstallmentCount,
                ),
            });

            return;
        }

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

        setData((currentData) => {
            const invoices = [...(currentData.invoices ?? [])];
            const fromInvoice = invoices[fromIndex];
            const toInvoice = invoices[toIndex];

            if (!fromInvoice || !toInvoice) {
                return currentData;
            }

            const fromItems = fromInvoice.items ?? [];
            const toItems = toInvoice.items ?? [];

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

            if (
                !itemKeys.length ||
                !itemKeys.every((key) => isRootHeader(key))
            ) {
                return currentData;
            }

            const movingItems = collectWithChildren(fromItems, itemKeys);
            const movingKeys = new Set(movingItems.map((i) => i._key));

            invoices[fromIndex] = recalculateInvoice({
                ...fromInvoice,
                items: normalizeItems(
                    fromItems.filter((i) => !movingKeys.has(i._key)),
                ),
                amount: 0,
            });

            invoices[toIndex] = recalculateInvoice({
                ...toInvoice,
                items: normalizeItems([...toItems, ...movingItems]),
                amount: 0,
            });

            return {
                ...currentData,
                invoices,
            };
        });
    }

    function handleInvoicePaymentMethodChange(
        invoiceIndex: number,
        paymentMethod: string,
    ) {
        updateInvoiceAtIndex(invoiceIndex, (currentInvoice) =>
            recalculateInvoice(
                applyInvoicePaymentMethodExtensions(
                    {
                        ...currentInvoice,
                        payment_method: paymentMethod,
                    },
                    paymentMethod,
                    normalizedOrderExtensionMasters,
                ),
            ),
        );
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
                    const errorMessage =
                        resolvePrimaryValidationMessage(errors);

                    if (errorMessage) {
                        toast.error(errorMessage);
                    }

                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },
            });
        } else if (isEdit) {
            put(`${url}/${data.id}`, {
                preserveState: true,
                preserveScroll: true,
                onError: (errors) => {
                    setError(errors);
                    const errorMessage =
                        resolvePrimaryValidationMessage(errors);

                    if (errorMessage) {
                        toast.error(errorMessage);
                    }

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
                invoice_number: 'invoice number',
                number_format_id: 'invoice number format',
                invoice_date: 'invoice date',
                due_date: 'due date',
                payment_method: 'payment method',
                description: 'invoice name',
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

    const focusErrorField = (errorKey: string) => {
        const parts = errorKey.split('.');

        if (parts[0] === 'invoices' && parts.length >= 3) {
            const invoiceIndex = Number(parts[1]);
            const invoiceField = parts[2];

            if (!Number.isFinite(invoiceIndex) || invoiceIndex < 0) {
                return;
            }

            const targetInvoice = data.invoices[invoiceIndex];

            if (!targetInvoice) {
                return;
            }

            setCollapsedInvoices((prev) => ({
                ...prev,
                [targetInvoice._key]: false,
            }));

            const cardElement = document.getElementById(
                `order-invoice-card-${invoiceIndex}`,
            );
            cardElement?.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
            });

            setTimeout(() => {
                if (
                    invoiceField === 'invoice_number' ||
                    invoiceField === 'number_format_id'
                ) {
                    const modelInput = document.getElementById(
                        `invoice-number-input-${invoiceIndex}`,
                    );
                    modelInput?.focus();
                    return;
                }

                const genericField = cardElement?.querySelector(
                    `#${invoiceField}`,
                ) as HTMLElement | null;
                genericField?.focus();
            }, 180);

            return;
        }

        const topField = document.getElementById(parts[0]);
        topField?.focus();
    };

    // expand & collapse invoice
    const [collapsedInvoices, setCollapsedInvoices] = useState<
        Record<string, boolean>
    >({});
    const [memberOptions, setMemberOptions] = useState<OptionType[]>([]);
    const [invoicePaymentMethodOptions, setInvoicePaymentMethodOptions] =
        useState<OptionType[]>(paymentMethods);
    const [memberSharingPlanById, setMemberSharingPlanById] = useState<
        Record<number, string | null>
    >({});

    useEffect(() => {
        setInvoicePaymentMethodOptions(paymentMethods);
    }, [paymentMethods]);

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

    const quotationExtensions = useMemo(
        () => quotation?.extensions ?? [],
        [quotation?.extensions],
    );

    const quotationItemTaxSummaries = useMemo(() => {
        return buildItemTaxSummaries(quotation?.items ?? []);
    }, [quotation?.items]);

    const quotationItemTaxRows = useMemo(
        () =>
            quotationItemTaxSummaries.map((tax, index) => ({
                key: `quotation-item-tax-${index}`,
                label: formatExtensionLabel(
                    String(tax.name ?? 'Tax'),
                    tax.calculation_mode,
                    Number(tax.calculation_value ?? 0),
                ),
                amount: Number(tax.amount ?? 0),
            })),
        [quotationItemTaxSummaries],
    );

    const quotationItemTaxTotal = quotationItemTaxSummaries.reduce(
        (sum, tax) => sum + Number(tax.amount ?? 0),
        0,
    );

    const quotationExtensionsWithAmount = useMemo(() => {
        return normalizeInvoiceExtensions(
            quotationExtensions as InvoiceExtensionInput[],
        ).map((extension) => ({
            ...extension,
            amount: computeInvoiceExtensionAmount(
                extension,
                quotationSubtotalAmount,
            ),
        }));
    }, [quotationExtensions, quotationSubtotalAmount]);

    const quotationExtensionAmountFromExtensions =
        quotationExtensionsWithAmount.reduce(
            (sum, extension) => sum + Number(extension.amount ?? 0),
            0,
        );

    const quotationExtensionTotalAmount = Number(
        quotation?.extension_total_amount ??
            quotationItemTaxTotal + quotationExtensionAmountFromExtensions,
    );

    const quotationTotalAmount = Number(
        quotation?.total_amount ??
            quotationSubtotalAmount + quotationExtensionTotalAmount,
    );

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

    // dont remove the console, its for me to check the data.
    console.log(data);
    console.log(data.invoices.map((i) => i.invoice_number));

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
                                                    <button
                                                        type="button"
                                                        className="cursor-pointer text-left underline decoration-red-300 underline-offset-2 hover:text-red-900"
                                                        onClick={() =>
                                                            focusErrorField(key)
                                                        }
                                                    >
                                                        •{' '}
                                                        {formatError(
                                                            key,
                                                            message,
                                                        )}
                                                    </button>
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
                                            const currentPlan = String(
                                                data.payment_plan ?? '',
                                            ).toLowerCase();
                                            const nextPlan = String(
                                                v ?? '',
                                            ).toLowerCase();
                                            const paidInvoiceCount =
                                                data.invoices.filter(
                                                    (invoice) =>
                                                        isPaidInvoice(invoice),
                                                ).length;

                                            if (
                                                isEdit &&
                                                currentPlan === 'installment' &&
                                                ['full', 'direct'].includes(
                                                    nextPlan,
                                                ) &&
                                                paidInvoiceCount > 1
                                            ) {
                                                const message =
                                                    'Cannot change payment plan from installment when more than one installment invoice is already paid.';

                                                setError(
                                                    'payment_plan',
                                                    message,
                                                );
                                                toast.error(message);

                                                return;
                                            }

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
                                                installment_invoice_count:
                                                    v === 'installment'
                                                        ? sanitizeInstallmentInvoiceCount(
                                                              data.installment_invoice_count ??
                                                                  data.invoices
                                                                      .length ??
                                                                  3,
                                                          )
                                                        : data.installment_invoice_count,
                                                deposit_type: nextDepositType,
                                                deposit_value: nextDepositValue,
                                                invoices:
                                                    rebuildInvoicesFromSource(
                                                        v,
                                                        nextDepositType,
                                                        nextDepositValue,
                                                        data.invoices,
                                                        v === 'installment'
                                                            ? sanitizeInstallmentInvoiceCount(
                                                                  data.installment_invoice_count ??
                                                                      data
                                                                          .invoices
                                                                          .length ??
                                                                      3,
                                                              )
                                                            : data.installment_invoice_count,
                                                    ),
                                            });
                                        }}
                                    />

                                    {data.payment_plan === 'installment' && (
                                        <FormField label="Installment Invoice Count">
                                            <ProperInput
                                                value={
                                                    data.installment_invoice_count ??
                                                    3
                                                }
                                                type="number"
                                                inputProps={{
                                                    min: '3',
                                                    step: '1',
                                                }}
                                                placeholder="Minimum 3"
                                                disabled={isView}
                                                onCommit={(value) => {
                                                    const normalizedCount =
                                                        sanitizeInstallmentInvoiceCount(
                                                            value,
                                                        );

                                                    setData({
                                                        ...data,
                                                        installment_invoice_count:
                                                            normalizedCount,
                                                        invoices:
                                                            rebuildInvoicesFromSource(
                                                                data.payment_plan ??
                                                                    'installment',
                                                                data.deposit_type,
                                                                data.deposit_value,
                                                                data.invoices,
                                                                normalizedCount,
                                                            ),
                                                    });
                                                }}
                                            />
                                            {renderError(
                                                'installment_invoice_count',
                                            )}
                                        </FormField>
                                    )}

                                    {data.payment_plan === 'installment' && (
                                        <>
                                            <FormField label="Deposit Type">
                                                <Select
                                                    value={String(
                                                        data.deposit_type ?? '',
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
                                                                    data.installment_invoice_count,
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
                                                                    {dt.label}
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                                {renderError('deposit_type')}
                                            </FormField>

                                            <FormField label="Deposit Value">
                                                <ProperInput
                                                    value={
                                                        data.deposit_value ?? ''
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
                                                            deposit_value: v,
                                                            invoices:
                                                                rebuildInvoicesFromSource(
                                                                    data.payment_plan ??
                                                                        'installment',
                                                                    data.deposit_type,
                                                                    v,
                                                                    data.invoices,
                                                                    data.installment_invoice_count,
                                                                ),
                                                        });
                                                    }}
                                                />
                                                {renderError('deposit_value')}
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
                                            ${invoicesGrandTotal}
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
                                const invoiceErrorMap = errors as Record<
                                    string,
                                    string | undefined
                                >;
                                const invoiceNumberError =
                                    invoiceErrorMap[
                                        `invoices.${idx}.invoice_number`
                                    ] ??
                                    invoiceErrorMap[
                                        `invoices.${idx}.number_format_id`
                                    ];
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
                                const itemTaxRows = itemTaxSummaries.map(
                                    (tax, taxIndex) => ({
                                        key: `invoice-${idx}-item-tax-${taxIndex}`,
                                        label: formatExtensionLabel(
                                            String(tax.name ?? 'Tax'),
                                            tax.calculation_mode,
                                            Number(tax.calculation_value ?? 0),
                                        ),
                                        amount: Number(tax.amount ?? 0),
                                    }),
                                );
                                const invoiceExtensionsWithAmount =
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
                                const extensionAmountFromExtensions =
                                    invoiceExtensionsWithAmount.reduce(
                                        (sum, extension) =>
                                            sum + Number(extension.amount ?? 0),
                                        0,
                                    );
                                const invoiceExtensionTotal =
                                    itemTaxTotal +
                                    extensionAmountFromExtensions;
                                const invoiceGrandTotal =
                                    invoiceSubtotal + invoiceExtensionTotal;

                                return (
                                    <Card
                                        id={`order-invoice-card-${idx}`}
                                        key={invoice._key}
                                        className={`overflow-hidden border-l-4 py-0 shadow-sm transition-shadow hover:shadow-md ${
                                            invoiceHasErrors
                                                ? 'border-red-200 border-l-red-500 bg-red-50/20'
                                                : 'border-muted/80 border-l-primary/70 bg-card'
                                        }`}
                                    >
                                        <CardContent className="space-y-4 px-0">
                                            {invoiceHasErrors && (
                                                <div className="mx-4 mt-4 rounded-md border border-red-200 bg-red-50 p-3">
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

                                            <div
                                                className={`border-b px-4 py-4 ${
                                                    invoiceHasErrors
                                                        ? 'border-red-200 bg-red-50/40'
                                                        : 'border-muted/70 bg-muted/20'
                                                }`}
                                            >
                                                <div className="flex items-center justify-between gap-4">
                                                    <div className="flex items-start gap-3">
                                                        <div className="space-y-1">
                                                            <div className="flex flex-wrap items-center gap-2">
                                                                <Badge
                                                                    variant="outline"
                                                                    className="px-2 py-1 text-base font-semibold text-primary"
                                                                >
                                                                    {invoice.invoice_number ||
                                                                        `Invoice #${idx + 1}`}
                                                                </Badge>
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
                                                                    {
                                                                        invoiceGrandTotal
                                                                    }
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

                                                        {!isView &&
                                                            !isInvoiceLockedForRemoval(
                                                                invoice,
                                                            ) && (
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
                                            </div>

                                            {!collapsedInvoices[
                                                invoice._key
                                            ] && (
                                                <div className="space-y-4 px-4 pb-4">
                                                    <InvoiceHeader
                                                        invoice={invoice}
                                                        disabled={
                                                            processing || isView
                                                        }
                                                        renderError={(path) =>
                                                            renderError(
                                                                `invoices.${idx}.${path}`,
                                                            )
                                                        }
                                                        modelNumberField={
                                                            <ModelNumberInput
                                                                modelKey="invoice"
                                                                label="Invoice Number"
                                                                inputId={`invoice-number-input-${idx}`}
                                                                value={
                                                                    invoice.invoice_number ??
                                                                    ''
                                                                }
                                                                formatId={
                                                                    invoice.number_format_id ??
                                                                    null
                                                                }
                                                                onValueChange={(
                                                                    nextValue,
                                                                ) => {
                                                                    updateInvoiceAtIndex(
                                                                        idx,
                                                                        (
                                                                            currentInvoice,
                                                                        ) => ({
                                                                            ...currentInvoice,
                                                                            invoice_number:
                                                                                nextValue,
                                                                        }),
                                                                    );
                                                                }}
                                                                onFormatIdChange={(
                                                                    nextFormatId,
                                                                ) => {
                                                                    updateInvoiceAtIndex(
                                                                        idx,
                                                                        (
                                                                            currentInvoice,
                                                                        ) => ({
                                                                            ...currentInvoice,
                                                                            number_format_id:
                                                                                nextFormatId,
                                                                        }),
                                                                    );
                                                                }}
                                                                disabled={
                                                                    processing ||
                                                                    isView
                                                                }
                                                                error={
                                                                    invoiceNumberError
                                                                }
                                                                hint="Select format to auto-generate invoice number for this invoice."
                                                                skipInitialAutofill
                                                            />
                                                        }
                                                        paymentMethodField={
                                                            <PaymentMethodMasterCombobox
                                                                triggerId={`invoice-payment-method-${idx}`}
                                                                value={String(
                                                                    invoice.payment_method ??
                                                                        '',
                                                                )}
                                                                options={
                                                                    invoicePaymentMethodOptions
                                                                }
                                                                disabled={
                                                                    processing ||
                                                                    isView
                                                                }
                                                                placeholder="Select payment method"
                                                                searchPlaceholder="Search payment method"
                                                                onChange={(
                                                                    value,
                                                                ) =>
                                                                    handleInvoicePaymentMethodChange(
                                                                        idx,
                                                                        value,
                                                                    )
                                                                }
                                                                onOptionsChange={
                                                                    setInvoicePaymentMethodOptions
                                                                }
                                                            />
                                                        }
                                                        onChange={(patch) => {
                                                            updateInvoiceAtIndex(
                                                                idx,
                                                                (
                                                                    currentInvoice,
                                                                ) => ({
                                                                    ...currentInvoice,
                                                                    ...patch,
                                                                }),
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
                                                            updateInvoiceAtIndex(
                                                                idx,
                                                                (
                                                                    currentInvoice,
                                                                ) =>
                                                                    recalculateInvoice(
                                                                        {
                                                                            ...currentInvoice,
                                                                            items: normalizeItems(
                                                                                items,
                                                                            ),
                                                                        },
                                                                    ),
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

                                                    <TotalsSummaryCard
                                                        className="mt-3"
                                                        subtotalAmount={
                                                            invoiceSubtotal
                                                        }
                                                        itemTaxRows={
                                                            itemTaxRows
                                                        }
                                                        extensions={
                                                            invoiceExtensionsWithAmount
                                                        }
                                                        extensionMasters={
                                                            normalizedOrderExtensionMasters
                                                        }
                                                        onExtensionsChange={(
                                                            extensions,
                                                        ) => {
                                                            updateInvoiceAtIndex(
                                                                idx,
                                                                (
                                                                    currentInvoice,
                                                                ) =>
                                                                    recalculateInvoice(
                                                                        {
                                                                            ...currentInvoice,
                                                                            extensions,
                                                                        },
                                                                    ),
                                                            );
                                                        }}
                                                        readOnly={isView}
                                                        extensionTotalAmount={
                                                            invoiceExtensionTotal
                                                        }
                                                        grandTotalAmount={Number(
                                                            invoice.amount ??
                                                                invoiceGrandTotal,
                                                        )}
                                                    />
                                                </div>
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
                                                <PackageSharingPlanInfo
                                                    packageName={
                                                        quotation.package_name
                                                    }
                                                    singlePrice={
                                                        quotation.package_price_single
                                                    }
                                                    doublePrice={
                                                        quotation.package_price_double
                                                    }
                                                    triplePrice={
                                                        quotation.package_price_triple
                                                    }
                                                    quadPrice={
                                                        quotation.package_price_quad
                                                    }
                                                    childWithBedPrice={
                                                        quotation.package_price_child_with_bed
                                                    }
                                                    childNoBedPrice={
                                                        quotation.package_price_child_no_bed
                                                    }
                                                    infantPrice={
                                                        quotation.package_price_infant
                                                    }
                                                    className="text-sm"
                                                />
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
                                                taxExtensionMasters={
                                                    taxExtensionMasters
                                                }
                                                memberSharingPlanById={
                                                    memberSharingPlanById
                                                }
                                            />

                                            <TotalsSummaryCard
                                                subtotalAmount={
                                                    quotationSubtotalAmount
                                                }
                                                itemTaxRows={
                                                    quotationItemTaxRows
                                                }
                                                extensions={
                                                    quotationExtensionsWithAmount
                                                }
                                                grandTotalAmount={
                                                    quotationTotalAmount
                                                }
                                            />
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
