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

function mergeSummaryExtensionsByNameAndType(
    extensions: TotalsSummaryExtension[] = [],
): TotalsSummaryExtension[] {
    const grouped = new Map<string, TotalsSummaryExtension>();

    extensions.forEach((extension, index) => {
        const name = String(extension.name ?? '').trim() || 'Extension';
        const type = String(extension.type ?? 'discount')
            .trim()
            .toLowerCase();
        const calculationMode =
            String(extension.calculation_mode ?? 'fixed') === 'percentage'
                ? 'percentage'
                : 'fixed';
        const calculationValue = Number(extension.calculation_value ?? 0);
        const mergeLabel =
            calculationMode === 'percentage'
                ? `${name} ${Math.abs(calculationValue)}%`
                : name;
        const key = `${type}|${mergeLabel.toLowerCase()}`;
        const amount = Number(extension.amount ?? 0);

        if (!grouped.has(key)) {
            grouped.set(key, {
                ...extension,
                _key: extension._key ?? `extension-${index + 1}`,
                name,
                type,
                quotation_extension_master_id: undefined,
                sort_order: Number(extension.sort_order ?? index + 1),
            });

            return;
        }

        const current = grouped.get(key);

        if (!current) {
            return;
        }

        const mergedAmount = Number(current.amount ?? 0) + amount;

        grouped.set(key, {
            ...current,
            quotation_extension_master_id: undefined,
            calculation_mode: 'fixed',
            calculation_value: mergedAmount,
            amount: mergedAmount,
        });
    });

    return Array.from(grouped.values()).map((extension, index) => ({
        ...extension,
        sort_order: index + 1,
    }));
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
    availableMembers = [],
    status,
}: Props) {
    const hasOrderInvoices = Boolean(data.have_invoices);

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
                        master.is_active !== false &&
                        ['tax', 'discount'].includes(
                            String(master.type ?? '').toLowerCase(),
                        ),
                )
                .map((master) => ({
                    id: Number(master.id ?? 0),
                    name: master.name,
                    type: String(master.type ?? '').toLowerCase(),
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
                type: 'tax' | 'discount';
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
            const memberName = item.member_name || (item.customer_confirmation_member_id && availableMembers
                ? availableMembers.find(m => Number(m.member_id) === Number(item.customer_confirmation_member_id))?.name
                : null);

            (item.taxes ?? []).forEach((tax) => {
                const calculationMode = String(tax.calculation_mode ?? '');
                const calculationValue = Number(tax.calculation_value ?? 0);

                if (
                    !['fixed', 'percentage'].includes(calculationMode) ||
                    calculationValue === 0
                ) {
                    return;
                }

                const taxType: 'tax' | 'discount' =
                    calculationValue < 0 ? 'discount' : 'tax';

                let taxName = String(tax.name ?? 'Tax');
                if (memberName) {
                    taxName = `${taxName} (${memberName})`;
                }

                const key = [
                    taxName.toLowerCase(),
                    taxType,
                    calculationMode,
                    calculationValue,
                    memberName || '',
                ].join('|');

                const current = grouped.get(key) ?? {
                    name: taxName,
                    type: taxType,
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
    }, [items, availableMembers]);

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
                    _key: extension._key,
                    id: extension.id ?? undefined,
                    quotation_extension_master_id: undefined,
                    sort_order: extension.sort_order,
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

                const amount = Number(extension.amount ?? rawAmount);

                return {
                    id: extension.id ?? undefined,
                    quotation_extension_master_id: undefined,
                    sort_order: extension.sort_order,
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

    const mergedQuotationExtensions = React.useMemo<TotalsSummaryExtension[]>(
        () => mergeSummaryExtensionsByNameAndType(normalizedExtensions),
        [normalizedExtensions],
    );

    const mergedInvoiceExtensions = React.useMemo<TotalsSummaryExtension[]>(
        () => mergeSummaryExtensionsByNameAndType(normalizedInvoiceExtensions),
        [normalizedInvoiceExtensions],
    );

    const combinedExtensions = React.useMemo<TotalsSummaryExtension[]>(
        () =>
            mergeSummaryExtensionsByNameAndType([
                ...mergedQuotationExtensions,
                ...mergedInvoiceExtensions,
            ]),
        [mergedInvoiceExtensions, mergedQuotationExtensions],
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

    const shouldMergeDisplayExtensions = isView || hasOrderInvoices;

    const displayExtensions = React.useMemo<TotalsSummaryExtension[]>(() => {
        if (!shouldMergeDisplayExtensions) {
            return combinedExtensions;
        }

        const grouped = new Map<string, TotalsSummaryExtension>();

        const upsert = (extension: TotalsSummaryExtension, index: number) => {
            const name = String(extension.name ?? 'Extension').trim();
            const type = String(extension.type ?? 'tax')
                .trim()
                .toLowerCase();
            const calculationMode =
                String(extension.calculation_mode ?? 'fixed') === 'percentage'
                    ? 'percentage'
                    : 'fixed';
            const calculationValue = Number(extension.calculation_value ?? 0);
            const mergeLabel =
                calculationMode === 'percentage'
                    ? `${name} ${Math.abs(calculationValue)}%`
                    : name;
            const key = `${type}|${mergeLabel.toLowerCase()}`;

            if (!grouped.has(key)) {
                grouped.set(key, {
                    ...extension,
                    _key: extension._key ?? `display-extension-${index + 1}`,
                    name,
                    type,
                });

                return;
            }

            const current = grouped.get(key);

            if (!current) {
                return;
            }

            grouped.set(key, {
                ...current,
                amount:
                    Number(current.amount ?? 0) + Number(extension.amount ?? 0),
            });
        };

        combinedExtensions.forEach((extension, index) => {
            upsert(extension, index);
        });

        itemTaxSummaries.forEach((tax, index) => {
            upsert(
                {
                    _key: `item-tax-extension-${index + 1}`,
                    quotation_extension_master_id: undefined,
                    name: String(tax.name ?? 'Tax'),
                    type: tax.type,
                    calculation_mode: String(tax.calculation_mode ?? 'fixed'),
                    calculation_value: Number(tax.calculation_value ?? 0),
                    amount: Number(tax.amount ?? 0),
                    sort_order: combinedExtensions.length + index + 1,
                },
                combinedExtensions.length + index,
            );
        });

        return Array.from(grouped.values()).map((extension, index) => ({
            ...extension,
            sort_order: index + 1,
        }));
    }, [combinedExtensions, itemTaxSummaries, shouldMergeDisplayExtensions]);

    const totalAmount = subtotalAmount + extensionTotalAmount;
    const resolvedGrandTotal = React.useMemo(() => {
        const invoiceBackedAmount = Number(
            data.order_invoices_total_amount ?? data.total_amount,
        );

        if (hasOrderInvoices && Number.isFinite(invoiceBackedAmount)) {
            return invoiceBackedAmount;
        }

        return totalAmount;
    }, [
        data.order_invoices_total_amount,
        data.total_amount,
        hasOrderInvoices,
        totalAmount,
    ]);

    const itemTaxRows = React.useMemo<TotalsSummaryRow[]>(() => {
        if (shouldMergeDisplayExtensions) {
            return [];
        }

        return itemTaxSummaries.map((tax, index) => ({
            key: `item-tax-${index}`,
            label:
                String(tax.calculation_mode ?? 'fixed') === 'percentage'
                    ? `${String(tax.name ?? 'Tax')} ${Number(tax.calculation_value ?? 0)}%`
                    : String(tax.name ?? 'Tax'),
            amount: Number(tax.amount ?? 0),
        }));
    }, [itemTaxSummaries, shouldMergeDisplayExtensions]);

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
                    id="section-assignment-details"
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
                                    childWithBedPrice={
                                        data.package_price_child_with_bed
                                    }
                                    childNoBedPrice={
                                        data.package_price_child_no_bed
                                    }
                                    infantPrice={data.package_price_infant}
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
                        extensions={displayExtensions}
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
                                        quotation_extension_master_id: null,
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

                            const mergedNextExtensions =
                                mergeSummaryExtensionsByNameAndType(
                                    normalizedNextExtensions,
                                ).map((extension, index) => ({
                                    ...extension,
                                    _key:
                                        extension._key ??
                                        `extension-${index + 1}`,
                                    name: String(extension.name ?? 'Extension'),
                                    type: String(extension.type ?? 'discount'),
                                    quotation_extension_master_id: null,
                                    calculation_mode:
                                        String(
                                            extension.calculation_mode ??
                                                'fixed',
                                        ) === 'percentage'
                                            ? 'percentage'
                                            : 'fixed',
                                    calculation_value: Number(
                                        extension.calculation_value ?? 0,
                                    ),
                                    amount: Number(extension.amount ?? 0),
                                    sort_order: index + 1,
                                }));

                            setData('extensions', mergedNextExtensions);
                        }}
                        readOnly={isView || hasOrderInvoices}
                        grandTotalAmount={resolvedGrandTotal}
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
