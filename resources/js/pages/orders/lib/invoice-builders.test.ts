import { InvoiceItemSchema, InvoiceSchema } from '@/pages/invoices/schema';
import { describe, expect, it } from 'vitest';
import { buildInvoicesFromItems } from './invoice-builders';

/**
 * Returns the non-header line items of an invoice.
 */
function lineItems(invoice: InvoiceSchema): InvoiceItemSchema[] {
    return (invoice.items ?? []).filter((item) => !item.is_header);
}

/**
 * Builds an edit-mode item as it would be hydrated from the DB: a stable
 * `id-{dbId}` key and (optionally) a parent header reference local to its
 * invoice.
 */
function dbItem(
    overrides: Partial<InvoiceItemSchema> & { id: number },
): InvoiceItemSchema {
    return {
        _key: `id-${overrides.id}`,
        parent_id: null,
        parent_key: null,
        quantity: 1,
        rate: 0,
        amount: 0,
        is_header: false,
        ...overrides,
    } as InvoiceItemSchema;
}

describe('mergeSplitInstallmentItems via buildInvoicesFromItems', () => {
    it('does not re-split already-split items when the deposit value changes (package items)', () => {
        // Edit mode: 3 installment invoices, each owning its own header row,
        // each holding one split copy of the same logical package line.
        // Original total 1000, previously split at 30% deposit.
        const items: InvoiceItemSchema[] = [
            dbItem({
                id: 101,
                is_header: true,
                description: 'Package ABC',
                quantity: 0,
                rate: 0,
                amount: 0,
            }),
            dbItem({
                id: 201,
                parent_id: 101,
                parent_key: 'id-101',
                customer_confirmation_member_id: 5,
                description: 'Umrah Package (Deposit)',
                rate: 300,
                amount: 300,
            }),
            dbItem({
                id: 102,
                is_header: true,
                description: 'Package ABC',
                quantity: 0,
                rate: 0,
                amount: 0,
            }),
            dbItem({
                id: 202,
                parent_id: 102,
                parent_key: 'id-102',
                customer_confirmation_member_id: 5,
                description: 'Umrah Package (50%)',
                rate: 350,
                amount: 350,
            }),
            dbItem({
                id: 103,
                is_header: true,
                description: 'Package ABC',
                quantity: 0,
                rate: 0,
                amount: 0,
            }),
            dbItem({
                id: 203,
                parent_id: 103,
                parent_key: 'id-103',
                customer_confirmation_member_id: 5,
                description: 'Umrah Package (Balance)',
                rate: 350,
                amount: 350,
            }),
        ];

        // Re-split with a new 50% deposit.
        const invoices = buildInvoicesFromItems(
            'installment',
            items,
            undefined,
            'percentage',
            50,
        );

        expect(invoices).toHaveLength(3);
        // The bug produced 3 line items per invoice (9 total). Each invoice
        // must carry exactly one merged-then-resplit line.
        invoices.forEach((invoice) => {
            expect(lineItems(invoice)).toHaveLength(1);
        });

        const [deposit, fifty, balance] = invoices;
        expect(lineItems(deposit)[0].amount).toBe(500);
        expect(lineItems(fifty)[0].amount).toBe(250);
        expect(lineItems(balance)[0].amount).toBe(250);

        // Deposit invoice keeps a single (deduped) header.
        expect((deposit.items ?? []).filter((i) => i.is_header)).toHaveLength(
            1,
        );
    });

    it('recombines split non-package items when switching installment -> full', () => {
        const items: InvoiceItemSchema[] = [
            dbItem({
                id: 301,
                description: 'Visa Fee (Deposit)',
                rate: 30,
                amount: 30,
            }),
            dbItem({
                id: 302,
                description: 'Visa Fee (50%)',
                rate: 35,
                amount: 35,
            }),
            dbItem({
                id: 303,
                description: 'Visa Fee (Balance)',
                rate: 35,
                amount: 35,
            }),
        ];

        const invoices = buildInvoicesFromItems('full', items);

        expect(invoices).toHaveLength(1);
        const merged = lineItems(invoices[0]);
        expect(merged).toHaveLength(1);
        expect(merged[0].description).toBe('Visa Fee');
        expect(merged[0].amount).toBe(100);
    });

    it('recombines split package items (per-invoice headers) when switching installment -> full', () => {
        const items: InvoiceItemSchema[] = [
            dbItem({
                id: 401,
                is_header: true,
                description: 'Package XYZ',
                quantity: 0,
            }),
            dbItem({
                id: 501,
                parent_id: 401,
                parent_key: 'id-401',
                customer_confirmation_member_id: 7,
                description: 'Hotel (Deposit)',
                rate: 100,
                amount: 100,
            }),
            dbItem({
                id: 402,
                is_header: true,
                description: 'Package XYZ',
                quantity: 0,
            }),
            dbItem({
                id: 502,
                parent_id: 402,
                parent_key: 'id-402',
                customer_confirmation_member_id: 7,
                description: 'Hotel (50%)',
                rate: 100,
                amount: 100,
            }),
            dbItem({
                id: 403,
                is_header: true,
                description: 'Package XYZ',
                quantity: 0,
            }),
            dbItem({
                id: 503,
                parent_id: 403,
                parent_key: 'id-403',
                customer_confirmation_member_id: 7,
                description: 'Hotel (Balance)',
                rate: 100,
                amount: 100,
            }),
        ];

        const invoices = buildInvoicesFromItems('full', items);

        expect(invoices).toHaveLength(1);
        const invoice = invoices[0];
        expect(lineItems(invoice)).toHaveLength(1);
        expect(lineItems(invoice)[0].description).toBe('Hotel');
        expect(lineItems(invoice)[0].amount).toBe(300);
        // The three duplicated headers collapse into one.
        expect((invoice.items ?? []).filter((i) => i.is_header)).toHaveLength(
            1,
        );
    });

    it('keeps distinct members separate even under the same header', () => {
        const items: InvoiceItemSchema[] = [
            dbItem({
                id: 601,
                is_header: true,
                description: 'Family Package',
                quantity: 0,
            }),
            dbItem({
                id: 701,
                parent_id: 601,
                parent_key: 'id-601',
                customer_confirmation_member_id: 1,
                description: 'Seat (Deposit)',
                rate: 50,
                amount: 50,
            }),
            dbItem({
                id: 702,
                parent_id: 601,
                parent_key: 'id-601',
                customer_confirmation_member_id: 2,
                description: 'Seat (Deposit)',
                rate: 60,
                amount: 60,
            }),
        ];

        const invoices = buildInvoicesFromItems('full', items);

        expect(invoices).toHaveLength(1);
        const merged = lineItems(invoices[0]);
        expect(merged).toHaveLength(2);
        expect(
            merged.map((i) => i.amount).sort((a, b) => Number(a) - Number(b)),
        ).toEqual([50, 60]);
    });

    it('splits cleanly on create (un-split source, no double split)', () => {
        const items: InvoiceItemSchema[] = [
            dbItem({
                id: 801,
                customer_confirmation_member_id: 9,
                description: 'Umrah Package',
                rate: 1000,
                amount: 1000,
            }),
        ];

        const invoices = buildInvoicesFromItems(
            'installment',
            items,
            undefined,
            'percentage',
            30,
        );

        expect(invoices).toHaveLength(3);
        invoices.forEach((invoice) => {
            expect(lineItems(invoice)).toHaveLength(1);
        });
        expect(lineItems(invoices[0])[0].amount).toBe(300);
        expect(lineItems(invoices[1])[0].amount).toBe(350);
        expect(lineItems(invoices[2])[0].amount).toBe(350);
    });
});
