import { Combobox } from '@/components/combobox';
import { DatePickerField } from '@/components/date-picker';
import { FieldRequirements } from '@/components/field-requirements';
import { FormSection } from '@/components/form-section';
import { Label } from '@/components/ui/label';
import { isBeforeToday, parseDisplayDate } from '@/lib/utils';
import { OptionType } from '@/types';
import { isBefore } from 'date-fns';
import { User } from 'lucide-react';
import React, { useState } from 'react';
import { QuotationSchema, SetDataFn } from '../schema';

interface Props {
    data: QuotationSchema;
    isView?: boolean;
    setData: SetDataFn;
    renderError: (field: keyof QuotationSchema) => React.ReactNode;
    customers?: OptionType[];
    getCustomerDetail?: (id: number) => void;
    status: 'incomplete' | 'complete' | 'error';
}

export default function QuotationInformationSection({
    data,
    isView = false,
    setData,
    renderError,
    customers = [],
    getCustomerDetail,
    status,
}: Props) {
    const [open, setOpen] = useState(false);

    return (
        <FormSection
            value="customer_and_quotation_information"
            title="Customer"
            description="Customer/Employer Information and Quotation Dates"
            status={status}
            required
        >
            <div
                id="section-quotation-information"
                className="grid grid-cols-1 items-start gap-4 pt-2 md:grid-cols-4"
            >
                {/* Customer */}
                <section className="order-1 grid grid-cols-1 items-start gap-4 md:col-span-2 md:grid-cols-1 lg:order-1">
                    <div
                        className={`relative w-full cursor-pointer rounded-md border border-gray-300 p-4 shadow-sm focus-within:ring-1 focus-within:ring-primary hover:border-gray-400 ${isView ? 'cursor-default opacity-70' : ''} `}
                        onClick={() => !isView && setOpen((prev) => !prev)}
                    >
                        {!data.customer_id ? (
                            <div className="flex flex-col items-center justify-center gap-2 text-gray-400">
                                <User size={32} />
                                <span>Select a customer</span>
                            </div>
                        ) : (
                            <div className="flex flex-col gap-1">
                                <span className="text-base font-semibold text-gray-800">
                                    {data.customer_name}
                                </span>
                                <div className="text-base text-gray-600">
                                    {data.customer_address
                                        ? data.customer_address
                                              .split('<br>')
                                              .map((line, idx, arr) => (
                                                  <div key={idx}>
                                                      {line}
                                                      {idx < arr.length - 1 && (
                                                          <br />
                                                      )}
                                                  </div>
                                              ))
                                        : '-'}
                                </div>
                                <span className="text-base text-gray-600">
                                    {data.customer_contact ?? '-'}
                                </span>
                                <span className="text-base text-gray-600">
                                    {data.customer_email ?? '-'}
                                </span>
                            </div>
                        )}

                        {/* Combobox overlay */}
                        {!isView && open && (
                            <div
                                className="relative mt-2 rounded-lg shadow-sm"
                                onClick={(e) => e.stopPropagation()}
                            >
                                <Combobox
                                    disabled={isView}
                                    value={data.customer_id ?? ''}
                                    onChange={(v) => {
                                        const id = Number(v);
                                        getCustomerDetail?.(id);
                                        setData('customer_id', id);
                                        setOpen(false);
                                    }}
                                    options={customers}
                                    placeholder="Search customer..."
                                />
                            </div>
                        )}
                    </div>
                </section>

                <section className="order-2 grid grid-cols-1 items-start gap-4 md:col-span-2 lg:order-2">
                    {/* Quote Date */}
                    <div className="grid w-full items-center gap-3 md:flex md:justify-end">
                        <Label className="text-nowrap" htmlFor="quotation_date">
                            Quote Date
                            <FieldRequirements
                                required
                                hint="Must be at least today date"
                                format="DD/MM/YYYY"
                            />
                        </Label>
                        <div className="relative w-full md:w-72">
                            <DatePickerField
                                id="quotation_date"
                                value={data.quotation_date ?? ''}
                                disabled={isView}
                                disabledDates={isBeforeToday}
                                quickDate
                                onChange={(val) =>
                                    setData('quotation_date', val)
                                }
                            />
                            {renderError('quotation_date')}
                        </div>
                    </div>

                    {/* Expiry Date */}
                    <div className="grid w-full items-center gap-3 md:flex md:justify-end">
                        <Label className="text-nowrap" htmlFor="expiry_date">
                            Valid Until
                            <FieldRequirements
                                required
                                hint="Must be at least today date & after quote date"
                                format="DD/MM/YYYY"
                            />
                        </Label>
                        <div className="relative w-full md:w-72">
                            <DatePickerField
                                id="expiry_date"
                                value={data.expiry_date ?? ''}
                                disabled={isView}
                                disabledDates={(date) => {
                                    const today = new Date();
                                    today.setHours(0, 0, 0, 0);

                                    const quoteDate = data.quotation_date
                                        ? parseDisplayDate(data.quotation_date)
                                        : null;

                                    if (isBefore(date, today)) return true;
                                    if (quoteDate && isBefore(date, quoteDate))
                                        return true;

                                    return false;
                                }}
                                quickDate
                                onChange={(val) => setData('expiry_date', val)}
                            />
                            {renderError('expiry_date')}
                        </div>
                    </div>

                </section>
            </div>
        </FormSection>
    );
}
