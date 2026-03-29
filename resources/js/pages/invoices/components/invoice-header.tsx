import { DatePickerField } from '@/components/date-picker';
import { FormField } from '@/components/form-field';
import { ProperInput } from '@/components/proper-input';
import { Label } from '@/components/ui/label';
import { parseDisplayDate } from '@/lib/utils';
import { InvoiceSchema } from '@/pages/invoices/schema';
import { isBefore } from 'date-fns';

export function InvoiceHeader({
    invoice,
    onChange,
    renderError,
    disabled,
    isView = false,
    paymentMethodField,
}: {
    invoice: InvoiceSchema;
    onChange: (patch: Partial<InvoiceSchema>) => void;
    renderError: (path: string) => React.ReactNode;
    disabled: boolean;
    isView?: boolean;
    paymentMethodField?: React.ReactNode;
}) {
    return (
        <div className="grid grid-cols-1 items-start gap-3 md:grid-cols-2">
            {isView && invoice.invoice_number && (
                <div className="grid w-full items-center gap-2">
                    <Label>Invoice Number</Label>
                    <div className="rounded-md border bg-muted/20 px-3 py-2 font-mono text-sm">
                        {invoice.invoice_number}
                    </div>
                </div>
            )}

            <section className="grid grid-cols-1 gap-3">
                <FormField
                    label="Invoice Name"
                    htmlFor="description"
                    fieldRequirementsProps={{
                        required: true,
                        hint: 'Invoice name/description',
                        example: 'Deposit, Handover, etc',
                    }}
                >
                    <ProperInput
                        id="description"
                        value={invoice.description ?? ''}
                        textarea
                        disabled={disabled}
                        placeholder="Invoice description"
                        onCommit={(v) => onChange({ description: v })}
                        className="font-semibold"
                    />
                    {renderError('description')}
                </FormField>
            </section>

            <section className="grid grid-cols-1 gap-3 md:grid-cols-2">
                <FormField
                    label="Invoice Date"
                    htmlFor="invoice_date"
                    fieldRequirementsProps={{
                        required: true,
                        hint: 'Default from quote date',
                        format: 'DD/MM/YYYY',
                    }}
                >
                    <DatePickerField
                        id="invoice_date"
                        value={invoice.invoice_date ?? ''}
                        disabled={disabled}
                        quickDate
                        onChange={(e) => onChange({ invoice_date: e })}
                    />
                    {renderError('invoice_date')}
                </FormField>

                <FormField
                    label="Due Date"
                    htmlFor="due_date"
                    fieldRequirementsProps={{
                        required: true,
                        hint: 'Must be same or after invoice date',
                        format: 'DD/MM/YYYY',
                    }}
                >
                    <DatePickerField
                        id="due_date"
                        value={invoice.due_date ?? ''}
                        disabled={disabled}
                        disabledDates={(date) => {
                            const invoiceDate = invoice.invoice_date
                                ? parseDisplayDate(invoice.invoice_date)
                                : null;

                            if (invoiceDate && isBefore(date, invoiceDate))
                                return true;

                            return false;
                        }}
                        quickDate
                        onChange={(e) => onChange({ due_date: e })}
                    />
                    {renderError('due_date')}
                </FormField>

                {paymentMethodField && (
                    <FormField label="Payment Method" className="md:col-span-2">
                        {paymentMethodField}
                        {renderError('payment_method')}
                    </FormField>
                )}
            </section>
        </div>
    );
}
