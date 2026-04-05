import { formatDateForDisplay, parseDisplayDate } from '@/lib/utils';
import { calculateTotal, collectAllItems } from '@/pages/invoices/lib/utils';
import { InvoiceItemSchema, InvoiceSchema } from '@/pages/invoices/schema';
import { QuotationSchema } from '@/pages/quotations/schema';
import { nanoid } from 'nanoid';

type QuotationExtensionInput = {
    type?: string | null;
    amount?: number | string | null;
};

export type InvoiceExtensionInput = {
    _key?: string;
    id?: number;
    quotation_extension_master_id?: number | null;
    name?: string | null;
    type?: string | null;
    calculation_mode?: string | null;
    calculation_value?: number | string | null;
    amount?: number | string | null;
    sort_order?: number;
};

export type InvoicePaymentMethodExtensionMaster = {
    id?: number | null;
    name?: string | null;
    type?: string | null;
    calculation_mode?: string | null;
    calculation_value?: number | string | null;
    is_active?: boolean | null;
    payment_methods?: string[];
    sort_order?: number;
};

function normalizePaymentMethodValue(value?: string | null): string {
    return String(value ?? '')
        .trim()
        .toLowerCase();
}

function isExtensionApplicableToPaymentMethod(
    master: InvoicePaymentMethodExtensionMaster,
    paymentMethod?: string | null,
): boolean {
    const supportedMethods = (master.payment_methods ?? [])
        .map((method) => normalizePaymentMethodValue(method))
        .filter((method) => method !== '');

    if (supportedMethods.length === 0) {
        return true;
    }

    const normalizedPaymentMethod = normalizePaymentMethodValue(paymentMethod);

    if (!normalizedPaymentMethod) {
        return false;
    }

    return supportedMethods.includes(normalizedPaymentMethod);
}

function calculateItemTaxTotal(items: InvoiceSchema['items'] = []): number {
    return items.reduce((sum, item) => {
        if (item.is_header) {
            return sum;
        }

        const lineAmount = Number(item.quantity ?? 0) * Number(item.rate ?? 0);
        const itemTaxTotal = (item.taxes ?? []).reduce((taxSum, tax) => {
            const calculationMode = String(tax.calculation_mode ?? '');
            const calculationValue = Number(tax.calculation_value ?? 0);

            if (
                !['fixed', 'percentage'].includes(calculationMode) ||
                calculationValue <= 0
            ) {
                return taxSum;
            }

            const taxAmount =
                calculationMode === 'percentage'
                    ? (lineAmount * calculationValue) / 100
                    : calculationValue;

            return taxSum + taxAmount;
        }, 0);

        return sum + itemTaxTotal;
    }, 0);
}

export function normalizeInvoiceExtensions(
    extensions: InvoiceExtensionInput[] = [],
): InvoiceExtensionInput[] {
    return extensions.map((extension, index) => {
        const calculationMode =
            String(extension.calculation_mode ?? 'fixed') === 'percentage'
                ? 'percentage'
                : 'fixed';

        return {
            ...extension,
            id:
                typeof extension.id === 'number' &&
                Number.isFinite(extension.id)
                    ? extension.id
                    : undefined,
            _key:
                extension._key ??
                (extension.id ? `id-${extension.id}` : nanoid()),
            name: String(extension.name ?? 'Extension'),
            type: String(extension.type ?? 'discount'),
            calculation_mode: calculationMode,
            calculation_value: Number(extension.calculation_value ?? 0),
            amount: Number(extension.amount ?? 0),
            sort_order: Number(extension.sort_order ?? index + 1),
        };
    });
}

export function computeInvoiceExtensionAmount(
    extension: InvoiceExtensionInput,
    subtotalAmount: number,
): number {
    const calculationMode = String(extension.calculation_mode ?? 'fixed');
    const calculationValue = Number(extension.calculation_value ?? 0);
    const extensionType = String(extension.type ?? 'discount');

    if (calculationMode === 'percentage') {
        const rawAmount = (subtotalAmount * calculationValue) / 100;

        return extensionType === 'discount' ? -Math.abs(rawAmount) : rawAmount;
    }

    const fallbackAmount = Number(extension.amount ?? 0);
    const rawAmount =
        calculationValue !== 0 || fallbackAmount === 0
            ? calculationValue
            : fallbackAmount;

    return extensionType === 'discount' ? -Math.abs(rawAmount) : rawAmount;
}

export function recalculateInvoice(invoice: InvoiceSchema): InvoiceSchema {
    const subtotalAmount = calculateTotal(invoice.items ?? []);
    const itemTaxTotal = calculateItemTaxTotal(invoice.items ?? []);
    const normalizedExtensions = normalizeInvoiceExtensions(
        (invoice.extensions ?? []) as InvoiceExtensionInput[],
    );

    const extensionsWithAmount = normalizedExtensions.map((extension) => ({
        ...extension,
        amount: computeInvoiceExtensionAmount(extension, subtotalAmount),
    }));

    const extensionTotal = extensionsWithAmount.reduce(
        (sum, extension) => sum + Number(extension.amount ?? 0),
        0,
    );

    return {
        ...invoice,
        extensions: extensionsWithAmount,
        amount: Number(
            (subtotalAmount + itemTaxTotal + extensionTotal).toFixed(2),
        ),
    };
}

export function applyInvoicePaymentMethodExtensions(
    invoice: InvoiceSchema,
    paymentMethod: string,
    extensionMasters: InvoicePaymentMethodExtensionMaster[],
): InvoiceSchema {
    const subtotalAmount = calculateTotal(invoice.items ?? []);
    const existingExtensions = normalizeInvoiceExtensions(
        (invoice.extensions ?? []) as InvoiceExtensionInput[],
    );

    const manualOrUnrelatedExtensions = existingExtensions.filter(
        (extension) => {
            const type = String(extension.type ?? 'discount');
            const isPaymentMethodType = ['credit_card', 'other'].includes(type);

            if (!isPaymentMethodType) {
                return true;
            }

            return Number(extension.quotation_extension_master_id ?? 0) <= 0;
        },
    );

    const applicableMasters = extensionMasters
        .filter(
            (master) =>
                master.is_active !== false &&
                ['credit_card', 'other'].includes(String(master.type ?? '')) &&
                isExtensionApplicableToPaymentMethod(master, paymentMethod),
        )
        .sort(
            (left, right) =>
                Number(left.sort_order ?? 0) - Number(right.sort_order ?? 0),
        );

    const autoExtensions = applicableMasters.map((master, index) => {
        const calculationMode =
            String(master.calculation_mode ?? 'fixed') === 'percentage'
                ? 'percentage'
                : 'fixed';
        const calculationValue = Number(master.calculation_value ?? 0);
        const amount =
            calculationMode === 'percentage'
                ? (subtotalAmount * calculationValue) / 100
                : calculationValue;

        return {
            _key: `method-${String(master.id ?? `new-${index}`)}-${nanoid()}`,
            quotation_extension_master_id:
                Number(master.id ?? 0) > 0 ? Number(master.id) : null,
            name: String(master.name ?? 'Additional Charges'),
            type: String(master.type ?? 'other'),
            calculation_mode: calculationMode,
            calculation_value: calculationValue,
            amount,
        };
    });

    return {
        ...invoice,
        payment_method: paymentMethod,
        extensions: [...manualOrUnrelatedExtensions, ...autoExtensions].map(
            (extension, index) => ({
                ...extension,
                sort_order: index + 1,
            }),
        ),
    };
}

function isTaxExtension(extension: QuotationExtensionInput): boolean {
    return (
        String(extension.type ?? '')
            .trim()
            .toLowerCase() === 'tax'
    );
}

export function autoFillInvoiceDates(
    invoices: InvoiceSchema[],
    options?:
        | string
        | {
              defaultDate?: string;
              paymentPlan?: string | null;
              hasCustomerConfirmationMemberItem?: boolean;
              packageDepartureDate?: string | null;
          },
): InvoiceSchema[] {
    const resolvedOptions =
        typeof options === 'string' || options === undefined
            ? {
                  defaultDate: options,
                  paymentPlan: undefined,
                  hasCustomerConfirmationMemberItem: false,
                  packageDepartureDate: undefined,
              }
            : options;

    const {
        defaultDate,
        paymentPlan,
        hasCustomerConfirmationMemberItem,
        packageDepartureDate,
    } = resolvedOptions;

    const todayDate = formatDateForDisplay(new Date());

    if (hasCustomerConfirmationMemberItem) {
        if (String(paymentPlan ?? '').toLowerCase() === 'installment') {
            const parsedDepartureDate = parseDisplayDate(packageDepartureDate);

            return invoices.map((invoice, index) => {
                let targetDate = todayDate;

                if (index > 0 && parsedDepartureDate) {
                    const adjustedDate = new Date(parsedDepartureDate);

                    adjustedDate.setMonth(
                        adjustedDate.getMonth() - (index === 1 ? 3 : 2),
                    );

                    targetDate = formatDateForDisplay(adjustedDate);
                }

                return {
                    ...invoice,
                    invoice_date: invoice.invoice_date || targetDate,
                    due_date: invoice.due_date || targetDate,
                };
            });
        }

        return invoices.map((invoice) => ({
            ...invoice,
            invoice_date: invoice.invoice_date || todayDate,
            due_date: invoice.due_date || todayDate,
        }));
    }

    if (!defaultDate) {
        return invoices;
    }

    return invoices.map((invoice) => ({
        ...invoice,
        invoice_date: invoice.invoice_date || defaultDate,
        due_date: invoice.due_date || defaultDate,
    }));
}

function roundToCents(value: number): number {
    return Math.round(value * 100) / 100;
}

function sumExtensions(extensions: QuotationExtensionInput[] = []): number {
    return roundToCents(
        extensions.reduce(
            (sum, extension) =>
                isTaxExtension(extension)
                    ? sum
                    : sum + Number(extension.amount ?? 0),
            0,
        ),
    );
}

function applyExtensionToInvoices(
    invoices: InvoiceSchema[],
    extensionTotal: number,
): InvoiceSchema[] {
    if (!invoices.length || extensionTotal === 0) {
        return invoices;
    }

    const baseAmounts = invoices.map((invoice) => Number(invoice.amount ?? 0));
    const baseTotalAbs = baseAmounts.reduce(
        (sum, amount) => sum + Math.abs(amount),
        0,
    );

    const shares = invoices.map((_, index) => {
        if (index === invoices.length - 1) {
            return extensionTotal;
        }

        if (baseTotalAbs === 0) {
            return 0;
        }

        return roundToCents(
            extensionTotal * (Math.abs(baseAmounts[index]) / baseTotalAbs),
        );
    });

    const allocatedSoFar = shares
        .slice(0, Math.max(0, shares.length - 1))
        .reduce((sum, amount) => sum + amount, 0);

    shares[shares.length - 1] = roundToCents(extensionTotal - allocatedSoFar);

    return invoices.map((invoice, index) => ({
        ...invoice,
        amount: roundToCents(Number(invoice.amount ?? 0) + shares[index]),
    }));
}

function stripInstallmentSuffix(description?: string | null): string {
    if (!description) {
        return '';
    }

    return description.replace(/\s*\((Deposit|50%|Balance)\)$/i, '').trim();
}

function normalizeInstallmentInvoiceCount(
    value?: number | string | null,
): number {
    const parsed = Number(value ?? 3);

    if (!Number.isFinite(parsed)) {
        return 3;
    }

    return Math.max(3, Math.floor(parsed));
}

function getInstallmentOrdinalLabel(order: number): string {
    const labels: Record<number, string> = {
        4: 'Fourth',
        5: 'Fifth',
        6: 'Sixth',
        7: 'Seventh',
        8: 'Eighth',
        9: 'Ninth',
        10: 'Tenth',
    };

    return labels[order] ?? `${order}th`;
}

function getInstallmentInvoiceDescription(index: number): string {
    if (index === 0) {
        return 'Invoice For Deposit';
    }

    if (index === 1) {
        return 'Invoice For 50%';
    }

    if (index === 2) {
        return 'Invoice For Balance';
    }

    const order = index + 1;

    return `Invoice For ${getInstallmentOrdinalLabel(order)} Payment`;
}

function cloneItemsWithFreshKeys(
    items: InvoiceItemSchema[],
): InvoiceItemSchema[] {
    const keyMap = new Map<string, string>();

    items.forEach((item) => {
        const previousKey = String(item._key ?? nanoid());
        keyMap.set(previousKey, nanoid());
    });

    return items.map((item) => {
        const previousKey = String(item._key ?? '');
        const nextKey = keyMap.get(previousKey) ?? nanoid();
        const previousParentKey = String(item.parent_key ?? '');

        return {
            ...item,
            _key: nextKey,
            id: undefined,
            parent_key: previousParentKey
                ? (keyMap.get(previousParentKey) ?? item.parent_key)
                : null,
        };
    });
}

function mergeSplitInstallmentItems(
    items: InvoiceItemSchema[],
): InvoiceItemSchema[] {
    const getGroupingKey = (item: InvoiceItemSchema): string => {
        const memberId = Number(item.customer_confirmation_member_id ?? 0);

        return [
            memberId,
            item.parent_id ?? '',
            item.parent_key ?? '',
            stripInstallmentSuffix(item.description),
        ].join('|');
    };

    const grouped = new Map<
        string,
        {
            baseItem: InvoiceItemSchema;
            quantity: number;
            totalAmount: number;
            sortOrder: number;
        }
    >();
    const baseItemsByGroupKey = new Map<string, InvoiceItemSchema>();
    const groupKeysWithSplitItems = new Set<string>();

    const untouchedItems: InvoiceItemSchema[] = [];

    items.forEach((item) => {
        const originalDescription = (item.description ?? '').trim();
        const baseDescription = stripInstallmentSuffix(item.description);
        const hasInstallmentSuffix =
            originalDescription.length > 0 &&
            originalDescription !== baseDescription;
        const groupKey = getGroupingKey(item);

        if (item.is_header || !baseDescription) {
            untouchedItems.push(item);
            return;
        }

        if (!hasInstallmentSuffix) {
            baseItemsByGroupKey.set(groupKey, item);
            untouchedItems.push(item);
            return;
        }

        groupKeysWithSplitItems.add(groupKey);

        const quantity = Number(item.quantity ?? 0) || 1;
        const itemAmount = roundToCents(
            Number(item.amount ?? Number(item.rate ?? 0) * quantity),
        );

        if (!grouped.has(groupKey)) {
            grouped.set(groupKey, {
                baseItem: item,
                quantity,
                totalAmount: itemAmount,
                sortOrder: Number(item.sort_order ?? 0),
            });
            return;
        }

        const current = grouped.get(groupKey);

        if (!current) {
            return;
        }

        current.totalAmount = roundToCents(current.totalAmount + itemAmount);
        current.sortOrder = Math.min(
            current.sortOrder || Number(item.sort_order ?? 0),
            Number(item.sort_order ?? 0) || current.sortOrder,
        );
    });

    const mergedItems = Array.from(grouped.values()).map((group) => {
        const groupKey = getGroupingKey(group.baseItem);
        const baseItem = baseItemsByGroupKey.get(groupKey) ?? group.baseItem;
        const normalizedQuantity = group.quantity || 1;
        const normalizedRate =
            normalizedQuantity > 0
                ? roundToCents(group.totalAmount / normalizedQuantity)
                : 0;

        return {
            ...baseItem,
            description: stripInstallmentSuffix(baseItem.description),
            quantity: normalizedQuantity,
            rate: normalizedRate,
            amount: group.totalAmount,
            sort_order: group.sortOrder || baseItem.sort_order,
        };
    });

    const filteredUntouchedItems = untouchedItems.filter((item) => {
        if (item.is_header) {
            return true;
        }

        const originalDescription = (item.description ?? '').trim();
        const baseDescription = stripInstallmentSuffix(item.description);
        const hasInstallmentSuffix =
            originalDescription.length > 0 &&
            originalDescription !== baseDescription;

        if (hasInstallmentSuffix) {
            return false;
        }

        return !groupKeysWithSplitItems.has(getGroupingKey(item));
    });

    return [...filteredUntouchedItems, ...mergedItems].sort(
        (a, b) => Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0),
    );
}

function ensureHeadersForItems(
    sourceItems: InvoiceItemSchema[],
    lineItems: InvoiceItemSchema[],
    baseItems: InvoiceItemSchema[] = [],
): InvoiceItemSchema[] {
    if (lineItems.length === 0) {
        return [...baseItems].sort(
            (a, b) => Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0),
        );
    }

    const headerById = new Map<number, InvoiceItemSchema>();
    const headerByKey = new Map<string, InvoiceItemSchema>();

    sourceItems.forEach((item) => {
        if (!item.is_header) {
            return;
        }

        const itemId = Number(item.id ?? 0);

        if (itemId > 0) {
            headerById.set(itemId, item);
        }

        if (item._key) {
            headerByKey.set(String(item._key), item);
        }
    });

    const existingHeaderKeys = new Set(
        baseItems
            .filter((item) => item.is_header)
            .map((item) => String(item._key ?? item.id ?? '')),
    );

    const neededHeaders = new Map<string, InvoiceItemSchema>();

    const collectHeaderAncestors = (item: InvoiceItemSchema) => {
        let cursorId = Number(item.parent_id ?? 0);
        let cursorKey = String(item.parent_key ?? '');

        while (cursorId > 0 || cursorKey) {
            const header =
                (cursorId > 0 ? headerById.get(cursorId) : undefined) ??
                (cursorKey ? headerByKey.get(cursorKey) : undefined);

            if (!header || !header.is_header) {
                break;
            }

            const identity = String(header._key ?? header.id ?? '');

            if (!existingHeaderKeys.has(identity)) {
                neededHeaders.set(identity, {
                    ...header,
                    id: undefined,
                    _key: header._key ?? nanoid(),
                });
            }

            cursorId = Number(header.parent_id ?? 0);
            cursorKey = String(header.parent_key ?? '');
        }
    };

    lineItems.forEach((item) => {
        collectHeaderAncestors(item);
    });

    return [
        ...baseItems,
        ...Array.from(neededHeaders.values()),
        ...lineItems,
    ].sort((a, b) => Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0));
}

function buildInstallmentItems(
    items: InvoiceItemSchema[],
    depositType?: string | null,
    depositValue?: number | string | null,
    installmentInvoiceCount?: number | string | null,
): {
    depositItems: InvoiceItemSchema[];
    fiftyPercentItems: InvoiceItemSchema[];
    balanceItems: InvoiceItemSchema[];
    additionalInstallmentInvoiceItems: InvoiceItemSchema[][];
} {
    const normalizedInvoiceCount = normalizeInstallmentInvoiceCount(
        installmentInvoiceCount,
    );
    const additionalInvoiceCount = Math.max(0, normalizedInvoiceCount - 3);
    const normalizedSourceItems = mergeSplitInstallmentItems(items);

    const lineItems = normalizedSourceItems.filter((item) => !item.is_header);

    const headersById = new Map<number, InvoiceItemSchema>();
    const headersByKey = new Map<string, InvoiceItemSchema>();

    normalizedSourceItems.forEach((item) => {
        if (!item.is_header) {
            return;
        }

        const itemId = Number(item.id ?? 0);

        if (itemId > 0) {
            headersById.set(itemId, item);
        }

        if (item._key) {
            headersByKey.set(String(item._key), item);
        }
    });

    if (!lineItems.length) {
        return {
            depositItems: normalizedSourceItems,
            fiftyPercentItems: [],
            balanceItems: [],
            additionalInstallmentInvoiceItems: Array.from(
                { length: additionalInvoiceCount },
                () => [],
            ),
        };
    }

    const lineItemsWithAmounts = lineItems.map((item) => {
        const quantity = Number(item.quantity ?? 0);
        const rate = Number(item.rate ?? 0);
        const fallbackAmount = roundToCents(quantity * rate);
        const amount = roundToCents(Number(item.amount ?? fallbackAmount));

        return {
            ...item,
            amount,
        };
    });

    const headerItems = normalizedSourceItems.filter((item) => item.is_header);
    const headerTemplateItems = headerItems.map((item) => ({
        ...item,
        id: undefined,
    }));

    const numericDepositValue = Number(depositValue ?? 0);
    const depositSectionLines: InvoiceItemSchema[] = [];
    const fiftyPercentSectionLines: InvoiceItemSchema[] = [];
    const balanceSectionLines: InvoiceItemSchema[] = [];
    const clampToItemAmount = (value: number, sourceAmount: number): number => {
        return sourceAmount >= 0
            ? Math.min(value, sourceAmount)
            : Math.max(value, sourceAmount);
    };

    const totalLineAmount = roundToCents(
        lineItemsWithAmounts.reduce(
            (sum, item) => sum + Number(item.amount ?? 0),
            0,
        ),
    );

    const fixedDepositTarget =
        depositType === 'fixed' && numericDepositValue > 0
            ? roundToCents(Math.min(numericDepositValue, totalLineAmount))
            : 0;

    const fixedDepositAllocations =
        fixedDepositTarget > 0
            ? (() => {
                  let remaining = fixedDepositTarget;

                  return lineItemsWithAmounts.map((item, index) => {
                      const amount = roundToCents(Number(item.amount ?? 0));

                      if (amount <= 0 || remaining <= 0) {
                          return 0;
                      }

                      if (index === lineItemsWithAmounts.length - 1) {
                          const allocation = roundToCents(
                              Math.min(amount, remaining),
                          );
                          remaining = roundToCents(remaining - allocation);

                          return allocation;
                      }

                      const proportionalShare = roundToCents(
                          (amount / totalLineAmount) * fixedDepositTarget,
                      );
                      const allocation = roundToCents(
                          Math.min(amount, proportionalShare, remaining),
                      );
                      remaining = roundToCents(remaining - allocation);

                      return allocation;
                  });
              })()
            : [];

    lineItemsWithAmounts.forEach((item, index) => {
        const quantity = Number(item.quantity ?? 0) || 1;
        const amount = roundToCents(Number(item.amount ?? 0));
        const perItemDepositAmount =
            depositType === 'percentage' && numericDepositValue > 0
                ? roundToCents(amount * (numericDepositValue / 100))
                : depositType === 'fixed' && fixedDepositTarget > 0
                  ? roundToCents(
                        clampToItemAmount(
                            Number(fixedDepositAllocations[index] ?? 0),
                            amount,
                        ),
                    )
                  : 0;

        const depositAmount = clampToItemAmount(perItemDepositAmount, amount);
        const fiftyPercentTarget = roundToCents(amount * 0.5);
        const remainingAfterDeposit = roundToCents(amount - depositAmount);
        const fiftyPercentAmount = clampToItemAmount(
            fiftyPercentTarget,
            remainingAfterDeposit,
        );
        const balanceAmount = roundToCents(
            amount - depositAmount - fiftyPercentAmount,
        );

        if (depositAmount !== 0) {
            depositSectionLines.push({
                ...item,
                _key: nanoid(),
                id: undefined,
                parent_id: item.parent_id ?? null,
                parent_key: item.parent_key ?? null,
                description: `${item.description ?? 'Package'} (Deposit)`,
                quantity,
                rate: roundToCents(depositAmount / quantity),
                amount: depositAmount,
            });
        }

        if (fiftyPercentAmount !== 0) {
            fiftyPercentSectionLines.push({
                ...item,
                _key: nanoid(),
                id: undefined,
                parent_id: item.parent_id ?? null,
                parent_key: item.parent_key ?? null,
                description: `${item.description ?? 'Package'} (50%)`,
                quantity,
                rate: roundToCents(fiftyPercentAmount / quantity),
                amount: fiftyPercentAmount,
            });
        }

        if (balanceAmount !== 0) {
            balanceSectionLines.push({
                ...item,
                _key: nanoid(),
                id: undefined,
                parent_id: item.parent_id ?? null,
                parent_key: item.parent_key ?? null,
                description: `${item.description ?? 'Package'} (Balance)`,
                quantity,
                rate: roundToCents(balanceAmount / quantity),
                amount: balanceAmount,
            });
        }
    });

    const depositItems = ensureHeadersForItems(
        normalizedSourceItems,
        depositSectionLines,
        headerTemplateItems,
    );
    const fiftyPercentItems = ensureHeadersForItems(
        normalizedSourceItems,
        fiftyPercentSectionLines,
    );
    const balanceItems = ensureHeadersForItems(
        normalizedSourceItems,
        balanceSectionLines,
    );

    const additionalInstallmentInvoiceItems = Array.from(
        { length: additionalInvoiceCount },
        () => cloneItemsWithFreshKeys([]),
    );

    return {
        depositItems,
        fiftyPercentItems,
        balanceItems,
        additionalInstallmentInvoiceItems,
    };
}

export function buildInvoicesFromItems(
    paymentPlan: string,
    items: InvoiceItemSchema[],
    totalAmount?: number,
    depositType?: string | null,
    depositValue?: number | string | null,
    extensions: QuotationExtensionInput[] = [],
    installmentInvoiceCount?: number | string | null,
): InvoiceSchema[] {
    let invoices: InvoiceSchema[] = [];
    const extensionTotal = sumExtensions(extensions);

    const sourceItems =
        paymentPlan === 'full' || paymentPlan === 'direct'
            ? mergeSplitInstallmentItems(items)
            : items;

    const amount =
        totalAmount ??
        roundToCents(calculateTotal(sourceItems) + extensionTotal);

    if (paymentPlan === 'direct') {
        invoices = [
            {
                _key: nanoid(),
                description: null,
                items: sourceItems,
                amount,
            },
        ];
    } else if (paymentPlan === 'full') {
        invoices = [
            {
                _key: nanoid(),
                description: 'Invoice For Full Payment',
                items: sourceItems,
                amount,
            },
        ];
    } else if (paymentPlan === 'installment') {
        const {
            depositItems,
            fiftyPercentItems,
            balanceItems,
            additionalInstallmentInvoiceItems,
        } = buildInstallmentItems(
            items,
            depositType,
            depositValue,
            installmentInvoiceCount,
        );

        const depositAmount = roundToCents(calculateTotal(depositItems));
        const fiftyPercentAmount = roundToCents(
            calculateTotal(fiftyPercentItems),
        );
        const balanceAmount = roundToCents(calculateTotal(balanceItems));

        const primaryInvoices = [
            {
                _key: nanoid(),
                description: getInstallmentInvoiceDescription(0),
                items: depositItems,
                amount: depositAmount,
            },
            {
                _key: nanoid(),
                description: getInstallmentInvoiceDescription(1),
                items: fiftyPercentItems,
                amount: fiftyPercentAmount,
            },
            {
                _key: nanoid(),
                description: getInstallmentInvoiceDescription(2),
                items: balanceItems,
                amount: balanceAmount,
            },
        ];

        const additionalInvoices = additionalInstallmentInvoiceItems.map(
            (additionalItems, additionalIndex) => ({
                _key: nanoid(),
                description: getInstallmentInvoiceDescription(
                    additionalIndex + 3,
                ),
                items: additionalItems,
                amount: roundToCents(calculateTotal(additionalItems)),
            }),
        );

        invoices = [...primaryInvoices, ...additionalInvoices];
    }

    if (paymentPlan === 'installment') {
        return applyExtensionToInvoices(invoices, extensionTotal);
    }

    return invoices;
}

export type ApplyInvoiceNumberingOptions = {
    sourceInvoices?: InvoiceSchema[];
    seededNumbers?: string[];
    preferredFormatId?: number | null;
};

function isMissingInvoiceNumber(invoiceNumber?: string | null): boolean {
    const normalizedNumber = String(invoiceNumber ?? '').trim();

    return normalizedNumber.length === 0 || normalizedNumber === '-';
}

function escapeRegexSegment(value: string): string {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function extractIncrementFromInvoiceNumber(
    number: string,
    formatName: string,
): number | null {
    const tokens = formatName.split(/(%DD%|%MM%|%YYYY%|%YY%|%I%)/g);

    if (!tokens.includes('%I%')) {
        return null;
    }

    let regexPattern = '^';

    tokens.forEach((token) => {
        if (token === '%DD%' || token === '%MM%' || token === '%YY%') {
            regexPattern += '(?:\\d{2})';
            return;
        }

        if (token === '%YYYY%') {
            regexPattern += '(?:\\d{4})';
            return;
        }

        if (token === '%I%') {
            regexPattern += '(\\d+)';
            return;
        }

        regexPattern += escapeRegexSegment(token);
    });

    regexPattern += '$';

    const match = number.match(new RegExp(regexPattern));

    if (!match || !match[1]) {
        return null;
    }

    const parsed = Number(match[1]);

    return Number.isFinite(parsed) ? parsed : null;
}

function extractIncrementFromSuggestedNumber(
    suggestedNumber: string,
    formatName?: string,
): number | null {
    const normalizedSuggestion = String(suggestedNumber ?? '').trim();

    if (normalizedSuggestion.length === 0) {
        return null;
    }

    if (formatName) {
        const parsedFromFormat = extractIncrementFromInvoiceNumber(
            normalizedSuggestion,
            formatName,
        );

        if (parsedFromFormat !== null) {
            return parsedFromFormat;
        }
    }

    const trailingIncrementMatch = normalizedSuggestion.match(/(\d+)$/);

    if (!trailingIncrementMatch?.[1]) {
        return null;
    }

    const parsed = Number(trailingIncrementMatch[1]);

    return Number.isFinite(parsed) ? parsed : null;
}

function buildInvoiceNumberFromSuggestion(
    suggestedNumber: string,
    increment: number,
): string {
    const normalizedSuggestion = String(suggestedNumber ?? '').trim();

    if (normalizedSuggestion.length === 0) {
        return String(Math.max(1, increment));
    }

    const trailingIncrementMatch = normalizedSuggestion.match(/^(.*?)(\d+)$/);

    if (!trailingIncrementMatch) {
        return `${normalizedSuggestion}-${Math.max(1, increment)}`;
    }

    const prefix = trailingIncrementMatch[1] ?? '';
    const padding = String(trailingIncrementMatch[2] ?? '').length;
    const nextSequence = String(Math.max(1, increment)).padStart(
        Math.max(1, padding),
        '0',
    );

    return `${prefix}${nextSequence}`;
}

export function buildSequentialInvoiceNumbersFromSeed(
    seedNumber: string,
    count: number,
): string[] {
    const normalizedSeedNumber = String(seedNumber ?? '').trim();
    const resolvedCount = Math.max(0, Math.floor(Number(count ?? 0)));

    if (
        resolvedCount === 0 ||
        normalizedSeedNumber.length === 0 ||
        normalizedSeedNumber === '-'
    ) {
        return [];
    }

    const baseIncrement =
        extractIncrementFromSuggestedNumber(normalizedSeedNumber);

    if (baseIncrement === null) {
        return Array.from({ length: resolvedCount }, (_, index) => {
            if (index === 0) {
                return normalizedSeedNumber;
            }

            return `${normalizedSeedNumber}-${index + 1}`;
        });
    }

    return Array.from({ length: resolvedCount }, (_, index) =>
        buildInvoiceNumberFromSuggestion(
            normalizedSeedNumber,
            baseIncrement + index,
        ),
    );
}

function findFirstInvoiceWithNumber(
    invoices: InvoiceSchema[] = [],
): InvoiceSchema | null {
    return (
        invoices.find(
            (invoice) => !isMissingInvoiceNumber(invoice.invoice_number),
        ) ?? null
    );
}

function findFirstInvoiceWithParsableIncrement(
    invoices: InvoiceSchema[] = [],
): InvoiceSchema | null {
    return (
        invoices.find((invoice) => {
            const invoiceNumber = String(invoice.invoice_number ?? '').trim();

            if (isMissingInvoiceNumber(invoiceNumber)) {
                return false;
            }

            return extractIncrementFromSuggestedNumber(invoiceNumber) !== null;
        }) ?? null
    );
}

export function applyInvoiceNumberingSequence(
    invoices: InvoiceSchema[],
    options: ApplyInvoiceNumberingOptions = {},
): InvoiceSchema[] {
    if (invoices.length === 0) {
        return invoices;
    }

    const {
        sourceInvoices = [],
        seededNumbers = [],
        preferredFormatId = null,
    } = options;

    const normalizedSeededNumbers = seededNumbers.map((number) =>
        String(number ?? '').trim(),
    );

    const seededInvoices = invoices.map((invoice, index) => {
        const currentInvoiceNumber = String(
            invoice.invoice_number ?? '',
        ).trim();
        const seededInvoiceNumber = String(
            normalizedSeededNumbers[index] ?? '',
        ).trim();

        const resolvedNumber = !isMissingInvoiceNumber(currentInvoiceNumber)
            ? currentInvoiceNumber
            : seededInvoiceNumber;
        const resolvedFormatId =
            typeof invoice.number_format_id === 'number' &&
            Number.isFinite(invoice.number_format_id)
                ? invoice.number_format_id
                : preferredFormatId;

        return {
            ...invoice,
            number_format_id: resolvedFormatId,
            invoice_number: resolvedNumber,
        };
    });

    const anchorInvoice =
        findFirstInvoiceWithParsableIncrement(sourceInvoices) ??
        findFirstInvoiceWithParsableIncrement(seededInvoices) ??
        findFirstInvoiceWithNumber(sourceInvoices) ??
        findFirstInvoiceWithNumber(seededInvoices);

    if (!anchorInvoice) {
        return seededInvoices;
    }

    const anchorInvoiceNumber = String(
        anchorInvoice.invoice_number ?? '',
    ).trim();
    const anchorFormatId =
        anchorInvoice.number_format_id ?? preferredFormatId ?? null;
    const initialIncrement =
        extractIncrementFromSuggestedNumber(anchorInvoiceNumber);

    if (initialIncrement === null) {
        return seededInvoices;
    }

    let nextIncrement = initialIncrement;

    const seenNumbers = new Set<string>();

    return seededInvoices.map((invoice, index) => {
        const currentInvoiceNumber = String(
            invoice.invoice_number ?? '',
        ).trim();
        const isDuplicate =
            !isMissingInvoiceNumber(currentInvoiceNumber) &&
            seenNumbers.has(currentInvoiceNumber);
        const shouldGenerateNumber =
            isMissingInvoiceNumber(currentInvoiceNumber) || isDuplicate;

        if (!shouldGenerateNumber) {
            seenNumbers.add(currentInvoiceNumber);

            const currentIncrement =
                extractIncrementFromSuggestedNumber(currentInvoiceNumber);

            if (currentIncrement !== null) {
                nextIncrement = Math.max(nextIncrement, currentIncrement);
            }

            return invoice;
        }

        if (index === 0 && isMissingInvoiceNumber(currentInvoiceNumber)) {
            seenNumbers.add(anchorInvoiceNumber);

            return {
                ...invoice,
                number_format_id: invoice.number_format_id ?? anchorFormatId,
                invoice_number: anchorInvoiceNumber,
            };
        }

        nextIncrement += 1;

        const generatedNumber = buildInvoiceNumberFromSuggestion(
            anchorInvoiceNumber,
            nextIncrement,
        );
        seenNumbers.add(generatedNumber);

        return {
            ...invoice,
            number_format_id: invoice.number_format_id ?? anchorFormatId,
            invoice_number: generatedNumber,
        };
    });
}

export function quotationItemsToInvoiceItems(
    quotation: QuotationSchema,
): InvoiceItemSchema[] {
    if (!quotation.items?.length) return [];

    const keyMapById = new Map<number, string>();
    const resolvedKeys = quotation.items.map((item, index) => {
        return (
            item._key ?? (item.id ? `id-${item.id}` : `quotation-item-${index}`)
        );
    });

    quotation.items.forEach((item, index) => {
        if (item.id) {
            keyMapById.set(item.id, resolvedKeys[index]);
        }
    });

    return quotation.items.map((item, index) => ({
        _key: resolvedKeys[index],
        id: item.id ?? undefined,
        parent_id: item.parent_id ?? null,
        customer_confirmation_member_id:
            item.customer_confirmation_member_id ?? null,
        sharing_plan: item.sharing_plan ?? null,
        parent_key:
            item.parent_key ??
            (item.parent_id ? (keyMapById.get(item.parent_id) ?? null) : null),
        description: item.description,
        is_header: item.is_header,
        quantity: item.quantity,
        rate: item.rate,
        taxes: (item.taxes ?? []).map((tax, taxIndex) => ({
            _key:
                tax._key ??
                (tax.id ? `tax-${tax.id}` : `tax-${index}-${taxIndex}`),
            id: tax.id,
            quotation_item_id: tax.quotation_item_id ?? null,
            quotation_extension_master_id:
                tax.quotation_extension_master_id ?? null,
            name: tax.name ?? null,
            calculation_mode: tax.calculation_mode ?? null,
            calculation_value: tax.calculation_value ?? null,
            sort_order: tax.sort_order ?? taxIndex + 1,
        })),
        amount: item.amount,
        sort_order: item.sort_order,
    }));
}

export function buildInitialInvoices(
    quotation: QuotationSchema,
    installmentInvoiceCount?: number | string | null,
): InvoiceSchema[] {
    const items = quotationItemsToInvoiceItems(quotation);

    return buildInvoicesFromItems(
        quotation.payment_plan ?? 'full',
        items,
        Number(quotation.total_amount),
        undefined,
        undefined,
        quotation.extensions ?? [],
        installmentInvoiceCount,
    );
}

export function buildInvoices(
    paymentPlan: string,
    previousInvoices: InvoiceSchema[],
    depositType?: string | null,
    depositValue?: number | string | null,
    installmentInvoiceCount?: number | string | null,
): InvoiceSchema[] {
    const items = collectAllItems(previousInvoices);

    return buildInvoicesFromItems(
        paymentPlan,
        items,
        undefined,
        depositType,
        depositValue,
        [],
        installmentInvoiceCount,
    );
}
