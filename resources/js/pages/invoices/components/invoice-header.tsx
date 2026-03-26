import { DatePickerField } from '@/components/date-picker';
import { FieldRequirements } from '@/components/field-requirements';
import ModelNumberInput from '@/components/model-number-input';
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
}: {
    invoice: InvoiceSchema;
    onChange: (patch: Partial<InvoiceSchema>) => void;
    renderError: (path: string) => React.ReactNode;
    disabled: boolean;
    isView?: boolean;
}) {
    return (
        <div className="grid grid-cols-1 items-start gap-3 lg:grid-cols-4">
            {!isView && (
                <div className="lg:col-span-2">
                    <ModelNumberInput
                        modelKey="invoice"
                        label="Invoice Number"
                        value={invoice.invoice_number ?? ''}
                        formatId={invoice.number_format_id ?? null}
                        onValueChange={(value) =>
                            onChange({ invoice_number: value })
                        }
                        onFormatIdChange={(formatId) =>
                            onChange({ number_format_id: formatId })
                        }
                        disabled={disabled}
                        hint="Choose a format, then adjust number if needed."
                    />
                    {renderError('invoice_number')}
                    {renderError('number_format_id')}
                </div>
            )}

            {isView && invoice.invoice_number && (
                <div className="grid w-full items-center gap-2 lg:col-span-2">
                    <Label>Invoice Number</Label>
                    <div className="rounded-md border bg-muted/20 px-3 py-2 font-mono text-sm">
                        {invoice.invoice_number}
                    </div>
                </div>
            )}

            {/* Description */}
            <div className="grid w-full items-center gap-3 lg:col-span-2">
                <Label htmlFor="description">
                    Invoice Name{' '}
                    <FieldRequirements
                        required
                        hint="Invoice name/description"
                        example="Deposit, Handover, etc"
                    />
                </Label>
                <div className="relative">
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
                </div>
            </div>

                {/* Invoice Date */}
                <div className="grid w-full items-center gap-3 lg:col-span-1">
                    <Label htmlFor="invoice_date">
                        Invoice Date{' '}
                        <FieldRequirements
                            required
                            hint="Default from quote date"
                            format="DD/MM/YYYY"
                        />
                    </Label>
                    <div className="relative">
                        <DatePickerField
                            id="invoice_date"
                            value={invoice.invoice_date ?? ''}
                            disabled={disabled}
                            quickDate
                            onChange={(e) => onChange({ invoice_date: e })}
                        />
                        {renderError('invoice_date')}
                    </div>
                </div>

                {/* Due Date */}
                <div className="grid w-full items-center gap-3 lg:col-span-1">
                    <Label htmlFor="due_date">
                        Due Date{' '}
                        <FieldRequirements
                            required
                            hint="Must be same or after invoice date"
                            format="DD/MM/YYYY"
                        />
                    </Label>
                    <div className="relative">
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
                    </div>
                </div>
            </div>
    );
}
