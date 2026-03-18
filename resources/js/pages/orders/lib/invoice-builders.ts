import { calculateTotal, collectAllItems } from '@/pages/invoices/lib/utils';
import { InvoiceItemSchema, InvoiceSchema } from '@/pages/invoices/schema';
import { QuotationSchema } from '@/pages/quotations/schema';
import { nanoid } from 'nanoid';

type QuotationExtensionInput = {
    amount?: number | string | null;
};

export function autoFillInvoiceDates(
    invoices: InvoiceSchema[],
    defaultDate?: string,
): InvoiceSchema[] {
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
            (sum, extension) => sum + Number(extension.amount ?? 0),
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

function mergeSplitInstallmentItems(
    items: InvoiceItemSchema[],
): InvoiceItemSchema[] {
    const grouped = new Map<
        string,
        {
            baseItem: InvoiceItemSchema;
            quantity: number;
            totalAmount: number;
            sortOrder: number;
        }
    >();

    const untouchedItems: InvoiceItemSchema[] = [];

    items.forEach((item) => {
        const memberId = Number(item.customer_confirmation_member_id ?? 0);
        const originalDescription = (item.description ?? '').trim();
        const baseDescription = stripInstallmentSuffix(item.description);
        const hasInstallmentSuffix =
            originalDescription.length > 0 &&
            originalDescription !== baseDescription;

        if (
            item.is_header ||
            memberId <= 0 ||
            !baseDescription ||
            !hasInstallmentSuffix
        ) {
            untouchedItems.push(item);
            return;
        }

        const groupKey = [
            memberId,
            item.parent_id ?? '',
            item.parent_key ?? '',
            baseDescription,
        ].join('|');

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
        const normalizedQuantity = group.quantity || 1;
        const normalizedRate =
            normalizedQuantity > 0
                ? roundToCents(group.totalAmount / normalizedQuantity)
                : 0;

        return {
            ...group.baseItem,
            description: stripInstallmentSuffix(group.baseItem.description),
            quantity: normalizedQuantity,
            rate: normalizedRate,
            amount: group.totalAmount,
            sort_order: group.sortOrder || group.baseItem.sort_order,
        };
    });

    return [...untouchedItems, ...mergedItems].sort(
        (a, b) => Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0),
    );
}

function buildInstallmentItems(
    items: InvoiceItemSchema[],
    depositType?: string | null,
    depositValue?: number | string | null,
): {
    depositItems: InvoiceItemSchema[];
    fiftyPercentItems: InvoiceItemSchema[];
    balanceItems: InvoiceItemSchema[];
} {
    const normalizedSourceItems = mergeSplitInstallmentItems(items);

    const packageItems = normalizedSourceItems.filter(
        (item) =>
            !item.is_header &&
            Number(item.customer_confirmation_member_id ?? 0) > 0,
    );

    if (!packageItems.length) {
        return {
            depositItems: normalizedSourceItems,
            fiftyPercentItems: [],
            balanceItems: [],
        };
    }

    const packageItemsWithAmounts = packageItems.map((item) => {
        const quantity = Number(item.quantity ?? 0);
        const rate = Number(item.rate ?? 0);
        const fallbackAmount = roundToCents(quantity * rate);
        const amount = roundToCents(Number(item.amount ?? fallbackAmount));

        return {
            ...item,
            amount,
        };
    });

    const nonPackageItems = normalizedSourceItems.filter(
        (item) =>
            item.is_header ||
            Number(item.customer_confirmation_member_id ?? 0) <= 0,
    );

    const numericDepositValue = Number(depositValue ?? 0);
    const depositItems: InvoiceItemSchema[] = [...nonPackageItems];
    const fiftyPercentItems: InvoiceItemSchema[] = [];
    const balanceItems: InvoiceItemSchema[] = [];

    packageItemsWithAmounts.forEach((item) => {
        const quantity = Number(item.quantity ?? 0) || 1;
        const amount = roundToCents(Number(item.amount ?? 0));
        const perItemDepositAmount =
            depositType === 'percentage' && numericDepositValue > 0
                ? roundToCents(amount * (numericDepositValue / 100))
                : depositType === 'fixed' && numericDepositValue > 0
                  ? roundToCents(
                        Math.min(numericDepositValue, Number(item.amount ?? 0)),
                    )
                  : 0;

        const depositAmount = Math.min(perItemDepositAmount, amount);
        const fiftyPercentTarget = roundToCents(amount * 0.5);
        const remainingAfterDeposit = roundToCents(amount - depositAmount);
        const fiftyPercentAmount = Math.min(
            fiftyPercentTarget,
            remainingAfterDeposit,
        );
        const balanceAmount = roundToCents(
            amount - depositAmount - fiftyPercentAmount,
        );

        if (depositAmount > 0) {
            depositItems.push({
                ...item,
                _key: nanoid(),
                id: undefined,
                parent_id: null,
                parent_key: null,
                description: `${item.description ?? 'Package'} (Deposit)`,
                quantity,
                rate: roundToCents(depositAmount / quantity),
                amount: depositAmount,
            });
        }

        if (fiftyPercentAmount > 0) {
            fiftyPercentItems.push({
                ...item,
                _key: nanoid(),
                id: undefined,
                parent_id: null,
                parent_key: null,
                description: `${item.description ?? 'Package'} (50%)`,
                quantity,
                rate: roundToCents(fiftyPercentAmount / quantity),
                amount: fiftyPercentAmount,
            });
        }

        if (balanceAmount > 0) {
            balanceItems.push({
                ...item,
                _key: nanoid(),
                id: undefined,
                parent_id: null,
                parent_key: null,
                description: `${item.description ?? 'Package'} (Balance)`,
                quantity,
                rate: roundToCents(balanceAmount / quantity),
                amount: balanceAmount,
            });
        }
    });

    return { depositItems, fiftyPercentItems, balanceItems };
}

export function buildInvoicesFromItems(
    paymentPlan: string,
    items: InvoiceItemSchema[],
    totalAmount?: number,
    depositType?: string | null,
    depositValue?: number | string | null,
    extensions: QuotationExtensionInput[] = [],
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
        const { depositItems, fiftyPercentItems, balanceItems } =
            buildInstallmentItems(items, depositType, depositValue);

        const depositAmount = roundToCents(calculateTotal(depositItems));
        const fiftyPercentAmount = roundToCents(
            calculateTotal(fiftyPercentItems),
        );
        const balanceAmount = roundToCents(calculateTotal(balanceItems));

        invoices = [
            {
                _key: nanoid(),
                description: 'Invoice For Deposit',
                items: depositItems,
                amount: depositAmount,
            },
            {
                _key: nanoid(),
                description: 'Invoice For 50%',
                items: fiftyPercentItems,
                amount: fiftyPercentAmount,
            },
            {
                _key: nanoid(),
                description: 'Invoice For Balance',
                items: balanceItems,
                amount: balanceAmount,
            },
        ];
    }

    if (paymentPlan === 'installment') {
        return applyExtensionToInvoices(invoices, extensionTotal);
    }

    return invoices;
}

export function quotationItemsToInvoiceItems(
    quotation: QuotationSchema,
): InvoiceItemSchema[] {
    if (!quotation.items?.length) return [];

    const keyMap = new Map<number, string>();

    quotation.items.forEach((item) => {
        if (item.id) keyMap.set(item.id, `id-${item.id}`);
    });

    return quotation.items.map((item) => ({
        _key: item.id ? `id-${item.id}` : nanoid(),
        id: item.id ?? undefined,
        parent_id: item.parent_id ?? null,
        customer_confirmation_member_id:
            item.customer_confirmation_member_id ?? null,
        sharing_plan: item.sharing_plan ?? null,
        parent_key: item.parent_id
            ? (keyMap.get(item.parent_id) ?? null)
            : null,
        description: item.description,
        is_header: item.is_header,
        quantity: item.quantity,
        rate: item.rate,
        amount: item.amount,
        sort_order: item.sort_order,
    }));
}

export function buildInitialInvoices(
    quotation: QuotationSchema,
): InvoiceSchema[] {
    const items = quotationItemsToInvoiceItems(quotation);

    return buildInvoicesFromItems(
        quotation.payment_plan ?? 'full',
        items,
        Number(quotation.total_amount),
        undefined,
        undefined,
        quotation.extensions ?? [],
    );
}

export function buildInvoices(
    paymentPlan: string,
    previousInvoices: InvoiceSchema[],
    depositType?: string | null,
    depositValue?: number | string | null,
): InvoiceSchema[] {
    const items = collectAllItems(previousInvoices);

    return buildInvoicesFromItems(
        paymentPlan,
        items,
        undefined,
        depositType,
        depositValue,
    );
}
