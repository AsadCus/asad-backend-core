import { FormField } from '@/components/form-field';
import { FormSection } from '@/components/form-section';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn, formatCurrency } from '@/lib/utils';
import NoteForm from '@/pages/notes/form';
import { NoteSchema } from '@/pages/notes/schema';
import { OptionType } from '@/types';
import { Trash2 } from 'lucide-react';
import { nanoid } from 'nanoid';
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
    availableMembers?: Array<{
        member_id: number;
        name: string;
        sharing_plan: string | null;
    }>;
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
    availableMembers = [],
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

    const memberOptions = availableMembers.map((member) => ({
        value: String(member.member_id),
        label: member.sharing_plan
            ? `${member.name} (${member.sharing_plan})`
            : member.name,
    }));

    const memberSharingPlanById = Object.fromEntries(
        availableMembers.map((member) => [
            member.member_id,
            member.sharing_plan,
        ]),
    );

    const extensions = data.extensions ?? [];
    const subtotalAmount = items.reduce((sum, item) => {
        if (item.is_header) {
            return sum;
        }

        return sum + Number(item.quantity ?? 0) * Number(item.rate ?? 0);
    }, 0);
    const extensionTotalAmount = extensions.reduce(
        (sum, extension) => sum + Number(extension.amount ?? 0),
        0,
    );
    const totalAmount = subtotalAmount + extensionTotalAmount;

    const handleExtensionChange = (
        index: number,
        patch: Partial<(typeof extensions)[number]>,
    ) => {
        const next = [...extensions];
        next[index] = {
            ...next[index],
            ...patch,
            sort_order: index + 1,
        };
        setData('extensions', next);
    };

    const addDiscountExtension = () => {
        setData('extensions', [
            ...extensions,
            {
                _key: nanoid(),
                name: 'Discount',
                type: 'discount',
                amount: 0,
                sort_order: extensions.length + 1,
            },
        ]);
    };

    const removeExtension = (index: number) => {
        const next = extensions
            .filter((_, currentIndex) => currentIndex !== index)
            .map((extension, currentIndex) => ({
                ...extension,
                sort_order: currentIndex + 1,
            }));

        setData('extensions', next);
    };

    return (
        <FormSection
            value="quotation_details"
            title="Quotation Details"
            description="Quotation and payment details"
            status={status}
            required
        >
            <div className="space-y-6">
                <div
                    id="section-maid-assignment"
                    className="grid grid-cols-1 items-start gap-4 pt-2 md:grid-cols-2"
                >
                    <section className="order-1 grid grid-cols-1 items-start gap-4 md:col-span-1 lg:order-1">
                        {/* Description */}
                        <FormField
                            label="Description"
                            htmlFor="description"
                            fieldRequirementsProps={{
                                required: true,
                                hint: 'Enter quotation description',
                            }}
                        >
                            <ProperInput
                                id="description"
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
                            htmlFor="payment_plan"
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
                                <SelectTrigger id="payment_plan">
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
                            htmlFor="payment_method"
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
                                <SelectTrigger id="payment_method">
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

                        {(data.package_name ||
                            data.customer_confirmation_id) && (
                            <FormField
                                label="Package Sharing Plan Cost"
                                fieldRequirementsProps={{
                                    hint: 'Reference package sharing prices used for member cost calculation',
                                }}
                            >
                                <div className="grid w-full items-center gap-3 rounded-md border p-3">
                                    <Label>Package & Sharing Plan Costs</Label>
                                    <div className="space-y-1 text-sm">
                                        {data.package_name && (
                                            <div className="flex items-center justify-between gap-3 border-b pb-2 font-medium">
                                                <span className="text-muted-foreground">
                                                    Package
                                                </span>
                                                <span>{data.package_name}</span>
                                            </div>
                                        )}
                                        {sharingPlanCosts.map((row) => (
                                            <div
                                                key={row.key}
                                                className="flex items-center justify-between gap-3"
                                            >
                                                <span className="text-muted-foreground">
                                                    {row.label}
                                                </span>
                                                <span>
                                                    ${row.value.toFixed(2)}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </FormField>
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
                        showMemberColumn
                        memberOptions={memberOptions}
                        memberSharingPlanById={memberSharingPlanById}
                    />

                    <div className="space-y-3 rounded-md border p-4">
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">
                                Sub Total
                            </span>
                            <span className="font-semibold">
                                {formatCurrency(subtotalAmount)}
                            </span>
                        </div>

                        {extensions.map((extension, index) => (
                            <div
                                key={extension._key ?? `extension-${index}`}
                                className={cn(
                                    'grid items-end gap-3 md:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1fr)_auto]',
                                    isView &&
                                        'md:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1fr)]',
                                )}
                            >
                                <FormField label="Extension Name">
                                    <ProperInput
                                        value={extension.name ?? ''}
                                        placeholder="Discount"
                                        disabled={isView}
                                        onCommit={(value) =>
                                            handleExtensionChange(index, {
                                                name: value,
                                            })
                                        }
                                    />
                                    {renderError(`extensions.${index}.name`)}
                                </FormField>

                                <FormField label="Type">
                                    <Select
                                        disabled={isView}
                                        value={extension.type ?? 'discount'}
                                        onValueChange={(value) =>
                                            handleExtensionChange(index, {
                                                type: value,
                                            })
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="discount">
                                                Discount
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {renderError(`extensions.${index}.type`)}
                                </FormField>

                                <FormField label="Amount">
                                    <ProperInput
                                        value={extension.amount ?? ''}
                                        type="number"
                                        inputProps={{ step: 'any' }}
                                        disabled={isView}
                                        onCommit={(value) =>
                                            handleExtensionChange(index, {
                                                amount: Number(value),
                                            })
                                        }
                                    />
                                    {renderError(`extensions.${index}.amount`)}
                                </FormField>

                                {!isView && (
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="icon"
                                        onClick={() => removeExtension(index)}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                )}
                            </div>
                        ))}

                        {!isView && (
                            <div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={addDiscountExtension}
                                >
                                    + Add Discount
                                </Button>
                            </div>
                        )}

                        <div className="flex items-center justify-between border-t pt-3 text-sm">
                            <span className="text-muted-foreground">
                                Extension Total
                            </span>
                            <span className="font-semibold">
                                {formatCurrency(extensionTotalAmount)}
                            </span>
                        </div>

                        <div className="flex items-center justify-between text-base">
                            <span className="font-semibold">Total Amount</span>
                            <span className="text-lg font-bold text-primary">
                                {formatCurrency(totalAmount)}
                            </span>
                        </div>
                    </div>
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
