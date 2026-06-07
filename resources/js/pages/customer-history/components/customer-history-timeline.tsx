import { Badge } from '@/components/ui/badge';
import {
    confirmationMemberStatusColors,
    confirmationMemberStatusLabels,
} from '@/pages/customer/schema';
import {
    packageStatusColors,
    packageStatusLabels,
    sharingPlanLabels,
} from '@/pages/packages/schema';
import {
    CalendarDays,
    Crown,
    Globe,
    Package,
    Plane,
    Users,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import type { CustomerHistoryRecord } from '../types';

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
            const response = await fetch(
                `/customer-history/${customerId}`,
            );
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

    const formatDate = (date: string | null): string => {
        if (!date) return '-';
        return new Date(date).toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });
    };

    const formatSharingLabel = (record: CustomerHistoryRecord): string => {
        const label =
            sharingPlanLabels[record.sharing_plan ?? ''] ??
            record.sharing_plan ??
            '';

        if (record.sharing_price != null && record.sharing_price > 0) {
            const symbol = record.currency_symbol ?? '$';
            const formatted = new Intl.NumberFormat('en', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 2,
            }).format(record.sharing_price);
            return `${label} (${symbol}${formatted})`;
        }

        return label;
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
                        No travel history found
                    </p>
                    <p className="mt-1 text-sm">
                        This customer has not participated in any packages yet.
                    </p>
                </div>
            )}

            {!isLoading && records.length > 0 && (
                <div className="relative space-y-0 py-4 pl-7">
                    <div className="absolute top-0 bottom-0 left-3 w-px bg-gray-200 dark:bg-gray-700" />

                    {records.map((record, idx) => (
                        <div
                            key={`${record.confirmation_id}-${idx}`}
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
                                              : record.package_status ===
                                                    'open'
                                                ? 'bg-green-500'
                                                : 'bg-gray-400 dark:bg-gray-500'
                                    }`}
                                />
                            </div>

                            <div className="rounded-lg border border-gray-100 bg-gray-50/50 p-5 dark:border-gray-800 dark:bg-gray-900/50">
                                <div className="mb-3 flex flex-wrap items-start justify-between gap-2">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                            <Plane className="h-5 w-5" />
                                            {record.package_name ??
                                                'Unknown Package'}
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
                                                {formatDate(
                                                    record.return_date,
                                                )}
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
                                            <span className="font-medium capitalize text-gray-800 dark:text-gray-200">
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
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </>
    );
}
