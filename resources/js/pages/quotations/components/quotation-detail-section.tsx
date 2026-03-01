import { FieldRequirements } from '@/components/field-requirements';
import { FormSection } from '@/components/form-section';
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
import {
    QuotationSchema,
    SetDataFn,
    depositTypes,
} from '../schema';

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
    return (
        <FormSection
            value="maid_and_quotation_details"
            title="Quotation Details"
            description="Quotation and payment details"
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
                                    hint="Enter quotation description"
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

                        {/* Deposit Config (shown for installment) */}
                        {data.payment_plan === 'installment' && (
                            <>
                                <div className="grid w-full items-center gap-3">
                                    <Label>
                                        Deposit Type
                                        <FieldRequirements
                                            required
                                            hint="Choose percentage or fixed amount"
                                        />
                                    </Label>
                                    <div className="relative">
                                        <Select
                                            disabled={isView}
                                            value={String(
                                                data.deposit_type ?? '',
                                            )}
                                            onValueChange={(value) =>
                                                setData('deposit_type', value)
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select type" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {depositTypes.map((dt) => (
                                                    <SelectItem
                                                        key={dt.value}
                                                        value={dt.value}
                                                    >
                                                        {dt.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {renderError('deposit_type')}
                                    </div>
                                </div>

                                <div className="grid w-full items-center gap-3">
                                    <Label htmlFor="deposit_value">
                                        Deposit Value
                                        <FieldRequirements
                                            required
                                            hint={
                                                data.deposit_type ===
                                                'percentage'
                                                    ? 'Enter percentage (1-100)'
                                                    : 'Enter deposit amount'
                                            }
                                            example={
                                                data.deposit_type ===
                                                'percentage'
                                                    ? '30, 50'
                                                    : '500, 1000'
                                            }
                                        />
                                    </Label>
                                    <div className="relative">
                                        <ProperInput
                                            id="deposit_value"
                                            value={data.deposit_value ?? ''}
                                            type="number"
                                            inputProps={{
                                                step: 'any',
                                                min: '0',
                                                ...(data.deposit_type ===
                                                'percentage'
                                                    ? { max: '100' }
                                                    : {}),
                                            }}
                                            placeholder={
                                                data.deposit_type ===
                                                'percentage'
                                                    ? 'Enter %'
                                                    : 'Enter amount'
                                            }
                                            disabled={isView}
                                            onCommit={(v) =>
                                                setData('deposit_value', v)
                                            }
                                        />
                                        {renderError('deposit_value')}
                                    </div>
                                </div>
                            </>
                        )}

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
