import { Badge } from '@/components/ui/badge';
import {
    confirmationMemberStatusColors,
    confirmationMemberStatusLabels,
} from '@/pages/customer/schema';
import {
    enquiryStatusLabels,
    enquiryTypeLabels,
} from '@/pages/enquiries/schema';
import {
    statusColors as invoiceStatusColors,
    statuses as invoiceStatuses,
} from '@/pages/invoices/schema';
import {
    packageStatusColors,
    packageStatusLabels,
    sharingPlanLabels,
} from '@/pages/packages/schema';
import {
    statusColors as quotationStatusColors,
    statuses as quotationStatuses,
} from '@/pages/quotations/schema';
import {
    CalendarDays,
    Crown,
    FileText,
    Globe,
    MessageSquare,
    Package,
    Plane,
    Receipt as ReceiptIcon,
    ShoppingCart,
    Users,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import type { CustomerHistoryRecord, HistoryPayment } from '../schema';

const labelOf = (
    list: { label: string; value: string }[],
    value: string,
): string => list.find((status) => status.value === value)?.label ?? value;

interface CustomerHistoryTimelineProps {
    isOpen: boolean;
    customerId: number | undefined;
}

export default function CustomerHistoryTimeline({
    isOpen,
    customerId,
}: CustomerHistoryTimelineProps) {
    const [records, setRecords] = useState<CustomerHistoryRecord[]>([]);
    const [isLoading, setIsLoading] = useState(false);

    const fetchRecords = useCallback(async () => {
        if (!customerId) return;

        setIsLoading(true);
        try {
            const response = await fetch(`/customer-history/${customerId}`);
            if (!response.ok) throw new Error('Failed to fetch history');
            const data = await response.json();
            setRecords(data);
        } catch {
            console.error('Failed to fetch customer history');
        } finally {
            setIsLoading(false);
        }
    }, [customerId]);

    useEffect(() => {
        if (isOpen && customerId) {
            fetchRecords();
        }
    }, [isOpen, customerId, fetchRecords]);

    const formatDate = (date: string | null | undefined): string => {
        if (!date) return '-';
        return new Date(date).toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });
    };

    const formatMoney = (
        amount: number | null | undefined,
        currencySymbol?: string | null,
    ): string => {
        const symbol = currencySymbol ?? '$';
        const formatted = new Intl.NumberFormat('en', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2,
        }).format(Number(amount ?? 0));
        return `${symbol}${formatted}`;
    };

    const formatSharingLabel = (record: CustomerHistoryRecord): string => {
        const label =
            sharingPlanLabels[record.sharing_plan ?? ''] ??
            record.sharing_plan ??
            '';

        if (record.sharing_price != null && record.sharing_price > 0) {
            return `${label} (${formatMoney(record.sharing_price, record.currency_symbol)})`;
        }

        return label;
    };

    const renderPayments = (
        payments: HistoryPayment[],
        currencySymbol?: string | null,
    ) => {
        if (!payments.length) {
            return null;
        }

        return (
            <div className="mt-4 space-y-3 border-t border-gray-100 pt-3 dark:border-gray-800">
                <span className="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                    <FileText className="h-4 w-4" />
                    Payments
                </span>

                {payments.map((payment) => (
                    <div
                        key={payment.quotation.id}
                        className="rounded-md border border-gray-100 bg-white p-3 dark:border-gray-800 dark:bg-gray-900"
                    >
                        <div className="flex flex-wrap items-center gap-2 text-sm">
                            <FileText className="h-4 w-4 shrink-0 text-muted-foreground" />
                            <span className="text-muted-foreground">
                                Quotation
                            </span>
                            <span className="font-medium text-gray-800 dark:text-gray-200">
                                {payment.quotation.quotation_number ?? '-'}
                            </span>
                            {payment.quotation.status && (
                                <Badge
                                    className={`text-xs ${quotationStatusColors[payment.quotation.status as keyof typeof quotationStatusColors] ?? 'bg-gray-100 text-gray-800'}`}
                                >
                                    {labelOf(
                                        quotationStatuses,
                                        payment.quotation.status,
                                    )}
                                </Badge>
                            )}
                            {payment.quotation.quotation_date && (
                                <span className="text-xs text-muted-foreground">
                                    {formatDate(
                                        payment.quotation.quotation_date,
                                    )}
                                </span>
                            )}
                        </div>

                        {payment.order && (
                            <div className="mt-2 flex items-center gap-2 text-sm">
                                <ShoppingCart className="h-4 w-4 shrink-0 text-muted-foreground" />
                                <span className="text-muted-foreground">
                                    Order
                                </span>
                                <span className="font-medium text-gray-800 dark:text-gray-200">
                                    {payment.order.order_number ?? '-'}
                                </span>
                            </div>
                        )}

                        {payment.invoices.length > 0 ? (
                            <div className="mt-2 space-y-2 border-l border-gray-200 pl-3 dark:border-gray-700">
                                {payment.invoices.map((invoice) => (
                                    <div key={invoice.id}>
                                        <div className="flex flex-wrap items-center gap-2 text-sm">
                                            <ReceiptIcon className="h-4 w-4 shrink-0 text-muted-foreground" />
                                            <span className="text-muted-foreground">
                                                Invoice
                                            </span>
                                            <span className="font-medium text-gray-800 dark:text-gray-200">
                                                {invoice.invoice_number ?? '-'}
                                            </span>
                                            <span className="font-medium text-gray-800 dark:text-gray-200">
                                                {formatMoney(
                                                    invoice.amount,
                                                    currencySymbol,
                                                )}
                                            </span>
                                            {invoice.status && (
                                                <Badge
                                                    className={`text-xs ${invoiceStatusColors[invoice.status as keyof typeof invoiceStatusColors] ?? 'bg-gray-100 text-gray-800'}`}
                                                >
                                                    {labelOf(
                                                        invoiceStatuses,
                                                        invoice.status,
                                                    )}
                                                </Badge>
                                            )}
                                        </div>

                                        {invoice.receipts.length > 0 ? (
                                            <div className="mt-1 ml-6 space-y-1">
                                                {invoice.receipts.map(
                                                    (receipt) => (
                                                        <div
                                                            key={receipt.id}
                                                            className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                                                        >
                                                            <span className="text-green-600 dark:text-green-400">
                                                                ✓ Receipt
                                                            </span>
                                                            <span className="font-medium text-gray-700 dark:text-gray-300">
                                                                {receipt.receipt_number ??
                                                                    '-'}
                                                            </span>
                                                            <span className="font-medium text-gray-700 dark:text-gray-300">
                                                                {formatMoney(
                                                                    receipt.amount,
                                                                    currencySymbol,
                                                                )}
                                                            </span>
                                                            {receipt.receipt_date && (
                                                                <span>
                                                                    {formatDate(
                                                                        receipt.receipt_date,
                                                                    )}
                                                                </span>
                                                            )}
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        ) : (
                                            <div className="mt-1 ml-6 text-xs text-muted-foreground">
                                                No payment received yet
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="mt-2 text-xs text-muted-foreground">
                                No invoices issued yet
                            </div>
                        )}
                    </div>
                ))}
            </div>
        );
    };

    return (
        <>
            {isLoading && (
                <div className="space-y-5 py-4">
                    {[1, 2, 3].map((i) => (
                        <div key={i} className="animate-pulse space-y-3">
                            <div className="flex items-center gap-3">
                                <div className="h-10 w-10 rounded-full bg-gray-200 dark:bg-gray-700" />
                                <div className="flex-1 space-y-2">
                                    <div className="h-5 w-2/3 rounded bg-gray-200 dark:bg-gray-700" />
                                    <div className="h-4 w-1/2 rounded bg-gray-200 dark:bg-gray-700" />
                                </div>
                            </div>
                            <div className="ml-13 h-20 rounded-lg bg-gray-200 dark:bg-gray-700" />
                        </div>
                    ))}
                </div>
            )}

            {!isLoading && records.length === 0 && (
                <div className="flex flex-col items-center justify-center py-16 text-muted-foreground">
                    <Package className="mb-3 h-12 w-12 opacity-40" />
                    <p className="text-lg font-medium">
                        No history records found
                    </p>
                    <p className="mt-1 text-sm">
                        This customer has no enquiries, packages, or payments
                        yet.
                    </p>
                </div>
            )}

            {!isLoading && records.length > 0 && (
                <div className="relative space-y-0 py-4 pl-7">
                    <div className="absolute top-0 bottom-0 left-3 w-px bg-gray-200 dark:bg-gray-700" />

                    {records.map((record) => (
                        <div
                            key={record.key}
                            className="group relative pb-6 last:pb-0"
                        >
                            <div className="absolute top-1.5 -left-7 flex h-6 w-6 items-center justify-center rounded-full border-2 border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                                <div
                                    className={`h-2.5 w-2.5 rounded-full ${
                                        record.package_status === 'completed'
                                            ? 'bg-blue-500'
                                            : record.package_status ===
                                                'ongoing'
                                              ? 'bg-cyan-500'
                                              : record.package_status === 'open'
                                                ? 'bg-green-500'
                                                : 'bg-gray-400 dark:bg-gray-500'
                                    }`}
                                />
                            </div>

                            <div className="rounded-lg border border-gray-100 bg-gray-50/50 p-5 dark:border-gray-800 dark:bg-gray-900/50">
                                <div className="mb-3 flex flex-wrap items-start justify-between gap-2">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                            {record.type === 'non_package' ? (
                                                <ShoppingCart className="h-5 w-5" />
                                            ) : (
                                                <Plane className="h-5 w-5" />
                                            )}
                                            {record.package_name ??
                                                (record.type === 'non_package'
                                                    ? 'Direct Order'
                                                    : 'Unknown Package')}
                                        </span>
                                        {record.is_leader && (
                                            <Badge className="gap-1 bg-amber-100 text-sm text-amber-800 dark:border dark:border-amber-400/25 dark:bg-amber-500/16 dark:text-amber-200">
                                                <Crown className="h-3.5 w-3.5" />
                                                Main
                                            </Badge>
                                        )}
                                    </div>
                                    <div className="flex flex-wrap items-center gap-1.5">
                                        {record.package_status && (
                                            <Badge
                                                className={`text-sm ${packageStatusColors[record.package_status] ?? 'bg-gray-100 text-gray-800'}`}
                                            >
                                                {packageStatusLabels[
                                                    record.package_status
                                                ] ?? record.package_status}
                                            </Badge>
                                        )}
                                        {record.member_status && (
                                            <Badge
                                                className={`text-sm ${confirmationMemberStatusColors[record.member_status] ?? 'bg-gray-100 text-gray-800'}`}
                                            >
                                                {confirmationMemberStatusLabels[
                                                    record.member_status
                                                ] ?? record.member_status}
                                            </Badge>
                                        )}
                                    </div>
                                </div>

                                {record.enquiry && (
                                    <div className="mb-3 flex flex-wrap items-center gap-2 rounded-md bg-gray-100/60 px-3 py-2 text-sm dark:bg-gray-800/40">
                                        <MessageSquare className="h-4 w-4 shrink-0 text-muted-foreground" />
                                        <span className="text-muted-foreground">
                                            Enquiry :
                                        </span>
                                        <span className="font-medium text-gray-800 dark:text-gray-200">
                                            {record.enquiry.enquiry_number ??
                                                '-'}
                                        </span>
                                        {record.enquiry.type && (
                                            <Badge className="text-xs capitalize">
                                                {enquiryTypeLabels[
                                                    record.enquiry.type
                                                ] ?? record.enquiry.type}
                                            </Badge>
                                        )}
                                        {record.enquiry.status && (
                                            <Badge className="bg-gray-100 text-xs text-gray-800">
                                                {enquiryStatusLabels[
                                                    record.enquiry.status
                                                ] ?? record.enquiry.status}
                                            </Badge>
                                        )}
                                    </div>
                                )}

                                <div className="grid grid-cols-1 gap-2.5 text-sm text-gray-600 sm:grid-cols-2 dark:text-gray-400">
                                    {record.package_number && (
                                        <div className="flex items-center gap-2">
                                            <Package className="h-4 w-4 shrink-0" />
                                            <span className="text-muted-foreground">
                                                Package :
                                            </span>
                                            <span className="font-medium text-gray-800 dark:text-gray-200">
                                                {record.package_number}
                                            </span>
                                        </div>
                                    )}
                                    {record.confirmation_number && (
                                        <div className="flex items-center gap-2">
                                            <Users className="h-4 w-4 shrink-0" />
                                            <span className="text-muted-foreground">
                                                Confirmation :
                                            </span>
                                            <span className="font-medium text-gray-800 dark:text-gray-200">
                                                {record.confirmation_number}
                                            </span>
                                        </div>
                                    )}
                                    {record.country_name && (
                                        <div className="flex items-center gap-2">
                                            <Globe className="h-4 w-4 shrink-0" />
                                            <span className="text-muted-foreground">
                                                Country :
                                            </span>
                                            <span className="font-medium text-gray-800 dark:text-gray-200">
                                                {record.country_name}
                                            </span>
                                        </div>
                                    )}
                                    {(record.departure_date ||
                                        record.return_date) && (
                                        <div className="flex items-center gap-2">
                                            <CalendarDays className="h-4 w-4 shrink-0" />
                                            <span className="text-muted-foreground">
                                                Travel :
                                            </span>
                                            <span className="font-medium text-gray-800 dark:text-gray-200">
                                                {formatDate(
                                                    record.departure_date,
                                                )}{' '}
                                                →{' '}
                                                {formatDate(record.return_date)}
                                            </span>
                                        </div>
                                    )}
                                    {record.sharing_plan && (
                                        <div className="flex items-center gap-2">
                                            <Users className="h-4 w-4 shrink-0" />
                                            <span className="text-muted-foreground">
                                                Sharing :
                                            </span>
                                            <span className="font-medium text-gray-800 dark:text-gray-200">
                                                {formatSharingLabel(record)}
                                            </span>
                                        </div>
                                    )}
                                    {record.relationship && (
                                        <div className="flex items-center gap-2">
                                            <Users className="h-4 w-4 shrink-0" />
                                            <span className="text-muted-foreground">
                                                Relationship :
                                            </span>
                                            <span className="font-medium text-gray-800 capitalize dark:text-gray-200">
                                                {record.relationship}
                                            </span>
                                        </div>
                                    )}
                                    {record.date_of_application && (
                                        <div className="flex items-center gap-2">
                                            <CalendarDays className="h-4 w-4 shrink-0" />
                                            <span className="text-muted-foreground">
                                                Applied :
                                            </span>
                                            <span className="font-medium text-gray-800 dark:text-gray-200">
                                                {formatDate(
                                                    record.date_of_application,
                                                )}
                                            </span>
                                        </div>
                                    )}
                                </div>

                                {record.travel.length > 0 && (
                                    <div className="mt-3 flex flex-wrap items-center gap-2 text-sm">
                                        <Plane className="h-4 w-4 shrink-0 text-muted-foreground" />
                                        <span className="text-muted-foreground">
                                            Manifest :
                                        </span>
                                        {record.travel.map((travel) => (
                                            <Badge
                                                key={`${travel.manifest_id}-${travel.member_name}`}
                                                className="bg-gray-100 text-xs text-gray-800"
                                            >
                                                {travel.manifest_number ?? '-'}
                                            </Badge>
                                        ))}
                                    </div>
                                )}

                                {renderPayments(
                                    record.payments,
                                    record.currency_symbol,
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </>
    );
}
