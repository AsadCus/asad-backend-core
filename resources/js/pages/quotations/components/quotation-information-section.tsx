import { Combobox } from '@/components/combobox';
import { DatePickerField } from '@/components/date-picker';
import { FormField } from '@/components/form-field';
import { FormSection } from '@/components/form-section';
import ModelNumberInput from '@/components/model-number-input';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { formatCurrency, parseDisplayDate } from '@/lib/utils';
import {
    sharingPlanBadgeColors,
    sharingPlanLabels,
} from '@/pages/packages/schema';
import { OptionType } from '@/types';
import { isBefore } from 'date-fns';
import React, { useMemo } from 'react';
import { ProperInput } from '../../../components/proper-input';
import { QuotationSchema, SetDataFn } from '../schema';

interface Props {
    data: QuotationSchema;
    isView?: boolean;
    disableCustomerConfirmation?: boolean;
    setData: SetDataFn;
    grandTotalAmount?: number;
    renderError: (field: keyof QuotationSchema) => React.ReactNode;
    customerConfirmations?: OptionType[];
    availableMembers?: Array<{
        member_id: number;
        name: string;
        sharing_plan: string | null;
        is_leader?: boolean;
    }>;
    selectedMemberIds?: number[];
    customerOptions?: OptionType[];
    selectedCustomerValue?: number | string | null;
    onCustomerConfirmationChange?: (value: string | number) => void;
    onSelectedMembersChange?: (memberIds: number[]) => void;
    onCustomerChange?: (value: string | number) => void;
    quotationNumberError?: string;
    status: 'incomplete' | 'complete' | 'error';
}

export default function QuotationInformationSection({
    data,
    isView = false,
    disableCustomerConfirmation = false,
    setData,
    grandTotalAmount = 0,
    renderError,
    customerConfirmations = [],
    availableMembers = [],
    selectedMemberIds = [],
    customerOptions = [],
    selectedCustomerValue = null,
    onCustomerConfirmationChange,
    onSelectedMembersChange,
    onCustomerChange,
    quotationNumberError,
    status,
}: Props) {
    const addressLines = useMemo(
        () =>
            data.customer_address ? data.customer_address.split('<br>') : [],
        [data.customer_address],
    );

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
                    <FormField
                        label="Customer Confirmation"
                        fieldRequirementsProps={{
                            required: false,
                            hint: 'Optional: select a customer confirmation record for this quotation',
                        }}
                    >
                        <Combobox
                            disabled={isView || disableCustomerConfirmation}
                            value={data.customer_confirmation_id ?? ''}
                            onChange={(value) => {
                                const nextValue = Number(value);
                                setData(
                                    'customer_confirmation_id',
                                    Number.isNaN(nextValue) ? null : nextValue,
                                );
                                onCustomerConfirmationChange?.(value);
                            }}
                            options={customerConfirmations}
                            placeholder="Select customer confirmation"
                        />
                        {renderError('customer_confirmation_id')}
                    </FormField>

                    {availableMembers.length > 0 && (
                        <FormField label="Members In This Quotation">
                            <div className="space-y-2 rounded-md border p-3">
                                {availableMembers.map((member) => {
                                    const checked = selectedMemberIds.includes(
                                        member.member_id,
                                    );

                                    return (
                                        <label
                                            key={member.member_id}
                                            htmlFor={`member-${member.member_id}`}
                                            className="flex cursor-pointer items-center gap-3 rounded hover:bg-gray-50 dark:hover:bg-gray-900"
                                        >
                                            <Checkbox
                                                id={`member-${member.member_id}`}
                                                disabled={isView}
                                                checked={checked}
                                                onCheckedChange={(
                                                    isChecked,
                                                ) => {
                                                    const nextMemberIds =
                                                        isChecked
                                                            ? [
                                                                  ...selectedMemberIds,
                                                                  member.member_id,
                                                              ]
                                                            : selectedMemberIds.filter(
                                                                  (id) =>
                                                                      id !==
                                                                      member.member_id,
                                                              );

                                                    onSelectedMembersChange?.(
                                                        nextMemberIds,
                                                    );
                                                }}
                                            />
                                            <div className="flex flex-1 items-center gap-2">
                                                <span className="text-base">
                                                    {member.name}
                                                </span>
                                                {member.sharing_plan && (
                                                    <Badge
                                                        className={
                                                            sharingPlanBadgeColors[
                                                                member
                                                                    .sharing_plan
                                                            ] ||
                                                            'bg-gray-100 text-gray-800'
                                                        }
                                                    >
                                                        {sharingPlanLabels[
                                                            member.sharing_plan
                                                        ] ||
                                                            member.sharing_plan}
                                                    </Badge>
                                                )}
                                                {member.is_leader && (
                                                    <Badge className="bg-amber-100 text-amber-800">
                                                        Main
                                                    </Badge>
                                                )}
                                            </div>
                                        </label>
                                    );
                                })}
                            </div>
                        </FormField>
                    )}

                    <FormField
                        label="Customer"
                        htmlFor="customer_id"
                        fieldRequirementsProps={{
                            required: true,
                            hint: 'Select one customer as the quotation owner',
                        }}
                    >
                        <div id="customer_id" tabIndex={-1}>
                            <Combobox
                                disabled={
                                    isView || customerOptions.length === 0
                                }
                                value={selectedCustomerValue ?? ''}
                                onChange={(value) => onCustomerChange?.(value)}
                                options={customerOptions}
                                placeholder={
                                    data.customer_confirmation_id
                                        ? 'Select customer from selected members'
                                        : 'Select customer'
                                }
                            />
                        </div>
                        {renderError('customer_id')}

                        {data.customer_name && (
                            <div className="mt-3 rounded-md border border-gray-300 p-3">
                                <div className="flex flex-col gap-1">
                                    <span className="text-base font-semibold text-gray-800">
                                        {data.customer_name}
                                    </span>
                                    <div className="text-base text-gray-600">
                                        {addressLines.length > 0
                                            ? addressLines.map(
                                                  (line, idx, arr) => (
                                                      <div key={idx}>
                                                          {line}
                                                          {idx <
                                                              arr.length -
                                                                  1 && <br />}
                                                      </div>
                                                  ),
                                              )
                                            : '-'}
                                    </div>
                                    <span className="text-base text-gray-600">
                                        {data.customer_contact ?? '-'}
                                    </span>
                                    <span className="text-base text-gray-600">
                                        {data.customer_email ?? '-'}
                                    </span>
                                </div>
                            </div>
                        )}
                    </FormField>
                </section>

                <section className="order-2 grid grid-cols-1 items-start gap-4 md:col-span-2 lg:order-2">
                    {!isView && (
                        <div className="md:ml-auto md:w-72">
                            <ModelNumberInput
                                modelKey="quotation"
                                label="Quotation Number"
                                value={data.quotation_number ?? ''}
                                formatId={data.number_format_id ?? null}
                                onValueChange={(value) =>
                                    setData('quotation_number', value)
                                }
                                onFormatIdChange={(formatId) =>
                                    setData('number_format_id', formatId)
                                }
                                error={quotationNumberError}
                                hint="Select a format from the number input to auto-generate the quotation number."
                            />
                        </div>
                    )}

                    {/* Quote Date */}
                    <div className="md:ml-auto md:w-72">
                        <FormField
                            label="Quote Date"
                            htmlFor="quotation_date"
                            fieldRequirementsProps={{
                                required: true,
                                hint: 'Default from current date',
                                format: 'DD/MM/YYYY',
                            }}
                        >
                            <DatePickerField
                                id="quotation_date"
                                value={data.quotation_date ?? ''}
                                disabled={isView}
                                quickDate
                                onChange={(val) =>
                                    setData('quotation_date', val)
                                }
                            />
                            {renderError('quotation_date')}
                        </FormField>
                    </div>

                    {/* Expiry Date */}
                    <div className="md:ml-auto md:w-72">
                        <FormField
                            label="Valid Until"
                            htmlFor="expiry_date"
                            fieldRequirementsProps={{
                                required: true,
                                hint: 'Must be same or after quote date',
                                format: 'DD/MM/YYYY',
                            }}
                        >
                            <DatePickerField
                                id="expiry_date"
                                value={data.expiry_date ?? ''}
                                disabled={isView}
                                disabledDates={(date) => {
                                    const quoteDate = data.quotation_date
                                        ? parseDisplayDate(data.quotation_date)
                                        : null;

                                    if (quoteDate && isBefore(date, quoteDate))
                                        return true;

                                    return false;
                                }}
                                quickDate
                                onChange={(val) => setData('expiry_date', val)}
                            />
                            {renderError('expiry_date')}
                        </FormField>
                    </div>

                    <div className="md:ml-auto md:w-72">
                        <FormField label="Grand Total" htmlFor="grand_total">
                            <ProperInput
                                id="grand_total"
                                value={formatCurrency(
                                    Number(grandTotalAmount ?? 0),
                                )}
                                disabled
                                onCommit={() => {}}
                            />
                        </FormField>
                    </div>
                </section>
            </div>
        </FormSection>
    );
}
