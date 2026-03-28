import { CreatableCombobox, type CreatableComboboxOption } from '@/components/creatable-combobox';
import { FormField } from '@/components/form-field';
import { FormSection } from '@/components/form-section';
import TotalsSummaryCard, { type TotalsSummaryRow } from '@/components/totals-summary-card';
import { Button } from '@/components/ui/button';
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuTrigger,
} from '@/components/ui/context-menu';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { Pencil, Trash2 } from 'lucide-react';
import { nanoid } from 'nanoid';
import React from 'react';
import { ProperInput } from '../../../components/proper-input';
import QuotationItemTableForm from '../items/form';
import { QuotationItemSchema } from '../items/schema';
import { QuotationSchema, SetDataFn } from '../schema';
import ExtensionMasterCombobox from './extension-master-combobox';

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
    paymentMethods = [],
    quotationNotes = [],
    noteErrors = [],
    extensionMasters: rawExtensionMasters = [],
    status,
}: Props) {
    const [paymentMethodOptions, setPaymentMethodOptions] = React.useState<
        CreatableComboboxOption[]
    >((paymentMethods ?? []).map((option) => ({
        value: String(option.value),
        label: String(option.label),
    })));

    React.useEffect(() => {
        setPaymentMethodOptions(
            (paymentMethods ?? []).map((option) => ({
                value: String(option.value),
                label: String(option.label),
            })),
        );
    }, [paymentMethods]);

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

    const extensions = React.useMemo(() => data.extensions ?? [], [data.extensions]);

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

    const activeDiscountExtensionMasters = React.useMemo(
        () =>
            extensionMasters
                .filter(
                    (master) =>
                        master.is_active !== false &&
                        master.type === 'discount',
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

    const [
        availableDiscountExtensionMasters,
        setAvailableDiscountExtensionMasters,
    ] = React.useState(activeDiscountExtensionMasters);
    const [isAddDiscountPickerOpen, setIsAddDiscountPickerOpen] =
        React.useState(false);

    React.useEffect(() => {
        setAvailableDiscountExtensionMasters(activeDiscountExtensionMasters);
    }, [activeDiscountExtensionMasters]);

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

    const nonDiscountExtensionsWithAmount = React.useMemo(() => {
        return extensions
            .filter(
                (extension) =>
                    String(extension.type ?? 'discount') !== 'discount',
            )
            .map((extension) => {
                const calculationMode = String(
                    extension.calculation_mode ?? 'fixed',
                );
                const calculationValue = Number(
                    extension.calculation_value ?? extension.amount ?? 0,
                );

                const amount =
                    calculationMode === 'percentage'
                        ? (subtotalAmount * calculationValue) / 100
                        : calculationValue;

                return {
                    ...extension,
                    amount,
                };
            });
    }, [extensions, subtotalAmount]);

    const nonDiscountTaxExtensions = React.useMemo(
        () =>
            nonDiscountExtensionsWithAmount.filter(
                (extension) => extension.type === 'tax',
            ),
        [nonDiscountExtensionsWithAmount],
    );

    const nonDiscountOtherExtensions = React.useMemo(
        () =>
            nonDiscountExtensionsWithAmount.filter(
                (extension) => extension.type !== 'tax',
            ),
        [nonDiscountExtensionsWithAmount],
    );

    const nonDiscountExtensionTotal = nonDiscountExtensionsWithAmount.reduce(
        (sum, extension) => sum + Number(extension.amount ?? 0),
        0,
    );

    const discountExtension = React.useMemo(
        () =>
            [...extensions]
                .filter(
                    (extension) =>
                        String(extension.type ?? 'discount') === 'discount',
                )
                .sort(
                    (left, right) =>
                        Number(left.sort_order ?? 0) -
                        Number(right.sort_order ?? 0),
                )[0],
        [extensions],
    );

    const discountBaseAmount = subtotalAmount;

    const discountAmount = discountExtension
        ? (() => {
              const calculationMode = String(
                  discountExtension.calculation_mode ?? 'fixed',
              );
              const calculationValue = Math.abs(
                  Number(
                      discountExtension.calculation_value ??
                          discountExtension.amount ??
                          0,
                  ),
              );

              const computed =
                  calculationMode === 'percentage'
                      ? (discountBaseAmount * calculationValue) / 100
                      : calculationValue;

              return -Math.abs(computed);
          })()
        : 0;

    const extensionTotalAmount =
        itemTaxTotal + nonDiscountExtensionTotal + discountAmount;

    const totalAmount = subtotalAmount + extensionTotalAmount;

    const discountIndex = extensions.findIndex(
        (extension) => String(extension.type ?? 'discount') === 'discount',
    );

    const [isDiscountDialogOpen, setIsDiscountDialogOpen] =
        React.useState(false);
    const [discountDraft, setDiscountDraft] = React.useState<{
        quotation_extension_master_id: number | null;
        name: string;
        calculation_mode: 'fixed' | 'percentage';
        calculation_value: number | null;
        amount: number | null;
    }>({
        quotation_extension_master_id: null,
        name: 'Discount',
        calculation_mode: 'fixed',
        calculation_value: null,
        amount: null,
    });

    const upsertDiscountExtension = (nextDiscount: {
        quotation_extension_master_id: number | null;
        name: string;
        calculation_mode: 'fixed' | 'percentage';
        calculation_value: number;
        amount: number;
    }) => {
        const nonDiscountExtensions = extensions.filter(
            (extension) => String(extension.type ?? 'discount') !== 'discount',
        );

        const mergedDiscount = {
            _key:
                discountExtension?._key ??
                (discountExtension?.id
                    ? `id-${discountExtension.id}`
                    : nanoid()),
            id: discountExtension?.id,
            quotation_extension_master_id:
                nextDiscount.quotation_extension_master_id,
            name: nextDiscount.name.trim() || 'Discount',
            type: 'discount',
            calculation_mode: nextDiscount.calculation_mode,
            calculation_value: nextDiscount.calculation_value,
            amount: nextDiscount.amount,
            sort_order: 0,
        };

        const nextExtensions = [...nonDiscountExtensions, mergedDiscount].map(
            (extension, index) => ({
                ...extension,
                sort_order: index + 1,
            }),
        );

        setData('extensions', nextExtensions);
    };

    const addDiscountFromMaster = (master: {
        id?: number;
        name: string;
        calculation_mode?: string | null;
        calculation_value?: string | number | null;
    }) => {
        const masterId = Number(master.id ?? 0);
        const calculationMode =
            String(master.calculation_mode ?? 'fixed') === 'percentage'
                ? 'percentage'
                : 'fixed';
        const calculationValue = Math.abs(
            Number(master.calculation_value ?? 0),
        );
        const amount =
            calculationMode === 'fixed'
                ? calculationValue
                : (discountBaseAmount * calculationValue) / 100;

        upsertDiscountExtension({
            quotation_extension_master_id: masterId > 0 ? masterId : null,
            name: String(master.name ?? 'Discount'),
            calculation_mode: calculationMode,
            calculation_value: calculationValue,
            amount,
        });

        setIsAddDiscountPickerOpen(false);
    };

    const openDiscountDialog = () => {
        if (discountExtension) {
            setDiscountDraft({
                quotation_extension_master_id:
                    Number(
                        discountExtension.quotation_extension_master_id ?? 0,
                    ) > 0
                        ? Number(
                              discountExtension.quotation_extension_master_id,
                          )
                        : null,
                name: String(discountExtension.name ?? 'Discount'),
                calculation_mode:
                    String(discountExtension.calculation_mode ?? 'fixed') ===
                    'percentage'
                        ? 'percentage'
                        : 'fixed',
                calculation_value: Math.abs(
                    Number(discountExtension.calculation_value ?? 0),
                ),
                amount: Math.abs(
                    Number(
                        discountExtension.amount ??
                            discountExtension.calculation_value ??
                            0,
                    ),
                ),
            });
        } else {
            setDiscountDraft({
                quotation_extension_master_id: null,
                name: 'Discount',
                calculation_mode: 'fixed',
                calculation_value: null,
                amount: null,
            });
        }

        setIsDiscountDialogOpen(true);
    };

    const saveDiscountExtension = () => {
        const normalizedCalculationValue =
            discountDraft.calculation_mode === 'percentage'
                ? Math.abs(Number(discountDraft.calculation_value ?? 0))
                : Math.abs(
                      Number(
                          discountDraft.amount ??
                              discountDraft.calculation_value ??
                              0,
                      ),
                  );

        const normalizedAmount =
            discountDraft.calculation_mode === 'fixed'
                ? normalizedCalculationValue
                : (discountBaseAmount * normalizedCalculationValue) / 100;

        upsertDiscountExtension({
            quotation_extension_master_id:
                discountDraft.quotation_extension_master_id,
            name: discountDraft.name,
            calculation_mode: discountDraft.calculation_mode,
            calculation_value: normalizedCalculationValue,
            amount: normalizedAmount,
        });

        setIsDiscountDialogOpen(false);
    };

    const removeDiscountExtension = () => {
        const nextExtensions = extensions
            .filter(
                (extension) =>
                    String(extension.type ?? 'discount') !== 'discount',
            )
            .map((extension, index) => ({
                ...extension,
                sort_order: index + 1,
            }));

        setData('extensions', nextExtensions);
    };

    const formatExtensionLabel = (
        name: string,
        calculationMode?: string | null,
        calculationValue?: number | null,
    ): string => {
        if (String(calculationMode ?? 'fixed') !== 'percentage') {
            return name;
        }

        return `${name} ${Number(calculationValue ?? 0)}%`;
    };

    const totalsRows = React.useMemo<TotalsSummaryRow[]>(() => {
        const rows: TotalsSummaryRow[] = [];

        nonDiscountOtherExtensions.forEach((extension, index) => {
            rows.push({
                key: extension._key ?? `ext-other-${index}`,
                label: formatExtensionLabel(
                    String(extension.name ?? 'Extension'),
                    String(extension.calculation_mode ?? 'fixed'),
                    Number(extension.calculation_value ?? 0),
                ),
                amount: Number(extension.amount ?? 0),
            });
        });

        if (discountExtension) {
            rows.push({
                key: discountExtension._key ?? 'discount',
                label: formatExtensionLabel(
                    String(discountExtension.name),
                    String(discountExtension.calculation_mode ?? 'fixed'),
                    Number(discountExtension.calculation_value ?? 0),
                ),
                amount: discountAmount,
            });
        }

        itemTaxSummaries.forEach((tax, index) => {
            rows.push({
                key: `item-tax-${index}`,
                label: formatExtensionLabel(
                    String(tax.name ?? 'Tax'),
                    tax.calculation_mode,
                    Number(tax.calculation_value ?? 0),
                ),
                amount: Number(tax.amount ?? 0),
            });
        });

        nonDiscountTaxExtensions.forEach((tax, index) => {
            rows.push({
                key: tax._key ?? `ext-tax-${index}`,
                label: formatExtensionLabel(
                    String(tax.name ?? 'Tax'),
                    String(tax.calculation_mode ?? 'fixed'),
                    Number(tax.calculation_value ?? 0),
                ),
                amount: Number(tax.amount ?? 0),
            });
        });

        return rows;
    }, [
        discountAmount,
        discountExtension,
        itemTaxSummaries,
        nonDiscountOtherExtensions,
        nonDiscountTaxExtensions,
    ]);

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

                        <FormField
                            label="Payment Method"
                            htmlFor="payment_method"
                            fieldRequirementsProps={{
                                required: true,
                                hint: 'Select payment method',
                            }}
                        >
                            <CreatableCombobox
                                options={paymentMethodOptions}
                                disabled={isView}
                                triggerId="payment_method"
                                value={String(data.payment_method ?? '')}
                                placeholder="Select method"
                                searchPlaceholder="Search payment method"
                                onChange={(value) =>
                                    setData('payment_method', value)
                                }
                                onCreateOption={(option) => {
                                    setPaymentMethodOptions((prev) => {
                                        if (
                                            prev.some(
                                                (existing) =>
                                                    existing.value ===
                                                    option.value,
                                            )
                                        ) {
                                            return prev;
                                        }

                                        return [...prev, option];
                                    });
                                }}
                                className="w-full"
                            />
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
                                    <div className="space-y-1 text-base">
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
                        showMemberColumn={false}
                        showTaxColumn
                        showTotalFooter={false}
                        taxExtensionMasters={availableTaxExtensionMasters}
                    />

                    <div className="space-y-3">
                        <TotalsSummaryCard
                            subtotalAmount={subtotalAmount}
                            rows={totalsRows}
                            grandTotalAmount={totalAmount}
                        />

                        {discountExtension ? (
                            <div className="ml-auto w-full text-right md:w-1/3">
                                <ContextMenu>
                                    <ContextMenuTrigger asChild>
                                        <button
                                            type="button"
                                            className="cursor-pointer font-medium underline"
                                            onClick={() => {
                                                if (!isView) {
                                                    openDiscountDialog();
                                                }
                                            }}
                                        >
                                            Configure discount
                                        </button>
                                    </ContextMenuTrigger>
                                    {!isView && (
                                        <ContextMenuContent>
                                            <ContextMenuItem
                                                onClick={openDiscountDialog}
                                            >
                                                <Pencil className="h-4 w-4" />
                                                Edit
                                            </ContextMenuItem>
                                            <ContextMenuItem
                                                variant="destructive"
                                                onClick={removeDiscountExtension}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                                Remove
                                            </ContextMenuItem>
                                        </ContextMenuContent>
                                    )}
                                </ContextMenu>
                            </div>
                        ) : (
                            !isView && (
                                <div className="ml-auto w-full text-right md:w-1/3">
                                    <ExtensionMasterCombobox
                                        value={null}
                                        triggerMode="button"
                                        triggerButtonLabel="Add Discount"
                                        open={isAddDiscountPickerOpen}
                                        onOpenChange={setIsAddDiscountPickerOpen}
                                        extensionType="discount"
                                        options={availableDiscountExtensionMasters
                                            .filter(
                                                (option) =>
                                                    Number(option.id ?? 0) > 0,
                                            )
                                            .map((option) => ({
                                                ...option,
                                                id: Number(option.id),
                                            }))}
                                        placeholder="Select discount"
                                        onSelect={(option) => {
                                            addDiscountFromMaster(option);
                                        }}
                                        onOptionsChange={(nextOptions) => {
                                            setAvailableDiscountExtensionMasters(
                                                nextOptions.map((option) => ({
                                                    id: Number(option.id),
                                                    name: option.name,
                                                    type: 'discount',
                                                    calculation_mode:
                                                        option.calculation_mode ??
                                                        null,
                                                    calculation_value:
                                                        option.calculation_value ??
                                                        null,
                                                    is_active: true,
                                                })),
                                            );
                                        }}
                                        className="cursor-pointer p-0 font-medium underline"
                                    />
                                </div>
                            )
                        )}

                        {discountIndex >= 0 &&
                            renderError(`extensions.${discountIndex}.name`)}
                        {discountIndex >= 0 &&
                            renderError(`extensions.${discountIndex}.amount`)}
                    </div>

                    <Dialog
                        open={isDiscountDialogOpen}
                        onOpenChange={setIsDiscountDialogOpen}
                    >
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Configure Discount</DialogTitle>
                                <DialogDescription>
                                    Edit discount and amount settings.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="space-y-3">
                                <FormField label="Discount Name">
                                    <ProperInput
                                        value={discountDraft.name}
                                        onCommit={(value) =>
                                            setDiscountDraft((prev) => ({
                                                ...prev,
                                                name: value,
                                            }))
                                        }
                                    />
                                </FormField>

                                <FormField label="Calculation">
                                    <Select
                                        value={discountDraft.calculation_mode}
                                        onValueChange={(value) =>
                                            setDiscountDraft((prev) => ({
                                                ...prev,
                                                calculation_mode:
                                                    value === 'percentage'
                                                        ? 'percentage'
                                                        : 'fixed',
                                            }))
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Calculation mode" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="fixed">
                                                Fixed Amount
                                            </SelectItem>
                                            <SelectItem value="percentage">
                                                Percentage
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </FormField>

                                <FormField
                                    label={
                                        discountDraft.calculation_mode ===
                                        'percentage'
                                            ? 'Value (%)'
                                            : 'Amount'
                                    }
                                >
                                    <ProperInput
                                        value={
                                            discountDraft.calculation_mode ===
                                            'percentage'
                                                ? (discountDraft.calculation_value ??
                                                  '')
                                                : (discountDraft.amount ?? '')
                                        }
                                        type="number"
                                        inputProps={{ step: 'any', min: 0 }}
                                        placeholder="0"
                                        onCommit={(value) =>
                                            setDiscountDraft((prev) => ({
                                                ...prev,
                                                calculation_value:
                                                    prev.calculation_mode ===
                                                    'percentage'
                                                        ? Math.abs(
                                                              Number(
                                                                  value ?? 0,
                                                              ),
                                                          )
                                                        : prev.calculation_value,
                                                amount:
                                                    prev.calculation_mode ===
                                                    'fixed'
                                                        ? Math.abs(
                                                              Number(
                                                                  value ?? 0,
                                                              ),
                                                          )
                                                        : prev.amount,
                                            }))
                                        }
                                    />
                                </FormField>
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        setIsDiscountDialogOpen(false)
                                    }
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="button"
                                    onClick={saveDiscountExtension}
                                >
                                    Save Discount
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
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
