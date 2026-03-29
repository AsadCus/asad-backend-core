import { FormField } from '@/components/form-field';
import { FormSection } from '@/components/form-section';
import PackageSharingPlanInfo from '@/components/package-sharing-plan-info';
import TotalsSummaryCard, {
    type TotalsSummaryExtension,
    type TotalsSummaryExtensionMaster,
    type TotalsSummaryRow,
} from '@/components/totals-summary-card';
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
    quotationNotes?: NoteSchema[];
    noteErrors?: string[];
    extensionMasters?: Array<{
        id?: number;
        name: string;
        type: string;
        calculation_mode?: string | null;
        calculation_value?: string | number | null;
        is_active?: boolean;
    }>;
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
    quotationNotes = [],
    noteErrors = [],
    extensionMasters: rawExtensionMasters = [],
    status,
}: Props) {
    const extensions = React.useMemo(
        () => data.extensions ?? [],
        [data.extensions],
    );

    const invoiceExtensions = React.useMemo(
        () => data.invoice_extensions ?? [],
        [data.invoice_extensions],
    );

    const extensionMasters = React.useMemo(
        () =>
            (rawExtensionMasters ?? []).map((master) => ({
                id: Number(master.id ?? 0),
                name: master.name,
                type: master.type,
                calculation_mode: master.calculation_mode ?? null,
                calculation_value: master.calculation_value ?? null,
                is_active: master.is_active ?? true,
            })),
        [rawExtensionMasters],
    );

    const subtotalAmount = items.reduce((sum, item) => {
        if (item.is_header) {
            return sum;
        }

        return sum + Number(item.quantity ?? 0) * Number(item.rate ?? 0);
    }, 0);

    const activeTaxExtensionMasters = React.useMemo(
        () =>
            extensionMasters
                .filter(
                    (master) =>
                        master.is_active !== false && master.type === 'tax',
                )
                .map((master) => ({
                    id: Number(master.id ?? 0),
                    name: master.name,
                    calculation_mode: master.calculation_mode,
                    calculation_value: master.calculation_value,
                }))
                .filter((master) => master.id > 0),
        [extensionMasters],
    );

    const [availableTaxExtensionMasters, setAvailableTaxExtensionMasters] =
        React.useState(activeTaxExtensionMasters);

    React.useEffect(() => {
        setAvailableTaxExtensionMasters(activeTaxExtensionMasters);
    }, [activeTaxExtensionMasters]);

    const itemTaxSummaries = React.useMemo(() => {
        const grouped = new Map<
            string,
            {
                name: string;
                calculation_mode: string;
                calculation_value: number;
                amount: number;
            }
        >();

        items.forEach((item) => {
            if (item.is_header) {
                return;
            }

            const lineAmount =
                Number(item.quantity ?? 0) * Number(item.rate ?? 0);

            (item.taxes ?? []).forEach((tax) => {
                const calculationMode = String(tax.calculation_mode ?? '');
                const calculationValue = Number(tax.calculation_value ?? 0);

                if (
                    !['fixed', 'percentage'].includes(calculationMode) ||
                    calculationValue <= 0
                ) {
                    return;
                }

                const key = [
                    Number(tax.quotation_extension_master_id ?? 0),
                    String(tax.name ?? 'Tax').toLowerCase(),
                    calculationMode,
                    calculationValue,
                ].join('|');

                const current = grouped.get(key) ?? {
                    name: String(tax.name ?? 'Tax'),
                    calculation_mode: calculationMode,
                    calculation_value: calculationValue,
                    amount: 0,
                };

                current.amount +=
                    calculationMode === 'percentage'
                        ? (lineAmount * calculationValue) / 100
                        : calculationValue;

                grouped.set(key, current);
            });
        });

        return Array.from(grouped.values());
    }, [items]);

    const itemTaxTotal = itemTaxSummaries.reduce((sum, tax) => {
        return sum + tax.amount;
    }, 0);

    const normalizedExtensions = React.useMemo<TotalsSummaryExtension[]>(
        () =>
            extensions.map((extension) => {
                const type = String(extension.type ?? 'discount');
                const calculationMode =
                    String(extension.calculation_mode ?? 'fixed') ===
                    'percentage'
                        ? 'percentage'
                        : 'fixed';
                const calculationValue = Number(
                    extension.calculation_value ?? extension.amount ?? 0,
                );

                const rawAmount =
                    calculationMode === 'percentage'
                        ? (subtotalAmount * calculationValue) / 100
                        : calculationValue;

                const amount =
                    type === 'discount' ? -Math.abs(rawAmount) : rawAmount;

                return {
                    ...extension,
                    name: String(extension.name ?? 'Extension'),
                    type,
                    calculation_mode: calculationMode,
                    calculation_value: calculationValue,
                    amount,
                };
            }),
        [extensions, subtotalAmount],
    );

    const normalizedInvoiceExtensions = React.useMemo<TotalsSummaryExtension[]>(
        () =>
            invoiceExtensions.map((extension) => {
                const type = String(extension.type ?? 'discount');
                const calculationMode =
                    String(extension.calculation_mode ?? 'fixed') ===
                    'percentage'
                        ? 'percentage'
                        : 'fixed';
                const calculationValue = Number(
                    extension.calculation_value ?? extension.amount ?? 0,
                );

                const rawAmount =
                    calculationMode === 'percentage'
                        ? (subtotalAmount * calculationValue) / 100
                        : calculationValue;

                const amount =
                    type === 'discount' ? -Math.abs(rawAmount) : rawAmount;

                return {
                    ...extension,
                    _key:
                        extension._key ??
                        (extension.id
                            ? `invoice-extension-${extension.id}`
                            : nanoid()),
                    name: String(extension.name ?? 'Extension'),
                    type,
                    calculation_mode: calculationMode,
                    calculation_value: calculationValue,
                    amount,
                };
            }),
        [invoiceExtensions, subtotalAmount],
    );

    const combinedExtensions = React.useMemo<TotalsSummaryExtension[]>(
        () => [...normalizedExtensions, ...normalizedInvoiceExtensions],
        [normalizedExtensions, normalizedInvoiceExtensions],
    );

    const extensionMastersForSummary = React.useMemo<
        TotalsSummaryExtensionMaster[]
    >(
        () =>
            extensionMasters.map((master) => ({
                id: Number(master.id ?? 0),
                name: String(master.name ?? 'Extension'),
                type: String(master.type ?? 'other'),
                calculation_mode: master.calculation_mode,
                calculation_value: master.calculation_value,
                is_active: master.is_active ?? true,
            })),
        [extensionMasters],
    );

    const extensionTotalAmount =
        itemTaxTotal +
        combinedExtensions.reduce(
            (sum, extension) => sum + Number(extension.amount ?? 0),
            0,
        );

    const totalAmount = subtotalAmount + extensionTotalAmount;

    const itemTaxRows = React.useMemo<TotalsSummaryRow[]>(
        () =>
            itemTaxSummaries.map((tax, index) => ({
                key: `item-tax-${index}`,
                label:
                    String(tax.calculation_mode ?? 'fixed') === 'percentage'
                        ? `${String(tax.name ?? 'Tax')} ${Number(tax.calculation_value ?? 0)}%`
                        : String(tax.name ?? 'Tax'),
                amount: Number(tax.amount ?? 0),
            })),
        [itemTaxSummaries],
    );

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

                        {(data.package_name ||
                            data.customer_confirmation_id) && (
                            <FormField
                                label="Package Sharing Plan Cost"
                                fieldRequirementsProps={{
                                    hint: 'Reference package sharing prices used for member cost calculation',
                                }}
                            >
                                <PackageSharingPlanInfo
                                    packageName={data.package_name}
                                    packageLabel="Package Name"
                                    singlePrice={data.package_price_single}
                                    doublePrice={data.package_price_double}
                                    triplePrice={data.package_price_triple}
                                    quadPrice={data.package_price_quad}
                                />
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
                        showMemberColumn={false}
                        showTaxColumn
                        showTotalFooter={false}
                        taxExtensionMasters={availableTaxExtensionMasters}
                    />

                    <TotalsSummaryCard
                        subtotalAmount={subtotalAmount}
                        itemTaxRows={itemTaxRows}
                        extensions={combinedExtensions}
                        extensionMasters={extensionMastersForSummary}
                        onExtensionsChange={(nextExtensions) => {
                            const normalizedNextExtensions = nextExtensions.map(
                                (extension, index) => {
                                    const calculationMode =
                                        String(
                                            extension.calculation_mode ??
                                                'fixed',
                                        ) === 'percentage'
                                            ? 'percentage'
                                            : 'fixed';

                                    return {
                                        ...extension,
                                        _key:
                                            extension._key ??
                                            `extension-${index + 1}`,
                                        name: String(
                                            extension.name ?? 'Extension',
                                        ),
                                        type: String(
                                            extension.type ?? 'discount',
                                        ),
                                        calculation_mode: calculationMode,
                                        calculation_value: Number(
                                            extension.calculation_value ?? 0,
                                        ),
                                        amount: Number(extension.amount ?? 0),
                                        sort_order: Number(
                                            extension.sort_order ?? index + 1,
                                        ),
                                    };
                                },
                            );

                            setData('extensions', normalizedNextExtensions);
                        }}
                        readOnly={
                            isView || normalizedInvoiceExtensions.length > 0
                        }
                        grandTotalAmount={totalAmount}
                    />
                </div>

                <div className="mx-auto w-full">
                    <NoteForm
                        mode="quotation"
                        model="quotation"
                        notes={quotationNotes}
                        onChange={(v) => setData('notes', v)}
                        disabled={isView}
                    />
                    {noteErrors.length > 0 && (
                        <div className="mt-2 space-y-1">
                            {noteErrors.map((error, index) => (
                                <p
                                    key={`${error}-${index}`}
                                    className="text-base text-red-500"
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
