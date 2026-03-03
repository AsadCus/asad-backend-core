import { FormField } from '@/components/form-field';
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
import { QuotationSchema, SetDataFn } from '../schema';

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
    noteErrors?: string[];
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
    noteErrors = [],
    status,
}: Props) {
    const sharingPlanCosts = [
        {
            key: 'single',
            label: 'Single',
            value: Number(data.package_price_single ?? 0),
        },
        {
            key: 'double',
            label: 'Double',
            value: Number(data.package_price_double ?? 0),
        },
        {
            key: 'triple',
            label: 'Triple',
            value: Number(data.package_price_triple ?? 0),
        },
        {
            key: 'quad',
            label: 'Quad',
            value: Number(data.package_price_quad ?? 0),
        },
    ];

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
                        <FormField
                            label="Description"
                            fieldRequirementsProps={{
                                required: true,
                                hint: 'Enter quotation description',
                            }}
                        >
                            <ProperInput
                                value={data.description ?? ''}
                                textarea={true}
                                placeholder="Input description"
                                disabled={isView}
                                onCommit={(v) => setData('description', v)}
                            />
                            {renderError('description')}
                        </FormField>
                    </section>

                    <section className="order-2 grid grid-cols-1 items-start gap-4 md:col-span-1 lg:order-2">
                        {/* Payment Plan */}
                        <FormField
                            label="Payment Plan"
                            fieldRequirementsProps={{
                                required: true,
                                hint: 'Select the payment plan',
                            }}
                        >
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
                        </FormField>

                        {/* Payment Method */}
                        <FormField
                            label="Payment Method"
                            fieldRequirementsProps={{
                                required: true,
                                hint: 'Select payment method',
                            }}
                        >
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
                        </FormField>

                        {data.package_name && (
                            <div className="grid w-full items-center gap-3 rounded-md border p-3">
                                <Label>Package & Sharing Plan Costs</Label>
                                <div className="space-y-1 text-sm">
                                    <div className="flex items-center justify-between gap-3 border-b pb-2 font-medium">
                                        <span className="text-muted-foreground">
                                            Package
                                        </span>
                                        <span>{data.package_name}</span>
                                    </div>
                                    {sharingPlanCosts.map((row) => (
                                        <div
                                            key={row.key}
                                            className="flex items-center justify-between gap-3"
                                        >
                                            <span className="text-muted-foreground">
                                                {row.label}
                                            </span>
                                            <span>${row.value.toFixed(2)}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
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
                    {noteErrors.length > 0 && (
                        <div className="mt-2 space-y-1">
                            {noteErrors.map((error, index) => (
                                <p
                                    key={`${error}-${index}`}
                                    className="text-sm text-red-500"
                                >
                                    {error}
                                </p>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </FormSection>
    );
}
