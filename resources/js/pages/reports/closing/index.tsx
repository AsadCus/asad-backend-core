import { DataTable } from '@/components/data-table';
import { CustomExport } from '@/components/data-table-export';
import { DateRangeFilter } from '@/components/date-range-filter';
import {
    MultiSelect,
    type MultiSelectGroup,
    type MultiSelectOption,
} from '@/components/multi-select';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type QuickDateOption } from '@/lib/quick-date';
import {
    formatCurrency,
    formatDateForDisplay,
    parseDisplayDate,
} from '@/lib/utils';
import { closingReportExport } from '@/routes/dashboard';
import closing from '@/routes/reports/closing';
import sales from '@/routes/sales';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { FileText } from 'lucide-react';
import { DateTime } from 'luxon';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Sales', href: sales.index.url() },
    { title: 'Closing Report', href: closing.index.url() },
];

interface GeneralEnquiryPackageOption {
    value: string | number;
    label: React.ReactNode | string;
    departure_date?: string;
}

interface ClosingReportProps {
    packageOptions?: GeneralEnquiryPackageOption[];
    categoryOptions?: { value: string; label: string }[];
}

interface ClosingRow {
    date_sort: string;
    date: string;
    day_name: string;
    total_sales: number | string;
    [key: string]: unknown;
}

interface ClosingReportData {
    payment_methods: string[];
    categories: Record<string, string>;
    rows: ClosingRow[];
}

export default function ClosingReportIndex({
    packageOptions = [],
    categoryOptions = [],
}: ClosingReportProps) {
    const todayDisplayDate = formatDateForDisplay(new Date());

    const quickOptions: QuickDateOption[] = [
        { label: 'This Week', value: 'thisweek' },
        { label: 'This Month', value: 'thismonth' },
        { label: 'This Year', value: 'thisyear' },
    ];

    const [dateRange, setDateRange] = useState<{ from?: string; to?: string }>({
        from: todayDisplayDate,
    });
    const [packageIds, setPackageIds] = useState<string[]>([]);
    const [categoryIds, setCategoryIds] = useState<string[]>([]);
    const [reportData, setReportData] = useState<ClosingReportData | null>(
        null,
    );
    const [isLoading, setIsLoading] = useState(false);

    const normalizedPackageOptions = useMemo(
        () => packageOptions as GeneralEnquiryPackageOption[],
        [packageOptions],
    );

    const groupedPackageOptions = useMemo((): MultiSelectGroup[] => {
        const options = [...normalizedPackageOptions].sort((a, b) => {
            const aDate = parseDisplayDate(a.departure_date);
            const bDate = parseDisplayDate(b.departure_date);
            if (aDate && bDate) return aDate.getTime() - bDate.getTime();
            if (aDate) return -1;
            if (bDate) return 1;
            return String(a.label).localeCompare(String(b.label));
        });

        const groups: MultiSelectGroup[] = [];
        let prevKey = '';
        let currentOpts: MultiSelectOption[] = [];

        options.forEach((opt) => {
            const d = parseDisplayDate(opt.departure_date);
            const key = d
                ? d.toLocaleDateString('en-US', {
                      month: 'long',
                      year: 'numeric',
                  })
                : 'No Departure Date';
            if (key !== prevKey) {
                currentOpts = [];
                groups.push({ heading: key, options: currentOpts });
                prevKey = key;
            }
            currentOpts.push({
                value: String(opt.value),
                label: `${opt.label}`.trim(),
            });
        });

        return groups;
    }, [normalizedPackageOptions]);

    const formattedCategoryOptions: MultiSelectGroup[] = useMemo(
        () => [
            {
                heading: 'Categories',
                options: categoryOptions.map((o) => ({
                    value: String(o.value),
                    label: o.label,
                })),
            },
        ],
        [categoryOptions],
    );

    const buildParams = useCallback(() => {
        const params = new URLSearchParams({ period: 'daily' });
        const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
        if (tz) params.set('timezone', tz);

        if (packageIds.length > 0)
            params.set('package_id', packageIds.join(','));
        if (categoryIds.length > 0)
            params.set('categories', categoryIds.join(','));

        const fromDt = dateRange.from
            ? DateTime.fromFormat(dateRange.from, 'dd MMMM yyyy', {
                  zone: tz || 'UTC',
                  locale: 'en-GB',
              })
            : null;
        const toDt = dateRange.to
            ? DateTime.fromFormat(dateRange.to, 'dd MMMM yyyy', {
                  zone: tz || 'UTC',
                  locale: 'en-GB',
              })
            : null;

        const rangeStart = fromDt?.isValid
            ? fromDt.startOf('day')
            : DateTime.now()
                  .setZone(tz || 'UTC')
                  .startOf('day');
        const rangeEnd =
            (toDt?.isValid ? toDt : fromDt)?.endOf('day') ??
            rangeStart.endOf('day');

        params.set('range_start_utc', rangeStart.toUTC().toISO() ?? '');
        params.set('range_end_utc', rangeEnd.toUTC().toISO() ?? '');

        return params;
    }, [dateRange, packageIds, categoryIds]);

    const buildParamsRef = useRef(buildParams);
    buildParamsRef.current = buildParams;

    const fetchData = useCallback(async () => {
        setIsLoading(true);
        try {
            const params = buildParamsRef.current();
            const res = await fetch(
                `${closing.data.definition.url}?${params.toString()}`,
            );
            if (res.ok) {
                const json = (await res.json()) as ClosingReportData;
                setReportData(json);
            }
        } catch (err) {
            console.error('Error fetching closing report:', err);
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        void fetchData();
    }, [fetchData]);

    const handleExportPdf = useCallback(() => {
        const params = buildParams();
        window.open(
            `${closingReportExport.definition.url}?${params.toString()}`,
            '_blank',
        );
    }, [buildParams]);

    const rows: ClosingRow[] = reportData?.rows ?? [];

    const columns: ColumnDef<ClosingRow>[] = useMemo(() => {
        const paymentMethods: string[] = reportData?.payment_methods ?? [];
        const categories = reportData?.categories ?? {};

        return [
            { accessorKey: 'date', header: 'Date', meta: { exportable: true } },
            {
                accessorKey: 'day_name',
                header: 'Day',
                meta: { exportable: true },
            },
            {
                accessorKey: 'total_sales',
                header: 'Total Sales',
                meta: { exportable: true },
                cell: ({ row }) => formatCurrency(row.original.total_sales),
            },
            ...Object.entries(categories).map(([key, label]) => ({
                accessorKey: key,
                header: label,
                meta: { exportable: true },
                cell: ({ row }: { row: { original: ClosingRow } }) =>
                    formatCurrency(
                        (row.original[key] as number | string | undefined) ?? 0,
                    ),
            })),
            ...paymentMethods.map((method) => ({
                accessorKey: method,
                header: method.charAt(0).toUpperCase() + method.slice(1),
                meta: { exportable: true },
                cell: ({ row }: { row: { original: ClosingRow } }) =>
                    formatCurrency(
                        (row.original[method] as number | string | undefined) ??
                            0,
                    ),
            })),
        ];
    }, [reportData]);

    const customExports: CustomExport[] = useMemo(
        () => [
            {
                label: 'Export PDF (Report)',
                icon: FileText,
                onClick: handleExportPdf,
            },
        ],
        [handleExportPdf],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Closing Report" />
            <div className="@container/main flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div>
                    <h2 className="text-lg font-semibold">Closing Report</h2>
                    <p className="text-base text-muted-foreground">
                        Per-date aggregated payment breakdown
                    </p>
                </div>

                <div className="flex flex-wrap items-end gap-3">
                    <div className="space-y-1">
                        <p className="font-medium">Package</p>
                        <MultiSelect
                            options={groupedPackageOptions}
                            onValueChange={setPackageIds}
                            defaultValue={packageIds}
                            placeholder="Select package..."
                            maxCount={0}
                            className="bg-background hover:bg-accent"
                        />
                    </div>
                    <div className="space-y-1">
                        <p className="font-medium">Category</p>
                        <MultiSelect
                            options={formattedCategoryOptions}
                            onValueChange={setCategoryIds}
                            defaultValue={categoryIds}
                            placeholder="Select category..."
                            maxCount={0}
                            className="bg-background hover:bg-accent"
                        />
                    </div>
                    <div className="space-y-1">
                        <p className="font-medium">Date Range</p>
                        <DateRangeFilter
                            title="Date Range"
                            value={dateRange}
                            onChange={setDateRange}
                            quickDate
                            quickOptions={quickOptions}
                            compact
                            dash={false}
                            align="start"
                        />
                    </div>
                    <Button
                        type="button"
                        disabled={isLoading}
                        onClick={() => void fetchData()}
                    >
                        Apply
                    </Button>
                </div>

                <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                    {isLoading ? (
                        <p className="py-6 text-center text-base text-muted-foreground">
                            Loading closing report...
                        </p>
                    ) : (
                        <DataTable
                            columns={columns}
                            data={rows}
                            actions={[]}
                            exportFilename="closing-report"
                            customExports={customExports}
                            initialState={{
                                pagination: { pageIndex: 0, pageSize: 25 },
                            }}
                        />
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
