import { FormField } from '@/components/form-field';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn, formatCurrency } from '@/lib/utils';
import { Pencil, Trash2 } from 'lucide-react';
import { nanoid } from 'nanoid';
import * as React from 'react';
import ExtensionMasterCombobox, {
    type ExtensionMasterComboboxOption,
} from '../pages/quotations/components/extension-master-combobox';
import { ProperInput } from './proper-input';

export interface TotalsSummaryRow {
    key: string;
    label: string;
    amount: number;
    labelClassName?: string;
    amountClassName?: string;
}

export interface TotalsSummaryExtension {
    _key?: string;
    id?: number;
    quotation_extension_master_id?: number | null;
    name?: string | null;
    type?: string | null;
    calculation_mode?: string | null;
    calculation_value?: number | string | null;
    amount?: number | string | null;
    sort_order?: number;
}

export interface TotalsSummaryExtensionMaster {
    id?: number;
    name: string;
    type: string;
    calculation_mode?: string | null;
    calculation_value?: string | number | null;
    is_active?: boolean;
}

type CalculationMode = 'fixed' | 'percentage';

type ExtensionDraft = {
    key: string | null;
    quotation_extension_master_id: number | null;
    name: string;
    type: 'discount' | 'credit_card' | 'other';
    calculation_mode: CalculationMode;
    calculation_value: number | null;
    amount: number | null;
};

interface TotalsSummaryCardProps {
    subtotalAmount: number;
    itemTaxRows?: TotalsSummaryRow[];
    extensions?: TotalsSummaryExtension[];
    extensionMasters?: TotalsSummaryExtensionMaster[];
    onExtensionsChange?: (extensions: TotalsSummaryExtension[]) => void;
    readOnly?: boolean;
    grandTotalAmount: number;
    showExtensionTotal?: boolean;
    extensionTotalAmount?: number;
    className?: string;
}

const EMPTY_SUMMARY_ROWS: TotalsSummaryRow[] = [];
const EMPTY_SUMMARY_EXTENSIONS: TotalsSummaryExtension[] = [];
const EMPTY_SUMMARY_EXTENSION_MASTERS: TotalsSummaryExtensionMaster[] = [];

function formatExtensionLabel(
    name: string,
    calculationMode?: string | null,
    calculationValue?: number | string | null,
): string {
    if (String(calculationMode ?? 'fixed') !== 'percentage') {
        return name;
    }

    return `${name} ${Math.abs(Number(calculationValue ?? 0))}%`;
}

function normalizeExtensions(
    extensions: TotalsSummaryExtension[],
): TotalsSummaryExtension[] {
    return extensions.map((extension, index) => ({
        ...extension,
        _key:
            extension._key ?? (extension.id ? `id-${extension.id}` : nanoid()),
        sort_order: Number(extension.sort_order ?? index + 1),
        type: String(extension.type ?? 'discount'),
        name: String(extension.name ?? 'Extension'),
        calculation_mode:
            String(extension.calculation_mode ?? 'fixed') === 'percentage'
                ? 'percentage'
                : 'fixed',
        calculation_value: Number(extension.calculation_value ?? 0),
        amount: Number(extension.amount ?? 0),
    }));
}

function normalizeExtensionMasterOptions(
    masters: TotalsSummaryExtensionMaster[],
): ExtensionMasterComboboxOption[] {
    return masters
        .filter((master) => Number(master.id ?? 0) > 0)
        .map((master) => ({
            id: Number(master.id),
            name: String(master.name ?? 'Extension'),
            type: String(master.type ?? 'other'),
            calculation_mode: String(master.calculation_mode ?? 'fixed'),
            calculation_value: Number(master.calculation_value ?? 0),
            is_active: master.is_active,
        }));
}

function buildExtensionAmount(
    subtotalAmount: number,
    calculationMode: CalculationMode,
    calculationValue: number,
    type: string,
): number {
    const raw =
        calculationMode === 'percentage'
            ? (subtotalAmount * calculationValue) / 100
            : calculationValue;

    if (String(type) === 'discount') {
        return -Math.abs(raw);
    }

    return raw;
}

export default function TotalsSummaryCard({
    subtotalAmount,
    itemTaxRows = EMPTY_SUMMARY_ROWS,
    extensions = EMPTY_SUMMARY_EXTENSIONS,
    extensionMasters = EMPTY_SUMMARY_EXTENSION_MASTERS,
    onExtensionsChange,
    readOnly = true,
    grandTotalAmount,
    showExtensionTotal = false,
    extensionTotalAmount = 0,
    className,
}: TotalsSummaryCardProps) {
    const [localExtensionMasters, setLocalExtensionMasters] = React.useState(
        normalizeExtensionMasterOptions(extensionMasters),
    );

    React.useEffect(() => {
        setLocalExtensionMasters(
            normalizeExtensionMasterOptions(extensionMasters),
        );
    }, [extensionMasters]);

    const normalizedExtensions = React.useMemo(
        () => normalizeExtensions(extensions),
        [extensions],
    );

    const additionalChargeExtensions = React.useMemo(
        () =>
            normalizedExtensions.filter((extension) =>
                ['credit_card', 'other'].includes(
                    String(extension.type ?? 'discount'),
                ),
            ),
        [normalizedExtensions],
    );

    const discountExtensions = React.useMemo(
        () =>
            normalizedExtensions.filter(
                (extension) => String(extension.type ?? '') === 'discount',
            ),
        [normalizedExtensions],
    );

    const taxExtensions = React.useMemo(
        () =>
            normalizedExtensions.filter(
                (extension) => String(extension.type ?? '') === 'tax',
            ),
        [normalizedExtensions],
    );

    const [isDiscountDialogOpen, setIsDiscountDialogOpen] =
        React.useState(false);
    const [discountDraft, setDiscountDraft] = React.useState<ExtensionDraft>({
        key: null,
        quotation_extension_master_id: null,
        name: 'Discount',
        type: 'discount',
        calculation_mode: 'fixed',
        calculation_value: null,
        amount: null,
    });

    const [isAdditionalEditorOpen, setIsAdditionalEditorOpen] =
        React.useState(false);
    const [additionalDraft, setAdditionalDraft] =
        React.useState<ExtensionDraft>({
            key: null,
            quotation_extension_master_id: null,
            name: 'Additional Charges',
            type: 'other',
            calculation_mode: 'fixed',
            calculation_value: null,
            amount: null,
        });

    const pushExtensions = React.useCallback(
        (nextExtensions: TotalsSummaryExtension[]) => {
            onExtensionsChange?.(
                nextExtensions.map((extension, index) => ({
                    ...extension,
                    sort_order: index + 1,
                })),
            );
        },
        [onExtensionsChange],
    );

    const openDiscountDialog = React.useCallback(
        (extension?: TotalsSummaryExtension | null) => {
            if (extension) {
                setDiscountDraft({
                    key: String(extension._key ?? ''),
                    quotation_extension_master_id: null,
                    name: String(extension.name ?? 'Discount'),
                    type: 'discount',
                    calculation_mode:
                        String(extension.calculation_mode ?? 'fixed') ===
                        'percentage'
                            ? 'percentage'
                            : 'fixed',
                    calculation_value: Math.abs(
                        Number(extension.calculation_value ?? 0),
                    ),
                    amount: Math.abs(Number(extension.amount ?? 0)),
                });
            } else {
                setDiscountDraft({
                    key: null,
                    quotation_extension_master_id: null,
                    name: 'Discount',
                    type: 'discount',
                    calculation_mode: 'fixed',
                    calculation_value: null,
                    amount: null,
                });
            }

            setIsDiscountDialogOpen(true);
        },
        [],
    );

    const addDiscountFromMaster = React.useCallback(
        (selectedMaster: ExtensionMasterComboboxOption) => {
            const calculationMode =
                String(selectedMaster.calculation_mode ?? 'fixed') ===
                'percentage'
                    ? 'percentage'
                    : 'fixed';
            const calculationValue = Math.abs(
                Number(selectedMaster.calculation_value ?? 0),
            );
            const amount = buildExtensionAmount(
                subtotalAmount,
                calculationMode,
                calculationValue,
                'discount',
            );

            pushExtensions([
                ...normalizedExtensions,
                {
                    _key: nanoid(),
                    quotation_extension_master_id: null,
                    name: String(selectedMaster.name ?? 'Discount'),
                    type: 'discount',
                    calculation_mode: calculationMode,
                    calculation_value: calculationValue,
                    amount,
                },
            ]);
        },
        [normalizedExtensions, pushExtensions, subtotalAmount],
    );

    const saveDiscount = React.useCallback(() => {
        const calculationValue =
            discountDraft.calculation_mode === 'percentage'
                ? Math.abs(Number(discountDraft.calculation_value ?? 0))
                : Math.abs(
                      Number(
                          discountDraft.amount ??
                              discountDraft.calculation_value ??
                              0,
                      ),
                  );
        const amount = buildExtensionAmount(
            subtotalAmount,
            discountDraft.calculation_mode,
            calculationValue,
            'discount',
        );

        const nextEntry: TotalsSummaryExtension = {
            _key: discountDraft.key ?? nanoid(),
            quotation_extension_master_id: null,
            name: discountDraft.name.trim() || 'Discount',
            type: 'discount',
            calculation_mode: discountDraft.calculation_mode,
            calculation_value: calculationValue,
            amount,
        };

        const targetIndex = normalizedExtensions.findIndex(
            (extension) =>
                String(extension._key ?? '') ===
                String(discountDraft.key ?? ''),
        );

        const nextExtensions = [...normalizedExtensions];

        if (targetIndex >= 0) {
            nextExtensions[targetIndex] = {
                ...nextExtensions[targetIndex],
                ...nextEntry,
            };
        } else {
            nextExtensions.push(nextEntry);
        }

        pushExtensions(nextExtensions);
        setIsDiscountDialogOpen(false);
    }, [discountDraft, normalizedExtensions, pushExtensions, subtotalAmount]);

    const removeDiscount = React.useCallback(
        (targetKey: string) => {
            pushExtensions(
                normalizedExtensions.filter(
                    (extension) => String(extension._key ?? '') !== targetKey,
                ),
            );
        },
        [normalizedExtensions, pushExtensions],
    );

    const addAdditionalFromMaster = React.useCallback(
        (selectedMaster: ExtensionMasterComboboxOption) => {
            const calculationMode =
                String(selectedMaster.calculation_mode ?? 'fixed') ===
                'percentage'
                    ? 'percentage'
                    : 'fixed';
            const calculationValue = Number(
                selectedMaster.calculation_value ?? 0,
            );
            const amount = buildExtensionAmount(
                subtotalAmount,
                calculationMode,
                calculationValue,
                String(selectedMaster.type ?? 'other'),
            );

            pushExtensions([
                ...normalizedExtensions,
                {
                    _key: nanoid(),
                    quotation_extension_master_id: null,
                    name: String(selectedMaster.name ?? 'Additional Charges'),
                    type: String(selectedMaster.type ?? 'other'),
                    calculation_mode: calculationMode,
                    calculation_value: calculationValue,
                    amount,
                },
            ]);
        },
        [normalizedExtensions, pushExtensions, subtotalAmount],
    );

    const openAdditionalEditor = React.useCallback(
        (extension?: TotalsSummaryExtension | null) => {
            if (extension) {
                setAdditionalDraft({
                    key: String(extension._key ?? ''),
                    quotation_extension_master_id: null,
                    name: String(extension.name ?? 'Additional Charges'),
                    type: ['credit_card', 'other'].includes(
                        String(extension.type ?? ''),
                    )
                        ? (String(extension.type) as 'credit_card' | 'other')
                        : 'other',
                    calculation_mode:
                        String(extension.calculation_mode ?? 'fixed') ===
                        'percentage'
                            ? 'percentage'
                            : 'fixed',
                    calculation_value: Number(extension.calculation_value ?? 0),
                    amount: Number(extension.amount ?? 0),
                });
            } else {
                setAdditionalDraft({
                    key: null,
                    quotation_extension_master_id: null,
                    name: 'Additional Charges',
                    type: 'other',
                    calculation_mode: 'fixed',
                    calculation_value: null,
                    amount: null,
                });
            }

            setIsAdditionalEditorOpen(true);
        },
        [],
    );

    const saveAdditionalDraft = React.useCallback(() => {
        const calculationValue =
            additionalDraft.calculation_mode === 'percentage'
                ? Math.abs(Number(additionalDraft.calculation_value ?? 0))
                : Math.abs(
                      Number(
                          additionalDraft.amount ??
                              additionalDraft.calculation_value ??
                              0,
                      ),
                  );

        const amount = buildExtensionAmount(
            subtotalAmount,
            additionalDraft.calculation_mode,
            calculationValue,
            additionalDraft.type,
        );

        const nextExtensions = [...normalizedExtensions];
        const targetIndex = nextExtensions.findIndex(
            (extension) =>
                String(extension._key ?? '') ===
                String(additionalDraft.key ?? ''),
        );

        const nextEntry: TotalsSummaryExtension = {
            _key: additionalDraft.key ?? nanoid(),
            quotation_extension_master_id: null,
            name: additionalDraft.name.trim() || 'Additional Charges',
            type: additionalDraft.type,
            calculation_mode: additionalDraft.calculation_mode,
            calculation_value: calculationValue,
            amount,
        };

        if (targetIndex >= 0) {
            nextExtensions[targetIndex] = {
                ...nextExtensions[targetIndex],
                ...nextEntry,
            };
        } else {
            nextExtensions.push(nextEntry);
        }

        pushExtensions(nextExtensions);
        setIsAdditionalEditorOpen(false);
    }, [additionalDraft, normalizedExtensions, pushExtensions, subtotalAmount]);

    const removeAdditional = React.useCallback(
        (targetKey: string) => {
            pushExtensions(
                normalizedExtensions.filter(
                    (extension) => String(extension._key ?? '') !== targetKey,
                ),
            );
        },
        [normalizedExtensions, pushExtensions],
    );

    const extensionRows = React.useMemo<TotalsSummaryRow[]>(() => {
        const taxRows: TotalsSummaryRow[] = taxExtensions.map(
            (extension, index) => ({
                key: String(extension._key ?? `tax-extension-${index}`),
                label: formatExtensionLabel(
                    String(extension.name ?? 'Tax'),
                    String(extension.calculation_mode ?? 'fixed'),
                    Number(extension.calculation_value ?? 0),
                ),
                amount: Number(extension.amount ?? 0),
            }),
        );

        return [...itemTaxRows, ...taxRows];
    }, [itemTaxRows, taxExtensions]);

    return (
        <>
            <div
                className={cn(
                    'ml-auto w-full rounded-md border p-4 md:w-1/3',
                    className,
                )}
            >
                <table className="w-full table-fixed text-base">
                    <tbody className="[&>tr>td]:py-1.5">
                        <tr>
                            <td className="w-2/3 text-right font-semibold">
                                Sub Total
                            </td>
                            <td className="w-1/3 text-right font-medium">
                                {formatCurrency(subtotalAmount)}
                            </td>
                        </tr>

                        {discountExtensions.map((extension, index) => (
                            <tr
                                key={String(
                                    extension._key ?? `discount-${index}`,
                                )}
                            >
                                <td className="text-right">
                                    {readOnly ? (
                                        formatExtensionLabel(
                                            String(
                                                extension.name ?? 'Discount',
                                            ),
                                            String(
                                                extension.calculation_mode ??
                                                    'fixed',
                                            ),
                                            Number(
                                                extension.calculation_value ??
                                                    0,
                                            ),
                                        )
                                    ) : (
                                        <ContextMenu>
                                            <ContextMenuTrigger asChild>
                                                <button
                                                    type="button"
                                                    className="cursor-pointer font-medium underline"
                                                    onClick={() =>
                                                        openDiscountDialog(
                                                            extension,
                                                        )
                                                    }
                                                >
                                                    {formatExtensionLabel(
                                                        String(
                                                            extension.name ??
                                                                'Discount',
                                                        ),
                                                        String(
                                                            extension.calculation_mode ??
                                                                'fixed',
                                                        ),
                                                        Number(
                                                            extension.calculation_value ??
                                                                0,
                                                        ),
                                                    )}
                                                </button>
                                            </ContextMenuTrigger>
                                            <ContextMenuContent>
                                                <ContextMenuItem
                                                    onClick={() =>
                                                        openDiscountDialog(
                                                            extension,
                                                        )
                                                    }
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                    Edit
                                                </ContextMenuItem>
                                                <ContextMenuItem
                                                    variant="destructive"
                                                    onClick={() =>
                                                        removeDiscount(
                                                            String(
                                                                extension._key ??
                                                                    '',
                                                            ),
                                                        )
                                                    }
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                    Remove
                                                </ContextMenuItem>
                                            </ContextMenuContent>
                                        </ContextMenu>
                                    )}
                                </td>
                                <td className="text-right">
                                    {formatCurrency(
                                        Number(extension.amount ?? 0),
                                    )}
                                </td>
                            </tr>
                        ))}

                        {!readOnly && (
                            <tr>
                                <td className="text-right">
                                    <ExtensionMasterCombobox
                                        value={null}
                                        options={localExtensionMasters}
                                        extensionType="discount"
                                        triggerMode="button"
                                        triggerButtonLabel="Add Discount"
                                        className="cursor-pointer font-medium text-primary underline"
                                        placeholder="Select discount"
                                        onSelect={(option) => {
                                            addDiscountFromMaster(option);
                                        }}
                                        onOptionsChange={
                                            setLocalExtensionMasters
                                        }
                                    />
                                </td>
                                <td className="text-right"></td>
                            </tr>
                        )}

                        {additionalChargeExtensions.map((extension, index) => (
                            <tr
                                key={String(
                                    extension._key ?? `additional-${index}`,
                                )}
                            >
                                <td className="text-right">
                                    {readOnly ? (
                                        formatExtensionLabel(
                                            String(
                                                extension.name ??
                                                    'Additional Charges',
                                            ),
                                            String(
                                                extension.calculation_mode ??
                                                    'fixed',
                                            ),
                                            Number(
                                                extension.calculation_value ??
                                                    0,
                                            ),
                                        )
                                    ) : (
                                        <ContextMenu>
                                            <ContextMenuTrigger asChild>
                                                <button
                                                    type="button"
                                                    className="cursor-pointer font-medium underline"
                                                    onClick={() =>
                                                        openAdditionalEditor(
                                                            extension,
                                                        )
                                                    }
                                                >
                                                    {formatExtensionLabel(
                                                        String(
                                                            extension.name ??
                                                                'Additional Charges',
                                                        ),
                                                        String(
                                                            extension.calculation_mode ??
                                                                'fixed',
                                                        ),
                                                        Number(
                                                            extension.calculation_value ??
                                                                0,
                                                        ),
                                                    )}
                                                </button>
                                            </ContextMenuTrigger>
                                            <ContextMenuContent>
                                                <ContextMenuItem
                                                    onClick={() =>
                                                        openAdditionalEditor(
                                                            extension,
                                                        )
                                                    }
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                    Edit
                                                </ContextMenuItem>
                                                <ContextMenuItem
                                                    variant="destructive"
                                                    onClick={() =>
                                                        removeAdditional(
                                                            String(
                                                                extension._key ??
                                                                    '',
                                                            ),
                                                        )
                                                    }
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                    Remove
                                                </ContextMenuItem>
                                            </ContextMenuContent>
                                        </ContextMenu>
                                    )}
                                </td>
                                <td className="text-right">
                                    {formatCurrency(
                                        Number(extension.amount ?? 0),
                                    )}
                                </td>
                            </tr>
                        ))}

                        {!readOnly && (
                            <tr>
                                <td className="text-right">
                                    <ExtensionMasterCombobox
                                        value={null}
                                        options={localExtensionMasters}
                                        extensionType="additional"
                                        triggerMode="button"
                                        triggerButtonLabel="Add Additional Charges"
                                        className="cursor-pointer font-medium text-primary underline"
                                        placeholder="Select additional charges"
                                        onSelect={(option) => {
                                            addAdditionalFromMaster(option);
                                        }}
                                        onOptionsChange={
                                            setLocalExtensionMasters
                                        }
                                    />
                                </td>
                                <td className="text-right"></td>
                            </tr>
                        )}

                        {extensionRows.map((row) => (
                            <tr key={row.key}>
                                <td
                                    className={cn(
                                        'text-right',
                                        row.labelClassName,
                                    )}
                                >
                                    {row.label}
                                </td>
                                <td
                                    className={cn(
                                        'text-right',
                                        row.amountClassName,
                                    )}
                                >
                                    {formatCurrency(Number(row.amount ?? 0))}
                                </td>
                            </tr>
                        ))}

                        {showExtensionTotal && (
                            <tr>
                                <td className="text-right font-semibold">
                                    Extension Total
                                </td>
                                <td className="text-right font-medium">
                                    {formatCurrency(extensionTotalAmount)}
                                </td>
                            </tr>
                        )}

                        <tr>
                            <td className="border-t pt-2 text-right text-base font-bold">
                                Grand Total
                            </td>
                            <td className="border-t pt-2 text-right text-lg font-bold text-primary">
                                {formatCurrency(grandTotalAmount)}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Dialog
                open={isDiscountDialogOpen}
                onOpenChange={setIsDiscountDialogOpen}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Configure Discount</DialogTitle>
                        <DialogDescription>
                            Add or update discount extension.
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
                                discountDraft.calculation_mode === 'percentage'
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
                                                ? Math.abs(Number(value ?? 0))
                                                : prev.calculation_value,
                                        amount:
                                            prev.calculation_mode === 'fixed'
                                                ? Math.abs(Number(value ?? 0))
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
                            onClick={() => setIsDiscountDialogOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="button" onClick={saveDiscount}>
                            Save Discount
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={isAdditionalEditorOpen}
                onOpenChange={setIsAdditionalEditorOpen}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Configure Additional Charges</DialogTitle>
                        <DialogDescription>
                            Add or update additional charges extension.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-3">
                        <FormField label="Name">
                            <ProperInput
                                value={additionalDraft.name}
                                onCommit={(value) =>
                                    setAdditionalDraft((prev) => ({
                                        ...prev,
                                        name: value,
                                    }))
                                }
                            />
                        </FormField>

                        <FormField label="Extension Type">
                            <Select
                                value={additionalDraft.type}
                                onValueChange={(value) =>
                                    setAdditionalDraft((prev) => ({
                                        ...prev,
                                        type:
                                            value === 'credit_card'
                                                ? 'credit_card'
                                                : 'other',
                                    }))
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="credit_card">
                                        Credit
                                    </SelectItem>
                                    <SelectItem value="other">
                                        Others
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </FormField>

                        <FormField label="Calculation">
                            <Select
                                value={additionalDraft.calculation_mode}
                                onValueChange={(value) =>
                                    setAdditionalDraft((prev) => ({
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
                                additionalDraft.calculation_mode ===
                                'percentage'
                                    ? 'Value (%)'
                                    : 'Amount'
                            }
                        >
                            <ProperInput
                                value={
                                    additionalDraft.calculation_mode ===
                                    'percentage'
                                        ? (additionalDraft.calculation_value ??
                                          '')
                                        : (additionalDraft.amount ?? '')
                                }
                                type="number"
                                inputProps={{ step: 'any', min: 0 }}
                                placeholder="0"
                                onCommit={(value) =>
                                    setAdditionalDraft((prev) => ({
                                        ...prev,
                                        calculation_value:
                                            prev.calculation_mode ===
                                            'percentage'
                                                ? Math.abs(Number(value ?? 0))
                                                : prev.calculation_value,
                                        amount:
                                            prev.calculation_mode === 'fixed'
                                                ? Math.abs(Number(value ?? 0))
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
                            onClick={() => setIsAdditionalEditorOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="button" onClick={saveAdditionalDraft}>
                            Save Additional Charges
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
