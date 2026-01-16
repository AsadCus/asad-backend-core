import { DatePickerField } from '@/components/date-picker';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { isBeforeToday } from '@/lib/utils';
import { OptionType } from '@/types';
import { useForm } from '@inertiajs/react';
import { AlertCircle, Trash } from 'lucide-react';
import { nanoid } from 'nanoid';
import { useEffect, useState } from 'react';
import { InvoiceHeader } from '../invoices/components/invoice-header';
import {
    calculateInvoicesTotal,
    calculateTotal,
    collectWithChildren,
    normalizeItems,
} from '../invoices/lib/utils';
import { InvoiceSchema } from '../invoices/schema';
import QuotationItemTableForm from '../quotations/items/form';
import { QuotationSchema } from '../quotations/schema';
import { PaymentPlanSection } from './components/payment-plan-section';
import {
    autoFillInvoiceDates,
    buildInitialInvoices,
    buildInvoices,
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
                ? (itemKeyMap.get(item.parent_id) ?? null)
                : null,
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
        description: '',
        invoice_date: '',
        due_date: '',
        items: [],
        amount: 0,
    };
}

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
        payment_plan: quotation?.payment_plan ?? 'direct',
        handover_date: quotation?.commencement_date ?? '',
        invoices: [],
        items: initialItems ?? [],

        quotation_id: quotation?.id,
        quotation_number: quotation?.quotation_number,
    };

    const defaultData: OrderSchema = initialData
        ? {
              ...initialData,
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

    useEffect(() => {
        if (!initialData && quotation) {
            let invoices;
            if (data.payment_plan) {
                const paymentPlan =
                    data.payment_plan ?? quotation.payment_plan ?? 'full';

                invoices = normalizeInvoices(
                    buildInitialInvoices({
                        ...quotation,
                        payment_plan: paymentPlan,
                    }),
                );
            } else {
                invoices = normalizeInvoices(buildInitialInvoices(quotation));
            }

            setData('invoices', invoices);
        }
    }, [initialData, quotation, data.payment_plan, setData]);

    function addInvoice() {
        const newInvoices = [...data.invoices, createEmptyInvoice()];
        const withDates = autoFillInvoiceDates(
            newInvoices,
            data.handover_date ?? '',
        );
        setData('invoices', withDates);
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

        const movingItems = collectWithChildren(fromItems, itemKeys);
        const movingKeys = new Set(movingItems.map((i) => i._key));

        invoices[fromIndex] = {
            ...invoices[fromIndex],
            items: normalizeItems(
                fromItems.filter((i) => !movingKeys.has(i._key)),
            ),
            amount: calculateTotal(
                fromItems.filter((i) => !movingKeys.has(i._key)),
            ),
        };

        invoices[toIndex] = {
            ...invoices[toIndex],
            items: normalizeItems([...toItems, ...movingItems]),
            amount: calculateTotal([...toItems, ...movingItems]),
        };

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
            <p className="mt-1 text-xs text-red-500">
                {formatError(path, message)}
            </p>
        );
    };

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

    useEffect(() => {
        if (!data.invoices.length) return;

        setCollapsedInvoices((prev) => {
            const next: Record<string, boolean> = {};

            data.invoices.forEach((invoice) => {
                next[invoice._key] = prev[invoice._key] ?? true;
            });

            return next;
        });
    }, [data]);

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

    // preview
    // const [previewInvoice, setPreviewInvoice] = useState<InvoiceSchema | null>(
    //     null,
    // );

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
                                    <ul className="mt-2 space-y-1 text-sm text-red-800">
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

                    {/* Order Number Box */}
                    {data.order_number && (
                        <div className="mb-2 rounded-lg border border-primary/20 bg-primary/5 p-4">
                            <p className="text-sm text-muted-foreground">
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
                                    {/* Payment Plan */}
                                    <PaymentPlanSection
                                        value={data.payment_plan ?? ''}
                                        plans={paymentPlans}
                                        disabled={isView}
                                        renderError={renderError}
                                        onChange={(v) =>
                                            setData({
                                                ...data,
                                                payment_plan: v,
                                                invoices: buildInvoices(
                                                    v,
                                                    data.invoices,
                                                    data.handover_date ??
                                                        undefined,
                                                ),
                                            })
                                        }
                                    />

                                    {/* Handover Date */}
                                    {data.payment_plan !== 'direct' && (
                                        <div className="grid gap-2">
                                            <Label>Handover Date</Label>
                                            <div className="relative">
                                                <DatePickerField
                                                    id="handover_date"
                                                    value={
                                                        data.handover_date ?? ''
                                                    }
                                                    disabled={isView}
                                                    disabledDates={
                                                        isBeforeToday
                                                    }
                                                    quickDate
                                                    onChange={(v) => {
                                                        const updatedInvoices =
                                                            autoFillInvoiceDates(
                                                                data.invoices,
                                                                v,
                                                            );
                                                        setData({
                                                            ...data,
                                                            handover_date: v,
                                                            invoices:
                                                                updatedInvoices,
                                                        });
                                                    }}
                                                />
                                                {renderError('handover_date')}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <h2 className="text-lg font-semibold">
                                        Invoices Breakdown
                                    </h2>

                                    <div className="rounded-md bg-primary/10 px-3 py-1 text-sm font-semibold text-primary">
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
                                                            <p className="text-sm font-semibold text-red-900">
                                                                Invoice #
                                                                {idx + 1} has
                                                                validation
                                                                errors:
                                                            </p>
                                                            <ul className="mt-1 space-y-0.5 text-xs text-red-800">
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
                                                            <span className="text-sm font-medium text-muted-foreground">
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
                                                        <div className="rounded-md bg-emerald-50 px-3 py-1 text-sm font-semibold text-emerald-700">
                                                            <span className="tabular-nums">
                                                                $
                                                                {calculateTotal(
                                                                    invoice.items,
                                                                )}
                                                            </span>
                                                        </div>
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
                                                            next[idx] = {
                                                                ...invoice,
                                                                items: normalizeItems(
                                                                    items,
                                                                ),
                                                                amount: calculateTotal(
                                                                    items,
                                                                ),
                                                            };
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
                                                        showPlacementFeeColumn={
                                                            true
                                                        }
                                                    />
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
                                    <p className="text-sm text-muted-foreground">
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
                                            <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                                                <div className="grid gap-2">
                                                    <Label>
                                                        Monthly Salary
                                                    </Label>
                                                    <Input
                                                        id="monthly_salary"
                                                        type="number"
                                                        step="any"
                                                        min="0"
                                                        value={
                                                            quotation.monthly_salary ??
                                                            ''
                                                        }
                                                        disabled
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label>
                                                        Loan Duration (Months)
                                                    </Label>
                                                    <Input
                                                        id="loan_duration"
                                                        type="number"
                                                        step="any"
                                                        min="0"
                                                        value={
                                                            quotation.loan_duration ??
                                                            ''
                                                        }
                                                        disabled
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label>
                                                        Cost of Maid/Placement
                                                        Fee
                                                    </Label>
                                                    <Input
                                                        id="cost_of_maid"
                                                        type="number"
                                                        step="any"
                                                        min="0"
                                                        value={
                                                            Number(
                                                                quotation.monthly_salary,
                                                            ) *
                                                            Number(
                                                                quotation.loan_duration,
                                                            )
                                                        }
                                                        disabled
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label>Total Amount</Label>
                                                    <Input
                                                        id="total_amount"
                                                        type="number"
                                                        step="any"
                                                        min="0"
                                                        value={
                                                            quotation.total_amount ??
                                                            ''
                                                        }
                                                        disabled
                                                    />
                                                </div>
                                            </div>
                                            <QuotationItemTableForm
                                                items={initialItems ?? []}
                                                onChange={(next) =>
                                                    setData('items', next)
                                                }
                                                disabled
                                                showPlacementFeeColumn
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

            {/* {previewInvoice && (
                <Dialog
                    open={!!previewInvoice}
                    onOpenChange={() => setPreviewInvoice(null)}
                >
                    <DialogContent className="flex max-h-[98%] min-h-fit max-w-[95%] min-w-fit flex-col items-center justify-center overflow-y-hidden">
                        <DialogHeader>
                            <DialogTitle>
                                {previewInvoice.invoice_number ||
                                    'Invoice Preview'}
                            </DialogTitle>
                            <DialogDescription>
                                {previewInvoice.description || '—'}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="mb-3 w-full text-sm text-muted-foreground">
                            <div className="grid grid-cols-1 gap-2">
                                <div>
                                    <span className="font-medium text-foreground">
                                        Invoice Date:
                                    </span>{' '}
                                    {previewInvoice.invoice_date
                                        ? new Date(
                                              previewInvoice.invoice_date,
                                          ).toLocaleDateString()
                                        : '—'}
                                </div>

                                <div>
                                    <span className="font-medium text-foreground">
                                        Due Date:
                                    </span>{' '}
                                    {previewInvoice.due_date
                                        ? new Date(
                                              previewInvoice.due_date,
                                          ).toLocaleDateString()
                                        : '—'}
                                </div>
                            </div>
                        </div>

                        <div className="max-h-[90vh] overflow-auto">
                            <QuotationItemTableForm
                                items={previewInvoice.items}
                                disabled
                                renderError={() => null}
                                invoices={[]}
                                currentInvoiceIndex={0}
                                onChange={() => {}}
                                onMoveItem={() => {}}
                                showPlacementFeeColumn={true}
                            />
                        </div>
                    </DialogContent>
                </Dialog>
            )} */}
        </>
    );
}
