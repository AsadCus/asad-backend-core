import { FieldRequirements } from '@/components/field-requirements';
import { FormSection } from '@/components/form-section';
import { MultiSelect } from '@/components/multi-select';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import NoteForm from '@/pages/notes/form';
import { NoteSchema } from '@/pages/notes/schema';
import { OptionType } from '@/types';
import React from 'react';
import { ProperInput } from '../../../components/proper-input';
import QuotationItemTableForm from '../items/form';
import { QuotationItemSchema } from '../items/schema';
import { QuotationSchema, SetDataFn, daysOfWeek } from '../schema';

interface Props {
    data: QuotationSchema;
    isView?: boolean;
    setData: SetDataFn;
    items: QuotationItemSchema[];
    onChange: (items: QuotationItemSchema[]) => void;
    renderError: (path: string) => React.ReactNode;
    paymentPlans?: OptionType[];
    paymentMethods?: OptionType[];
    quotationNotes?: NoteSchema[];
    status: 'incomplete' | 'complete' | 'error';
}

export default function QuotationDetailSection({
    data,
    isView = false,
    setData,
    items,
    onChange,
    renderError,
    paymentPlans = [],
    paymentMethods = [],
    quotationNotes = [],
    status,
}: Props) {
    const handleLoanDuration = (value: string) => {
        setData('loan_duration', value);
        if (value && data.monthly_salary) {
            const loan = parseFloat(value);
            const salary = parseFloat(String(data.monthly_salary));
            if (!isNaN(loan) && !isNaN(salary)) {
                setData('cost_of_maid', String(loan * salary));
            }
        } else {
            setData('cost_of_maid', '');
        }
    };

    const handleMonthlySalaryChange = (value: string) => {
        setData('monthly_salary', value);

        if (value && data.loan_duration) {
            const salary = parseFloat(value);
            const loan = parseFloat(String(data.loan_duration));
            if (!isNaN(salary) && !isNaN(loan)) {
                setData('cost_of_maid', String(loan * salary));
            }
        } else {
            setData('cost_of_maid', '');
        }

        if (value) {
            const salary = parseFloat(value);
            if (!isNaN(salary) && salary > 0) {
                const compensation = salary / 20;
                setData('compensation_off_in_lieu', compensation);
            }
        } else {
            setData('compensation_off_in_lieu', '');
        }
    };

    return (
        <FormSection
            value="maid_and_quotation_details"
            title="Maid & Quotation Details"
            description="Selected Maid & Quotation Details"
            status={status}
            required
        >
            <div className="space-y-6">
                <div
                    id="section-maid-assignment"
                    className="grid grid-cols-1 items-start gap-4 pt-2 md:grid-cols-3"
                >
                    <section className="order-1 grid grid-cols-1 items-start gap-4 md:col-span-2 md:grid-cols-1 lg:order-1">
                        {/* Description */}
                        <div className="grid w-full items-center gap-3">
                            <Label>
                                Description{' '}
                                <FieldRequirements
                                    required
                                    hint="Enter maid description"
                                />
                            </Label>
                            <div className="relative">
                                <ProperInput
                                    value={data.description ?? ''}
                                    textarea={true}
                                    placeholder="Input description"
                                    disabled={isView}
                                    onCommit={(v) => setData('description', v)}
                                />
                                {renderError('description')}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-2">
                            {/* Monthly Salary */}
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="monthly_salary">
                                    Monthly Salary{' '}
                                    <FieldRequirements
                                        required
                                        hint="Monthly salary amount for the maid"
                                        format="Numeric amount"
                                        example="600, 800, 1500.5"
                                    />
                                </Label>
                                <div className="relative">
                                    <ProperInput
                                        id="monthly_salary"
                                        value={data.monthly_salary ?? ''}
                                        type="number"
                                        inputProps={{ step: 'any', min: '0' }}
                                        placeholder="Enter amount"
                                        disabled={isView}
                                        onCommit={(v) =>
                                            handleMonthlySalaryChange(v)
                                        }
                                    />
                                    {renderError('monthly_salary')}
                                </div>
                            </div>

                            {/* Loan Duration */}
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="loan_duration">
                                    Loan Duration (Months){' '}
                                    <FieldRequirements
                                        required
                                        hint="Number of months remaining to pay off the maid's loan"
                                        format="Decimal number"
                                        example="6, 12.5, 18"
                                    />
                                </Label>
                                <div className="relative">
                                    <ProperInput
                                        id="loan_duration"
                                        value={data.loan_duration ?? ''}
                                        type="number"
                                        inputProps={{ step: 'any', min: '0' }}
                                        placeholder="Enter months"
                                        disabled={isView}
                                        onCommit={(v) => handleLoanDuration(v)}
                                    />
                                    {renderError('loan_duration')}
                                </div>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-2">
                            {/* Rest Days Per Month */}
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="rest_days_per_month">
                                    Rest Days per Month
                                    <FieldRequirements
                                        required
                                        hint="Number of rest days preferred per month. (min 0, max 6)"
                                        example="4"
                                    />
                                </Label>
                                <div className="relative">
                                    <ProperInput
                                        id="rest_days_per_month"
                                        value={data.rest_days_per_month ?? ''}
                                        type="number"
                                        inputProps={{
                                            step: 'any',
                                            min: '0',
                                            max: '6',
                                        }}
                                        placeholder="Enter amount"
                                        disabled={isView}
                                        onCommit={(v) =>
                                            setData('rest_days_per_month', v)
                                        }
                                    />
                                    {renderError('rest_days_per_month')}
                                </div>
                            </div>

                            {/* Compensation Off In Lieu */}
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="compensation_off_in_lieu">
                                    Compensation Off in Lieu
                                    <FieldRequirements
                                        required
                                        hint="Amount paid to the maid for each off day worked (e.g. Sunday or public holiday). Auto-calculated as Monthly Salary ÷ 20"
                                        format="Numeric amount"
                                        example="20, 36.5"
                                    />
                                </Label>
                                <div className="relative">
                                    <ProperInput
                                        id="compensation_off_in_lieu"
                                        value={
                                            data.compensation_off_in_lieu ?? ''
                                        }
                                        type="number"
                                        inputProps={{ step: 'any', min: '0' }}
                                        placeholder="Auto-calculated from salary"
                                        disabled={isView}
                                        onCommit={(v) =>
                                            setData(
                                                'compensation_off_in_lieu',
                                                v,
                                            )
                                        }
                                    />
                                    {renderError('compensation_off_in_lieu')}
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="order-2 grid grid-cols-1 items-start gap-4 md:col-span-1 lg:order-2">
                        {/* Payment Plan */}
                        <div className="grid w-full items-center gap-3">
                            <Label>
                                Payment Plan
                                <FieldRequirements
                                    required
                                    hint="Select the payment plan"
                                />
                            </Label>
                            <div className="relative">
                                <Select
                                    disabled={isView}
                                    value={String(data.payment_plan ?? '')}
                                    onValueChange={(value) =>
                                        setData('payment_plan', value)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select plan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {paymentPlans.map((p) => (
                                            <SelectItem
                                                key={p.value}
                                                value={String(p.value)}
                                            >
                                                {p.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {renderError('payment_plan')}
                            </div>
                        </div>

                        {/* Payment Method */}
                        <div className="grid w-full items-center gap-3">
                            <Label>
                                Payment Method
                                <FieldRequirements
                                    required
                                    hint="Select payment method"
                                />
                            </Label>
                            <div className="relative">
                                <Select
                                    disabled={isView}
                                    value={String(data.payment_method ?? '')}
                                    onValueChange={(value) =>
                                        setData('payment_method', value)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select method" />
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

                        {/* Rest Day of the Week */}
                        <div className="grid w-full items-center gap-3">
                            <Label>
                                Rest Day of the Week{' '}
                                <FieldRequirements
                                    required
                                    hint="Rest day preferred"
                                    example="Weekend"
                                />
                            </Label>
                            <div className="relative">
                                <MultiSelect
                                    disabled={isView}
                                    options={daysOfWeek}
                                    placeholder="Select day(s)"
                                    defaultValue={data.rest_day_of_the_week}
                                    onValueChange={(value) => {
                                        return setData(
                                            'rest_day_of_the_week',
                                            value,
                                        );
                                    }}
                                    responsive={true}
                                    minWidth="0px"
                                />
                                {renderError('rest_day_of_the_week')}
                            </div>
                        </div>
                    </section>
                </div>

                <div
                    id="section-quotation-items"
                    className="grid grid-cols-1 gap-4"
                >
                    <hr />
                    <QuotationItemTableForm
                        quotation={data}
                        items={items}
                        onChange={onChange}
                        renderError={renderError}
                        disabled={isView}
                        showOptionalColumn={false}
                        showPlacementFeeColumn={true}
                    />
                </div>

                <div className="mx-auto w-full">
                    <NoteForm
                        mode="quotation"
                        notes={quotationNotes}
                        onChange={(v) => setData('notes', v)}
                        disabled={isView}
                    />
                </div>
            </div>
        </FormSection>
    );
}
