import { DatePickerField } from '@/components/date-picker';
import { FormField } from '@/components/form-field';
import PaymentMethodMasterCombobox from '@/components/payment-method-master-combobox';
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
import { useState } from 'react';
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

    const resolvedDefaultPaymentMethod =
        defaultPaymentMethod ?? String(paymentMethods[0]?.value ?? '');

    const initialFormState: ReceiptSchema = {
        receipt_number: '',
        number_format_id: null,
        invoice_id: invoiceId ? Number(invoiceId) : undefined,
        amount: selectedInvoice?.amount,
        receipt_date: formatDateForDisplay(new Date()),
        payment_method: resolvedDefaultPaymentMethod,
        reference: '',
        description: '',
    };

    const defaultData: ReceiptSchema = {
        ...(initialData ?? initialFormState),
    };

    const { data, setData, post, put, processing, errors } =
        useForm<ReceiptSchema>(defaultData);

    // invoice
    const getInvoiceDetail = async (id: number) => {
        try {
            const response = await fetch(getForShow(id).url);
            if (!response) throw new Error('Network error');
            const invoice = await response.json();
            setSelectedInvoice(invoice);
            setData('amount', invoice?.amount);
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
                        <Select
                            value={
                                data.invoice_id ? String(data.invoice_id) : ''
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
                        <Input
                            type="number"
                            step="any"
                            value={data.amount || selectedInvoice?.amount || ''}
                            onChange={(e) => setData('amount', e.target.value)}
                            disabled
                        />
                    </FormField>

                    {/* Receipt Date */}
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
                            options={paymentMethods}
                            triggerId="payment_method"
                            value={String(data.payment_method ?? '')}
                            placeholder="Select payment method"
                            searchPlaceholder="Search payment method"
                            onChange={(nextValue) =>
                                setData('payment_method', nextValue)
                            }
                        />
                    </FormField>

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
