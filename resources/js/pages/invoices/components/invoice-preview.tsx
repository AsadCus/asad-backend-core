import { formatCurrency } from '@/lib/utils';
import { paymentMethods, paymentPlans } from '@/pages/quotations/schema';
import React, { forwardRef } from 'react';
import { InvoiceItemSchema, InvoiceSchema } from '../schema';

interface Props {
    data: InvoiceSchema;
    items?: InvoiceItemSchema[];
}

type InvoiceItemInternal = InvoiceItemSchema & {
    _internalKey: string;
};

function buildSortedItems(
    rawItems: InvoiceItemSchema[],
): InvoiceItemInternal[] {
    const items: InvoiceItemInternal[] = rawItems.map((item, index) => ({
        ...item,
        _internalKey: String(item._key ?? item.id ?? `tmp-${index}`),
    }));

    const sorted: InvoiceItemInternal[] = [];
    const visited = new Set<string>();

    const normalize = (
        value: string | number | null | undefined,
    ): string | undefined => {
        if (value === undefined || value === null || value === '') {
            return undefined;
        }
        return String(value);
    };

    const getKey = (item: InvoiceItemInternal): string => item._internalKey;

    const addItemAndChildren = (item: InvoiceItemInternal): void => {
        const key = getKey(item);
        if (visited.has(key)) return;

        visited.add(key);
        sorted.push(item);

        items
            .filter((child) => {
                const parentIdMatch =
                    normalize(child.parent_id) !== undefined &&
                    normalize(child.parent_id) === normalize(item.id);

                const parentKeyMatch =
                    normalize(child.parent_key) !== undefined &&
                    normalize(child.parent_key) ===
                        normalize(item._key ?? item._internalKey);

                return parentIdMatch || parentKeyMatch;
            })
            .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))
            .forEach(addItemAndChildren);
    };

    items
        .filter(
            (item) =>
                normalize(item.parent_id) === undefined &&
                normalize(item.parent_key) === undefined,
        )
        .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))
        .forEach(addItemAndChildren);

    items.forEach((item) => {
        const key = getKey(item);
        if (!visited.has(key)) {
            sorted.push(item);
        }
    });

    return sorted;
}

function alphabetIndex(index: number) {
    const alphabet = 'abcdefghijklmnopqrstuvwxyz';
    if (index < 26) {
        return alphabet[index];
    }
    const firstLetter = alphabet[Math.floor(index / 26) - 1];
    const secondLetter = alphabet[index % 26];
    return firstLetter + secondLetter;
}

const InvoicePreview = forwardRef<HTMLDivElement, Props>(
    ({ data, items = [] }, ref) => {
        const sortedItems = buildSortedItems(items);

        let rootCounter = 0;
        const childCounters = new Map<string, number>();

        const getItemKey = (item: InvoiceItemInternal): string =>
            item._internalKey;
        const getParentKey = (item: InvoiceItemInternal): string =>
            String(item.parent_key ?? item.parent_id ?? 'root');

        const formatDate = (dateString?: string): string => {
            if (!dateString) return '';
            return new Date(dateString).toLocaleDateString('en-GB', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            });
        };

        const subtotal = sortedItems.reduce<number>((sum, item) => {
            if (item.is_header) return sum;
            return sum + Number(item.quantity ?? 0) * Number(item.rate ?? 0);
        }, 0);

        const paymentMethod = data.payment_method ?? 'transfer';
        const paymentMethodLabel =
            paymentMethods.find((s) => s.value === paymentMethod)?.label ||
            paymentMethod;

        const paymentPlan = data.payment_plan ?? 'full';
        const paymentPlanLabel =
            paymentPlans.find((s) => s.value === paymentPlan)?.label ||
            paymentMethod;

        return (
            <div
                ref={ref}
                className="w-[800px] bg-white p-8 text-sm text-gray-900"
                style={{ fontFamily: 'Arial, sans-serif' }}
            >
                {/* Header */}
                <div className="mb-2 flex items-center justify-between border-gray-300">
                    <div className="flex-shrink-0">
                        <img
                            src="/logo_agency.png"
                            alt="Urban Care Logo"
                            className="h-[102px] w-80 object-contain"
                        />
                    </div>
                    <div className="flex-1 text-right text-sm leading-snug">
                        <p className="mb-1 text-base font-bold">
                            Urban Care Employment Agency
                        </p>
                        <p>931 Yishun Central 1</p>
                        <p>#01-109, Singapore 760931</p>
                        <div className="mt-1 font-bold">
                            {data.sales_registration_number && (
                                <p>
                                    REGISTRATION NO.{' '}
                                    {data.sales_registration_number}
                                </p>
                            )}
                            <p>LICENCE NO. 25C2708</p>
                        </div>
                    </div>
                </div>

                {/* Invoice Title Bar */}
                <div
                    style={{ backgroundColor: '#40A09DD4' }}
                    className="mb-4 py-2 text-center text-base font-bold tracking-widest text-white"
                >
                    INVOICE
                </div>

                <div className="px-10">
                    {/* Invoice Info */}
                    <div className="mb-4 grid grid-cols-3 items-start gap-4 text-sm">
                        <table className="col-span-2 w-full">
                            <tbody>
                                <tr>
                                    <td>
                                        <strong>Customer Number</strong>
                                    </td>
                                    <td>: {data.customer_number}</td>
                                </tr>
                                <tr>
                                    <td>
                                        <strong>Name</strong>
                                    </td>
                                    <td>: {data.customer_name}</td>
                                </tr>
                                <tr>
                                    <td className="align-top">
                                        <strong>Address</strong>
                                    </td>
                                    <td>
                                        {data.customer_address ? (
                                            <span>
                                                :{' '}
                                                {data.customer_address
                                                    .split('<br>')
                                                    .map((line, idx) => (
                                                        <React.Fragment
                                                            key={idx}
                                                        >
                                                            {idx === 0 ? (
                                                                line
                                                            ) : (
                                                                <>
                                                                    <br />
                                                                    <span className="inline-block w-2" />
                                                                    {line}
                                                                </>
                                                            )}
                                                        </React.Fragment>
                                                    ))}
                                            </span>
                                        ) : (
                                            <span>: -</span>
                                        )}
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <strong>Contact</strong>
                                    </td>
                                    <td>: {data.customer_contact}</td>
                                </tr>
                                <tr>
                                    <td>
                                        <strong>Email</strong>
                                    </td>
                                    <td>: {data.customer_email}</td>
                                </tr>
                                <tr>
                                    <td>
                                        <strong>Description</strong>
                                    </td>
                                    <td>: {data.description}</td>
                                </tr>
                            </tbody>
                        </table>
                        <table className="col-span-1 w-full">
                            <tbody>
                                <tr>
                                    <td>
                                        <strong>Order Number</strong>
                                    </td>
                                    <td>: {data.order_number}</td>
                                </tr>
                                <tr>
                                    <td>
                                        <strong>Invoice Number</strong>
                                    </td>
                                    <td>: {data.invoice_number}</td>
                                </tr>
                                <tr>
                                    <td>
                                        <strong>Placement Fee</strong>
                                    </td>
                                    <td>: {paymentPlanLabel}</td>
                                </tr>
                                <tr>
                                    <td>
                                        <strong>Payment Method</strong>
                                    </td>
                                    <td>: {paymentMethodLabel}</td>
                                </tr>
                                <tr>
                                    <td>
                                        <strong>Invoice Date</strong>
                                    </td>
                                    <td>: {data.invoice_date}</td>
                                </tr>
                                <tr>
                                    <td>
                                        <strong>Due Date</strong>
                                    </td>
                                    <td>: {data.due_date}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {/* Helper Name */}
                    <div className="font-bold">
                        Name of Helper Deployed :{' '}
                        {data.maid_name?.toUpperCase() || '-'}
                    </div>

                    {/* Items Table */}
                    <div className="space-y-4 border-b py-4">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-gray-900 font-bold">
                                    <th className="py-1 text-left">
                                        Item Description
                                    </th>
                                    {/* <th className="w-16 py-1 text-right">
                                        Qty
                                    </th> */}
                                    <th className="w-20 py-1 text-right">
                                        Cost
                                    </th>
                                    <th className="w-24 py-1 text-right">
                                        Total
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {sortedItems.map((item, index) => {
                                    const isRoot =
                                        !item.parent_id && !item.parent_key;

                                    let label = '';
                                    let indent = '';

                                    if (isRoot) {
                                        rootCounter += 1;
                                        label = `${rootCounter}.`;
                                        childCounters.set(getItemKey(item), 0);
                                    } else {
                                        const parentKey = getParentKey(item);
                                        const current =
                                            childCounters.get(parentKey) ?? 0;
                                        label = `${alphabetIndex(current)}.`;
                                        childCounters.set(
                                            parentKey,
                                            current + 1,
                                        );
                                        indent = 'pl-6';
                                    }

                                    const qty = item.is_header
                                        ? ''
                                        : Number(item.quantity ?? 0);
                                    const rate = item.is_header
                                        ? ''
                                        : Number(item.rate ?? 0);
                                    const total = item.is_header
                                        ? ''
                                        : Number(qty) * Number(rate);

                                    const descriptionText =
                                        item.is_placement_fee && !item.is_header
                                            ? `${item.description} - $${rate} x ${qty} month(s)`
                                            : item.description;

                                    const nextItem = sortedItems[index + 1];
                                    const isLastChild =
                                        !isRoot &&
                                        nextItem &&
                                        !nextItem.parent_id &&
                                        !nextItem.parent_key;
                                    const isLastItem = !nextItem;
                                    const shouldAddSpacing =
                                        (isRoot &&
                                            !nextItem?.parent_id &&
                                            !nextItem?.parent_key) ||
                                        isLastChild ||
                                        isLastItem;

                                    return (
                                        <React.Fragment key={getItemKey(item)}>
                                            <tr className="border-b border-gray-200">
                                                <td
                                                    className={`py-1 align-top ${indent}`}
                                                >
                                                    {label} {descriptionText}
                                                </td>

                                                {/* <td className="py-1 text-right align-top">
                                                    {qty !== ''
                                                        ? `${qty.toFixed(2)}`
                                                        : ''}
                                                </td> */}

                                                <td className="py-1 text-right align-top">
                                                    {rate !== ''
                                                        ? `${formatCurrency(rate)}`
                                                        : ''}
                                                </td>

                                                <td className="py-1 text-right align-top font-medium">
                                                    {total !== ''
                                                        ? `${formatCurrency(total)}`
                                                        : ''}
                                                </td>
                                            </tr>
                                            {shouldAddSpacing &&
                                                index <
                                                    sortedItems.length - 1 && (
                                                    <tr className="h-2">
                                                        <td colSpan={3}></td>
                                                    </tr>
                                                )}
                                        </React.Fragment>
                                    );
                                })}
                            </tbody>
                        </table>

                        {/* Totals Section */}
                        <div className="flex justify-end">
                            <div className="text-right font-bold">
                                Total Amount: {formatCurrency(subtotal)}
                            </div>
                        </div>
                    </div>

                    {/* Footer Notes */}
                    <div className="space-y-4 pt-4 text-sm">
                        <p className="text-center">
                            Paynow to UEN 53496387X or Bank Transfer to DBS
                            Business Multi Currency Account 072-131956-0.
                            <br />
                            For further assistance, please contact us at 8785
                            5651.
                        </p>

                        <p className="text-center font-bold">
                            UPDATE: {formatDate(new Date().toISOString())}
                        </p>
                    </div>
                </div>
            </div>
        );
    },
);

InvoicePreview.displayName = 'InvoicePreview';

export default InvoicePreview;
