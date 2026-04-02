import { FormField } from '@/components/form-field';
import { ProperInput } from '@/components/proper-input';
import { ProperInputSelect } from '@/components/proper-input-select';
import TotalsSummaryCard, {
    type TotalsSummaryExtensionMaster,
} from '@/components/totals-summary-card';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { update as updateInvoiceRoute } from '@/routes/invoice';
import { OptionType } from '@/types';
import { useForm } from '@inertiajs/react';
import { nanoid } from 'nanoid';
import { useCallback, useMemo } from 'react';
import {
    applyInvoicePaymentMethodExtensions,
    type InvoicePaymentMethodExtensionMaster,
    recalculateInvoice,
} from '../orders/lib/invoice-builders';
import QuotationItemTableForm from '../quotations/items/form';
import { QuotationItemSchema } from '../quotations/items/schema';
import { InvoiceHeader } from './components/invoice-header';
import { normalizeItems } from './lib/utils';
import { InvoiceItemSchema, InvoiceSchema } from './schema';

interface InvoiceFormProps {
    mode: 'create' | 'edit' | 'view';
    invoice?: InvoiceSchema;
    paymentMethods?: OptionType[];
    extensionMasters?: TotalsSummaryExtensionMaster[];
    defaultPaymentMethod?: string;
    initialInvoiceNumberFormatId?: number | null;
    initialInvoiceNumber?: string;
    onCancel?: () => void;
}

type InvoiceExtensionMasterInput = TotalsSummaryExtensionMaster &
    InvoicePaymentMethodExtensionMaster;

type TaxExtensionMasterOption = {
    id: number;
    name: string;
    calculation_mode?: string | null;
    calculation_value?: string | number | null;
};

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

function normalizeInvoices(invoices: InvoiceSchema[] = []): InvoiceSchema[] {
    return invoices.map((invoice) => ({
        ...invoice,
        _key: invoice._key ?? (invoice.id ? `id-${invoice.id}` : nanoid()),
    }));
}

export default function InvoiceForm({
    mode,
    invoice,
    paymentMethods = [],
    extensionMasters = [],
    defaultPaymentMethod = '',
    initialInvoiceNumberFormatId = null,
    initialInvoiceNumber = '',
    onCancel,
}: InvoiceFormProps) {
    const isView = mode === 'view';
    const isEdit = mode === 'edit';
    const isCreate = mode === 'create';
    const isReadOnly = isView || isCreate;

    const normalizedExtensionMasters = useMemo(
        () =>
            extensionMasters.map((master) => {
                const extensionMaster = master as InvoiceExtensionMasterInput;

                return {
                    ...master,
                    payment_methods: Array.isArray(
                        extensionMaster.payment_methods,
                    )
                        ? (extensionMaster.payment_methods ?? [])
                        : [],
                };
            }) as InvoiceExtensionMasterInput[],
        [extensionMasters],
    );

    const defaultInvoice: InvoiceSchema = useMemo(() => {
        if (invoice) {
            return normalizeInvoices([invoice])[0];
        }

        const newInvoice = createEmptyInvoice(defaultPaymentMethod);
        // Apply seeded number if creating
        if (initialInvoiceNumber && isCreate) {
            newInvoice.invoice_number = initialInvoiceNumber;
            newInvoice.number_format_id = initialInvoiceNumberFormatId;
        }
        return newInvoice;
    }, [
        invoice,
        defaultPaymentMethod,
        initialInvoiceNumber,
        initialInvoiceNumberFormatId,
        isCreate,
    ]);

    const { data, setData, put, processing, errors, clearErrors } =
        useForm<InvoiceSchema>(defaultInvoice);

    const updateInvoice = useCallback(
        (updater: (invoice: InvoiceSchema) => InvoiceSchema) => {
            setData((currentData) => updater(currentData));
        },
        [setData],
    );

    const updateInvoiceNumber = useCallback(
        (newNumber: string) => {
            updateInvoice((inv) => ({
                ...inv,
                invoice_number: newNumber,
            }));
        },
        [updateInvoice],
    );

    const updateInvoicePaymentMethod = useCallback(
        (paymentMethod: string) => {
            updateInvoice((inv) => {
                const updated = applyInvoicePaymentMethodExtensions(
                    {
                        ...inv,
                        payment_method: paymentMethod,
                    },
                    paymentMethod,
                    normalizedExtensionMasters,
                );
                return recalculateInvoice(updated);
            });
        },
        [normalizedExtensionMasters, updateInvoice],
    );

    const handleItemsChange = useCallback(
        (items: QuotationItemSchema[]) => {
            const coercedItems: InvoiceItemSchema[] = items.map((item) => ({
                ...item,
                invoice_id:
                    item.invoice_id === null ? undefined : item.invoice_id,
            }));

            const normalizedItems = normalizeItems(coercedItems);

            updateInvoice((inv) =>
                recalculateInvoice({
                    ...inv,
                    items: normalizedItems,
                }),
            );
        },
        [updateInvoice],
    );

    const paymentMethodOptions = useMemo(
        () =>
            paymentMethods.map((option) => ({
                label: String(option.label ?? ''),
                value: String(option.value ?? ''),
            })),
        [paymentMethods],
    );

    const taxExtensionMasters = useMemo<TaxExtensionMasterOption[]>(() => {
        const grouped = new Map<string, TaxExtensionMasterOption>();

        normalizedExtensionMasters.forEach((master) => {
            if (String(master.type ?? '').toLowerCase() !== 'tax') {
                return;
            }

            const id = Number(master.id ?? 0);
            const name = String(master.name ?? 'Tax');
            const calculationMode =
                String(master.calculation_mode ?? '') === 'percentage'
                    ? 'percentage'
                    : String(master.calculation_mode ?? 'fixed');
            const calculationValue = Number(master.calculation_value ?? 0);
            const key = [
                id,
                name.toLowerCase(),
                calculationMode,
                calculationValue,
            ].join('|');

            if (!grouped.has(key)) {
                grouped.set(key, {
                    id,
                    name,
                    calculation_mode: calculationMode,
                    calculation_value: calculationValue,
                });
            }
        });

        (data.items ?? []).forEach((item) => {
            (item.taxes ?? []).forEach((tax) => {
                const id = Number(tax.quotation_extension_master_id ?? 0);
                const name = String(tax.name ?? 'Tax');
                const calculationMode =
                    String(tax.calculation_mode ?? '') === 'percentage'
                        ? 'percentage'
                        : String(tax.calculation_mode ?? 'fixed');
                const calculationValue = Number(tax.calculation_value ?? 0);
                const key = [
                    id,
                    name.toLowerCase(),
                    calculationMode,
                    calculationValue,
                ].join('|');

                if (!grouped.has(key)) {
                    grouped.set(key, {
                        id,
                        name,
                        calculation_mode: calculationMode,
                        calculation_value: calculationValue,
                    });
                }
            });
        });

        return Array.from(grouped.values()).sort((left, right) =>
            left.name.localeCompare(right.name),
        );
    }, [data.items, normalizedExtensionMasters]);

    const renderError = useCallback(
        (path: string) => {
            const message = (errors as Record<string, string | undefined>)[
                path
            ];

            if (!message) {
                return null;
            }

            return <p className="mt-1 text-xs text-red-500">{message}</p>;
        },
        [errors],
    );

    function submit(e: React.FormEvent) {
        e.preventDefault();
        clearErrors();

        if (!isEdit || !data.id) {
            return;
        }

        put(updateInvoiceRoute(Number(data.id)).url, {
            preserveScroll: true,
            onSuccess: () => {
                onCancel?.();
            },
        });
    }

    const invoiceItems = normalizeItems(data.items ?? []);
    const invoiceAmount = Number(data.amount ?? 0);
    const subtotalAmount = invoiceItems.reduce((sum, item) => {
        if (item.is_header) {
            return sum;
        }

        return sum + Number(item.quantity ?? 0) * Number(item.rate ?? 0);
    }, 0);
    const extensionTotalAmount = (data.extensions ?? []).reduce<number>(
        (sum, extension) => sum + Number(extension.amount ?? 0),
        0,
    );

    return (
        <form onSubmit={submit} className="flex flex-col gap-6">
            {/* Header */}
            <div className="flex flex-col gap-4">
                <InvoiceHeader
                    invoice={data}
                    disabled={processing || isReadOnly}
                    renderError={renderError}
                    modelNumberField={
                        <FormField
                            label="Invoice Number"
                            htmlFor="invoice_number"
                        >
                            <ProperInput
                                id="invoice_number"
                                value={data.invoice_number ?? ''}
                                disabled={processing || isReadOnly}
                                onCommit={updateInvoiceNumber}
                                placeholder="Invoice number"
                            />
                            {renderError('invoice_number')}
                        </FormField>
                    }
                    paymentMethodField={
                        <ProperInputSelect
                            id="payment_method"
                            value={String(data.payment_method ?? '')}
                            onValueChange={(nextValue) => {
                                if (Array.isArray(nextValue)) {
                                    return;
                                }

                                updateInvoicePaymentMethod(
                                    String(nextValue ?? ''),
                                );
                            }}
                            options={paymentMethodOptions}
                            placeholder="Select payment method"
                            disabled={processing || isReadOnly}
                            searchable
                        />
                    }
                    onChange={(patch) => {
                        updateInvoice((inv) => ({
                            ...inv,
                            ...patch,
                        }));
                    }}
                />
            </div>

            {/* Items */}
            <Card>
                <CardContent className="pt-6">
                    <div className="mb-4 flex items-center justify-between">
                        <h3 className="font-semibold">Line Items</h3>
                    </div>

                    <QuotationItemTableForm
                        items={invoiceItems}
                        onChange={handleItemsChange}
                        disabled={processing || isReadOnly}
                        renderError={renderError}
                        showOptionalColumn={false}
                        showMemberColumn={false}
                        showTaxColumn
                        taxExtensionMasters={taxExtensionMasters}
                    />
                </CardContent>
            </Card>

            {/* Summary */}
            <TotalsSummaryCard
                extensions={
                    (data.extensions ?? []) as TotalsSummaryExtensionMaster[]
                }
                extensionMasters={normalizedExtensionMasters}
                subtotalAmount={subtotalAmount}
                readOnly={isReadOnly}
                extensionTotalAmount={extensionTotalAmount}
                grandTotalAmount={invoiceAmount}
            />

            {/* Actions */}
            <div className="flex justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    onClick={onCancel}
                    disabled={processing}
                >
                    Back
                </Button>
                {isEdit && (
                    <Button type="submit" disabled={processing}>
                        Update Invoice
                    </Button>
                )}
            </div>
        </form>
    );
}
