import { DatePickerField } from '@/components/date-picker';
import { FormField } from '@/components/form-field';
import ModelNumberInput from '@/components/model-number-input';
import PaymentMethodMasterCombobox from '@/components/payment-method-master-combobox';
import { ProperInput } from '@/components/proper-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { formatDateForDisplay } from '@/lib/utils';
import { getForShow } from '@/routes/invoice';
import {
    store as storeReceipt,
    update as updateReceipt,
} from '@/routes/receipt';
import { OptionType } from '@/types';
import { useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { InvoiceSchema } from '../invoices/schema';
import { ReceiptSchema } from './schema';

interface ReceiptFormProps {
    mode: 'create' | 'edit';
    initialData?: ReceiptSchema;
    invoiceId?: number | undefined;
    invoiceData?: InvoiceSchema;
    invoiceOptions: OptionType[];
    paymentMethods?: OptionType[];
    defaultPaymentMethod?: string;
    onCancel?: () => void;
}

export default function ReceiptForm({
    mode,
    initialData,
    invoiceId,
    invoiceData,
    invoiceOptions,
    paymentMethods = [],
    defaultPaymentMethod,
    onCancel,
}: ReceiptFormProps) {
    const isEditMode = mode === 'edit';

    const [selectedInvoice, setSelectedInvoice] =
        useState<InvoiceSchema | null>(invoiceData ?? null);
    const hasManualPaymentMethodOverrideRef = useRef(false);

    const resolvedDefaultPaymentMethod =
        defaultPaymentMethod ?? String(paymentMethods[0]?.value ?? '');

    const initialFormState: ReceiptSchema = {
        receipt_number: '',
        number_format_id: null,
        invoice_id: invoiceId ? Number(invoiceId) : undefined,
        amount: selectedInvoice?.amount,
        receipt_date: formatDateForDisplay(new Date()),
        payment_method:
            String(selectedInvoice?.payment_method ?? '') ||
            resolvedDefaultPaymentMethod,
        reference: '',
        description: '',
        refund_to: '',
    };

    const defaultData: ReceiptSchema = {
        ...(initialData ?? initialFormState),
    };

    const { data, setData, post, put, processing, errors } =
        useForm<ReceiptSchema>(defaultData);

    const isRefund = Boolean(
        selectedInvoice?.is_refund || data.is_refund_receipt_report,
    );

    // In refund mode only payment methods flagged for refund are selectable.
    const visiblePaymentMethods = isRefund
        ? paymentMethods.filter((method) => method.is_available_for_refund)
        : paymentMethods;
    const modelNumberError =
        (errors as Record<string, string | undefined>).receipt_number ??
        (errors as Record<string, string | undefined>).number_format_id;

    // invoice
    const getInvoiceDetail = async (id: number) => {
        hasManualPaymentMethodOverrideRef.current = false;

        try {
            const response = await fetch(getForShow(id).url);
            if (!response) throw new Error('Network error');
            const invoice = await response.json();
            setSelectedInvoice(invoice);
            setData('amount', invoice?.amount);

            if (!hasManualPaymentMethodOverrideRef.current) {
                setData(
                    'payment_method',
                    String(invoice?.payment_method ?? '') ||
                        resolvedDefaultPaymentMethod,
                );
            }
        } catch (err) {
            console.error('Failed to fetch customer details:', err);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (isEditMode && data.id) {
            put(updateReceipt(data.id).url);

            return;
        }

        post(storeReceipt().url);
    };

    return (
        <div
            className="mx-auto max-h-[90vh] w-full overflow-y-auto p-2"
            style={{
                scrollbarWidth: 'none',
                msOverflowStyle: 'none',
            }}
        >
            <form onSubmit={handleSubmit} className="space-y-4">
                <div className="grid items-start gap-4 md:grid-cols-2">
                    {/* Invoice */}
                    <FormField
                        label="Invoice"
                        fieldRequirementsProps={{
                            required: true,
                            hint: 'Choose the invoice that this receipt applies to.',
                        }}
                        error={errors.invoice_id}
                    >
                        {isEditMode || !!invoiceId ? (
                            <ProperInput
                                value={
                                    selectedInvoice?.invoice_number ??
                                    invoiceOptions.find(
                                        (option) =>
                                            Number(option.value) ===
                                            Number(data.invoice_id ?? 0),
                                    )?.label ??
                                    ''
                                }
                                onCommit={() => {}}
                                disabled
                            />
                        ) : (
                            <Select
                                value={
                                    data.invoice_id
                                        ? String(data.invoice_id)
                                        : ''
                                }
                                onValueChange={(v) => {
                                    const id = Number(v);
                                    getInvoiceDetail(id);
                                    setData('invoice_id', id);
                                }}
                                disabled={isEditMode || !!invoiceId}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select invoice" />
                                </SelectTrigger>
                                <SelectContent>
                                    {invoiceOptions.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={String(option.value)}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </FormField>

                    {/* Amount */}
                    <FormField
                        label="Amount"
                        fieldRequirementsProps={{
                            required: true,
                            hint: 'Automatically follows the selected invoice amount.',
                        }}
                        error={errors.amount}
                    >
                        <ProperInput
                            value={data.amount || selectedInvoice?.amount || ''}
                            onCommit={() => {}}
                            disabled
                        />
                    </FormField>

                    {/* Receipt Date */}
                    <ModelNumberInput
                        modelKey="receipt"
                        label="Receipt Number"
                        value={data.receipt_number ?? ''}
                        formatId={data.number_format_id ?? null}
                        onValueChange={(nextValue) =>
                            setData('receipt_number', nextValue)
                        }
                        onFormatIdChange={(nextFormatId) =>
                            setData('number_format_id', nextFormatId)
                        }
                        disabled={processing}
                        error={modelNumberError}
                        hint="Select a format to auto-generate receipt number."
                    />

                    <FormField
                        label="Receipt Date"
                        fieldRequirementsProps={{
                            required: true,
                            hint: 'The actual payment date recorded for this receipt.',
                        }}
                        error={errors.receipt_date}
                    >
                        <DatePickerField
                            id="receipt_date"
                            value={data.receipt_date ?? ''}
                            onChange={(v) => setData('receipt_date', v)}
                            disabled={processing}
                        />
                    </FormField>

                    {/* Payment Method */}
                    <FormField
                        label="Payment Method"
                        fieldRequirementsProps={{
                            required: true,
                            hint: 'Default comes from active payment method master and can be changed.',
                        }}
                        error={errors.payment_method}
                    >
                        <PaymentMethodMasterCombobox
                            options={visiblePaymentMethods}
                            triggerId="payment_method"
                            value={String(data.payment_method ?? '')}
                            placeholder="Select payment method"
                            searchPlaceholder="Search payment method"
                            onChange={(nextValue) => {
                                hasManualPaymentMethodOverrideRef.current = true;
                                setData('payment_method', nextValue);
                            }}
                        />
                    </FormField>

                    {/* Refund To */}
                    {isRefund && (
                        <FormField
                            label="Refund To"
                            fieldRequirementsProps={{
                                required: false,
                                hint: 'Recipient contact details or account info for the refund receipt (defaults to customer contact number).',
                                example: '08123456789',
                                format: 'Up to 255 characters',
                            }}
                            error={errors.refund_to}
                        >
                            <Input
                                value={data.refund_to ?? ''}
                                placeholder="Refund to contact/info"
                                onChange={(e) =>
                                    setData('refund_to', e.target.value)
                                }
                                disabled={processing}
                            />
                        </FormField>
                    )}

                    {/* Reference */}
                    <FormField
                        label="Reference"
                        fieldRequirementsProps={{
                            hint: 'Optional bank reference, transaction ID, or note.',
                        }}
                        error={errors.reference}
                    >
                        <Input
                            value={data.reference ?? ''}
                            placeholder="Enter bank transfer reference / transaction ID"
                            onChange={(e) =>
                                setData('reference', e.target.value)
                            }
                        />
                    </FormField>

                    {/* Description */}
                    <FormField
                        label="Remarks"
                        fieldRequirementsProps={{
                            hint: 'Optional internal remarks for this receipt.',
                        }}
                        error={errors.description}
                    >
                        <Textarea
                            rows={4}
                            placeholder="Add internal remarks (optional)"
                            value={data.description ?? ''}
                            onChange={(e) =>
                                setData('description', e.target.value)
                            }
                        />
                    </FormField>
                </div>

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
                    <Button type="submit" disabled={processing}>
                        {processing
                            ? 'Saving...'
                            : isEditMode
                              ? 'Update'
                              : 'Create'}
                    </Button>
                </div>
            </form>
        </div>
    );
}
