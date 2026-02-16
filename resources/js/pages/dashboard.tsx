import { ActionType } from '@/components/action-column';
import useConfirmDialog from '@/components/confirm-popup';
import { DataTable } from '@/components/data-table';
import { IncomeByMonthChart } from '@/components/income-by-month-chart';
import { createSelectColumn } from '@/components/select-column';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { formatUserTime } from '@/lib/timezone';
import { formatCurrency } from '@/lib/utils';
import { dashboard } from '@/routes';
import {
    create as customerCreate,
    destroy as customerDestroy,
    edit as customerEdit,
    handle as customerHandle,
    index as customerIndex,
    show as customerShow,
    recommendMaidEdit,
} from '@/routes/customer';
import {
    fiscalYearTotalSales,
    incomeByMonth,
    quotationConvertedBySalesperson,
    revenueByMonth,
    salesPeriodOptions,
} from '@/routes/dashboard';
import { index as enquiriesIndex } from '@/routes/enquiries';
import { show as generalEnquiryShow } from '@/routes/general-enquiries';
import {
    create as maidCreate,
    destroy as maidDestroy,
    edit as maidEdit,
    show as maidShow,
} from '@/routes/maid';
import { show as privateEnquiryShow } from '@/routes/private-enquiries';
import {
    SharedData,
    ValueNumberOptionType,
    type BreadcrumbItem,
} from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { useCallback, useEffect, useState } from 'react';
import { MaidCardList } from './maid/card-list';
import { MaidSchema } from './maid/schema';
import { UserSchema } from './masters/users/schema';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

interface QuotationConvertedBySalespersonType {
    id: number;
    name: string;
    email: string;
    branch_name: string;
    converted_quotation: number;
    amount: number | string;
}

interface SalesPeriodOption {
    value: string;
    label: string;
    start_date: string;
    end_date: string;
}

interface FiscalYearTotalSalesType {
    count: number;
    amount: number | string;
}

interface RevenueByMonthType {
    label: string;
    count: number;
    amount: number | string;
    start_date: string;
    end_date: string;
}

interface IncomeByMonthType {
    label: string;
    date: string;
    amount: number | string;
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
        maids?: MaidSchema[];
        nationality: [];
        religion: [];
        educationLevel: [];
        supplier: [];
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
            maids?: Array<{
                date: string;
                count: number;
                label: string;
            }>;
        };
        misc?: {
            nationalities: ValueNumberOptionType[];
            religions: ValueNumberOptionType[];
            education_levels: ValueNumberOptionType[];
            suppliers: ValueNumberOptionType[];
        };
        enquiries?: EnquiryRowType[];
        enquirySummary?: EnquirySummaryType;
    };
}

export default function Dashboard({ data }: DashboardProps) {
    const { auth } = usePage<SharedData>().props;
    const userPermissions = auth.permissions || [];

    // roles
    const isAdmin = auth.roles.includes('admin');
    const isSales = auth.roles.includes('sales');
    const isCustomer = auth.roles.includes('customer');

    // State for API fetched data
    const [isLoadingData, setIsLoadingData] = useState(false);
    const [salesPeriodOptionsData, setSalesPeriodOptionsData] = useState<
        SalesPeriodOption[]
    >([]);
    const [selectedSalesPeriod, setSelectedSalesPeriod] =
        useState<string>('full-year');
    const [
        quotationConvertedBySalespersonData,
        setQuotationConvertedBySalespersonData,
    ] = useState<QuotationConvertedBySalespersonType[]>([]);
    const [fiscalYearTotalSalesData, setFiscalYearTotalSalesData] =
        useState<FiscalYearTotalSalesType | null>(null);
    const [revenueByMonthData, setRevenueByMonthData] = useState<
        RevenueByMonthType[]
    >([]);
    const [incomeByMonthData, setIncomeByMonthData] = useState<
        IncomeByMonthType[]
    >([]);

    // Fetch dashboard data for admin
    const fetchAdminDashboardData = useCallback(
        async (yearId?: number, period?: string) => {
            if (!isAdmin) return;

            setIsLoadingData(true);
            try {
                const queryOptions = yearId
                    ? { query: { financial_year_id: yearId.toString() } }
                    : undefined;
                const periodQueryOptions = yearId
                    ? {
                          query: {
                              financial_year_id: yearId.toString(),
                              period: period || 'full-year',
                          },
                      }
                    : { query: { period: period || 'full-year' } };

                // Fetch all data in parallel
                const [
                    periodOptionsRes,
                    fytdRes,
                    revenueRes,
                    incomeRes,
                    salespersonRes,
                ] = await Promise.all([
                    fetch(salesPeriodOptions(queryOptions).url),
                    fetch(fiscalYearTotalSales(queryOptions).url),
                    fetch(revenueByMonth(queryOptions).url),
                    fetch(incomeByMonth(queryOptions).url),
                    fetch(
                        quotationConvertedBySalesperson(periodQueryOptions).url,
                    ),
                ]);

                if (periodOptionsRes.ok) {
                    const periodData = await periodOptionsRes.json();
                    setSalesPeriodOptionsData(periodData.options || []);
                    if (!period) {
                        setSelectedSalesPeriod(
                            periodData.default || 'full-year',
                        );
                    }
                }

                if (fytdRes.ok) {
                    const fytdData = await fytdRes.json();
                    setFiscalYearTotalSalesData(fytdData);
                }

                if (revenueRes.ok) {
                    const revenueData = await revenueRes.json();
                    setRevenueByMonthData(revenueData);
                }

                if (incomeRes.ok) {
                    const incomeData = await incomeRes.json();
                    setIncomeByMonthData(incomeData);
                }

                if (salespersonRes.ok) {
                    const salespersonData = await salespersonRes.json();
                    setQuotationConvertedBySalespersonData(salespersonData);
                }
            } catch (error) {
                console.error('Error fetching dashboard data:', error);
            } finally {
                setIsLoadingData(false);
            }
        },
        [isAdmin],
    );

    // Fetch salesperson data when period changes
    const fetchSalespersonData = useCallback(
        async (yearId?: number, period?: string) => {
            if (!isAdmin) return;

            try {
                const queryOptions = {
                    query: {
                        financial_year_id: yearId?.toString() || '',
                        period: period || 'full-year',
                    },
                };

                const res = await fetch(
                    quotationConvertedBySalesperson(queryOptions).url,
                );
                if (res.ok) {
                    const data = await res.json();
                    setQuotationConvertedBySalespersonData(data);
                }
            } catch (error) {
                console.error('Error fetching salesperson data:', error);
            }
        },
        [isAdmin],
    );

    // Initial data fetch for admin
    useEffect(() => {
        if (isAdmin && data.selectedYearId) {
            fetchAdminDashboardData(data.selectedYearId);
        }
    }, [isAdmin, data.selectedYearId, fetchAdminDashboardData]);

    // Handle period change
    const handlePeriodChange = (value: string) => {
        setSelectedSalesPeriod(value);
        fetchSalespersonData(data.selectedYearId, value);
    };

    // actions
    const actions: ActionType[] = ['handle-customer', 'recommend-maid'];
    if (userPermissions.includes('customer create')) actions.push('add');
    if (userPermissions.includes('customer view')) actions.push('view');
    if (userPermissions.includes('customer edit')) actions.push('edit');
    if (userPermissions.includes('customer delete')) actions.push('delete');

    const actionsForCustomer: ActionType[] = [];
    if (userPermissions.includes('maid create')) actionsForCustomer.push('add');
    if (userPermissions.includes('maid view'))
        actionsForCustomer.push('preview');
    if (userPermissions.includes('maid edit')) actionsForCustomer.push('edit');
    if (userPermissions.includes('maid delete'))
        actionsForCustomer.push('delete');

    // columns
    const customerColumns: ColumnDef<UserSchema>[] = [
        createSelectColumn<UserSchema>(),
        { accessorKey: 'name', header: 'Name' },
        { accessorKey: 'email', header: 'Email' },
        { accessorKey: 'contact', header: 'Contact' },
        {
            accessorKey: 'handler_name',
            header: 'Sales',
            meta: { exportable: true },
        },
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

    const quotationConvertedBySalespersonColumns: ColumnDef<QuotationConvertedBySalespersonType>[] =
        [
            createSelectColumn<QuotationConvertedBySalespersonType>(),
            {
                accessorKey: 'name',
                header: 'Sales Name',
                meta: { exportable: true },
            },
            {
                accessorKey: 'email',
                header: 'Email',
                meta: { exportable: true },
            },
            {
                accessorKey: 'branch_name',
                header: 'Branch',
                meta: { exportable: true },
            },
            {
                accessorKey: 'converted_quotation',
                header: 'Total of Sales',
                meta: { exportable: true },
                cell: ({ row }) => (
                    <span className="font-semibold">
                        {row.getValue('converted_quotation')}
                    </span>
                ),
            },
            {
                accessorKey: 'amount',
                header: 'Amount',
                meta: { exportable: true },
                cell: ({ row }) => formatCurrency(row.original.amount),
            },
        ];

    // const salesCustomerColumns: ColumnDef<UserSchema>[] = [
    //     createSelectColumn<UserSchema>(),
    //     { accessorKey: 'name', header: 'Name', meta: { exportable: true } },
    //     { accessorKey: 'email', header: 'Email', meta: { exportable: true } },
    //     {
    //         accessorKey: 'contact',
    //         header: 'Contact',
    //         meta: { exportable: true },
    //     },
    //     {
    //         accessorKey: 'status',
    //         header: 'Status',
    //         meta: { exportable: true },
    //         cell: ({ row }) => {
    //             const status = row.getValue('status') as string;
    //             return (
    //                 <span
    //                     className={`rounded-full px-2 py-1 text-sm ${
    //                         status === 'Unassigned'
    //                             ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
    //                             : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
    //                     }`}
    //                 >
    //                     {status}
    //                 </span>
    //             );
    //         },
    //     },
    //     {
    //         accessorKey: 'assigned_sales',
    //         header: 'Assigned To',
    //         meta: { exportable: true },
    //     },
    // ];

    const { confirm, ConfirmDialog } = useConfirmDialog();

    return (
        <>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Dashboard" />
                <div className="@container/main flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    {/* Admin: Fiscal Year Selector */}
                    {isAdmin &&
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
                    {isAdmin && fiscalYearTotalSalesData && (
                        <div>
                            <h2 className="mb-3 text-lg font-semibold">
                                Fiscal Year - Total Sales
                            </h2>
                            <div className="mx-auto grid grid-cols-1 gap-4 md:w-[80%] md:grid-cols-2 lg:w-[50%]">
                                <Card className="bg-gradient-to-t from-primary/5 to-card">
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
                                            Total converted quotations
                                        </p>
                                    </CardContent>
                                </Card>
                                <Card className="bg-gradient-to-t from-primary/5 to-card">
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
                                            Total quotation amount
                                        </p>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                    )}

                    {/* Admin: Revenue by Month */}
                    {isAdmin && revenueByMonthData.length > 0 && (
                        <div>
                            <h2 className="mb-3 text-lg font-semibold">
                                Revenue by Month
                            </h2>
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-4 2xl:grid-cols-6">
                                {revenueByMonthData.map((month, index) => (
                                    <Card
                                        key={index}
                                        className="gap-0 overflow-hidden bg-gradient-to-t from-primary/5 to-card py-0"
                                    >
                                        <div className="grid grid-cols-2 border-b">
                                            <div className="border-r p-3 text-center text-base font-medium text-muted-foreground">
                                                Monthly (#)
                                            </div>
                                            <div className="p-3 text-center text-base font-medium text-muted-foreground">
                                                Monthly ($)
                                            </div>
                                        </div>
                                        <div className="grid grid-cols-2 border-b">
                                            <div className="text-md border-r p-3 text-center font-bold">
                                                {month.count}
                                            </div>
                                            <div className="text-md p-3 text-center font-bold">
                                                {formatCurrency(month.amount)}
                                            </div>
                                        </div>
                                        <div className="p-3 text-center text-base text-muted-foreground">
                                            {month.label}
                                        </div>
                                    </Card>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Admin: Income by Month Chart */}
                    {isAdmin && (
                        <IncomeByMonthChart
                            data={incomeByMonthData}
                            fiscalYear={data.fiscalYear}
                            isLoading={isLoadingData}
                        />
                    )}

                    {/* Admin: Sales FYTD Dashboard */}
                    {isAdmin && (
                        <div>
                            <div className="mb-3 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <h2 className="text-lg font-semibold">
                                        Monthly Sales Closed By Salesperson
                                    </h2>
                                    {data.fiscalYear && (
                                        <p className="text-base text-muted-foreground">
                                            Fiscal Year: {data.fiscalYear}
                                        </p>
                                    )}
                                </div>
                                {salesPeriodOptionsData.length > 0 && (
                                    <div className="flex items-center gap-2">
                                        <Label
                                            htmlFor="sales-period-select"
                                            className="text-base font-medium"
                                        >
                                            Month:
                                        </Label>
                                        <Select
                                            value={selectedSalesPeriod}
                                            onValueChange={handlePeriodChange}
                                        >
                                            <SelectTrigger
                                                id="sales-period-select"
                                                className="w-[200px]"
                                            >
                                                <SelectValue placeholder="Select period" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {salesPeriodOptionsData.map(
                                                    (option) => (
                                                        <SelectItem
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                )}
                            </div>
                            <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                                <DataTable
                                    columns={
                                        quotationConvertedBySalespersonColumns
                                    }
                                    data={quotationConvertedBySalespersonData}
                                    actions={[]}
                                    url={dashboard().url}
                                    onAction={() => {}}
                                />
                            </div>
                        </div>
                    )}

                    {/* Sales: Customer List (Unassigned + Assigned to me) */}
                    {/* {isSales && (
                        <div>
                            <div className="mb-3 flex items-center justify-between">
                                <div>
                                    <h2 className="text-lg font-semibold">
                                        Customer List
                                    </h2>
                                    <p className="text-base text-muted-foreground">
                                        Unassigned customers and customers
                                        assigned to you
                                    </p>
                                </div>
                                <Button asChild variant="outline">
                                    <Link href={customerIndex().url}>
                                        View All
                                    </Link>
                                </Button>
                            </div>
                            <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 md:min-h-min dark:border-sidebar-border not-dark:bg-white">
                                <DataTable
                                    columns={salesCustomerColumns}
                                    data={data.customers || []}
                                    actions={['view']}
                                    url={customerIndex().url}
                                    onAction={(action, row) => {
                                        const userId = row?.id;
                                        if (
                                            userId !== undefined &&
                                            action === 'view'
                                        ) {
                                            router.get(
                                                customerShow(userId).url,
                                            );
                                        }
                                    }}
                                />
                            </div>
                        </div>
                    )} */}

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
                                <Button asChild variant="outline">
                                    <Link href={enquiriesIndex().url}>
                                        View All
                                    </Link>
                                </Button>
                            </div>
                            <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                                <DataTable
                                    columns={[
                                        {
                                            accessorKey: 'type',
                                            header: 'Type',
                                            cell: ({ row }) => (
                                                <span
                                                    className={`rounded-full px-2 py-1 text-sm ${
                                                        row.original.type ===
                                                        'General'
                                                            ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
                                                            : 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200'
                                                    }`}
                                                >
                                                    {row.original.type}
                                                </span>
                                            ),
                                        },
                                        {
                                            accessorKey: 'status',
                                            header: 'Status',
                                            cell: ({ row }) => {
                                                const statusColors: Record<
                                                    string,
                                                    string
                                                > = {
                                                    new_lead:
                                                        'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
                                                    contacted:
                                                        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                                    negotiating:
                                                        'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                                    confirmed:
                                                        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                                };
                                                return (
                                                    <span
                                                        className={`rounded-full px-2 py-1 text-sm ${statusColors[row.original.status] ?? ''}`}
                                                    >
                                                        {
                                                            row.original
                                                                .status_label
                                                        }
                                                    </span>
                                                );
                                            },
                                        },
                                        {
                                            accessorKey: 'name',
                                            header: 'Full Name',
                                        },
                                        {
                                            accessorKey: 'contact',
                                            header: 'Contact',
                                        },
                                        {
                                            accessorKey: 'email',
                                            header: 'Email',
                                        },
                                        {
                                            accessorKey: 'created_at',
                                            header: 'Created At',
                                        },
                                    ]}
                                    data={data.enquiries}
                                    actions={['view']}
                                    url={enquiriesIndex().url}
                                    onAction={(action, row) => {
                                        const enquiry = row?.original;
                                        if (!enquiry) return;
                                        if (action === 'view') {
                                            if (
                                                enquiry.type === 'General' &&
                                                enquiry.child_id
                                            ) {
                                                router.get(
                                                    generalEnquiryShow(
                                                        enquiry.child_id,
                                                    ).url,
                                                );
                                            } else if (enquiry.child_id) {
                                                router.get(
                                                    privateEnquiryShow(
                                                        enquiry.child_id,
                                                    ).url,
                                                );
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

                    {/* Customer DataTable - Recent Customers for Admin */}
                    {isAdmin && (
                        <div>
                            <div className="mb-3 flex items-center justify-between">
                                <h2 className="text-lg font-semibold">
                                    Recent Customers
                                </h2>
                                <Button asChild variant="outline">
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
                                            } else if (
                                                action == 'handle-customer'
                                            ) {
                                                router.put(
                                                    customerHandle(userId).url,
                                                );
                                            } else if (
                                                action == 'recommend-maid'
                                            ) {
                                                router.get(
                                                    recommendMaidEdit(userId)
                                                        .url,
                                                );
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

                    {/* Maid Card List */}
                    {isCustomer && (
                        <div
                            className={`relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 px-1 py-3 md:min-h-min dark:border-sidebar-border`}
                        >
                            <div className="flex items-center justify-between px-2 pb-2">
                                <h2 className="text-lg font-semibold">
                                    Maid Profile
                                </h2>
                            </div>
                            <MaidCardList
                                data={data.maids || []}
                                dataNationality={data.nationality || []}
                                dataReligion={data.religion || []}
                                dataEducationLevel={data.educationLevel || []}
                                misc={data.misc}
                                actions={actionsForCustomer}
                                onAction={(action, row) => {
                                    if (action === 'add') {
                                        router.get(maidCreate().url);
                                    }

                                    const maidId = row?.id;

                                    if (maidId !== undefined) {
                                        if (action === 'view') {
                                            router.get(maidShow(maidId).url);
                                        } else if (action === 'edit') {
                                            router.get(maidEdit(maidId).url);
                                        } else if (action === 'delete') {
                                            confirm({
                                                title: 'Delete User',
                                                message: `Are you sure you want to delete maid "${row?.name}"?`,
                                                confirmText: 'Delete',
                                                cancelText: 'Cancel',
                                                onConfirm: () => {
                                                    router.delete(
                                                        maidDestroy(maidId).url,
                                                    );
                                                },
                                            });
                                        }
                                    }
                                }}
                            />
                        </div>
                    )}
                </div>
            </AppLayout>
            <ConfirmDialog />
        </>
    );
}
