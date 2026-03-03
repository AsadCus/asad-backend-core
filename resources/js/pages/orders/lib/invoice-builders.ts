import { calculateTotal, collectAllItems } from '@/pages/invoices/lib/utils';
import { InvoiceItemSchema, InvoiceSchema } from '@/pages/invoices/schema';
import { QuotationSchema } from '@/pages/quotations/schema';
import { nanoid } from 'nanoid';

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

function buildInstallmentItems(
    items: InvoiceItemSchema[],
    depositType?: string | null,
    depositValue?: number | string | null,
): { depositItems: InvoiceItemSchema[]; balanceItems: InvoiceItemSchema[] } {
    const packageItems = items.filter(
        (item) =>
            !item.is_header &&
            Number(item.customer_confirmation_member_id ?? 0) > 0,
    );

    if (!packageItems.length) {
        return {
            depositItems: items,
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

    const packageTotal = roundToCents(
        packageItemsWithAmounts.reduce(
            (sum, item) => sum + Number(item.amount ?? 0),
            0,
        ),
    );
    const nonPackageItems = items.filter(
        (item) =>
            item.is_header ||
            Number(item.customer_confirmation_member_id ?? 0) <= 0,
    );

    let depositAmount = 0;
    const numericDepositValue = Number(depositValue ?? 0);

    if (depositType === 'percentage' && numericDepositValue > 0) {
        depositAmount = roundToCents(
            packageTotal * (numericDepositValue / 100),
        );
    } else if (depositType === 'fixed' && numericDepositValue > 0) {
        depositAmount = Math.min(
            roundToCents(numericDepositValue),
            packageTotal,
        );
    }

    if (depositAmount <= 0) {
        return {
            depositItems: nonPackageItems,
            balanceItems: packageItemsWithAmounts,
        };
    }

    const perItemDeposit = roundToCents(
        depositAmount / packageItemsWithAmounts.length,
    );
    let allocated = 0;
    const depositItems: InvoiceItemSchema[] = [...nonPackageItems];
    const balanceItems: InvoiceItemSchema[] = [];

    packageItemsWithAmounts.forEach((item, index) => {
        const quantity = Number(item.quantity ?? 0) || 1;
        const amount = roundToCents(Number(item.amount ?? 0));

        const lineDepositAmount =
            index === packageItemsWithAmounts.length - 1
                ? roundToCents(depositAmount - allocated)
                : Math.min(perItemDeposit, amount);

        allocated = roundToCents(allocated + lineDepositAmount);

        const lineBalanceAmount = roundToCents(amount - lineDepositAmount);

        if (lineDepositAmount > 0) {
            depositItems.push({
                ...item,
                _key: nanoid(),
                id: undefined,
                parent_id: null,
                parent_key: null,
                description: `${item.description ?? 'Package'} (Deposit)`,
                quantity,
                rate: roundToCents(lineDepositAmount / quantity),
                amount: lineDepositAmount,
            });
        }

        if (lineBalanceAmount > 0) {
            balanceItems.push({
                ...item,
                _key: nanoid(),
                id: undefined,
                parent_id: null,
                parent_key: null,
                description: `${item.description ?? 'Package'} (Balance)`,
                quantity,
                rate: roundToCents(lineBalanceAmount / quantity),
                amount: lineBalanceAmount,
            });
        }
    });

    return { depositItems, balanceItems };
}

export function buildInvoicesFromItems(
    paymentPlan: string,
    items: InvoiceItemSchema[],
    totalAmount?: number,
    depositType?: string | null,
    depositValue?: number | string | null,
): InvoiceSchema[] {
    let invoices: InvoiceSchema[] = [];

    const amount = totalAmount ?? calculateTotal(items);

    if (paymentPlan === 'direct') {
        invoices = [
            {
                _key: nanoid(),
                description: null,
                items,
                amount,
            },
        ];
    } else if (paymentPlan === 'full') {
        invoices = [
            {
                _key: nanoid(),
                description: 'Invoice For Full Payment',
                items,
                amount,
            },
        ];
    } else if (paymentPlan === 'installment') {
        const { depositItems, balanceItems } = buildInstallmentItems(
            items,
            depositType,
            depositValue,
        );

        const depositAmount = roundToCents(calculateTotal(depositItems));
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
                description: 'Invoice For Balance',
                items: balanceItems,
                amount: balanceAmount,
            },
        ];
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
