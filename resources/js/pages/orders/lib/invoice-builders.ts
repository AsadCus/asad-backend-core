import { formatDateForDisplay } from '@/lib/utils';
import { calculateTotal, collectAllItems } from '@/pages/invoices/lib/utils';
import { InvoiceItemSchema, InvoiceSchema } from '@/pages/invoices/schema';
import { QuotationSchema } from '@/pages/quotations/schema';
import { addMonths, setDate } from 'date-fns';
import { nanoid } from 'nanoid';

function calculateInvoiceDates(
    invoiceIndex: number,
    handoverDate: string,
): { invoice_date: string; due_date: string } {
    if (!handoverDate) {
        return { invoice_date: '', due_date: '' };
    }

    const handover = new Date(handoverDate);

    if (isNaN(handover.getTime())) {
        return { invoice_date: '', due_date: '' };
    }

    let invoiceDate: Date;
    let dueDate: Date;

    if (invoiceIndex === 0) {
        invoiceDate = new Date();
        dueDate = new Date();
    } else if (invoiceIndex === 1) {
        invoiceDate = new Date(handover);
        dueDate = new Date(handover);
    } else {
        invoiceDate = setDate(addMonths(handover, invoiceIndex - 1), 20);
        dueDate = invoiceDate;
    }

    return {
        invoice_date: formatDateForDisplay(invoiceDate),
        due_date: formatDateForDisplay(dueDate),
    };
}

export function autoFillInvoiceDates(
    invoices: InvoiceSchema[],
    handoverDate: string,
): InvoiceSchema[] {
    if (!handoverDate) return invoices;

    return invoices.map((invoice, index) => {
        const { invoice_date, due_date } = calculateInvoiceDates(
            index,
            handoverDate,
        );

        return {
            ...invoice,
            invoice_date,
            due_date,
        };
    });
}

export function buildInvoicesFromItems(
    paymentPlan: string,
    items: InvoiceItemSchema[],
    totalAmount?: number,
    handoverDate?: string,
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
                description: 'Invoice For Deposit',
                items,
                amount,
            },
            {
                _key: nanoid(),
                description: 'Invoice For Handover',
                items: [],
                amount: 0,
            },
        ];
    } else if (paymentPlan === 'installment') {
        // Travel flow: deposit + balance based on deposit config
        let depositAmount = 0;
        const numericDepositValue = Number(depositValue ?? 0);

        if (depositType === 'percentage' && numericDepositValue > 0) {
            depositAmount =
                Math.round(amount * (numericDepositValue / 100) * 100) /
                100;
        } else if (depositType === 'fixed' && numericDepositValue > 0) {
            depositAmount = Math.min(numericDepositValue, amount);
        }

        const balanceAmount =
            Math.round((amount - depositAmount) * 100) / 100;

        invoices = [
            {
                _key: nanoid(),
                description: 'Invoice For Deposit',
                items,
                amount: depositAmount,
            },
            {
                _key: nanoid(),
                description: 'Invoice For Balance',
                items: [],
                amount: balanceAmount,
            },
        ];
    }

    if (handoverDate) {
        invoices = autoFillInvoiceDates(invoices, handoverDate);
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
        quotation.deposit_type,
        quotation.deposit_value,
    );
}

export function buildInvoices(
    paymentPlan: string,
    previousInvoices: InvoiceSchema[],
    handoverDate?: string,
    depositType?: string | null,
    depositValue?: number | string | null,
): InvoiceSchema[] {
    const items = collectAllItems(previousInvoices);

    return buildInvoicesFromItems(
        paymentPlan,
        items,
        undefined,
        handoverDate,
        depositType,
        depositValue,
    );
}
