import { ActionType } from '@/components/action-column';
import { ColumnFilter } from '@/components/column-filter';
import useConfirmDialog from '@/components/confirm-popup';
import { DataTable } from '@/components/data-table';
import { createSelectColumn } from '@/components/select-column';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

import {
    MultiSelect,
    type MultiSelectGroup,
    type MultiSelectOption,
} from '@/components/multi-select';
import AppLayout from '@/layouts/app-layout';
import {
    getYearOptions,
    joinYearMonth,
    MONTHS,
    splitYearMonth,
} from '@/lib/months';
import {
    QuickDateKey,
    resolveQuickDateRange,
    type QuickDateOption,
} from '@/lib/quick-date';
import { formatUserTime } from '@/lib/timezone';
import {
    formatCurrency,
    formatDateForDisplay,
    parseDisplayDate,
} from '@/lib/utils';
import { dashboard } from '@/routes';
import {
    create as customerCreate,
    destroy as customerDestroy,
    edit as customerEdit,
    index as customerIndex,
    show as customerShow,
} from '@/routes/customer';
import {
    closingReportExport,
    fiscalYearSales,
    paymentReport,
    paymentReportExport,
} from '@/routes/dashboard';
import { index as enquiriesIndex } from '@/routes/enquiries';
import { edit as generalEnquiryEdit } from '@/routes/general-enquiries';
import { create as generalPublicCreate } from '@/routes/general-enquiries/public';
import {
    create as packageCreate,
    download as packageDownload,
    edit as packageEdit,
    index as packageIndex,
    show as packageShow,
} from '@/routes/packages';
import { edit as privateEnquiryEdit } from '@/routes/private-enquiries';
import { create as privatePublicCreate } from '@/routes/private-enquiries/public';
import {
    SharedData,
    ValueNumberOptionType,
    type BreadcrumbItem,
} from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { Download } from 'lucide-react';
import { DateTime } from 'luxon';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { DateRange } from 'react-day-picker';
import { toast } from 'sonner';
import { UserSchema } from './masters/users/schema';
import { packageStatusColors, packageStatusLabels } from './packages/schema';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

interface FiscalYearTotalSalesType {
    count: number;
    amount: number | string;
}

interface PaymentSummaryCategoryType {
    category: string;
    amount: number | string;
    receipt_count: number;
}

interface PaymentSummaryType {
    period: 'daily' | 'monthly' | 'yearly';
    period_label: string;
    start_date: string;
    end_date: string;
    date_range_label: string;
    total_amount: number | string;
    receipt_count: number;
    categories: PaymentSummaryCategoryType[];
    buckets: Array<{
        key: string;
        label: string;
        amount: number | string;
    }>;
}

interface EnquiryRowType {
    id: number;
    type: 'General' | 'Private';
    status: string;
    status_label: string;
    name: string;
    contact: string;
    email: string;
    child_id: number | null;
    package_id?: number | null;
    package_name?: string | null;
    latest_remark?: string;
    created_at: string;
}

interface EnquirySummaryType {
    total: number;
    general: number;
    private: number;
    new_lead: number;
    contacted: number;
    negotiating: number;
    confirmed: number;
}

interface GeneralEnquiryPackageOption {
    value: string | number;
    label: React.ReactNode | string;
    package_number?: string | null;
    is_private?: boolean;
    is_selectable?: boolean;
    status?: string;
    country_id?: number | null;
    country_name?: string | null;
    total_seats?: number | null;
    seats_left?: number;
    departure_date?: string;
    return_date?: string | null;
}

interface UpcomingDepartureRow {
    id: number;
    package_number: string;
    name: string;
    status: string;
    country_id?: string;
    country_name?: string | null;
    departure_date: string;
    return_date?: string | null;
    total_seats?: number | null;
    seats_left?: number | null;
}

interface DashboardProps {
    data: {
        widgets?: {
            title: string;
            value: string | number;
            previous?: number;
            current?: number;
            period_start?: string;
            period_type?: 'year' | 'month';
        }[];
        customers?: UserSchema[];
        fiscalYear?: string;
        selectedYearId?: number;
        fiscalYearStartDate?: string;
        availableYears?: Array<{ value: number; label: string }>;
        nationality: [];
        religion: [];
        educationLevel: [];
        packageOptions?: GeneralEnquiryPackageOption[];
        categoryOptions?: { value: string; label: string }[];
        chartData?: {
            financial?: {
                'this-year': Array<{
                    month: string;
                    date: string;
                    expenses: number;
                    revenue: number;
                    profit: number;
                }>;
                'last-year': Array<{
                    month: string;
                    date: string;
                    expenses: number;
                    revenue: number;
                    profit: number;
                }>;
                'last-semester': Array<{
                    month: string;
                    date: string;
                    expenses: number;
                    revenue: number;
                    profit: number;
                }>;
                'last-quarter': Array<{
                    month: string;
                    date: string;
                    expenses: number;
                    revenue: number;
                    profit: number;
                }>;
            };
            customers?: Array<{
                date: string;
                count: number;
                label: string;
            }>;
        };
        misc?: {
            nationalities: ValueNumberOptionType[];
            religions: ValueNumberOptionType[];
            education_levels: ValueNumberOptionType[];
        };
        enquiries?: EnquiryRowType[];
        enquirySummary?: EnquirySummaryType;
    };
}

export default function Dashboard({ data }: DashboardProps) {
    const { auth } = usePage<SharedData>().props;
    const userPermissions = auth.permissions || [];

    // roles
    const isSuperadmin = auth.roles.includes('superadmin');
    const isAdmin = auth.roles.includes('admin');
    const isSales = auth.roles.includes('sales');
    const canViewClosingReport = isSales || isAdmin || isSuperadmin;
    const scopeMode = (auth.scope_mode ?? 'country') as 'country' | 'branch';
    const scopeCountryOptions = useMemo(
        () =>
            Array.isArray(auth.scope_country_options)
                ? auth.scope_country_options.map((option) => ({
                      value: String(option.id),
                      label: option.label,
                  }))
                : [],
        [auth.scope_country_options],
    );

    // State for API fetched data
    const [fiscalYearTotalSalesData, setFiscalYearTotalSalesData] =
        useState<FiscalYearTotalSalesType | null>(null);
    const paymentSummaryPeriod = 'daily' as const;
    const [paymentSummaryData, setPaymentSummaryData] =
        useState<PaymentSummaryType | null>(null);
    const [isLoadingPaymentSummary, setIsLoadingPaymentSummary] =
        useState(false);
    const [isExportPopoverOpen, setIsExportPopoverOpen] = useState(false);
    const todayDisplayDate = formatDateForDisplay(new Date());
    const [exportDateRange, setExportDateRange] = useState<{
        from?: string;
        to?: string;
    }>({ from: todayDisplayDate });
    const [exportPackageIds, setExportPackageIds] = useState<string[]>([]);
    const [exportCategoryIds, setExportCategoryIds] = useState<string[]>([]);
    const dailyReceivedQuickOptions: QuickDateOption[] = [
        { label: 'Today', value: 'today' },
        { label: 'Yesterday', value: 'yesterday' },
        { label: 'This Month', value: 'thismonth' },
        { label: 'Last Month', value: 'lastmonth' },
    ];

    const exportSelectedRange: DateRange | undefined = exportDateRange.from
        ? {
              from: parseDisplayDate(exportDateRange.from),
              to: exportDateRange.to
                  ? parseDisplayDate(exportDateRange.to)
                  : undefined,
          }
        : undefined;

    const applyQuickDate = useCallback((type: QuickDateKey) => {
        const { from: fromDate, to: toDate } = resolveQuickDateRange(type);

        setExportDateRange({
            from: formatDateForDisplay(fromDate),
            to: toDate ? formatDateForDisplay(toDate) : undefined,
        });
    }, []);

    // ── Group Report state ────────────────────────────────────────────
    const nowDt = DateTime.now();
    const currentMonthStr = nowDt.toFormat('yyyy-MM'); // e.g. '2026-04'
    const [groupPeriod, setGroupPeriod] = useState<'daily' | 'monthly'>(
        'daily',
    );
    const [groupDateRange, setGroupDateRange] = useState<{
        from?: string;
        to?: string;
    }>({ from: todayDisplayDate });
    const [groupMonth, setGroupMonth] = useState<string>(currentMonthStr);
    const [groupPackageIds, setGroupPackageIds] = useState<string[]>([]);
    const [groupCategoryIds, setGroupCategoryIds] = useState<string[]>([]);
    const [isGroupPopoverOpen, setIsGroupPopoverOpen] = useState(false);
    const closingQuickOptions: QuickDateOption[] = [
        { label: 'This Week', value: 'thisweek' },
        { label: 'This Month', value: 'thismonth' },
        { label: 'This Year', value: 'thisyear' },
    ];

    const applyGroupQuickDate = useCallback((type: QuickDateKey) => {
        const { from: fromDate, to: toDate } = resolveQuickDateRange(type);
        setGroupDateRange({
            from: formatDateForDisplay(fromDate),
            to: toDate ? formatDateForDisplay(toDate) : undefined,
        });
    }, []);

    const normalizedPackageOptions = useMemo(
        () => (data.packageOptions ?? []) as GeneralEnquiryPackageOption[],
        [data.packageOptions],
    );
    const categoryOptions: MultiSelectOption[] = (
        data.categoryOptions ?? []
    ).map((option) => ({
        value: String(option.value),
        label: option.label,
    }));
    const formattedCategoryOptions: MultiSelectGroup[] = [
        {
            heading: 'Categories',
            options: categoryOptions,
        },
    ];

    const groupedPackageOptions = useMemo(() => {
        const options = normalizedPackageOptions.sort((left, right) => {
            const leftDate = parseDisplayDate(left.departure_date);
            const rightDate = parseDisplayDate(right.departure_date);

            if (leftDate && rightDate) {
                return leftDate.getTime() - rightDate.getTime();
            }

            if (leftDate) {
                return -1;
            }

            if (rightDate) {
                return 1;
            }

            return String(left.label).localeCompare(String(right.label));
        });

        const groups: MultiSelectGroup[] = [];
        let previousGroupKey = '';
        let currentGroupOptions: MultiSelectOption[] = [];

        options.forEach((option) => {
            const departureDate = parseDisplayDate(option.departure_date);
            const groupKey = departureDate
                ? departureDate.toLocaleDateString('en-US', {
                      month: 'long',
                      year: 'numeric',
                  })
                : 'No Departure Date';

            if (groupKey !== previousGroupKey) {
                currentGroupOptions = [];
                groups.push({
                    heading: groupKey,
                    options: currentGroupOptions,
                });
                previousGroupKey = groupKey;
            }

            currentGroupOptions.push({
                value: String(option.value),
                label: `${option.label}`.trim(),
            });
        });

        return groups;
    }, [normalizedPackageOptions]);

    const upcomingDepartures = useMemo((): UpcomingDepartureRow[] => {
        const todayStart = DateTime.now().startOf('day');
        const rows: UpcomingDepartureRow[] = [];

        normalizedPackageOptions.forEach((option) => {
            const departureDate = parseDisplayDate(option.departure_date);
            if (!departureDate) return;

            const parsedDeparture = DateTime.fromJSDate(departureDate);
            if (parsedDeparture < todayStart) return;

            rows.push({
                id: Number(option.value),
                package_number: String(option.package_number ?? ''),
                name: String(option.label ?? ''),
                status: String(option.status ?? ''),
                country_id:
                    option.country_id !== null &&
                    option.country_id !== undefined
                        ? String(option.country_id)
                        : undefined,
                country_name: option.country_name ?? null,
                departure_date: formatDateForDisplay(departureDate),
                return_date: option.return_date ?? null,
                total_seats: option.total_seats ?? null,
                seats_left: option.seats_left ?? null,
            });
        });

        return rows.sort((left, right) => {
            const leftDate = parseDisplayDate(left.departure_date);
            const rightDate = parseDisplayDate(right.departure_date);
            if (leftDate && rightDate)
                return leftDate.getTime() - rightDate.getTime();
            if (leftDate) return -1;
            if (rightDate) return 1;
            return left.package_number.localeCompare(right.package_number);
        });
    }, [normalizedPackageOptions]);

    const upcomingDepartureColumns = useMemo(
        (): ColumnDef<UpcomingDepartureRow>[] => [
            {
                accessorKey: 'package_number',
                header: 'Package No.',
                meta: { exportable: true },
            },
            {
                accessorKey: 'name',
                header: 'Package',
                meta: { exportable: true },
            },
            {
                accessorKey: 'departure_date',
                header: 'Departure Date',
                meta: { exportable: true },
            },
            {
                accessorKey: 'return_date',
                header: 'Return Date',
                meta: { exportable: true },
                cell: ({ row }) => row.original.return_date ?? '-',
            },
            {
                accessorKey: 'seats_left',
                header: 'Available Seats',
                meta: { exportable: true },
                cell: ({ row }) => {
                    const total = row.original.total_seats;
                    const left = row.original.seats_left;

                    if (total === null || total === undefined) {
                        return '-';
                    }

                    return `${left ?? 0} / ${total}`;
                },
            },
            {
                accessorKey: 'status',
                header: 'Status',
                meta: { exportable: true },
                cell: ({ row }) => {
                    const normalizedStatus = String(row.original.status ?? '')
                        .trim()
                        .toLowerCase();

                    if (!normalizedStatus) {
                        return <span className="text-muted-foreground">-</span>;
                    }

                    return (
                        <Badge
                            className={`${packageStatusColors[normalizedStatus] ?? 'bg-gray-100 text-gray-800'} rounded-full px-3 py-1 text-base`}
                        >
                            {packageStatusLabels[normalizedStatus] ??
                                normalizedStatus}
                        </Badge>
                    );
                },
            },
            ...(scopeMode === 'country'
                ? [
                      {
                          accessorKey: 'country_name',
                          header: 'Country',
                          meta: { exportable: true },
                          cell: ({ row }) => row.original.country_name ?? '-',
                      } as ColumnDef<UpcomingDepartureRow>,
                  ]
                : []),
            {
                accessorKey: 'country_id',
                header: 'Country Id',
                meta: { exportable: true },
                filterFn: 'includesValue',
            },
        ],
        [scopeMode],
    );

    const groupSelectedRange: DateRange | undefined = groupDateRange.from
        ? {
              from: parseDisplayDate(groupDateRange.from),
              to: groupDateRange.to
                  ? parseDisplayDate(groupDateRange.to)
                  : undefined,
          }
        : undefined;

    const buildGroupReportParams = useCallback(() => {
        const params = new URLSearchParams({ period: 'monthly' });
        const userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
        if (userTimezone) params.set('timezone', userTimezone);
        if (groupPackageIds && groupPackageIds.length > 0) {
            params.set('package_id', groupPackageIds.join(','));
        }
        if (groupCategoryIds && groupCategoryIds.length > 0) {
            params.set('categories', groupCategoryIds.join(','));
        }

        if (groupPeriod === 'monthly') {
            params.set('month', groupMonth);

            const monthDt = DateTime.fromFormat(groupMonth, 'yyyy-MM', {
                zone: userTimezone || 'UTC',
            });
            const rangeStart = monthDt.isValid
                ? monthDt.startOf('month')
                : DateTime.now()
                      .setZone(userTimezone || 'UTC')
                      .startOf('month');
            const rangeEnd = monthDt.isValid
                ? monthDt.endOf('month')
                : rangeStart.endOf('month');
            params.set('range_start_utc', rangeStart.toUTC().toISO() ?? '');
            params.set('range_end_utc', rangeEnd.toUTC().toISO() ?? '');
        } else {
            // daily range
            const fromDate = groupDateRange.from
                ? DateTime.fromFormat(groupDateRange.from, 'dd MMMM yyyy', {
                      zone: userTimezone || 'UTC',
                      locale: 'en-GB',
                  })
                : null;
            const toDate = groupDateRange.to
                ? DateTime.fromFormat(groupDateRange.to, 'dd MMMM yyyy', {
                      zone: userTimezone || 'UTC',
                      locale: 'en-GB',
                  })
                : null;
            const rangeStart = fromDate?.isValid
                ? fromDate.startOf('day')
                : DateTime.now()
                      .setZone(userTimezone || 'UTC')
                      .startOf('day');
            const rangeEnd =
                (toDate?.isValid ? toDate : fromDate)?.endOf('day') ??
                rangeStart.endOf('day');
            params.set('range_start_utc', rangeStart.toUTC().toISO() ?? '');
            params.set('range_end_utc', rangeEnd.toUTC().toISO() ?? '');
        }
        return params;
    }, [
        groupPeriod,
        groupDateRange,
        groupMonth,
        groupPackageIds,
        groupCategoryIds,
    ]);

    const handleExportGroupReportPdf = useCallback(() => {
        const params = buildGroupReportParams();
        window.open(
            `${closingReportExport.definition.url}?${params.toString()}`,
            '_blank',
        );
        setIsGroupPopoverOpen(false);
    }, [buildGroupReportParams]);

    const yearOptions = getYearOptions(nowDt.year);

    const buildPaymentSummaryParams = useCallback(
        (
            period: 'daily' | 'monthly' | 'yearly',
            yearId?: number,
            dateRange?: { from?: string; to?: string },
        ) => {
            const params = new URLSearchParams({ period });

            if (yearId) {
                params.set('financial_year_id', String(yearId));
            }

            const userTimezone =
                Intl.DateTimeFormat().resolvedOptions().timeZone;

            if (userTimezone) {
                params.set('timezone', userTimezone);
            }

            // Keep yearly + selected financial year on backend fiscal-year boundaries.
            if (period === 'yearly' && yearId) {
                return params;
            }

            const nowInUserTimezone = DateTime.now().setZone(
                userTimezone || 'UTC',
            );

            let localRangeStart =
                period === 'monthly'
                    ? nowInUserTimezone.startOf('month')
                    : period === 'yearly'
                      ? nowInUserTimezone.startOf('year')
                      : nowInUserTimezone.startOf('day');
            let localRangeEnd =
                period === 'monthly'
                    ? nowInUserTimezone.endOf('month')
                    : period === 'yearly'
                      ? nowInUserTimezone.endOf('year')
                      : nowInUserTimezone.endOf('day');

            if (period === 'daily' && dateRange) {
                const fromDate = dateRange.from
                    ? DateTime.fromFormat(dateRange.from, 'dd MMMM yyyy', {
                          zone: userTimezone || 'UTC',
                          locale: 'en-GB',
                      })
                    : null;
                const toDate = dateRange.to
                    ? DateTime.fromFormat(dateRange.to, 'dd MMMM yyyy', {
                          zone: userTimezone || 'UTC',
                          locale: 'en-GB',
                      })
                    : null;

                if (fromDate?.isValid) {
                    localRangeStart = fromDate.startOf('day');
                    // If only a start date set (no end), treat as single-day
                    localRangeEnd = (toDate?.isValid ? toDate : fromDate).endOf(
                        'day',
                    );
                }
            }

            const rangeStartUtc = localRangeStart.toUTC().toISO();
            const rangeEndUtc = localRangeEnd.toUTC().toISO();

            if (rangeStartUtc) {
                params.set('range_start_utc', rangeStartUtc);
            }

            if (rangeEndUtc) {
                params.set('range_end_utc', rangeEndUtc);
            }

            if (exportPackageIds && exportPackageIds.length > 0) {
                params.set('packages', exportPackageIds.join(','));
            }

            if (exportCategoryIds && exportCategoryIds.length > 0) {
                params.set('categories', exportCategoryIds.join(','));
            }

            return params;
        },
        [exportPackageIds, exportCategoryIds],
    );

    // Fetch dashboard data for admin
    const fetchAdminDashboardData = useCallback(
        async (yearId?: number) => {
            if (!isSuperadmin) return;

            try {
                const queryOptions = yearId
                    ? { query: { financial_year_id: yearId.toString() } }
                    : undefined;

                const fytdRes = await fetch(fiscalYearSales(queryOptions).url);

                if (fytdRes.ok) {
                    const fytdData = await fytdRes.json();
                    setFiscalYearTotalSalesData(fytdData);
                }
            } catch (error) {
                console.error('Error fetching dashboard data:', error);
            }
        },
        [isSuperadmin],
    );

    const fetchPaymentSummaryData = useCallback(
        async (period: 'daily' | 'monthly' | 'yearly', yearId?: number) => {
            if (!isSuperadmin) return;

            setIsLoadingPaymentSummary(true);

            try {
                const params = buildPaymentSummaryParams(period, yearId);

                const response = await fetch(
                    `${paymentReport.definition.url}?${params.toString()}`,
                );

                if (response.ok) {
                    const summary =
                        (await response.json()) as PaymentSummaryType;
                    setPaymentSummaryData(summary);
                }
            } catch (error) {
                console.error('Error fetching payment summary:', error);
            } finally {
                setIsLoadingPaymentSummary(false);
            }
        },
        [buildPaymentSummaryParams, isSuperadmin],
    );

    const handleExportPaymentSummaryPdf = useCallback(() => {
        const params = buildPaymentSummaryParams(
            paymentSummaryPeriod,
            data.selectedYearId,
            exportDateRange,
        );

        window.open(
            `${paymentReportExport.definition.url}?${params.toString()}`,
            '_blank',
        );
        setIsExportPopoverOpen(false);
    }, [
        buildPaymentSummaryParams,
        data.selectedYearId,
        exportDateRange,
        paymentSummaryPeriod,
    ]);

    const paymentSummaryDateLabel = (() => {
        const rawLabel = String(
            paymentSummaryData?.date_range_label ?? '',
        ).trim();

        if (rawLabel.length === 0) {
            return '-';
        }

        if (paymentSummaryPeriod !== 'daily') {
            return rawLabel;
        }

        const [startLabel] = rawLabel.split(' - ');

        return String(startLabel ?? rawLabel).trim() || rawLabel;
    })();

    // Initial data fetch for admin
    useEffect(() => {
        if (isSuperadmin && data.selectedYearId) {
            fetchAdminDashboardData(data.selectedYearId);
        }
    }, [isSuperadmin, data.selectedYearId, fetchAdminDashboardData]);

    useEffect(() => {
        if (!isSuperadmin) {
            return;
        }

        fetchPaymentSummaryData(paymentSummaryPeriod, data.selectedYearId);
    }, [
        data.selectedYearId,
        fetchPaymentSummaryData,
        isSuperadmin,
        paymentSummaryPeriod,
    ]);

    // actions
    const actions: ActionType[] = [];
    if (userPermissions.includes('customer create')) actions.push('add');
    if (userPermissions.includes('customer view')) actions.push('view');
    if (userPermissions.includes('customer edit')) actions.push('edit');
    if (userPermissions.includes('customer delete')) actions.push('delete');

    // columns
    const customerColumns: ColumnDef<UserSchema>[] = [
        createSelectColumn<UserSchema>(),
        { accessorKey: 'name', header: 'Name' },
        { accessorKey: 'email', header: 'Email' },
        { accessorKey: 'contact', header: 'Contact' },
        {
            accessorKey: 'last_login',
            header: 'Last Login',
            meta: { exportable: true },
            cell: ({ row }) => {
                const value = row.getValue('last_login');

                if (!value)
                    return <span className="text-muted-foreground">Never</span>;

                return (
                    <span className="text-base text-muted-foreground capitalize">
                        {formatUserTime(String(value))}
                    </span>
                );
            },
        },
    ];

    const { confirm, ConfirmDialog } = useConfirmDialog();

    const copyPublicEnquiryLink = async (
        enquiryType: 'general' | 'private',
    ): Promise<void> => {
        try {
            const path =
                enquiryType === 'general'
                    ? generalPublicCreate().url
                    : privatePublicCreate().url;
            const fullUrl = new URL(path, window.location.origin).toString();

            await navigator.clipboard.writeText(fullUrl);

            toast.success('Public form link copied.', {
                description:
                    enquiryType === 'general'
                        ? 'General enquiry public link copied to clipboard.'
                        : 'Private enquiry public link copied to clipboard.',
            });
        } catch {
            toast.error('Failed to copy public form link.');
        }
    };

    return (
        <>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Dashboard" />
                <div className="@container/main flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    {/* Admin: Fiscal Year Selector */}
                    {isSuperadmin &&
                        data.availableYears &&
                        data.availableYears.length > 0 && (
                            <div className="flex items-center justify-between rounded-lg border bg-card p-4">
                                <div>
                                    <h3 className="text-2xl font-semibold">
                                        Insights for you
                                    </h3>
                                    <p className="text-base text-muted-foreground">
                                        Select a fiscal year
                                    </p>
                                </div>
                                <div>
                                    <Select
                                        value={data.selectedYearId?.toString()}
                                        onValueChange={(value) => {
                                            router.get(
                                                dashboard().url,
                                                {
                                                    financial_year_id:
                                                        parseInt(value),
                                                },
                                                {
                                                    preserveState: false,
                                                    preserveScroll: false,
                                                },
                                            );
                                        }}
                                    >
                                        <SelectTrigger className="w-[120px]">
                                            <SelectValue placeholder="Select fiscal year" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {data.availableYears.map((year) => (
                                                <SelectItem
                                                    key={year.value}
                                                    value={year.value.toString()}
                                                >
                                                    {year.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        )}

                    {/* Admin: Fiscal Year - Total Sales (FYTD # and $) */}
                    {isSuperadmin && fiscalYearTotalSalesData && (
                        <div>
                            <h2 className="mb-3 text-lg font-semibold">
                                Fiscal Year - Total Sales
                            </h2>
                            <div className="grid grid-cols-1 gap-4 md:w-[80%] md:grid-cols-2 lg:w-[50%]">
                                {/* <div className="mx-auto grid grid-cols-1 gap-4 md:w-[80%] md:grid-cols-2 lg:w-[50%]"> */}
                                <Card className="gap-3 bg-gradient-to-t from-primary/5 to-card">
                                    <CardHeader className="gap-0">
                                        <CardTitle className="text-md font-semibold">
                                            FYTD (#)
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <p className="text-3xl font-bold">
                                            {fiscalYearTotalSalesData.count}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            Total Number of Sales
                                        </p>
                                    </CardContent>
                                </Card>
                                <Card className="gap-3 bg-gradient-to-t from-primary/5 to-card">
                                    <CardHeader className="gap-0">
                                        <CardTitle className="text-md font-semibold">
                                            FYTD ($)
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <p className="text-3xl font-bold">
                                            {formatCurrency(
                                                fiscalYearTotalSalesData.amount,
                                            )}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            Total Amount
                                        </p>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                    )}

                    {/* Admin: Daily/Monthly/Yearly Payment */}
                    {isSuperadmin && (
                        <div>
                            <div className="mb-3 flex flex-col gap-3 md:flex-row md:items-center">
                                <div>
                                    <h2 className="text-lg font-semibold">
                                        Daily Received
                                    </h2>
                                    <p className="hidden text-base text-muted-foreground">
                                        Receipt payment breakdown by item
                                        category ({paymentSummaryDateLabel})
                                    </p>
                                </div>

                                <div className="flex items-center gap-2">
                                    <Popover
                                        open={isExportPopoverOpen}
                                        onOpenChange={setIsExportPopoverOpen}
                                    >
                                        <PopoverTrigger asChild>
                                            <Button
                                                type="button"
                                                variant="default"
                                            >
                                                <Download className="h-4 w-4" />
                                                Export Report
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent
                                            className="w-auto p-3"
                                            align="start"
                                            side="bottom"
                                            sideOffset={4}
                                        >
                                            <div className="flex gap-3">
                                                <div className="space-y-2">
                                                    <div className="hidden space-y-1">
                                                        <p className="font-medium">
                                                            Departure Group
                                                            (Package)
                                                        </p>
                                                        <MultiSelect
                                                            options={
                                                                groupedPackageOptions
                                                            }
                                                            onValueChange={
                                                                setExportPackageIds
                                                            }
                                                            defaultValue={
                                                                exportPackageIds
                                                            }
                                                            placeholder="Select package..."
                                                            maxCount={0}
                                                        />
                                                    </div>

                                                    <div className="hidden space-y-1">
                                                        <p className="font-medium">
                                                            Category
                                                        </p>
                                                        <MultiSelect
                                                            options={
                                                                formattedCategoryOptions
                                                            }
                                                            onValueChange={
                                                                setExportCategoryIds
                                                            }
                                                            defaultValue={
                                                                exportCategoryIds
                                                            }
                                                            placeholder="Select category..."
                                                            maxCount={0}
                                                        />
                                                    </div>

                                                    <Calendar
                                                        mode="range"
                                                        numberOfMonths={1}
                                                        selected={
                                                            exportSelectedRange
                                                        }
                                                        defaultMonth={
                                                            exportSelectedRange?.from
                                                        }
                                                        onSelect={(range) => {
                                                            if (!range?.from) {
                                                                setExportDateRange(
                                                                    {
                                                                        from: todayDisplayDate,
                                                                        to: undefined,
                                                                    },
                                                                );

                                                                return;
                                                            }

                                                            setExportDateRange({
                                                                from: formatDateForDisplay(
                                                                    range.from,
                                                                ),
                                                                to: range.to
                                                                    ? formatDateForDisplay(
                                                                          range.to,
                                                                      )
                                                                    : undefined,
                                                            });
                                                        }}
                                                        className="rounded-lg border shadow-sm"
                                                    />

                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        className="w-full"
                                                        disabled={
                                                            isLoadingPaymentSummary
                                                        }
                                                        onClick={
                                                            handleExportPaymentSummaryPdf
                                                        }
                                                    >
                                                        <Download className="h-4 w-4" />
                                                        Export
                                                    </Button>

                                                    <div className="mt-3 flex flex-col gap-1 border-t pt-3 md:hidden">
                                                        <p className="mb-2 text-sm font-medium">
                                                            Quick Select
                                                        </p>

                                                        {dailyReceivedQuickOptions.map(
                                                            (item) => (
                                                                <Button
                                                                    key={
                                                                        item.value
                                                                    }
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="justify-start"
                                                                    onClick={() =>
                                                                        applyQuickDate(
                                                                            item.value,
                                                                        )
                                                                    }
                                                                >
                                                                    {item.label}
                                                                </Button>
                                                            ),
                                                        )}
                                                    </div>
                                                </div>

                                                <div className="hidden min-w-[160px] flex-col gap-1 border-l pl-4 md:flex">
                                                    <p className="mb-2 text-sm font-medium">
                                                        Quick Select
                                                    </p>

                                                    {dailyReceivedQuickOptions.map(
                                                        (item) => (
                                                            <Button
                                                                key={item.value}
                                                                variant="ghost"
                                                                size="sm"
                                                                className="justify-start"
                                                                onClick={() =>
                                                                    applyQuickDate(
                                                                        item.value,
                                                                    )
                                                                }
                                                            >
                                                                {item.label}
                                                            </Button>
                                                        ),
                                                    )}
                                                </div>
                                            </div>
                                        </PopoverContent>
                                    </Popover>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                                {isLoadingPaymentSummary && (
                                    <Card className="bg-gradient-to-t from-primary/5 to-card">
                                        <CardContent className="pt-6">
                                            <p className="text-base text-muted-foreground">
                                                Loading payment summary...
                                            </p>
                                        </CardContent>
                                    </Card>
                                )}

                                {!isLoadingPaymentSummary &&
                                    paymentSummaryData?.categories?.map(
                                        (category) => (
                                            <Card
                                                key={category.category}
                                                className="bg-gradient-to-t from-primary/5 to-card"
                                            >
                                                <CardHeader className="gap-1 pb-2">
                                                    <CardTitle className="text-base font-semibold">
                                                        {category.category}
                                                    </CardTitle>
                                                </CardHeader>
                                                <CardContent className="pt-0">
                                                    <p className="text-2xl font-bold">
                                                        {formatCurrency(
                                                            category.amount,
                                                        )}
                                                    </p>
                                                </CardContent>
                                            </Card>
                                        ),
                                    )}

                                {!isLoadingPaymentSummary &&
                                    (!paymentSummaryData?.categories ||
                                        paymentSummaryData.categories.length ===
                                            0) && (
                                        <Card className="bg-gradient-to-t from-primary/5 to-card">
                                            <CardContent>
                                                <p className="text-base text-muted-foreground">
                                                    No payment data found.
                                                </p>
                                            </CardContent>
                                        </Card>
                                    )}
                            </div>
                        </div>
                    )}

                    {canViewClosingReport && (
                        <div>
                            <div className="mb-3 flex flex-col gap-3 md:flex-row md:items-center">
                                <div>
                                    <h2 className="text-lg font-semibold">
                                        Closing Report
                                    </h2>
                                </div>

                                <div className="flex items-center gap-2">
                                    <Popover
                                        open={isGroupPopoverOpen}
                                        onOpenChange={setIsGroupPopoverOpen}
                                    >
                                        <PopoverTrigger asChild>
                                            <Button
                                                type="button"
                                                variant="default"
                                            >
                                                <Download className="h-4 w-4" />
                                                Export Report
                                            </Button>
                                        </PopoverTrigger>

                                        <PopoverContent
                                            className="w-auto p-4"
                                            align="start"
                                            side="bottom"
                                            sideOffset={4}
                                        >
                                            <div className="flex gap-3">
                                                <div className="space-y-2">
                                                    <div className="hidden space-y-1">
                                                        <p className="font-medium">
                                                            Period
                                                        </p>
                                                        <div className="flex gap-2">
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant={
                                                                    groupPeriod ===
                                                                    'daily'
                                                                        ? 'default'
                                                                        : 'outline'
                                                                }
                                                                onClick={() =>
                                                                    setGroupPeriod(
                                                                        'daily',
                                                                    )
                                                                }
                                                            >
                                                                Daily
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant={
                                                                    groupPeriod ===
                                                                    'monthly'
                                                                        ? 'default'
                                                                        : 'outline'
                                                                }
                                                                onClick={() =>
                                                                    setGroupPeriod(
                                                                        'monthly',
                                                                    )
                                                                }
                                                            >
                                                                Monthly
                                                            </Button>
                                                        </div>
                                                    </div>

                                                    {groupPeriod === 'daily' ? (
                                                        <Calendar
                                                            mode="range"
                                                            numberOfMonths={1}
                                                            selected={
                                                                groupSelectedRange
                                                            }
                                                            defaultMonth={
                                                                groupSelectedRange?.from
                                                            }
                                                            onSelect={(
                                                                range,
                                                            ) => {
                                                                if (
                                                                    !range?.from
                                                                ) {
                                                                    setGroupDateRange(
                                                                        {
                                                                            from: todayDisplayDate,
                                                                            to: undefined,
                                                                        },
                                                                    );
                                                                    return;
                                                                }
                                                                setGroupDateRange(
                                                                    {
                                                                        from: formatDateForDisplay(
                                                                            range.from,
                                                                        ),
                                                                        to: range.to
                                                                            ? formatDateForDisplay(
                                                                                  range.to,
                                                                              )
                                                                            : undefined,
                                                                    },
                                                                );
                                                            }}
                                                            className="rounded-lg border shadow-sm"
                                                        />
                                                    ) : (
                                                        <div className="space-y-2">
                                                            <div className="flex gap-2">
                                                                <Select
                                                                    value={
                                                                        splitYearMonth(
                                                                            groupMonth,
                                                                        ).month
                                                                    }
                                                                    onValueChange={(
                                                                        month,
                                                                    ) =>
                                                                        setGroupMonth(
                                                                            joinYearMonth(
                                                                                splitYearMonth(
                                                                                    groupMonth,
                                                                                )
                                                                                    .year,
                                                                                month,
                                                                            ),
                                                                        )
                                                                    }
                                                                >
                                                                    <SelectTrigger className="h-8">
                                                                        <SelectValue />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        {MONTHS.map(
                                                                            (
                                                                                month,
                                                                            ) => (
                                                                                <SelectItem
                                                                                    key={
                                                                                        month.value
                                                                                    }
                                                                                    value={
                                                                                        month.value
                                                                                    }
                                                                                >
                                                                                    {
                                                                                        month.label
                                                                                    }
                                                                                </SelectItem>
                                                                            ),
                                                                        )}
                                                                    </SelectContent>
                                                                </Select>
                                                                <Select
                                                                    value={
                                                                        splitYearMonth(
                                                                            groupMonth,
                                                                        ).year
                                                                    }
                                                                    onValueChange={(
                                                                        year,
                                                                    ) =>
                                                                        setGroupMonth(
                                                                            joinYearMonth(
                                                                                year,
                                                                                splitYearMonth(
                                                                                    groupMonth,
                                                                                )
                                                                                    .month,
                                                                            ),
                                                                        )
                                                                    }
                                                                >
                                                                    <SelectTrigger className="h-8">
                                                                        <SelectValue />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        {yearOptions.map(
                                                                            (
                                                                                year,
                                                                            ) => (
                                                                                <SelectItem
                                                                                    key={
                                                                                        year
                                                                                    }
                                                                                    value={
                                                                                        year
                                                                                    }
                                                                                >
                                                                                    {
                                                                                        year
                                                                                    }
                                                                                </SelectItem>
                                                                            ),
                                                                        )}
                                                                    </SelectContent>
                                                                </Select>
                                                            </div>
                                                        </div>
                                                    )}

                                                    {groupPeriod ===
                                                        'daily' && (
                                                        <div className="mt-3 flex flex-col gap-1 border-t pt-3 md:hidden">
                                                            <p className="mb-2 text-sm font-medium">
                                                                Quick Select
                                                            </p>

                                                            {closingQuickOptions.map(
                                                                (item) => (
                                                                    <Button
                                                                        key={
                                                                            item.value
                                                                        }
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        className="justify-start"
                                                                        onClick={() =>
                                                                            applyGroupQuickDate(
                                                                                item.value,
                                                                            )
                                                                        }
                                                                    >
                                                                        {
                                                                            item.label
                                                                        }
                                                                    </Button>
                                                                ),
                                                            )}
                                                        </div>
                                                    )}

                                                    <div className="flex flex-col gap-3 md:hidden">
                                                        <div className="space-y-1">
                                                            <p className="font-medium">
                                                                Departure Group
                                                                (Package)
                                                            </p>
                                                            <MultiSelect
                                                                options={
                                                                    groupedPackageOptions
                                                                }
                                                                onValueChange={
                                                                    setGroupPackageIds
                                                                }
                                                                defaultValue={
                                                                    groupPackageIds
                                                                }
                                                                placeholder="Select package..."
                                                                maxCount={0}
                                                            />
                                                        </div>

                                                        <div className="space-y-1">
                                                            <p className="font-medium">
                                                                Category
                                                            </p>
                                                            <MultiSelect
                                                                options={
                                                                    formattedCategoryOptions
                                                                }
                                                                onValueChange={
                                                                    setGroupCategoryIds
                                                                }
                                                                defaultValue={
                                                                    groupCategoryIds
                                                                }
                                                                placeholder="Select category..."
                                                                maxCount={0}
                                                            />
                                                        </div>

                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            className="w-full"
                                                            onClick={
                                                                handleExportGroupReportPdf
                                                            }
                                                        >
                                                            <Download className="h-4 w-4" />
                                                            Export
                                                        </Button>
                                                    </div>
                                                </div>

                                                <div className="hidden flex-col gap-3 border-l pl-3 md:flex">
                                                    <div className="space-y-1">
                                                        <p className="font-medium">
                                                            Departure Group
                                                            (Package)
                                                        </p>
                                                        <MultiSelect
                                                            options={
                                                                groupedPackageOptions
                                                            }
                                                            onValueChange={
                                                                setGroupPackageIds
                                                            }
                                                            defaultValue={
                                                                groupPackageIds
                                                            }
                                                            placeholder="Select package..."
                                                            maxCount={0}
                                                        />
                                                    </div>

                                                    <div className="space-y-1">
                                                        <p className="font-medium">
                                                            Category
                                                        </p>
                                                        <MultiSelect
                                                            options={
                                                                formattedCategoryOptions
                                                            }
                                                            onValueChange={
                                                                setGroupCategoryIds
                                                            }
                                                            defaultValue={
                                                                groupCategoryIds
                                                            }
                                                            placeholder="Select category..."
                                                            maxCount={0}
                                                        />
                                                    </div>

                                                    {groupPeriod ===
                                                        'daily' && (
                                                        <div className="mt-1 flex flex-col gap-1 border-t pt-3">
                                                            <p className="mb-2 text-sm font-medium">
                                                                Quick Select
                                                            </p>

                                                            {closingQuickOptions.map(
                                                                (item) => (
                                                                    <Button
                                                                        key={
                                                                            item.value
                                                                        }
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        className="justify-start"
                                                                        onClick={() =>
                                                                            applyGroupQuickDate(
                                                                                item.value,
                                                                            )
                                                                        }
                                                                    >
                                                                        {
                                                                            item.label
                                                                        }
                                                                    </Button>
                                                                ),
                                                            )}
                                                        </div>
                                                    )}

                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        className="w-full"
                                                        onClick={
                                                            handleExportGroupReportPdf
                                                        }
                                                    >
                                                        <Download className="h-4 w-4" />
                                                        Export
                                                    </Button>
                                                </div>
                                            </div>
                                        </PopoverContent>
                                    </Popover>
                                </div>
                            </div>
                        </div>
                    )}

                    {(isSuperadmin || isAdmin) && (
                        <div>
                            <div className="mb-3 flex items-center justify-between">
                                <div>
                                    <h2 className="text-lg font-semibold">
                                        Upcoming Departures
                                    </h2>
                                    <p className="text-base text-muted-foreground">
                                        Packages sorted by nearest departure
                                        date
                                    </p>
                                </div>
                                <Button asChild variant="default">
                                    <Link href={packageIndex().url}>
                                        View All
                                    </Link>
                                </Button>
                            </div>

                            <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                                <DataTable
                                    columns={upcomingDepartureColumns}
                                    data={upcomingDepartures}
                                    actions={[
                                        // 'add',
                                        'view',
                                        'edit',
                                        'download',
                                    ]}
                                    addButtonText="Create New Package"
                                    url={packageIndex().url}
                                    onAction={(action, row) => {
                                        if (action === 'add') {
                                            router.get(packageCreate().url);
                                        }

                                        const targetId = row?.original?.id;

                                        if (targetId !== undefined) {
                                            if (action === 'view') {
                                                router.get(
                                                    packageShow(targetId).url,
                                                );
                                            } else if (action === 'edit') {
                                                router.get(
                                                    packageEdit(targetId).url,
                                                );
                                            } else if (action === 'download') {
                                                window.open(
                                                    packageDownload(targetId)
                                                        .url,
                                                    '_blank',
                                                );
                                            }
                                        }
                                    }}
                                    onRowDoubleClick={(row) => {
                                        if (row.id) {
                                            router.get(packageEdit(row.id).url);
                                        }
                                    }}
                                    initialState={{
                                        pagination: {
                                            pageIndex: 0,
                                            pageSize: 10,
                                        },
                                        columnVisibility: {
                                            country_id: false,
                                        },
                                    }}
                                    renderFilter={(table) => (
                                        <>
                                            {scopeMode === 'country' &&
                                                scopeCountryOptions.length >
                                                    0 && (
                                                    <ColumnFilter
                                                        table={table}
                                                        columnId="country_id"
                                                        title="Country"
                                                        options={
                                                            scopeCountryOptions
                                                        }
                                                    />
                                                )}
                                        </>
                                    )}
                                />
                            </div>
                        </div>
                    )}

                    {/* Sales: Enquiry Dashboard */}
                    {isSales && data.enquiries && (
                        <div>
                            <div className="mb-3 flex items-center justify-between">
                                <div>
                                    <h2 className="text-lg font-semibold">
                                        Enquiry Dashboard
                                    </h2>
                                    <p className="text-base text-muted-foreground">
                                        {data.enquirySummary
                                            ? `${data.enquirySummary.total} total — ${data.enquirySummary.general} general, ${data.enquirySummary.private} private`
                                            : 'General and private enquiries'}
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            void copyPublicEnquiryLink(
                                                'general',
                                            )
                                        }
                                    >
                                        Copy General Public Link
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            void copyPublicEnquiryLink(
                                                'private',
                                            )
                                        }
                                    >
                                        Copy Private Public Link
                                    </Button>
                                    <Button asChild variant="outline">
                                        <Link href={enquiriesIndex().url}>
                                            View All
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                            <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                                <DataTable
                                    columns={[
                                        createSelectColumn<EnquiryRowType>(),
                                        {
                                            accessorKey: 'id',
                                            header: 'ID',
                                            meta: { exportable: true },
                                        },
                                        {
                                            accessorKey: 'type',
                                            header: 'Type',
                                            meta: { exportable: true },
                                            cell: ({ row }) => {
                                                const type = row.original.type;
                                                const color =
                                                    type === 'General'
                                                        ? 'bg-blue-500/10 text-blue-700 hover:bg-blue-500/20 dark:bg-blue-500/20 dark:text-blue-400'
                                                        : 'bg-purple-500/10 text-purple-700 hover:bg-purple-500/20 dark:bg-purple-500/20 dark:text-purple-400';
                                                return (
                                                    <Badge
                                                        className={`${color} rounded-full px-3 py-1 text-base`}
                                                    >
                                                        {type}
                                                    </Badge>
                                                );
                                            },
                                        },
                                        {
                                            accessorKey: 'status',
                                            header: 'Status',
                                            meta: { exportable: true },
                                            cell: ({ row }) => {
                                                const status =
                                                    row.original.status;
                                                const label =
                                                    row.original.status_label;
                                                const statusColors: Record<
                                                    string,
                                                    string
                                                > = {
                                                    new_lead:
                                                        'bg-gray-500/10 text-gray-700 hover:bg-gray-500/20 dark:bg-gray-500/20 dark:text-gray-400',
                                                    contacted:
                                                        'bg-yellow-500/10 text-yellow-700 hover:bg-yellow-500/20 dark:bg-yellow-500/20 dark:text-yellow-400',
                                                    negotiating:
                                                        'bg-blue-500/10 text-blue-700 hover:bg-blue-500/20 dark:bg-blue-500/20 dark:text-blue-400',
                                                    confirmed:
                                                        'bg-green-500/10 text-green-700 hover:bg-green-500/20 dark:bg-green-500/20 dark:text-green-400',
                                                };
                                                return (
                                                    <Badge
                                                        className={`${statusColors[status] ?? ''} rounded-full px-3 py-1 text-base`}
                                                    >
                                                        {label}
                                                    </Badge>
                                                );
                                            },
                                        },
                                        {
                                            accessorKey: 'name',
                                            header: 'Full Name',
                                            meta: { exportable: true },
                                        },
                                        {
                                            accessorKey: 'contact',
                                            header: 'Contact',
                                            meta: { exportable: true },
                                        },
                                        {
                                            accessorKey: 'email',
                                            header: 'Email',
                                            meta: { exportable: true },
                                        },
                                        {
                                            accessorKey: 'package_name',
                                            header: 'Package',
                                            meta: { exportable: true },
                                            cell: ({ row }) => {
                                                const name =
                                                    row.original.package_name;
                                                if (!name)
                                                    return (
                                                        <span className="text-muted-foreground">
                                                            -
                                                        </span>
                                                    );
                                                return (
                                                    <Badge
                                                        variant="outline"
                                                        className="rounded-full px-3 py-1 text-base"
                                                    >
                                                        {name}
                                                    </Badge>
                                                );
                                            },
                                        },
                                        {
                                            accessorKey: 'latest_remark',
                                            header: 'Latest Remark',
                                            meta: { exportable: true },
                                        },
                                        {
                                            accessorKey: 'created_at',
                                            header: 'Created At',
                                            meta: { exportable: true },
                                        },
                                    ]}
                                    data={data.enquiries}
                                    actions={['view', 'edit']}
                                    url={enquiriesIndex().url}
                                    onAction={(action, row) => {
                                        const enquiry = row?.original;
                                        if (!enquiry) return;
                                        if (action === 'view') {
                                            router.get(enquiriesIndex().url);
                                        } else if (action === 'edit') {
                                            if (
                                                enquiry.type === 'General' &&
                                                enquiry.child_id
                                            ) {
                                                router.get(
                                                    generalEnquiryEdit(
                                                        enquiry.child_id,
                                                    ).url,
                                                );
                                            } else if (enquiry.child_id) {
                                                router.get(
                                                    privateEnquiryEdit(
                                                        enquiry.child_id,
                                                    ).url,
                                                );
                                            }
                                        }
                                    }}
                                    initialState={{
                                        columnVisibility: {
                                            id: false,
                                            package_name: false,
                                        },
                                        pagination: {
                                            pageIndex: 0,
                                            pageSize: 10,
                                        },
                                    }}
                                />
                            </div>
                        </div>
                    )}

                    {/* Customer DataTable - Recent Customers for Admin */}
                    {isSuperadmin && (
                        <div>
                            <div className="mb-3 flex items-center justify-between">
                                <h2 className="text-lg font-semibold">
                                    Recent Customers
                                </h2>
                                <Button asChild variant="default">
                                    <Link href={customerIndex().url}>
                                        View All
                                    </Link>
                                </Button>
                            </div>
                            <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                                <DataTable
                                    columns={customerColumns}
                                    data={data.customers || []}
                                    actions={actions}
                                    url={customerIndex().url}
                                    onAction={(action, row) => {
                                        if (action === 'add') {
                                            router.get(customerCreate().url);
                                        }

                                        const userId = row?.original.id;

                                        if (userId !== undefined) {
                                            if (action === 'view') {
                                                router.get(
                                                    customerShow(userId).url,
                                                );
                                            } else if (action === 'edit') {
                                                router.get(
                                                    customerEdit(userId).url,
                                                );
                                            } else if (action === 'delete') {
                                                confirm({
                                                    title: 'Delete User',
                                                    message: `Are you sure you want to delete "${row?.original.name}"?`,
                                                    confirmText: 'Delete',
                                                    cancelText: 'Cancel',
                                                    onConfirm: () => {
                                                        router.delete(
                                                            customerDestroy(
                                                                userId,
                                                            ).url,
                                                        );
                                                    },
                                                });
                                            }
                                        }
                                    }}
                                    initialState={{
                                        pagination: {
                                            pageIndex: 0,
                                            pageSize: 10,
                                        },
                                    }}
                                />
                            </div>
                        </div>
                    )}
                </div>
            </AppLayout>
            <ConfirmDialog />
        </>
    );
}
