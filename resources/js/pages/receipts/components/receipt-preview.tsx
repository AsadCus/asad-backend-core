import { formatCurrency } from '@/lib/utils';
import { InvoiceItemSchema } from '@/pages/invoices/schema';
import { paymentMethods } from '@/pages/quotations/schema';
import React, { forwardRef } from 'react';
import { ReceiptSchema } from '../schema';

interface BrandingData {
    company_name: string;
    company_address: string;
    company_phone?: string;
    company_email?: string;
    logo_url?: string;
    stamp_url?: string;
    signature_url?: string;
    module_templates?: {
        receipt?: {
            title_color: string;
            footer_text?: string;
            show_stamp?: boolean;
            show_signature?: boolean;
        };
    };
}

interface Props {
    data: ReceiptSchema;
    items?: InvoiceItemSchema[];
    branding?: BrandingData | null;
}

type ReceiptItemInternal = InvoiceItemSchema & {
    _internalKey: string;
};

function buildSortedItems(
    rawItems: InvoiceItemSchema[],
): ReceiptItemInternal[] {
    const items: ReceiptItemInternal[] = rawItems.map((item, index) => ({
        ...item,
        _internalKey: String(item._key ?? item.id ?? `tmp-${index}`),
    }));

    const sorted: ReceiptItemInternal[] = [];
    const visited = new Set<string>();

    const normalize = (
        value: string | number | null | undefined,
    ): string | undefined =>
        value === undefined || value === null || value === ''
            ? undefined
            : String(value);

    const addItemAndChildren = (item: ReceiptItemInternal): void => {
        if (visited.has(item._internalKey)) return;

        visited.add(item._internalKey);
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
        if (!visited.has(item._internalKey)) {
            sorted.push(item);
        }
    });

    return sorted;
}

function alphabetIndex(index: number) {
    const alphabet = 'abcdefghijklmnopqrstuvwxyz';
    if (index < 26) return alphabet[index];
    return alphabet[Math.floor(index / 26) - 1] + alphabet[index % 26];
}

const ReceiptPreview = forwardRef<HTMLDivElement, Props>(
    ({ data, items = [], branding }, ref) => {
        const sortedItems = buildSortedItems(items);

        // Use branding data with fallbacks
        const companyName = branding?.company_name || 'Urban Care Employment Agency';
        const companyAddress = branding?.company_address || '931 Yishun Central 1\n#01-109, Singapore 760931';
        const titleColor = branding?.module_templates?.receipt?.title_color || '#40A09DD4';
        const logoUrl = branding?.logo_url ?? '/logo_agency.png'; // Use ?? to handle empty strings
        const companyPhone = branding?.company_phone || '';
        const companyEmail = branding?.company_email || '';

        let rootCounter = 0;
        const childCounters = new Map<string, number>();

        const subtotal = sortedItems.reduce((sum, item) => {
            if (item.is_header) return sum;
            return sum + Number(item.quantity ?? 0) * Number(item.rate ?? 0);
        }, 0);

        const paymentMethod = data.payment_method ?? 'transfer';
        const paymentMethodLabel =
            paymentMethods.find((s) => s.value === paymentMethod)?.label ||
            paymentMethod;

        return (
            <div
                ref={ref}
                className="w-[800px] bg-white p-8 text-sm text-gray-900"
                style={{ fontFamily: 'Arial, sans-serif' }}
            >
                {/* Header */}
                <div className="mb-2 flex items-center justify-between">
                    <img
                        src={logoUrl}
                        alt="Company Logo"
                        className="h-[102px] w-80 object-contain"
                    />
                    <div className="text-right">
                        <p className="mb-1 text-base font-bold">
                            {companyName}
                        </p>
                        {companyAddress.split('\n').map((line, idx) => (
                            <p key={`addr-${idx}`}>{line}</p>
                        ))}
                        {(companyPhone || companyEmail) && (
                            <div className="mt-1">
                                {companyPhone && <p>Tel: {companyPhone}</p>}
                                {companyEmail && <p>Email: {companyEmail}</p>}
                            </div>
                        )}
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

                {/* Title */}
                <div
                    style={{ backgroundColor: titleColor }}
                    className="mb-4 py-2 text-center text-base font-bold tracking-widest text-white"
                >
                    OFFICIAL RECEIPT
                </div>

                <div className="px-10">
                    {/* Receipt Info */}
                    <div className="mb-4 grid grid-cols-3 items-start gap-4 text-sm">
                        <table className="col-span-2 w-full">
                            <tbody>
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
                            </tbody>
                        </table>

                        <table>
                            <tbody>
                                <tr>
                                    <td>
                                        <strong>Receipt Number</strong>
                                    </td>
                                    <td>: {data.receipt_number}</td>
                                </tr>
                                <tr>
                                    <td>
                                        <strong>Receipt Date</strong>
                                    </td>
                                    <td>: {data.receipt_date}</td>
                                </tr>
                                <tr>
                                    <td>
                                        <strong>Order Number</strong>
                                    </td>
                                    <td>: {data.order_number}</td>
                                </tr>
                                <tr>
                                    <td>
                                        <strong>Payment Method</strong>
                                    </td>
                                    <td>: {paymentMethodLabel}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {/* Items */}
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
                                        childCounters.set(item._internalKey, 0);
                                    } else {
                                        const parentKey = String(
                                            item.parent_key ?? item.parent_id,
                                        );
                                        const idx =
                                            childCounters.get(parentKey) ?? 0;
                                        label = `${alphabetIndex(idx)}.`;
                                        childCounters.set(parentKey, idx + 1);
                                        indent = 'pl-6';
                                    }

                                    const qty = item.is_header
                                        ? ''
                                        : Number(item.quantity ?? 0);
                                    const rate = item.is_header
                                        ? ''
                                        : Number(item.rate ?? 0);
                                    const total =
                                        qty !== '' && rate !== ''
                                            ? qty * rate
                                            : '';

                                    const descriptionText = item.description;

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
                                        <React.Fragment key={item._internalKey}>
                                            <tr className="border-b border-gray-200">
                                                <td
                                                    className={`py-1 ${indent}`}
                                                >
                                                    {label} {descriptionText}
                                                </td>
                                                {/* <td className="py-1 text-right">
                                                    {qty !== ''
                                                        ? qty.toFixed(2)
                                                        : ''}
                                                </td> */}
                                                <td className="py-1 text-right">
                                                    {rate !== ''
                                                        ? `${formatCurrency(rate)}`
                                                        : ''}
                                                </td>
                                                <td className="py-1 text-right font-medium">
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

                        <div className="mt-2 text-right font-bold">
                            Total Amount: {formatCurrency(subtotal)}
                        </div>
                    </div>

                    {/* Remarks */}
                    <div className="mt-4">
                        <strong>Remarks:</strong>
                        <div className="mt-1 min-h-[60px] border p-2">
                            {data.description}
                        </div>
                    </div>

                    {/* Footer */}
                    <div className="mt-8 space-y-4 text-center text-sm">
                        <p>
                            Paynow to UEN 53496387X or Bank Transfer to DBS Business
                            Multi Currency Account 072-131956-0.
                            <br />
                            For assistance, contact 8785 5651.
                        </p>

                        {/* Stamp Section */}
                        {branding?.module_templates?.receipt?.show_stamp && branding?.stamp_url && (
                            <div className="mt-6 text-center">
                                <img
                                    src={branding.stamp_url}
                                    alt="Company Stamp"
                                    className="mx-auto inline-block max-h-24 object-contain"
                                />
                            </div>
                        )}

                        {/* Signature Section */}
                        {branding?.module_templates?.receipt?.show_signature && branding?.signature_url && (
                            <div className="mt-6 text-center">
                                <p className="mb-2 text-xs font-medium">
                                    Authorised Signature
                                </p>
                                <img
                                    src={branding.signature_url}
                                    alt="Authorised Signature"
                                    className="mx-auto inline-block max-h-20 object-contain"
                                />
                            </div>
                        )}
                    </div>
                </div>
            </div>
        );
    },
);

ReceiptPreview.displayName = 'ReceiptPreview';
export default ReceiptPreview;
