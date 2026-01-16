import { InvoiceItemSchema } from '../schema';

export function normalizeItems(
    items: InvoiceItemSchema[],
): InvoiceItemSchema[] {
    return items.map((item, index) => ({
        ...item,
        parent_id: item.parent_id ?? null,
        parent_key: item.parent_key ?? null,
        sort_order: index + 1,
    }));
}

export function calculateTotal(items: InvoiceItemSchema[] = []) {
    const totalCents = items.reduce((sum, i) => {
        if (i.is_header) return sum;

        const quantity = Number(i.quantity ?? 0);
        const rateCents = Math.round(Number(i.rate ?? 0) * 100);

        return sum + quantity * rateCents;
    }, 0);

    return totalCents / 100;
}

export function calculateInvoicesTotal(
    invoices?: { items?: InvoiceItemSchema[] }[],
) {
    if (!invoices?.length) return 0;

    const totalCents = invoices.reduce((sum, inv) => {
        return sum + Math.round(calculateTotal(inv.items ?? []) * 100);
    }, 0);

    return totalCents / 100;
}

export function collectAllItems(
    invoices: { items?: InvoiceItemSchema[] }[],
): InvoiceItemSchema[] {
    const map = new Map<string, InvoiceItemSchema>();

    invoices.forEach((inv) =>
        inv.items?.forEach((item) => map.set(item._key, item)),
    );

    return normalizeItems(
        Array.from(map.values()).sort(
            (a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0),
        ),
    );
}

export function collectWithChildren(
    items: InvoiceItemSchema[],
    rootKeys: string[],
): InvoiceItemSchema[] {
    const collected = new Map<string, InvoiceItemSchema>();

    const walk = (item: InvoiceItemSchema) => {
        if (collected.has(item._key)) return;
        collected.set(item._key, item);

        items.forEach((child) => {
            if (
                child.parent_key === item._key ||
                (item.id && child.parent_id === item.id)
            ) {
                walk(child);
            }
        });
    };

    items.forEach((i) => rootKeys.includes(i._key) && walk(i));

    return Array.from(collected.values());
}
