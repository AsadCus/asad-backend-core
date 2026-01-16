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
): InvoiceSchema[] {
    let invoices: InvoiceSchema[] = [];

    if (paymentPlan !== 'installment') {
        const amount = totalAmount ?? calculateTotal(items);

        invoices = [
            {
                _key: nanoid(),
                description:
                    paymentPlan === 'direct' ? null : 'Invoice For Deposit',
                items,
                amount,
            },
            ...(paymentPlan !== 'direct'
                ? [
                      {
                          _key: nanoid(),
                          description: 'Invoice For Handover',
                          items: [],
                          amount: 0,
                      },
                  ]
                : []),
        ];
    } else {
        // installment logic
        const depositItems: InvoiceItemSchema[] = [];
        const handoverItems: InvoiceItemSchema[] = [];
        const installmentBuckets: InvoiceItemSchema[][] = [];

        items.forEach((item) => {
            if (!item.is_placement_fee) {
                depositItems.push(item);
                return;
            }

            const split = splitPlacementFeeItem(item);

            handoverItems.push(...split.handover);

            split.installments.forEach((instItem, index) => {
                if (!installmentBuckets[index]) {
                    installmentBuckets[index] = [];
                }
                installmentBuckets[index].push(instItem);
            });
        });

        // deposit
        invoices.push({
            _key: nanoid(),
            description: 'Invoice For Deposit',
            items: depositItems,
            amount: calculateTotal(depositItems),
        });

        // handover
        invoices.push({
            _key: nanoid(),
            description: 'Invoice For Handover',
            items: handoverItems,
            amount: calculateTotal(handoverItems),
        });

        // installments
        installmentBuckets.forEach((items, index) => {
            invoices.push({
                _key: nanoid(),
                description: `Invoice For Installment ${index + 1}`,
                items,
                amount: calculateTotal(items),
            });
        });
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
        is_placement_fee: item.is_placement_fee,
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
        quotation.commencement_date ?? undefined,
    );
}

export function buildInvoices(
    paymentPlan: string,
    previousInvoices: InvoiceSchema[],
    handoverDate?: string,
): InvoiceSchema[] {
    let items = collectAllItems(previousInvoices);

    const placementFeeItems = items.filter(
        (item) => item.is_placement_fee === true && !item.is_header,
    );

    const wasInInstallmentMode = placementFeeItems.length > 1;

    if (wasInInstallmentMode && paymentPlan !== 'installment') {
        items = mergeSplitPlacementFeeItems(items);
    }

    return buildInvoicesFromItems(paymentPlan, items, undefined, handoverDate);
}

function cloneItemWithQuantity(
    item: InvoiceItemSchema,
    quantity: number,
): InvoiceItemSchema {
    return {
        ...item,
        _key: nanoid(),
        quantity,
        amount: Number(quantity) * Number(item.rate ?? 0),
    };
}

function splitPlacementFeeItem(
    item: InvoiceItemSchema,
    handoverQty = 2,
): {
    handover: InvoiceItemSchema[];
    installments: InvoiceItemSchema[];
} {
    const SCALE = 100;

    const toInt = (v: number) => Math.round(v * SCALE);
    const fromInt = (v: number) => v / SCALE;

    const totalQtyInt = toInt(Number(item.quantity ?? 0));
    const handoverQtyInt = toInt(handoverQty);

    if (totalQtyInt <= 0) {
        return { handover: [], installments: [] };
    }

    const result = {
        handover: [] as InvoiceItemSchema[],
        installments: [] as InvoiceItemSchema[],
    };

    const handoverInt = Math.min(handoverQtyInt, totalQtyInt);
    result.handover.push(cloneItemWithQuantity(item, fromInt(handoverInt)));

    let remaining = totalQtyInt - handoverInt;

    while (remaining > 0) {
        const qtyInt = Math.min(SCALE, remaining);
        result.installments.push(cloneItemWithQuantity(item, fromInt(qtyInt)));
        remaining -= qtyInt;
    }

    return result;
}

function mergeSplitPlacementFeeItems(
    items: InvoiceItemSchema[],
): InvoiceItemSchema[] {
    const placementFeeItems = items.filter(
        (item) => item.is_placement_fee === true,
    );

    if (placementFeeItems.length === 0) {
        return items;
    }

    const groups = new Map<string, InvoiceItemSchema[]>();

    placementFeeItems.forEach((item) => {
        const key = `${item.description || ''}|${item.rate || ''}|${item.is_header || false}`;
        if (!groups.has(key)) {
            groups.set(key, []);
        }
        groups.get(key)!.push(item);
    });

    const mergedItems: InvoiceItemSchema[] = [];
    const seenKeys = new Set<string>();

    for (const groupItems of groups.values()) {
        if (groupItems.length === 1) {
            mergedItems.push(...groupItems);
            seenKeys.add(groupItems[0]._key);
            continue;
        }

        const totalQuantity = groupItems.reduce(
            (sum, item) => sum + Number(item.quantity || 0),
            0,
        );

        const templateItem = groupItems[0];

        const mergedItem: InvoiceItemSchema = {
            ...templateItem,
            _key: nanoid(),
            quantity: totalQuantity,
            amount: totalQuantity * Number(templateItem.rate || 0),
            parent_id: null,
            parent_key: null,
        };

        mergedItems.push(mergedItem);

        groupItems.forEach((item) => seenKeys.add(item._key));
    }

    return [
        ...items.filter((item) => !item.is_placement_fee),
        ...items.filter(
            (item) =>
                item.is_placement_fee &&
                !seenKeys.has(item._key) &&
                mergedItems.length > 0,
        ),
        ...mergedItems,
    ];
}
