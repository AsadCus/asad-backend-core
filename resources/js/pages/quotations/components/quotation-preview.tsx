import { capitalize, formatCurrency } from '@/lib/utils';
import React, { forwardRef } from 'react';
import { QuotationItemSchema } from '../items/schema';
import { paymentPlans, QuotationSchema } from '../schema';

interface Props {
    data: QuotationSchema;
    items?: QuotationItemSchema[];
}

type QuotationItemInternal = QuotationItemSchema & {
    _internalKey: string;
};

function buildSortedItems(
    rawItems: QuotationItemSchema[],
): QuotationItemInternal[] {
    const items: QuotationItemInternal[] = rawItems.map((item, index) => ({
        ...item,
        _internalKey: String(item._key ?? item.id ?? `tmp-${index}`),
    }));

    const sorted: QuotationItemInternal[] = [];
    const visited = new Set<string>();

    const normalize = (
        value: string | number | null | undefined,
    ): string | undefined => {
        if (value === undefined || value === null || value === '')
            return undefined;
        return String(value);
    };

    const getKey = (item: QuotationItemInternal): string => item._internalKey;

    function addItemAndChildren(item: QuotationItemInternal): void {
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
    }

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

function alphabetIndex(index: number): string {
    const alphabet = 'abcdefghijklmnopqrstuvwxyz';
    if (index < 26) {
        return alphabet[index];
    }
    const firstLetter = alphabet[Math.floor(index / 26) - 1];
    const secondLetter = alphabet[index % 26];
    return firstLetter + secondLetter;
}

function mergePlacementFeeItemsRaw(
    rawItems: QuotationItemSchema[],
    data: QuotationSchema,
): QuotationItemSchema[] {
    const placementItems = rawItems.filter(
        (i) => i.is_placement_fee === true && !i.is_header,
    );

    if (!placementItems.length) return rawItems;

    const monthlySalary = Number(data.monthly_salary ?? 0);
    const loanDuration = Number(data.loan_duration ?? 0);

    const base = placementItems[0];

    const mergedItem: QuotationItemSchema = {
        ...base,
        description: 'Placement Fee',
        quantity: loanDuration,
        rate: monthlySalary,
        parent_id: null,
        parent_key: null,
        sort_order: base.sort_order ?? 0,
        is_placement_fee: true,
    };

    return [...rawItems.filter((i) => i.is_placement_fee !== true), mergedItem];
}

const QuotationPreview = forwardRef<HTMLDivElement, Props>(
    ({ data, items = [] }, ref) => {
        const sortedItems = buildSortedItems(
            mergePlacementFeeItemsRaw(items, data),
        );

        let rootCounter = 0;
        const childCounters = new Map<string, number>();

        function getItemKey(item: QuotationItemInternal): string {
            return item._internalKey;
        }
        function getParentKey(item: QuotationItemInternal): string {
            return String(item.parent_key ?? item.parent_id ?? 'root');
        }

        const paymentPlanLabel =
            paymentPlans.find((p) => p.value === (data.payment_plan ?? ''))
                ?.label || capitalize(data.payment_plan ?? '');

        const subtotal = sortedItems.reduce<number>((sum, item) => {
            if (item.is_header) return sum;

            const isPlacement = item.is_placement_fee === true;
            const amount = isPlacement
                ? Number(data.monthly_salary ?? 0) *
                  Number(data.loan_duration ?? 0)
                : Number(item.quantity ?? 0) * Number(item.rate ?? 0);

            return sum + amount;
        }, 0);

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

                {/* Quotation Title Bar */}
                <div
                    style={{ backgroundColor: '#40A09DD4' }}
                    className="mb-4 py-2 text-center text-base font-bold tracking-widest text-white"
                >
                    QUOTATION
                </div>

                <div className="px-10">
                    {/* Info */}
                    <div className="mb-4 grid grid-cols-5 items-start gap-4 text-sm">
                        <table className="col-span-3 w-full">
                            <tbody>
                                <tr>
                                    <td>
                                        <strong>Name</strong>
                                    </td>
                                    <td>: {data.customer_name ?? '-'}</td>
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
                                        <strong>Description</strong>
                                    </td>
                                    <td>: {data.description}</td>
                                </tr>
                            </tbody>
                        </table>
                        <table className="col-span-2 w-full">
                            <tbody>
                                <tr>
                                    <td className="w-4/9">
                                        <strong>Quotation Number</strong>
                                    </td>
                                    <td>: {data.quotation_number}</td>
                                </tr>
                                <tr>
                                    <td className="w-4/9">
                                        <strong>Placement Fee</strong>
                                    </td>
                                    <td>: {paymentPlanLabel || '-'}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {/* Detail */}
                    <div className="space-y-4 border-y py-4">
                        {/* Items Table */}
                        <table className="w-full text-sm">
                            <tbody>
                                {sortedItems.map((item) => {
                                    const isRoot =
                                        !item.parent_id && !item.parent_key;

                                    let label = '';
                                    let indent = '';

                                    if (isRoot) {
                                        rootCounter += 1;
                                        label = `${rootCounter}.`;
                                        childCounters.set(getItemKey(item), 0);
                                        indent = '';
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

                                    const monthlySalary = Number(
                                        data.monthly_salary ??
                                            item.quantity ??
                                            0,
                                    );
                                    const loanDuration = Number(
                                        data.loan_duration ?? item.rate ?? 0,
                                    );

                                    const isUpfrontPlacement =
                                        item.is_placement_fee === true;

                                    const computedAmount = isUpfrontPlacement
                                        ? monthlySalary * loanDuration
                                        : Number(item.quantity ?? 0) *
                                          Number(item.rate ?? 0);

                                    const amount = item.is_header
                                        ? ''
                                        : computedAmount;

                                    const descriptionText =
                                        isUpfrontPlacement &&
                                        monthlySalary &&
                                        loanDuration
                                            ? `${item.description} - $${monthlySalary} x ${loanDuration} month(s)`
                                            : item.description;

                                    if (item.is_header) {
                                        return (
                                            <tr
                                                key={getItemKey(item)}
                                                className="border-b border-gray-300"
                                            >
                                                <td
                                                    className={`py-2 font-bold ${indent}`}
                                                    colSpan={3}
                                                >
                                                    {label} {descriptionText}
                                                </td>
                                            </tr>
                                        );
                                    }

                                    const rowClass = isRoot
                                        ? 'font-bold'
                                        : 'font-medium';

                                    return (
                                        <tr
                                            key={getItemKey(item)}
                                            className={`border-b border-gray-200 ${rowClass}`}
                                        >
                                            <td
                                                className={`w-6 py-2 align-top ${indent} ${rowClass}`}
                                            >
                                                {label} {descriptionText}
                                            </td>
                                            <td className="w-20 py-2 text-right align-top font-medium">
                                                {amount !== ''
                                                    ? formatCurrency(
                                                          Number(amount),
                                                      )
                                                    : ''}
                                            </td>
                                        </tr>
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
                        {data.notes.map((note, index) => (
                            <div
                                key={index}
                                className="tiptap text-center"
                                dangerouslySetInnerHTML={{
                                    __html: note.description || '',
                                }}
                            />
                        ))}

                        <p className="mt-4 text-center font-bold">
                            UPDATED:{' '}
                            {new Date()
                                .toLocaleDateString('en-GB', {
                                    day: '2-digit',
                                    month: '2-digit',
                                    year: 'numeric',
                                })
                                .replace(/\//g, '/')}
                        </p>
                    </div>
                </div>
            </div>
        );
    },
);

export default QuotationPreview;
