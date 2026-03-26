import { DatePickerField } from '@/components/date-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { paymentMethods } from '../quotations/schema';
import { ReceiptSchema } from './schema';

interface ReceiptFormProps {
    mode: 'create' | 'edit';
    initialData?: ReceiptSchema;
    invoiceId?: number | undefined;
    invoiceData?: InvoiceSchema;
    invoiceOptions: OptionType[];
    onCancel?: () => void;
}

export default function ReceiptForm({
    mode,
    initialData,
    invoiceId,
    invoiceData,
    invoiceOptions,
    onCancel,
}: ReceiptFormProps) {
    const isEditMode = mode === 'edit';

    const [selectedInvoice, setSelectedInvoice] =
        useState<InvoiceSchema | null>(invoiceData ?? null);

    const initialFormState: ReceiptSchema = {
        receipt_number: '',
        number_format_id: null,
        invoice_id: invoiceId ? Number(invoiceId) : undefined,
        amount: selectedInvoice?.amount,
        receipt_date: formatDateForDisplay(new Date()),
        payment_method: selectedInvoice?.payment_method ?? 'transfer',
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

    // err
    const renderError = (path: string) => {
        const errorMap = errors as Record<string, string | undefined>;
        const message = errorMap[path];

        if (!message) return null;

        return <p className="mt-1 text-sm text-red-500">{message}</p>;
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
                    <div className="grid w-full items-center gap-3">
                        <Label>
                            Invoice <span className="text-red-500">*</span>
                        </Label>
                        <div className="relative">
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
                            {renderError('invoice_id')}
                        </div>
                    </div>

                    {/* Amount */}
                    <div className="grid w-full items-center gap-3">
                        <Label>
                            Amount <span className="text-red-500">*</span>
                        </Label>
                        <div className="relative">
                            <Input
                                type="number"
                                step="any"
                                value={
                                    data.amount || selectedInvoice?.amount || ''
                                }
                                onChange={(e) =>
                                    setData('amount', e.target.value)
                                }
                                disabled
                            />
                            {renderError('amount')}
                        </div>
                    </div>

                    {/* Receipt Date */}
                    <div className="grid w-full items-center gap-3">
                        <Label>Receipt Date *</Label>
                        <div className="relative">
                            <DatePickerField
                                id="receipt_date"
                                value={data.receipt_date ?? ''}
                                onChange={(v) => setData('receipt_date', v)}
                                disabled={processing}
                            />
                            {renderError('receipt_date')}
                        </div>
                    </div>

                    {/* Payment Method */}
                    <div className="grid w-full items-center gap-3">
                        <Label>Payment Method</Label>
                        <div className="relative">
                            <Select
                                value={data.payment_method ?? 'transfer'}
                                onValueChange={(v) =>
                                    setData('payment_method', v)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {paymentMethods.map((m) => (
                                        <SelectItem
                                            key={m.value}
                                            value={String(m.value)}
                                        >
                                            {m.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {renderError('payment_method')}
                        </div>
                    </div>

                    {/* Reference */}
                    <div className="grid w-full items-center gap-3">
                        <Label>Reference</Label>
                        <div className="relative">
                            <Input
                                value={data.reference ?? ''}
                                onChange={(e) =>
                                    setData('reference', e.target.value)
                                }
                            />
                            {renderError('reference')}
                        </div>
                    </div>

                    {/* Description */}
                    <div className="grid w-full items-center gap-3">
                        <Label>Remarks</Label>
                        <div className="relative">
                            <Textarea
                                rows={4}
                                value={data.description ?? ''}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                            />
                            {renderError('description')}
                        </div>
                    </div>
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
