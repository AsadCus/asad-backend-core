import { DatePickerField } from '@/components/date-picker';
import { FieldRequirements } from '@/components/field-requirements';
import { ProperInput } from '@/components/proper-input';
import { Label } from '@/components/ui/label';
import { isBeforeToday, parseDisplayDate } from '@/lib/utils';
import { InvoiceSchema } from '@/pages/invoices/schema';
import { isBefore } from 'date-fns';

export function InvoiceHeader({
    invoice,
    onChange,
    renderError,
    disabled,
}: {
    invoice: InvoiceSchema;
    onChange: (patch: Partial<InvoiceSchema>) => void;
    renderError: (path: string) => React.ReactNode;
    disabled: boolean;
}) {
    return (
        <div className="grid grid-cols-1 items-start gap-3 lg:grid-cols-4">
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
                        hint="Must be at least today date"
                        format="DD/MM/YYYY"
                    />
                </Label>
                <div className="relative">
                    <DatePickerField
                        id="invoice_date"
                        value={invoice.invoice_date ?? ''}
                        disabled={disabled}
                        disabledDates={isBeforeToday}
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
                        hint="Must be at least today date & after invoice date"
                        format="DD/MM/YYYY"
                    />
                </Label>
                <div className="relative">
                    <DatePickerField
                        id="due_date"
                        value={invoice.due_date ?? ''}
                        disabled={disabled}
                        disabledDates={(date) => {
                            const today = new Date();
                            today.setHours(0, 0, 0, 0);

                            const quoteDate = invoice.invoice_date
                                ? parseDisplayDate(invoice.invoice_date)
                                : null;

                            if (isBefore(date, today)) return true;
                            if (quoteDate && isBefore(date, quoteDate))
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
