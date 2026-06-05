import { ActionType } from '@/components/action-column';
import { ColumnFilter } from '@/components/column-filter';
import useConfirmDialog from '@/components/confirm-popup';
import { DataTable } from '@/components/data-table';
import { FormField } from '@/components/form-field';
import { ProperInput } from '@/components/proper-input';
import { ProperInputSelect } from '@/components/proper-input-select';
import { createSelectColumn } from '@/components/select-column';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import {
    focusFirstDialogFormField,
    handleDialogTabKey,
} from '@/lib/dialog-focus';
import {
    index as confirmedCustomerIndex,
    destroy as destroyConfirmedCustomer,
} from '@/routes/confirmed-customer';
import {
    generateEditLink,
    generateQuotations,
    show as showGroup,
} from '@/routes/customer-confirmations';
import { receiptsPdf as memberReceiptsPdf } from '@/routes/customer-confirmations/members';
import { edit as editOrder } from '@/routes/order';
import { OptionType, SharedData, type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef, Row } from '@tanstack/react-table';
import { useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import ConfirmedCustomerFormFields from '../customer/confirmed-customer-form-fields';
import CustomerFormFields from '../customer/form-fields';
import {
    confirmationMemberStatusColors,
    confirmationMemberStatusLabels,
    emptyMember,
    type CustomerConfirmationDatatableSchema,
    type CustomerConfirmationFormSchema,
    type CustomerConfirmationMemberDatatableSchema,
    type CustomerDocumentItemSchema,
    type CustomerMemberFormData,
} from '../customer/schema';
import { customerValidationSchema } from '../customer/validation';
import { statusColors, typeColors } from '../enquiries/schema';
import {
    sharingPlanBadgeColors,
    sharingPlanLabels,
    sharingPlanOptions,
} from '../packages/schema';
import CustomerConfirmationForm from './form';
import {
    confirmedCustomerPublicEditLinkConfig,
    customerConfirmationPublicEditLinkLabels,
    type CustomerConfirmationPublicEditLinkType,
} from './schema';
import { validateQuotationGenerationPayload } from './validation';

const formatCurrency = (value: number): string => {
    return new Intl.NumberFormat('en-SG', {
        style: 'currency',
        currency: 'SGD',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value || 0);
};

// Resolve a business/validation error returned on an Inertia "success" redirect
// (controllers convert aborts to back()->with('error', ...)).
const resolveCombineResponseError = (page: {
    props: Record<string, unknown>;
}): string => {
    const flash = (page.props as unknown as SharedData).flash;
    const flashError =
        typeof flash?.error === 'string' ? flash.error.trim() : '';

    const pageErrors = (page.props.errors as Record<string, unknown>) ?? {};
    const firstValidationError = Object.values(pageErrors)
        .map((value) =>
            Array.isArray(value)
                ? String(value[0] ?? '').trim()
                : String(value ?? '').trim(),
        )
        .find((message) => message.length > 0);

    return flashError.length > 0 ? flashError : (firstValidationError ?? '');
};

const resolveCombineValidationError = (
    errors: Record<string, string | string[]>,
    fallback: string,
): string => {
    const resolvedError = Object.values(errors)[0] ?? fallback;

    return Array.isArray(resolvedError)
        ? String(resolvedError[0] ?? fallback)
        : String(resolvedError);
};

const PAYMENT_ISSUE_STATUS_PRIORITY = [
    'pending_payment',
    'partially_paid',
    'overpaid',
];

const formatPaymentStatusLabel = (status: string): string => {
    return (
        confirmationMemberStatusLabels[status] ??
        status.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase())
    );
};

const resolvePaymentIssueBreakdown = (
    members: CustomerConfirmationMemberDatatableSchema[],
): {
    total: number;
    entries: Array<{ status: string; label: string; count: number }>;
} => {
    const statusCounts = new Map<string, number>();

    members.forEach((member) => {
        const normalizedStatus = String(
            member.status ?? 'pending_payment',
        ).toLowerCase();

        if (
            normalizedStatus === 'cancelled' ||
            normalizedStatus === 'fully_paid'
        ) {
            return;
        }

        statusCounts.set(
            normalizedStatus,
            (statusCounts.get(normalizedStatus) ?? 0) + 1,
        );
    });

    const entries = Array.from(statusCounts.entries())
        .sort(([leftStatus], [rightStatus]) => {
            const leftIndex = PAYMENT_ISSUE_STATUS_PRIORITY.indexOf(leftStatus);
            const rightIndex =
                PAYMENT_ISSUE_STATUS_PRIORITY.indexOf(rightStatus);

            if (leftIndex === -1 && rightIndex === -1) {
                return leftStatus.localeCompare(rightStatus);
            }

            if (leftIndex === -1) {
                return 1;
            }

            if (rightIndex === -1) {
                return -1;
            }

            return leftIndex - rightIndex;
        })
        .map(([status, count]) => ({
            status,
            label: formatPaymentStatusLabel(status),
            count,
        }));

    const total = entries.reduce((sum, entry) => sum + entry.count, 0);

    return { total, entries };
};

const groupColumns: ColumnDef<CustomerConfirmationDatatableSchema>[] = [
    createSelectColumn<CustomerConfirmationDatatableSchema>(),
    {
        accessorKey: 'id',
        header: 'Id',
        meta: { exportable: true },
    },
    {
        accessorKey: 'customer_name',
        header: 'Customer Name',
        meta: { exportable: true },
    },
    {
        accessorKey: 'customer_number',
        header: 'Customer No',
        meta: { exportable: true },
    },
    {
        accessorKey: 'enquiry_email',
        header: 'Email',
        meta: { exportable: true },
    },
    {
        accessorKey: 'enquiry_contact',
        header: 'Contact',
        meta: { exportable: true },
    },
    {
        accessorKey: 'member_count',
        header: 'Member(s)',
        meta: { exportable: true },
        cell: ({ row }) => (
            <div className="flex items-center gap-2">
                <Badge
                    variant="secondary"
                    className="rounded-full px-3 py-1 text-base"
                >
                    Total: {row.original.member_count}
                </Badge>
                {/* <Badge
                    variant="outline"
                    className="rounded-full px-3 py-1 text-base"
                >
                    Active: {row.original.active_member_count}
                </Badge> */}
            </div>
        ),
    },
    {
        id: 'payment_issues',
        header: 'Payment Updates',
        meta: { exportable: false },
        cell: ({ row }) => {
            const members = row.original.members ?? [];
            const isHoldingPage =
                typeof window !== 'undefined' &&
                window.location.pathname.includes('/customer-holding');
            const isHoldingGroup =
                isHoldingPage ||
                Boolean((row.original as { is_holding?: boolean }).is_holding);
            const breakdown = resolvePaymentIssueBreakdown(members);
            const hasOnlyCancelledMembers =
                members.length > 0 &&
                members.every(
                    (member) =>
                        String(member.status ?? '')
                            .trim()
                            .toLowerCase() === 'cancelled',
                );

            if (isHoldingGroup) {
                return (
                    <Badge
                        variant="secondary"
                        className="rounded-full px-3 py-1 text-base"
                    >
                        Holding
                    </Badge>
                );
            }

            if (breakdown.total === 0) {
                return (
                    <Badge
                        variant="secondary"
                        className={`rounded-full px-3 py-1 text-base ${hasOnlyCancelledMembers ? 'bg-red-100 text-red-800 dark:border dark:border-red-400/25 dark:bg-red-500/16 dark:text-red-200' : ''}`}
                    >
                        {hasOnlyCancelledMembers
                            ? 'Trip Cancelled'
                            : 'Fully Paid'}
                    </Badge>
                );
            }

            return (
                <Tooltip>
                    <TooltipTrigger asChild>
                        <Badge className="rounded-full bg-amber-100 px-3 py-1 text-base text-amber-800 dark:border dark:border-amber-400/25 dark:bg-amber-500/16 dark:text-amber-200">
                            {breakdown.total} Outstanding
                            {breakdown.total > 1 ? 's' : ''}
                        </Badge>
                    </TooltipTrigger>
                    <TooltipContent side="top">
                        <div className="space-y-1">
                            {breakdown.entries.map((entry) => (
                                <p key={entry.status}>
                                    {entry.label}: {entry.count}
                                </p>
                            ))}
                        </div>
                    </TooltipContent>
                </Tooltip>
            );
        },
    },
    {
        accessorKey: 'package_name',
        header: 'Package',
        meta: { exportable: true },
    },
    {
        accessorKey: 'package_country',
        header: 'Country',
        meta: { exportable: true },
        cell: ({ row }) => {
            const country = row.original.package_country;

            if (!country || String(country).trim().length === 0) {
                return <span className="text-muted-foreground">-</span>;
            }

            return <span>{country}</span>;
        },
        filterFn: 'includesValue',
    },
    {
        accessorKey: 'refund_cancel_date',
        header: 'Refund Cancel Date',
        meta: { exportable: true },
        cell: ({ row }) => {
            const value = row.original.refund_cancel_date;

            if (!value || String(value).trim().length === 0) {
                return <span className="text-muted-foreground">-</span>;
            }

            return <span>{value}</span>;
        },
    },
    {
        accessorKey: 'paid_amount',
        header: 'Payment',
        meta: { exportable: true },
        cell: ({ row }) => (
            <Badge variant="outline" className="text-sm">
                {formatCurrency(row.original.paid_amount ?? 0)} /{' '}
                {formatCurrency(row.original.total_amount ?? 0)}
            </Badge>
        ),
    },
    {
        id: 'refunded_paid_summary',
        header: 'Refunded/Paid',
        meta: { exportable: true },
        cell: ({ row }) => {
            const members = row.original.members ?? [];
            const totalRefundedAmount = members.reduce(
                (sum, member) => sum + (member.refunded_amount ?? 0),
                0,
            );
            const totalPaidAmount = members.reduce(
                (sum, member) => sum + (member.invoice_paid_amount ?? 0),
                0,
            );
            const holdAmount = totalPaidAmount - totalRefundedAmount;

            return (
                <div className="flex flex-col gap-1 text-sm">
                    <div className="font-medium text-green-600">
                        Paid: {formatCurrency(totalPaidAmount)}
                    </div>
                    <div className="font-medium text-red-600">
                        Refunded: {formatCurrency(totalRefundedAmount)}
                    </div>
                    <div className="font-medium text-muted-foreground">
                        Hold Amount: {formatCurrency(holdAmount)}
                    </div>
                </div>
            );
        },
    },
    {
        accessorKey: 'enquiry_type',
        header: 'Enquiry Type',
        meta: { exportable: true },
        cell: ({ row }) => {
            const type = row.original.enquiry_type;

            if (!type) {
                return <span className="text-muted-foreground">-</span>;
            }

            return (
                <Badge
                    className={`${typeColors[type] ?? ''} rounded-full px-3 py-1 text-base`}
                >
                    {type}
                </Badge>
            );
        },
        filterFn: 'includesValue',
    },
    {
        accessorKey: 'enquiry_status',
        header: 'Enquiry Status',
        meta: { exportable: true },
        cell: ({ row }) => {
            const status = row.original.enquiry_status;
            if (!status) {
                return <span className="text-muted-foreground">-</span>;
            }

            const color = statusColors[status] ?? '';

            return (
                <Badge className={`${color} rounded-full px-3 py-1 text-base`}>
                    {status}
                </Badge>
            );
        },
        filterFn: 'includesValue',
    },
    {
        accessorKey: 'quoted_member_count',
        header: 'Quoted',
        meta: { exportable: true },
        cell: ({ row }) => (
            <Badge variant="outline" className="text-sm">
                {row.original.quoted_member_count} /{' '}
                {row.original.active_member_count}
            </Badge>
        ),
    },
    {
        accessorKey: 'can_create_quotation',
        header: 'Quotation',
        meta: { exportable: true },
        cell: ({ row }) => (
            <Badge
                variant={
                    row.original.can_create_quotation ? 'secondary' : 'default'
                }
                className="text-sm"
            >
                {row.original.can_create_quotation ? 'Pending' : 'Completed'}
            </Badge>
        ),
        filterFn: 'includesValue',
    },
    {
        accessorKey: 'date_of_application',
        header: 'Applied Date',
        meta: { exportable: true },
        filterFn: 'dateRangeFilter',
    },
    {
        accessorKey: 'created_at',
        header: 'Created At',
        meta: { exportable: true },
        filterFn: 'dateRangeFilter',
    },
    {
        id: 'members_search_blob',
        accessorFn: (row) => {
            const groupContent = [
                row.main_customer_name,
                row.main_customer_number,
                row.enquiry_email,
                row.enquiry_contact,
                row.package_name,
                row.enquiry_type,
                row.enquiry_status,
            ]
                .filter(Boolean)
                .join(' ');

            const memberContent = (row.members ?? [])
                .flatMap((member) => [
                    member.name,
                    member.email,
                    member.contact,
                    member.customer_number,
                    member.nric_number,
                    member.nationality,
                    member.passport_number,
                    member.status,
                    member.sharing_plan,
                    member.relationship,
                ])
                .filter(Boolean)
                .join(' ');

            return `${groupContent} ${memberContent}`.trim();
        },
        header: 'Member Search',
        meta: { exportable: false },
        enableSorting: false,
    },
];

const memberColumns: ColumnDef<CustomerConfirmationMemberDatatableSchema>[] = [
    {
        accessorKey: 'is_leader',
        header: 'Role',
        cell: ({ row }) => (
            <Badge variant={row.original.is_leader ? 'default' : 'secondary'}>
                {row.original.is_leader ? 'Main' : 'Participant'}
            </Badge>
        ),
    },
    {
        accessorKey: 'name',
        header: 'Name',
        meta: { exportable: true },
    },
    {
        accessorKey: 'email',
        header: 'Email',
        meta: { exportable: true },
    },
    {
        accessorKey: 'contact',
        header: 'Contact',
        meta: { exportable: true },
    },
    {
        accessorKey: 'customer_number',
        header: 'Customer Number',
        meta: { exportable: true },
    },
    {
        accessorKey: 'nric_number',
        header: 'NRIC',
        meta: { exportable: true },
    },
    {
        accessorKey: 'nationality',
        header: 'Nationality',
        meta: { exportable: true },
    },
    {
        accessorKey: 'sharing_plan',
        header: 'Pricing Plan',
        meta: { exportable: true },
        cell: ({ row }) => {
            const sharingPlan = row.original.sharing_plan;

            if (!sharingPlan) {
                return <span className="text-muted-foreground">-</span>;
            }

            const normalizedPlan = String(sharingPlan).trim().toLowerCase();
            const label = sharingPlanLabels[normalizedPlan] ?? sharingPlan;
            const colorClass =
                sharingPlanBadgeColors[normalizedPlan] ??
                'bg-gray-100 text-gray-800 dark:border dark:border-white/15 dark:bg-white/8 dark:text-gray-200';

            return (
                <Badge
                    className={`${colorClass} rounded-full px-3 py-1 text-base`}
                >
                    {label}
                </Badge>
            );
        },
        filterFn: 'includesValue',
    },
    {
        accessorKey: 'has_quotation',
        header: 'Quotation',
        meta: { exportable: true },
        cell: ({ row }) => (
            <Badge
                variant={row.original.has_quotation ? 'default' : 'secondary'}
            >
                {row.original.has_quotation ? 'Created' : 'Pending'}
            </Badge>
        ),
        filterFn: 'includesValue',
    },
    {
        accessorKey: 'passport_number',
        header: 'Passport',
        meta: { exportable: true },
    },
    {
        accessorKey: 'status',
        header: 'Payment Status',
        meta: { exportable: true },
        cell: ({ row }) => {
            const status = row.original.status ?? 'pending_payment';
            const statusColor =
                confirmationMemberStatusColors[status] ??
                'bg-gray-100 text-gray-800 dark:border dark:border-white/15 dark:bg-white/8 dark:text-gray-200';
            const statusLabel =
                confirmationMemberStatusLabels[status] ?? status;

            return (
                <Badge
                    className={`${statusColor} rounded-full px-3 py-1 text-base`}
                >
                    {statusLabel}
                </Badge>
            );
        },
        filterFn: 'includesValue',
    },
    {
        accessorKey: 'order_number',
        header: 'Order No.',
        meta: { exportable: true },
        cell: ({ row }) => {
            const orderId = row.original.order_id;
            const orderNumber = row.original.order_number;

            if (!orderId || !orderNumber) {
                return <span className="text-muted-foreground">-</span>;
            }

            return (
                <button
                    type="button"
                    className="cursor-pointer font-medium text-primary underline-offset-4 hover:underline"
                    onClick={(event) => {
                        event.stopPropagation();
                        router.get(editOrder(orderId).url);
                    }}
                >
                    {orderNumber}
                </button>
            );
        },
    },
    {
        accessorKey: 'discount',
        header: 'Discount',
        meta: { exportable: true },
        cell: ({ row }) => (
            <Badge variant="outline" className="text-sm">
                {formatCurrency(row.original.discount ?? 0)}
            </Badge>
        ),
    },
    {
        accessorKey: 'paid_amount',
        header: 'Payment',
        meta: { exportable: true },
        cell: ({ row }) => (
            <Badge variant="outline" className="text-sm">
                {formatCurrency(row.original.paid_amount ?? 0)} /{' '}
                {formatCurrency(row.original.total_amount ?? 0)}
            </Badge>
        ),
    },
    {
        accessorKey: 'refunded_paid_amount',
        header: 'Refunded/Paid',
        meta: { exportable: true },
        cell: ({ row }) => {
            const refundedAmount = row.original.refunded_amount ?? 0;
            const paidAmount = row.original.invoice_paid_amount ?? 0;
            const holdAmount = paidAmount - refundedAmount;

            return (
                <div className="flex flex-col gap-1 text-sm">
                    <div className="font-medium text-green-600">
                        Paid: {formatCurrency(paidAmount)}
                    </div>
                    <div className="font-medium text-red-600">
                        Refunded: {formatCurrency(refundedAmount)}
                    </div>
                    <div className="font-medium text-muted-foreground">
                        Hold Amount: {formatCurrency(holdAmount)}
                    </div>
                </div>
            );
        },
    },
];

interface ConfirmedCustomerProps {
    dataGroups: CustomerConfirmationDatatableSchema[];
    packageOptions?: OptionType[];
    paymentMethods?: OptionType[];
    autoBillingSyncEnabled?: boolean;
    combineFeatureEnabled?: boolean;
    pageTitle?: string;
    indexUrl?: string;
    countryOptions?: OptionType[];
    branchOptions?: OptionType[];
}

interface PackageSelectOption extends OptionType {
    status?: string | null;
    seats_left?: number | null;
    is_private?: boolean;
    is_selectable?: boolean;
}

type RefundMode = 'percentage' | 'fixed';
type RefundType = 'cancel' | 'overpaid';

const REFUND_PURPOSE_LABELS: Record<RefundType, string> = {
    cancel: 'Trip Cancelled-Refund',
    overpaid: 'Overpaid Refund',
};

const REFUND_DESCRIPTION_BY_TYPE: Record<RefundType, string> = {
    cancel: 'Receipt For Trip Cancelled-Refund',
    overpaid: 'Receipt For Overpaid Refund',
};

interface RefundDraftRow {
    member_id: number;
    member_name: string;
    paid_amount: number;
    total_amount: number;
    overpaid_amount: number;
    payment_method: string;
    refund_to: string;
    description: string;
    selected: boolean;
    mode: RefundMode;
    percentage: string;
    amount: string;
}

export default function ConfirmedCustomerIndex({
    dataGroups,
    packageOptions = [],
    paymentMethods = [],
    autoBillingSyncEnabled = true,
    combineFeatureEnabled = true,
    pageTitle = 'Confirmed Customers',
    indexUrl = confirmedCustomerIndex().url,
    countryOptions = [],
    branchOptions = [],
}: ConfirmedCustomerProps) {
    const { auth } = usePage<SharedData>().props;
    const userPermissions = auth.permissions || [];
    const isSuperadmin = auth.roles.includes('superadmin');
    const { confirm, ConfirmDialog } = useConfirmDialog();
    const isHoldingIndex = indexUrl.includes('/customer-holding');
    const isCompletedIndex = indexUrl.includes('/completed-customer');
    const isCancelledIndex = indexUrl.includes('/cancelled-customer');
    const isReadOnlyIndex = isCompletedIndex || isCancelledIndex;

    const groupTableColumns = useMemo(() => {
        return groupColumns.filter((column) => {
            const columnKey =
                'accessorKey' in column
                    ? String(column.accessorKey ?? '')
                    : String(column.id ?? '');

            if (columnKey === 'package_country' && !isSuperadmin) {
                return false;
            }

            if (isCancelledIndex) {
                return columnKey !== 'paid_amount';
            }

            return (
                columnKey !== 'refunded_paid_summary' &&
                columnKey !== 'refund_cancel_date'
            );
        });
    }, [isSuperadmin, isCancelledIndex]);

    const memberTableColumns = useMemo(() => {
        return memberColumns.filter((column) => {
            const columnKey =
                'accessorKey' in column
                    ? String(column.accessorKey ?? '')
                    : String(column.id ?? '');

            if (isCancelledIndex) {
                return columnKey !== 'paid_amount';
            }

            return columnKey !== 'refunded_paid_amount';
        });
    }, [isCancelledIndex]);
    // const canCreateCustomerConfirmation =
    //     !isHoldingIndex &&
    //     !isReadOnlyIndex &&
    //     userPermissions.includes('customer create');
    const canCreateCustomerConfirmation = false;

    const actions: ActionType[] = [];
    if (canCreateCustomerConfirmation) actions.push('add');
    if (userPermissions.includes('customer view')) actions.push('view');

    const [groupDialogOpen, setGroupDialogOpen] = useState(false);
    const [groupDialogMode, setGroupDialogMode] = useState<
        'view' | 'edit' | 'create'
    >('view');
    const [groupDialogData, setGroupDialogData] =
        useState<CustomerConfirmationFormSchema | null>(null);
    const [isLoadingGroup, setIsLoadingGroup] = useState(false);

    const [publicLinkDialogOpen, setPublicLinkDialogOpen] = useState(false);
    const [selectedPublicLinkGroupId, setSelectedPublicLinkGroupId] = useState<
        number | null
    >(null);
    const enabledPublicEditLinkTypes =
        confirmedCustomerPublicEditLinkConfig.enabledLinkTypes;
    const fallbackPublicEditLinkType =
        confirmedCustomerPublicEditLinkConfig.defaultLinkType;
    const shouldShowPublicEditLinkDialog =
        enabledPublicEditLinkTypes.length > 1;

    const [moveDialogOpen, setMoveDialogOpen] = useState(false);
    const [selectedMoveGroup, setSelectedMoveGroup] =
        useState<CustomerConfirmationDatatableSchema | null>(null);
    const [selectedMoveMemberIds, setSelectedMoveMemberIds] = useState<
        number[]
    >([]);
    const [targetPackageId, setTargetPackageId] = useState<number | null>(null);
    const [movingMembers, setMovingMembers] = useState(false);
    const [moveDialogError, setMoveDialogError] = useState<string | null>(null);

    const [memberDialogOpen, setMemberDialogOpen] = useState(false);
    const [memberDialogMode, setMemberDialogMode] = useState<'view' | 'edit'>(
        'view',
    );
    const [memberDialogData, setMemberDialogData] =
        useState<CustomerMemberFormData | null>(null);
    const memberDialogDataRef = useRef<CustomerMemberFormData | null>(null);
    const [memberDialogMemberId, setMemberDialogMemberId] = useState<
        number | null
    >(null);
    const [memberDialogErrors, setMemberDialogErrors] = useState<
        Record<string, string>
    >({});
    const [isSavingMember, setIsSavingMember] = useState(false);
    const [memberSharingPlanOptions, setMemberSharingPlanOptions] =
        useState<OptionType[]>(sharingPlanOptions);

    const [quotationDialogOpen, setQuotationDialogOpen] = useState(false);
    const [quotationGroup, setQuotationGroup] =
        useState<CustomerConfirmationDatatableSchema | null>(null);
    const [payerMapping, setPayerMapping] = useState<
        Record<number, number | null>
    >({});
    const [isGeneratingQuotations, setIsGeneratingQuotations] = useState(false);
    const [quotationGenerationError, setQuotationGenerationError] = useState<
        string | null
    >(null);

    const [refundDialogOpen, setRefundDialogOpen] = useState(false);
    const [refundGroup, setRefundGroup] =
        useState<CustomerConfirmationDatatableSchema | null>(null);
    const [refundRows, setRefundRows] = useState<RefundDraftRow[]>([]);
    const [refundType, setRefundType] = useState<RefundType>('cancel');
    const [isSubmittingRefund, setIsSubmittingRefund] = useState(false);
    const [isCreatingBalanceInvoice, setIsCreatingBalanceInvoice] =
        useState(false);

    // Combine Quotation dialog
    const [combineQuotationOpen, setCombineQuotationOpen] = useState(false);
    const [combineQuotationGroup, setCombineQuotationGroup] =
        useState<CustomerConfirmationDatatableSchema | null>(null);
    const [combineQuotationTargetId, setCombineQuotationTargetId] = useState<
        number | null
    >(null);
    const [combineQuotationMemberIds, setCombineQuotationMemberIds] = useState<
        number[]
    >([]);
    const [combiningQuotation, setCombiningQuotation] = useState(false);
    const [combineQuotationError, setCombineQuotationError] = useState<
        string | null
    >(null);

    // Combine Confirmation dialog
    const [combineConfirmationOpen, setCombineConfirmationOpen] =
        useState(false);
    const [combineConfirmationGroup, setCombineConfirmationGroup] =
        useState<CustomerConfirmationDatatableSchema | null>(null);
    const [combineConfirmationTargetCcId, setCombineConfirmationTargetCcId] =
        useState<number | null>(null);
    const [combineConfirmationMemberIds, setCombineConfirmationMemberIds] =
        useState<number[]>([]);
    const [combineConfirmationMode, setCombineConfirmationMode] = useState<
        'keep' | 'merge'
    >('keep');
    const [
        combineConfirmationTargetQuotationId,
        setCombineConfirmationTargetQuotationId,
    ] = useState<number | null>(null);
    const [combiningConfirmation, setCombiningConfirmation] = useState(false);
    const [combineConfirmationError, setCombineConfirmationError] = useState<
        string | null
    >(null);

    const isMemberView = memberDialogMode === 'view';

    const targetPackageOptions = useMemo(() => {
        return (packageOptions as PackageSelectOption[]).filter((option) => {
            if (option.is_private) {
                return false;
            }

            if (option.is_selectable !== undefined) {
                return Boolean(option.is_selectable);
            }

            const status = String(option.status ?? '')
                .trim()
                .toLowerCase();
            const seatsLeft = Number(option.seats_left ?? NaN);

            return (
                status === 'open' && Number.isFinite(seatsLeft) && seatsLeft > 0
            );
        });
    }, [packageOptions]);

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: pageTitle,
            href: indexUrl,
        },
    ];

    const packageCountryFilterOptions = useMemo(() => {
        return Array.from(
            new Set(
                dataGroups
                    .map((group) => String(group.package_country ?? '').trim())
                    .filter((country) => country.length > 0 && country !== '-'),
            ),
        )
            .sort((left, right) => left.localeCompare(right))
            .map((country) => ({
                label: country,
                value: country,
            }));
    }, [dataGroups]);

    const buildSharingPlanOptions = (
        packageData:
            | {
                  price_single?: number | null;
                  price_double?: number | null;
                  price_triple?: number | null;
                  price_quad?: number | null;
                  child_with_bed_price?: number | null;
                  child_no_bed_price?: number | null;
                  infant_price?: number | null;
              }
            | null
            | undefined,
    ): OptionType[] => {
        if (!packageData) {
            return sharingPlanOptions;
        }

        const dynamic = [
            {
                value: 'single',
                label: 'Single',
                price: Number(packageData.price_single ?? 0),
            },
            {
                value: 'double',
                label: 'Double',
                price: Number(packageData.price_double ?? 0),
            },
            {
                value: 'triple',
                label: 'Triple',
                price: Number(packageData.price_triple ?? 0),
            },
            {
                value: 'quad',
                label: 'Quad',
                price: Number(packageData.price_quad ?? 0),
            },
            {
                value: 'child_with_bed',
                label: 'Child (7-11 years)',
                price: Number(packageData.child_with_bed_price ?? 0),
            },
            {
                value: 'child_no_bed',
                label: 'Child (2-6 years)',
                price: Number(packageData.child_no_bed_price ?? 0),
            },
            {
                value: 'infant',
                label: 'Infant (0-2 years)',
                price: Number(packageData.infant_price ?? 0),
            },
        ]
            .filter((item) => item.price > 0)
            .map((item) => ({
                value: item.value,
                label: `${item.label} (${formatCurrency(item.price)})`,
            }));

        return dynamic.length > 0 ? dynamic : sharingPlanOptions;
    };

    const openMoveDialog = (
        group: CustomerConfirmationDatatableSchema,
        memberIds?: number[],
    ) => {
        const activeMemberIds = (memberIds ?? [])
            .filter((memberId) =>
                group.members.some(
                    (member) =>
                        member.id === memberId && member.status !== 'cancelled',
                ),
            )
            .concat(
                memberIds && memberIds.length > 0
                    ? []
                    : group.members
                          .filter((member) => member.status !== 'cancelled')
                          .map((member) => member.id),
            );

        setSelectedMoveGroup(group);
        setSelectedMoveMemberIds(Array.from(new Set(activeMemberIds)));
        setTargetPackageId(null);
        setMoveDialogError(null);
        setMoveDialogOpen(true);
    };

    const submitMoveMembers = () => {
        if (!selectedMoveGroup || selectedMoveMemberIds.length === 0) {
            return;
        }

        if (isHoldingIndex && !targetPackageId) {
            setMoveDialogError(
                'Please select a package before moving members.',
            );

            return;
        }

        setMovingMembers(true);
        setMoveDialogError(null);

        router.post(
            `/customer-confirmations/${selectedMoveGroup.id}/move-members`,
            {
                member_ids: selectedMoveMemberIds,
                target_package_id: targetPackageId,
            },
            {
                preserveScroll: true,
                preserveState: false,
                onSuccess: () => {
                    toast.success(
                        targetPackageId
                            ? 'Members moved to confirmed customer with selected package.'
                            : 'Members moved to holding confirmation.',
                    );
                    setMoveDialogOpen(false);
                },
                onError: () => {
                    toast.error('Failed to move selected members.');
                },
                onFinish: () => {
                    setMovingMembers(false);
                },
            },
        );
    };

    const openCombineQuotationDialog = (
        group: CustomerConfirmationDatatableSchema,
    ) => {
        const quotations = group.quotations ?? [];

        if (quotations.length < 2) {
            toast.error('At least two quotations are required to combine.');

            return;
        }

        setCombineQuotationGroup(group);
        setCombineQuotationTargetId(quotations[0]?.id ?? null);
        setCombineQuotationMemberIds([]);
        setCombineQuotationError(null);
        setCombineQuotationOpen(true);
    };

    const submitCombineQuotation = () => {
        if (!combineQuotationGroup) {
            return;
        }

        if (!combineQuotationTargetId) {
            setCombineQuotationError('Please select a target quotation.');

            return;
        }

        if (combineQuotationMemberIds.length === 0) {
            setCombineQuotationError(
                'Select at least one member to combine into the target quotation.',
            );

            return;
        }

        setCombineQuotationError(null);
        setCombiningQuotation(true);

        router.post(
            `/customer-confirmations/${combineQuotationGroup.id}/combine-quotations`,
            {
                target_quotation_id: combineQuotationTargetId,
                member_ids: combineQuotationMemberIds,
            },
            {
                preserveScroll: true,
                preserveState: false,
                onSuccess: (page) => {
                    const resolvedError = resolveCombineResponseError(page);

                    if (resolvedError.length > 0) {
                        setCombineQuotationError(resolvedError);
                        toast.error(resolvedError);

                        return;
                    }

                    toast.success('Quotations combined successfully.');
                    setCombineQuotationError(null);
                    setCombineQuotationOpen(false);
                },
                onError: (errors) => {
                    const errorMessage = resolveCombineValidationError(
                        errors,
                        'Failed to combine quotations.',
                    );
                    setCombineQuotationError(errorMessage);
                    toast.error(errorMessage);
                },
                onFinish: () => {
                    setCombiningQuotation(false);
                },
            },
        );
    };

    const openCombineConfirmationDialog = (
        group: CustomerConfirmationDatatableSchema,
    ) => {
        const siblingGroups = dataGroups.filter(
            (candidate) =>
                candidate.id !== group.id &&
                candidate.package_id != null &&
                candidate.package_id === group.package_id,
        );

        if (siblingGroups.length === 0) {
            toast.error(
                'No other confirmation in the same package to combine with.',
            );

            return;
        }

        const activeMemberIds = group.members
            .filter((member) => member.status !== 'cancelled')
            .map((member) => member.id);

        setCombineConfirmationGroup(group);
        setCombineConfirmationTargetCcId(siblingGroups[0]?.id ?? null);
        setCombineConfirmationMemberIds(activeMemberIds);
        setCombineConfirmationMode('keep');
        setCombineConfirmationTargetQuotationId(null);
        setCombineConfirmationError(null);
        setCombineConfirmationOpen(true);
    };

    const submitCombineConfirmation = () => {
        if (!combineConfirmationGroup) {
            return;
        }

        if (!combineConfirmationTargetCcId) {
            setCombineConfirmationError(
                'Please select a confirmation to combine into.',
            );

            return;
        }

        if (combineConfirmationMemberIds.length === 0) {
            setCombineConfirmationError('Select at least one member to move.');

            return;
        }

        if (
            combineConfirmationMode === 'merge' &&
            !combineConfirmationTargetQuotationId
        ) {
            setCombineConfirmationError(
                'Select the target quotation to merge the moved members into.',
            );

            return;
        }

        setCombineConfirmationError(null);
        setCombiningConfirmation(true);

        router.post(
            `/customer-confirmations/${combineConfirmationGroup.id}/combine-confirmation`,
            {
                target_confirmation_id: combineConfirmationTargetCcId,
                member_ids: combineConfirmationMemberIds,
                target_quotation_id:
                    combineConfirmationMode === 'merge'
                        ? combineConfirmationTargetQuotationId
                        : null,
            },
            {
                preserveScroll: true,
                preserveState: false,
                onSuccess: (page) => {
                    const resolvedError = resolveCombineResponseError(page);

                    if (resolvedError.length > 0) {
                        setCombineConfirmationError(resolvedError);
                        toast.error(resolvedError);

                        return;
                    }

                    toast.success(
                        'Customer confirmations combined successfully.',
                    );
                    setCombineConfirmationError(null);
                    setCombineConfirmationOpen(false);
                },
                onError: (errors) => {
                    const errorMessage = resolveCombineValidationError(
                        errors,
                        'Failed to combine confirmations.',
                    );
                    setCombineConfirmationError(errorMessage);
                    toast.error(errorMessage);
                },
                onFinish: () => {
                    setCombiningConfirmation(false);
                },
            },
        );
    };

    const handleOpenGroupDialog = async (
        groupId: number,
        mode: 'view' | 'edit',
    ) => {
        setGroupDialogMode(mode);
        setGroupDialogOpen(true);
        setIsLoadingGroup(true);
        setGroupDialogData(null);

        try {
            const response = await fetch(showGroup(groupId).url);
            if (!response.ok) throw new Error('Failed to fetch group data');
            const data = await response.json();
            setGroupDialogData(data);
        } catch (error) {
            console.error('Failed to fetch group details:', error);
        } finally {
            setIsLoadingGroup(false);
        }
    };

    const handleCopyPublicEditLink = async (
        linkType: CustomerConfirmationPublicEditLinkType,
        groupId?: number,
    ) => {
        const targetGroupId = groupId ?? selectedPublicLinkGroupId;

        if (!targetGroupId) {
            return;
        }

        try {
            const response = await fetch(
                generateEditLink(targetGroupId, {
                    query: { link_type: linkType },
                }).url,
            );

            if (!response.ok) {
                throw new Error('Failed to generate public link.');
            }

            const data = (await response.json()) as { url: string };

            await navigator.clipboard.writeText(data.url);
            toast.success('Link copied', {
                description:
                    linkType === 'one_time'
                        ? 'One-time public edit link copied to clipboard.'
                        : 'Continuous public edit link copied to clipboard.',
            });

            if (shouldShowPublicEditLinkDialog) {
                setPublicLinkDialogOpen(false);
                setSelectedPublicLinkGroupId(null);
            }
        } catch {
            toast.error('Failed to generate public link.');
        }
    };

    const openMemberDialog = async (
        groupId: number,
        memberId: number,
        mode: 'view' | 'edit',
    ) => {
        setMemberDialogMode(mode);
        setMemberDialogErrors({});

        try {
            const response = await fetch(showGroup(groupId).url);
            if (!response.ok) {
                throw new Error('Failed to load group data');
            }

            const data =
                (await response.json()) as CustomerConfirmationFormSchema;
            const members = data.members ?? [];
            const selectedMember = members.find(
                (member) =>
                    Number(
                        (member as { id?: number; member_id?: number }).id,
                    ) === memberId ||
                    Number(
                        (member as { id?: number; member_id?: number })
                            .member_id,
                    ) === memberId,
            );

            if (!selectedMember) {
                toast.error('Unable to locate member detail.');
                return;
            }

            const initialMemberDialogData: CustomerMemberFormData = {
                ...emptyMember(false),
                ...selectedMember,
                member_id: memberId,
            };

            setMemberDialogMemberId(memberId);
            memberDialogDataRef.current = initialMemberDialogData;
            setMemberDialogData(initialMemberDialogData);
            setMemberDialogOpen(true);

            if (data.package_id) {
                const packageResponse = await fetch(
                    `/packages-get-for-show/${data.package_id}`,
                );

                if (packageResponse.ok) {
                    const packageData = (await packageResponse.json()) as {
                        price_single?: number | null;
                        price_double?: number | null;
                        price_triple?: number | null;
                        price_quad?: number | null;
                        child_with_bed_price?: number | null;
                        child_no_bed_price?: number | null;
                        infant_price?: number | null;
                    };
                    setMemberSharingPlanOptions(
                        buildSharingPlanOptions(packageData),
                    );
                } else {
                    setMemberSharingPlanOptions(sharingPlanOptions);
                }
            } else {
                setMemberSharingPlanOptions(sharingPlanOptions);
            }
        } catch {
            toast.error('Failed to load member detail.');
        }
    };

    const updateMemberDraft = (
        field: keyof CustomerMemberFormData,
        value: string | boolean | File | null | CustomerDocumentItemSchema[],
    ) => {
        setMemberDialogData((prev) => {
            if (!prev) {
                return prev;
            }

            const updatedDraft: CustomerMemberFormData = {
                ...prev,
                [field]: value,
            };

            memberDialogDataRef.current = updatedDraft;

            return updatedDraft;
        });
    };

    const cancelMember = (memberId: number) => {
        router.post(
            `/customer-confirmations/members/${memberId}/cancel`,
            {},
            {
                preserveScroll: true,
                preserveState: false,
                onSuccess: () => {
                    toast.success('Member cancelled successfully.');
                },
                onError: () => {
                    toast.error('Failed to cancel member.');
                },
            },
        );
    };

    const validateMemberDraft = (): boolean => {
        if (!memberDialogData) {
            return false;
        }

        const errors: Record<string, string> = {};

        const result = customerValidationSchema.safeParse(memberDialogData);
        if (!result.success) {
            result.error.issues.forEach((issue) => {
                const key = issue.path.join('.');
                if (!errors[key]) {
                    errors[key] = issue.message;
                }
            });
        }

        if (
            memberDialogData.status == null ||
            memberDialogData.status.toString().trim().length === 0
        ) {
            errors.status = 'Payment status is required.';
        }

        setMemberDialogErrors(errors);

        return Object.keys(errors).length === 0;
    };

    const submitMemberUpdate = () => {
        const latestMemberDialogData =
            memberDialogDataRef.current ?? memberDialogData;

        if (!latestMemberDialogData || !memberDialogMemberId) {
            return;
        }

        if (!validateMemberDraft()) {
            return;
        }

        setIsSavingMember(true);

        const payload = new FormData();
        const appendValue = (
            key: string,
            value: string | boolean | File | null | undefined,
        ) => {
            if (value === undefined || value === null) {
                payload.append(key, '');

                return;
            }

            if (typeof value === 'boolean') {
                payload.append(key, value ? '1' : '0');

                return;
            }

            payload.append(key, value);
        };

        appendValue('_method', 'PUT');
        appendValue('name', latestMemberDialogData.name ?? '');
        appendValue('email', latestMemberDialogData.email ?? '');
        appendValue(
            'contact_number',
            latestMemberDialogData.contact_number ?? '',
        );
        appendValue('nric_number', latestMemberDialogData.nric_number ?? '');
        appendValue('address', latestMemberDialogData.address ?? '');
        appendValue('nationality', latestMemberDialogData.nationality ?? '');
        appendValue(
            'passport_number',
            latestMemberDialogData.passport_number ?? '',
        );
        appendValue(
            'passport_issue_date',
            latestMemberDialogData.passport_issue_date ?? '',
        );
        appendValue(
            'passport_expiry_date',
            latestMemberDialogData.passport_expiry_date ?? '',
        );
        appendValue(
            'passport_place_of_issue',
            latestMemberDialogData.passport_place_of_issue ?? '',
        );
        appendValue('gender', latestMemberDialogData.gender ?? '');
        appendValue(
            'marital_status',
            latestMemberDialogData.marital_status ?? '',
        );
        appendValue(
            'date_of_birth',
            latestMemberDialogData.date_of_birth ?? '',
        );
        appendValue(
            'place_of_birth',
            latestMemberDialogData.place_of_birth ?? '',
        );
        appendValue(
            'first_time_umrah',
            Boolean(latestMemberDialogData.first_time_umrah),
        );
        appendValue(
            'has_chronic_disease',
            Boolean(latestMemberDialogData.has_chronic_disease),
        );
        appendValue(
            'chronic_disease_details',
            latestMemberDialogData.chronic_disease_details ?? '',
        );
        appendValue(
            'status',
            String(
                (latestMemberDialogData as { status?: string }).status ?? '',
            ),
        );
        appendValue(
            'sharing_plan',
            String(latestMemberDialogData.sharing_plan ?? ''),
        );
        appendValue(
            'relationship',
            String(latestMemberDialogData.relationship ?? ''),
        );

        const appendDocumentRows = (
            key: 'passport_documents' | 'photo_documents',
            rows: CustomerDocumentItemSchema[] | null | undefined,
        ) => {
            if (!Array.isArray(rows)) {
                return;
            }

            rows.forEach((row, index) => {
                const baseKey = `${key}[${index}]`;

                if (typeof row.id === 'number') {
                    payload.append(`${baseKey}[id]`, String(row.id));
                }

                if (row.file instanceof File) {
                    payload.append(`${baseKey}[file]`, row.file);
                }

                if (
                    typeof row.file_name === 'string' &&
                    row.file_name.trim() !== ''
                ) {
                    payload.append(`${baseKey}[file_name]`, row.file_name);
                }

                if (
                    typeof row.file_path === 'string' &&
                    row.file_path.trim() !== ''
                ) {
                    payload.append(`${baseKey}[file_path]`, row.file_path);
                }

                payload.append(`${baseKey}[removed]`, row.removed ? '1' : '0');
            });
        };

        appendDocumentRows(
            'passport_documents',
            latestMemberDialogData.passport_documents,
        );
        appendDocumentRows(
            'photo_documents',
            latestMemberDialogData.photo_documents,
        );

        router.post(
            `/customer-confirmations/members/${memberDialogMemberId}`,
            payload,
            {
                preserveScroll: true,
                preserveState: false,
                onSuccess: () => {
                    toast.success('Member updated successfully.');
                    setMemberDialogOpen(false);
                },
                onError: (errors) => {
                    setMemberDialogErrors(errors);
                    toast.error('Failed to update member.');
                },
                onFinish: () => {
                    setIsSavingMember(false);
                },
            },
        );
    };

    const openQuotationDialog = (
        group: CustomerConfirmationDatatableSchema,
    ) => {
        const unquotedMembers = group.members.filter(
            (m) => m.status !== 'cancelled' && !m.has_quotation,
        );

        if (unquotedMembers.length === 0) {
            toast.error('All active members already have quotations.');
            return;
        }

        const leader = unquotedMembers.find((m) => m.is_leader);
        const defaultPayerId = leader?.id ?? unquotedMembers[0].id;

        const initialMapping: Record<number, number> = {};
        for (const member of unquotedMembers) {
            initialMapping[member.id] = defaultPayerId;
        }

        setQuotationGroup(group);
        setPayerMapping(initialMapping);
        setQuotationGenerationError(null);
        setQuotationDialogOpen(true);
    };

    const submitGenerateQuotations = () => {
        const validation = validateQuotationGenerationPayload(
            quotationGroup,
            payerMapping,
        );

        if (!validation.isValid) {
            const errorMessage =
                validation.errorMessage ?? 'Failed to generate quotations.';
            setQuotationGenerationError(errorMessage);
            toast.error(errorMessage);

            return;
        }

        if (!quotationGroup) {
            return;
        }

        setQuotationGenerationError(null);
        setIsGeneratingQuotations(true);

        const route = generateQuotations(quotationGroup.id);

        router.post(
            route.url,
            {
                payer_to_members: validation.payerToMembers,
            },
            {
                preserveScroll: true,
                preserveState: false,
                onSuccess: (page) => {
                    const pageProps = page.props as Record<string, unknown>;
                    const flash = (page.props as unknown as SharedData).flash;
                    const flashError =
                        typeof flash?.error === 'string'
                            ? flash.error.trim()
                            : '';

                    const pageErrors =
                        (pageProps.errors as Record<string, unknown>) ?? {};
                    const firstValidationError = Object.values(pageErrors)
                        .map((value) => {
                            if (Array.isArray(value)) {
                                return String(value[0] ?? '').trim();
                            }

                            return String(value ?? '').trim();
                        })
                        .find((message) => message.length > 0);

                    const resolvedError =
                        flashError.length > 0
                            ? flashError
                            : (firstValidationError ?? '');

                    if (resolvedError.length > 0) {
                        setQuotationGenerationError(resolvedError);
                        toast.error(resolvedError);

                        return;
                    }

                    toast.success('Quotation created successfully.');
                    setQuotationGenerationError(null);
                    setQuotationDialogOpen(false);
                },
                onError: (errors) => {
                    const resolvedError =
                        errors.payer_to_members ??
                        errors.customer_confirmation_id ??
                        Object.values(errors)[0] ??
                        'Failed to generate quotations.';

                    const errorMessage = Array.isArray(resolvedError)
                        ? String(
                              resolvedError[0] ??
                                  'Failed to generate quotations.',
                          )
                        : String(resolvedError);

                    setQuotationGenerationError(errorMessage);
                    toast.error(errorMessage);
                },
                onFinish: () => {
                    setIsGeneratingQuotations(false);
                },
            },
        );
    };

    const normalizeRefundAmount = (value: string): number | null => {
        if (value.trim() === '') {
            return null;
        }

        const parsed = Number(value);

        if (!Number.isFinite(parsed) || parsed < 0) {
            return null;
        }

        return parsed;
    };

    const defaultRefundPaymentMethod =
        String(
            paymentMethods.find(
                (method) =>
                    String(method.value).trim().toLowerCase() === 'paynow',
            )?.value ?? 'paynow',
        ).trim() || 'paynow';

    const resolveOverpaidAmount = (row: {
        paid_amount?: number | null;
        total_amount?: number | null;
        overpaid_amount?: number | null;
    }): number => {
        const paidAmount = Number(row.paid_amount ?? 0);
        const totalAmount = Number(row.total_amount ?? 0);
        const computedFromNeed = Math.max(0, paidAmount - totalAmount);
        const providedOverpaid = Math.max(0, Number(row.overpaid_amount ?? 0));

        return Number(Math.max(providedOverpaid, computedFromNeed).toFixed(2));
    };

    const getRefundBaseAmount = (
        row: RefundDraftRow,
        type: RefundType,
    ): number => {
        if (type === 'overpaid') {
            return resolveOverpaidAmount(row);
        }

        return Number(row.paid_amount ?? 0);
    };

    const computeRefundAmount = (
        row: RefundDraftRow,
        type: RefundType,
    ): number | null => {
        const baseAmount = getRefundBaseAmount(row, type);

        if (baseAmount <= 0) {
            return null;
        }

        if (row.mode === 'percentage') {
            const percentage = normalizeRefundAmount(row.percentage);

            if (percentage === null || percentage < 0 || percentage > 100) {
                return null;
            }

            return Number(((baseAmount * percentage) / 100).toFixed(2));
        }

        const fixedAmount = normalizeRefundAmount(row.amount);

        if (
            fixedAmount === null ||
            fixedAmount < 0 ||
            fixedAmount > baseAmount
        ) {
            return null;
        }

        return Number(fixedAmount.toFixed(2));
    };

    const openRefundDialog = (
        group: CustomerConfirmationDatatableSchema,
        singleMemberId?: number,
    ) => {
        const refundableMembers = group.members.filter(
            (member) =>
                member.status !== 'cancelled' && (member.paid_amount ?? 0) > 0,
        );

        if (refundableMembers.length === 0) {
            toast.error('No paid members are available for refund.');

            return;
        }

        if (singleMemberId) {
            const targetMember = refundableMembers.find(
                (member) => member.id === singleMemberId,
            );

            if (!targetMember) {
                toast.error('Selected member has no paid amount to refund.');

                return;
            }

            setRefundRows([
                {
                    member_id: targetMember.id,
                    member_name: targetMember.name,
                    paid_amount: Number(targetMember.paid_amount ?? 0),
                    total_amount: Number(targetMember.total_amount ?? 0),
                    overpaid_amount: resolveOverpaidAmount(targetMember),
                    payment_method: defaultRefundPaymentMethod,
                    refund_to: targetMember.contact || '',
                    description: REFUND_DESCRIPTION_BY_TYPE.cancel,
                    selected: true,
                    mode: 'fixed',
                    percentage: '',
                    amount: '',
                },
            ]);
        } else {
            setRefundRows(
                refundableMembers.map((member) => ({
                    member_id: member.id,
                    member_name: member.name,
                    paid_amount: Number(member.paid_amount ?? 0),
                    total_amount: Number(member.total_amount ?? 0),
                    overpaid_amount: resolveOverpaidAmount(member),
                    payment_method: defaultRefundPaymentMethod,
                    refund_to: member.contact || '',
                    description: REFUND_DESCRIPTION_BY_TYPE.cancel,
                    selected: true,
                    mode: 'fixed',
                    percentage: '',
                    amount: '',
                })),
            );
        }

        setRefundType('cancel');
        setRefundGroup(group);
        setRefundDialogOpen(true);
    };

    const updateRefundRow = (
        memberId: number,
        updater: (prev: RefundDraftRow) => RefundDraftRow,
    ) => {
        setRefundRows((prevRows) =>
            prevRows.map((row) =>
                row.member_id === memberId ? updater(row) : row,
            ),
        );
    };

    const updateRefundType = (nextType: RefundType) => {
        setRefundType(nextType);

        setRefundRows((prevRows) =>
            prevRows.map((row) => {
                const isOverpaidEligible = resolveOverpaidAmount(row) > 0;

                if (nextType === 'overpaid' && !isOverpaidEligible) {
                    return {
                        ...row,
                        selected: false,
                        description: REFUND_DESCRIPTION_BY_TYPE[nextType],
                    };
                }

                return {
                    ...row,
                    description: REFUND_DESCRIPTION_BY_TYPE[nextType],
                };
            }),
        );
    };

    const submitRefunds = () => {
        if (!refundGroup) {
            return;
        }

        const selectedRows = refundRows.filter((row) => row.selected);

        if (selectedRows.length === 0) {
            toast.error('Please select at least one member to refund.');

            return;
        }

        if (
            refundType === 'overpaid' &&
            selectedRows.some((row) => resolveOverpaidAmount(row) <= 0)
        ) {
            toast.error(
                'Overpaid refund can only be used for members with overpaid amount.',
            );

            return;
        }

        const memberRefunds = selectedRows.map((row) => {
            const amount = computeRefundAmount(row, refundType);

            if (amount === null) {
                return null;
            }

            return {
                member_id: row.member_id,
                mode: row.mode,
                percentage:
                    row.mode === 'percentage'
                        ? Number(row.percentage || 0)
                        : null,
                amount: row.mode === 'fixed' ? amount : null,
                payment_method: row.payment_method.trim(),
                refund_to: row.refund_to.trim(),
                description: row.description.trim(),
            };
        });

        if (memberRefunds.some((row) => row === null)) {
            toast.error(
                'Please enter valid refund values that do not exceed paid amount.',
            );

            return;
        }

        setIsSubmittingRefund(true);

        router.post(
            `/customer-confirmations/${refundGroup.id}/refunds`,
            {
                refund_type: refundType,
                member_refunds: memberRefunds,
            },
            {
                preserveState: false,
                preserveScroll: true,
                onError: () => {
                    toast.error(
                        'Failed to create refund invoice/receipt documents.',
                    );
                },
                onFinish: () => {
                    setIsSubmittingRefund(false);
                },
            },
        );
    };

    const resolveBalanceInvoiceAmount = (
        member: CustomerConfirmationMemberDatatableSchema,
    ): number => {
        const providedAmount = Number(member.balance_invoice_amount ?? 0);

        if (Number.isFinite(providedAmount) && providedAmount > 0) {
            return Number(providedAmount.toFixed(2));
        }

        const totalAmount = Number(member.total_amount ?? 0);
        const billedAmount = Number(member.billed_amount ?? totalAmount);

        return Number(Math.max(0, totalAmount - billedAmount).toFixed(2));
    };

    const submitCreateBalanceInvoice = (
        groupId: number,
        member: CustomerConfirmationMemberDatatableSchema,
    ) => {
        if (isCreatingBalanceInvoice) {
            return;
        }

        const balanceAmount = resolveBalanceInvoiceAmount(member);

        if (balanceAmount <= 0) {
            toast.error(
                'No unbilled balance amount available for this member.',
            );

            return;
        }

        setIsCreatingBalanceInvoice(true);

        router.post(
            `/customer-confirmations/${groupId}/members/${member.id}/balance-invoice`,
            {},
            {
                preserveScroll: true,
                preserveState: false,
                onSuccess: () => {
                    toast.success('Balance invoice created successfully.');
                },
                onError: () => {
                    toast.error('Failed to create balance invoice.');
                },
                onFinish: () => {
                    setIsCreatingBalanceInvoice(false);
                },
            },
        );
    };

    const renderGroupSubComponent = (
        row: Row<CustomerConfirmationDatatableSchema>,
    ) => {
        const data = row.original.members;

        return (
            <DataTable
                columns={memberTableColumns}
                data={data}
                actions={['view']}
                showSettings={false}
                showExport={false}
                getRowActions={(member) => {
                    if (isReadOnlyIndex) {
                        return [];
                    }

                    const rowActions: ActionType[] = [];
                    const memberPaidAmount = Number(member.paid_amount ?? 0);

                    if (member.status !== 'cancelled') {
                        rowActions.push('edit');
                    }

                    if (member.status !== 'cancelled') {
                        rowActions.push(
                            isHoldingIndex ? 'move' : 'move-members',
                        );
                    }

                    if (
                        member.status !== 'cancelled' &&
                        memberPaidAmount <= 0
                    ) {
                        rowActions.push('cancel-member');
                    }

                    if (
                        member.status !== 'cancelled' &&
                        (member.paid_amount ?? 0) > 0
                    ) {
                        rowActions.push('refund');
                    }

                    if (
                        member.status === 'partially_paid' &&
                        (member.has_quotation ?? false) &&
                        resolveBalanceInvoiceAmount(member) > 0
                    ) {
                        rowActions.push('create-balance-invoice');
                    }

                    if (
                        member.status !== 'cancelled' &&
                        (member.paid_amount ?? 0) > 0
                    ) {
                        rowActions.push('export-member-receipts-pdf');
                    }

                    return rowActions;
                }}
                addButtonText=""
                onAction={(action, payload) => {
                    if (!payload) {
                        return;
                    }

                    if (isReadOnlyIndex && action !== 'view') {
                        return;
                    }

                    const tableRow =
                        payload as Row<CustomerConfirmationMemberDatatableSchema>;
                    const member = tableRow.original;

                    if (action === 'view') {
                        openMemberDialog(member.group_id, member.id, 'view');
                        return;
                    }

                    if (action === 'edit') {
                        openMemberDialog(member.group_id, member.id, 'edit');
                        return;
                    }

                    if (action === 'move-members' || action === 'move') {
                        openMoveDialog(row.original, [member.id]);
                        return;
                    }

                    if (action === 'refund') {
                        openRefundDialog(row.original, member.id);
                        return;
                    }

                    if (action === 'create-balance-invoice') {
                        submitCreateBalanceInvoice(row.original.id, member);
                        return;
                    }

                    if (action === 'cancel-member') {
                        confirm({
                            title: 'Customer Cancel Trip',
                            message: `Cancel trip for ${member.name}?`,
                            confirmText: 'Customer Cancel Trip',
                            cancelText: 'Back',
                            onConfirm: () => cancelMember(member.id),
                        });
                    }

                    if (action === 'export-member-receipts-pdf') {
                        window.open(
                            memberReceiptsPdf({
                                id: member.group_id,
                                memberId: member.id,
                            }).url,
                            '_blank',
                        );
                    }
                }}
                onRowDoubleClick={(member) => {
                    if (isReadOnlyIndex) {
                        openMemberDialog(member.group_id, member.id, 'view');

                        return;
                    }

                    if (member.status !== 'cancelled') {
                        openMemberDialog(member.group_id, member.id, 'edit');

                        return;
                    }

                    openMemberDialog(member.group_id, member.id, 'view');
                }}
                initialState={{
                    columnVisibility: {
                        nric_number: false,
                        nationality: false,
                        has_quotation: false,
                        passport_number: false,
                        customer_number: false,
                        contact: false,
                        email: false,
                        paid_amount: !isCancelledIndex,
                        refunded_paid_amount: isCancelledIndex,
                    },
                    pagination: {
                        pageIndex: 0,
                        pageSize: 'all',
                    },
                }}
            />
        );
    };

    return (
        <>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title={pageTitle} />
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">{pageTitle}</h2>
                    </div>

                    <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                        <DataTable
                            columns={groupTableColumns}
                            data={dataGroups}
                            actions={actions}
                            showExport={false}
                            searchFilterMode="outside"
                            columnFilterMode="outside"
                            inheritExpandedRowBackground
                            groupByRowColorKey="id"
                            addButtonText={
                                canCreateCustomerConfirmation
                                    ? 'Create Customer Confirmation'
                                    : ''
                            }
                            enableExpand
                            getRowActions={(group) => {
                                if (isReadOnlyIndex) {
                                    return [];
                                }

                                const rowActions: ActionType[] = [];
                                const hasActiveMembers = group.members.some(
                                    (member) => member.status !== 'cancelled',
                                );

                                if (hasActiveMembers) {
                                    rowActions.push(
                                        'copy-customer-confirmation-public-edit-link',
                                        isHoldingIndex
                                            ? 'move'
                                            : 'move-members',
                                    );

                                    if (!autoBillingSyncEnabled) {
                                        rowActions.push('sync-billing');
                                    }

                                    if (
                                        userPermissions.includes(
                                            'customer edit',
                                        )
                                    ) {
                                        rowActions.push('edit');
                                    }
                                }

                                const hasRefundableMembers = group.members.some(
                                    (member) =>
                                        member.status !== 'cancelled' &&
                                        (member.paid_amount ?? 0) > 0,
                                );

                                if (hasRefundableMembers) {
                                    rowActions.push('refund');
                                }

                                if (group.can_create_quotation) {
                                    rowActions.push('create-quotation');
                                }

                                if (
                                    combineFeatureEnabled &&
                                    userPermissions.includes('customer edit') &&
                                    (group.quotation_count ?? 0) > 1
                                ) {
                                    rowActions.push('combine-quotations');
                                }

                                if (
                                    combineFeatureEnabled &&
                                    userPermissions.includes('customer edit') &&
                                    hasActiveMembers &&
                                    dataGroups.some(
                                        (candidate) =>
                                            candidate.id !== group.id &&
                                            candidate.package_id != null &&
                                            candidate.package_id ===
                                                group.package_id,
                                    )
                                ) {
                                    rowActions.push('combine-confirmations');
                                }

                                if (
                                    userPermissions.includes('customer edit') &&
                                    group.can_delete
                                ) {
                                    rowActions.push('delete');
                                }

                                return rowActions;
                            }}
                            renderSubComponent={renderGroupSubComponent}
                            url={indexUrl}
                            onAction={(action, row) => {
                                if (action === 'add') {
                                    setGroupDialogMode('create');
                                    setGroupDialogData(null);
                                    setIsLoadingGroup(false);
                                    setGroupDialogOpen(true);
                                }

                                const groupId = row?.original.id;
                                if (groupId !== undefined) {
                                    if (action === 'view') {
                                        handleOpenGroupDialog(groupId, 'view');
                                    } else if (action === 'edit') {
                                        handleOpenGroupDialog(groupId, 'edit');
                                    } else if (
                                        action ===
                                        'copy-customer-confirmation-public-edit-link'
                                    ) {
                                        const preferredLinkType =
                                            enabledPublicEditLinkTypes[0] ??
                                            fallbackPublicEditLinkType;

                                        if (shouldShowPublicEditLinkDialog) {
                                            setSelectedPublicLinkGroupId(
                                                groupId,
                                            );
                                            setPublicLinkDialogOpen(true);
                                        } else {
                                            void handleCopyPublicEditLink(
                                                preferredLinkType,
                                                groupId,
                                            );
                                        }
                                    } else if (
                                        (action === 'move-members' ||
                                            action === 'move') &&
                                        row
                                    ) {
                                        openMoveDialog(row.original);
                                    } else if (
                                        action === 'create-quotation' &&
                                        row
                                    ) {
                                        openQuotationDialog(row.original);
                                    } else if (
                                        action === 'combine-quotations' &&
                                        row
                                    ) {
                                        openCombineQuotationDialog(
                                            row.original,
                                        );
                                    } else if (
                                        action === 'combine-confirmations' &&
                                        row
                                    ) {
                                        openCombineConfirmationDialog(
                                            row.original,
                                        );
                                    } else if (action === 'refund' && row) {
                                        openRefundDialog(row.original);
                                    } else if (action === 'sync-billing') {
                                        confirm({
                                            title: 'Sync Billing',
                                            message: `Sync billing data for confirmation #${groupId}?`,
                                            confirmText: 'Sync',
                                            cancelText: 'Cancel',
                                            onConfirm: () => {
                                                router.post(
                                                    `/customer-confirmations/${groupId}/sync-billing`,
                                                    {},
                                                    {
                                                        preserveScroll: true,
                                                        preserveState: false,
                                                        onSuccess: () => {
                                                            toast.success(
                                                                'Billing sync completed successfully.',
                                                            );
                                                        },
                                                        onError: () => {
                                                            toast.error(
                                                                'Failed to sync billing data.',
                                                            );
                                                        },
                                                    },
                                                );
                                            },
                                        });
                                    } else if (action === 'delete') {
                                        confirm({
                                            title: 'Delete Customer Confirmation',
                                            message: `Delete confirmation #${groupId}? This is allowed only when all members are cancelled.`,
                                            confirmText: 'Delete',
                                            cancelText: 'Cancel',
                                            onConfirm: () => {
                                                router.delete(
                                                    destroyConfirmedCustomer(
                                                        groupId,
                                                    ).url,
                                                );
                                            },
                                        });
                                    }
                                }
                            }}
                            onRowDoubleClick={(row) => {
                                if (!row.id) {
                                    return;
                                }

                                if (isReadOnlyIndex) {
                                    handleOpenGroupDialog(row.id, 'view');

                                    return;
                                }

                                const hasActiveMembers = row.members.some(
                                    (member) => member.status !== 'cancelled',
                                );

                                if (
                                    userPermissions.includes('customer edit') &&
                                    hasActiveMembers
                                ) {
                                    handleOpenGroupDialog(row.id, 'edit');

                                    return;
                                }

                                handleOpenGroupDialog(row.id, 'view');
                            }}
                            initialState={{
                                columnVisibility: {
                                    id: false,
                                    customer_name: true,
                                    customer_number: false,
                                    enquiry_email: false,
                                    enquiry_contact: false,
                                    member_count: true,
                                    package_name: true,
                                    package_country: isSuperadmin,
                                    paid_amount: !isCancelledIndex,
                                    refunded_paid_summary: isCancelledIndex,
                                    enquiry_type: true,
                                    enquiry_status: false,
                                    quoted_member_count: false,
                                    can_create_quotation: false,
                                    date_of_application: false,
                                    created_at: false,
                                    members_search_blob: false,
                                },
                                pagination: {
                                    pageIndex: 0,
                                    pageSize: 'all',
                                },
                            }}
                            renderFilter={(table) => (
                                <>
                                    <ColumnFilter
                                        table={table}
                                        columnId="enquiry_type"
                                        title="Enquiry Type"
                                        options={[
                                            {
                                                label: 'General',
                                                value: 'General',
                                            },
                                            {
                                                label: 'Private',
                                                value: 'Private',
                                            },
                                        ]}
                                    />
                                    {isSuperadmin &&
                                        packageCountryFilterOptions.length >
                                            0 && (
                                            <ColumnFilter
                                                table={table}
                                                columnId="package_country"
                                                title="Country"
                                                options={
                                                    packageCountryFilterOptions
                                                }
                                            />
                                        )}
                                </>
                            )}
                        />
                    </div>
                </div>
            </AppLayout>

            <Dialog open={groupDialogOpen} onOpenChange={setGroupDialogOpen}>
                <DialogContent
                    className="flex max-h-[95%] max-w-[95%] min-w-[95%] flex-col"
                    onOpenAutoFocus={focusFirstDialogFormField}
                    onKeyDown={handleDialogTabKey}
                >
                    <DialogHeader className="gap-0">
                        <DialogTitle className="text-xl">
                            {groupDialogMode === 'create'
                                ? 'Create Customer Confirmation'
                                : groupDialogMode === 'view'
                                  ? 'View Customer Confirmation'
                                  : 'Edit Customer Confirmation'}
                        </DialogTitle>
                        <DialogDescription>
                            {groupDialogMode === 'create'
                                ? 'Create customer confirmation details.'
                                : groupDialogMode === 'view'
                                  ? 'View customer confirmation details.'
                                  : 'Edit customer confirmation details.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="h-full w-full flex-1 overflow-y-auto pb-2">
                        {isLoadingGroup && (
                            <div className="flex h-full items-center justify-center text-muted-foreground">
                                Loading customer confirmation details...
                            </div>
                        )}
                        {!isLoadingGroup && groupDialogMode === 'create' && (
                            <CustomerConfirmationForm
                                mode="create"
                                packageOptions={packageOptions}
                                countryOptions={countryOptions}
                                branchOptions={branchOptions}
                                onSuccess={() => {
                                    setGroupDialogOpen(false);
                                    router.reload();
                                }}
                                onCancel={() => setGroupDialogOpen(false)}
                            />
                        )}
                        {!isLoadingGroup && groupDialogData && (
                            <CustomerConfirmationForm
                                mode={groupDialogMode}
                                packageOptions={packageOptions}
                                countryOptions={countryOptions}
                                branchOptions={branchOptions}
                                initialData={groupDialogData}
                                onSuccess={() => {
                                    setGroupDialogOpen(false);
                                    router.reload();
                                }}
                                onCancel={() => setGroupDialogOpen(false)}
                            />
                        )}
                        {!isLoadingGroup &&
                            groupDialogMode !== 'create' &&
                            !groupDialogData && (
                                <div className="flex h-full items-center justify-center text-muted-foreground">
                                    Failed to load customer confirmation
                                    details.
                                </div>
                            )}
                    </div>
                </DialogContent>
            </Dialog>

            <Dialog
                open={publicLinkDialogOpen}
                onOpenChange={setPublicLinkDialogOpen}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="text-xl">
                            Copy Public Edit Link
                        </DialogTitle>
                        <DialogDescription>
                            Choose which link type you want to copy for this
                            customer confirmation.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-3">
                        {enabledPublicEditLinkTypes.map((linkType) => (
                            <Button
                                key={linkType}
                                type="button"
                                variant={
                                    linkType === fallbackPublicEditLinkType
                                        ? 'default'
                                        : 'outline'
                                }
                                onClick={() =>
                                    handleCopyPublicEditLink(linkType)
                                }
                            >
                                Copy{' '}
                                {
                                    customerConfirmationPublicEditLinkLabels[
                                        linkType
                                    ]
                                }
                            </Button>
                        ))}
                    </div>
                </DialogContent>
            </Dialog>

            <Dialog open={moveDialogOpen} onOpenChange={setMoveDialogOpen}>
                <DialogContent
                    className="flex max-h-[95%] max-w-[95%] flex-col"
                    onKeyDown={handleDialogTabKey}
                >
                    <DialogHeader>
                        <DialogTitle>
                            {isHoldingIndex
                                ? 'Move Members to Confirmed Customer'
                                : 'Move Members to Holding'}
                        </DialogTitle>
                        <DialogDescription>
                            {isHoldingIndex
                                ? 'Select members to move out of holding and assign them to a confirmed customer package.'
                                : 'Select members to cancel from this confirmation and move into a new holding confirmation.'}
                        </DialogDescription>
                    </DialogHeader>

                    {selectedMoveGroup && (
                        <div className="space-y-4">
                            <FormField
                                label={
                                    isHoldingIndex
                                        ? 'Target Package'
                                        : 'Target Package (Optional)'
                                }
                                error={
                                    isHoldingIndex && moveDialogError
                                        ? moveDialogError
                                        : undefined
                                }
                            >
                                <ProperInputSelect
                                    options={targetPackageOptions}
                                    value={targetPackageId ?? ''}
                                    onValueChange={(value) => {
                                        setMoveDialogError(null);

                                        if (!value) {
                                            setTargetPackageId(null);

                                            return;
                                        }

                                        setTargetPackageId(Number(value));
                                    }}
                                    placeholder={
                                        isHoldingIndex
                                            ? 'Select a package to confirm these members'
                                            : 'Leave empty to discuss package later'
                                    }
                                />
                            </FormField>

                            <div className="space-y-2 rounded-md border p-3">
                                {selectedMoveGroup.members.map((member) => {
                                    const disabled =
                                        member.status === 'cancelled';
                                    const checked =
                                        selectedMoveMemberIds.includes(
                                            member.id,
                                        );

                                    return (
                                        <label
                                            key={member.id}
                                            className="flex items-center justify-between gap-2 rounded px-2 py-1"
                                        >
                                            <div className="flex items-center gap-2">
                                                <Checkbox
                                                    checked={checked}
                                                    disabled={disabled}
                                                    onCheckedChange={(
                                                        value,
                                                    ) => {
                                                        if (!value) {
                                                            setSelectedMoveMemberIds(
                                                                (prev) =>
                                                                    prev.filter(
                                                                        (id) =>
                                                                            id !==
                                                                            member.id,
                                                                    ),
                                                            );

                                                            return;
                                                        }

                                                        setSelectedMoveMemberIds(
                                                            (prev) =>
                                                                Array.from(
                                                                    new Set([
                                                                        ...prev,
                                                                        member.id,
                                                                    ]),
                                                                ),
                                                        );
                                                    }}
                                                />
                                                <span>{member.name}</span>
                                            </div>

                                            <Badge variant="outline">
                                                {confirmationMemberStatusLabels[
                                                    member.status ??
                                                        'pending_payment'
                                                ] ?? member.status}
                                            </Badge>
                                        </label>
                                    );
                                })}
                            </div>

                            <div className="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setMoveDialogOpen(false)}
                                    disabled={movingMembers}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="button"
                                    onClick={submitMoveMembers}
                                    disabled={movingMembers}
                                >
                                    Move Selected Members
                                </Button>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog
                open={combineQuotationOpen}
                onOpenChange={setCombineQuotationOpen}
            >
                <DialogContent
                    className="flex max-h-[95%] max-w-[95%] flex-col"
                    onKeyDown={handleDialogTabKey}
                >
                    <DialogHeader>
                        <DialogTitle>Combine Quotations</DialogTitle>
                        <DialogDescription>
                            Move members from other quotations into one target
                            quotation. Their items, invoices, and receipts move
                            with them; quotations left empty are removed.
                        </DialogDescription>
                    </DialogHeader>

                    {combineQuotationGroup && (
                        <div className="h-full w-full flex-1 space-y-4 overflow-y-auto pb-2">
                            {combineQuotationError && (
                                <Alert variant="destructive">
                                    <AlertDescription>
                                        {combineQuotationError}
                                    </AlertDescription>
                                </Alert>
                            )}

                            <FormField label="Target Quotation">
                                <ProperInputSelect
                                    options={(
                                        combineQuotationGroup.quotations ?? []
                                    ).map((quotation) => ({
                                        value: String(quotation.id),
                                        label: `${quotation.number ?? `#${quotation.id}`} — ${quotation.payer_name} — ${quotation.member_count} member(s)`,
                                    }))}
                                    value={
                                        combineQuotationTargetId
                                            ? String(combineQuotationTargetId)
                                            : ''
                                    }
                                    onValueChange={(value) => {
                                        setCombineQuotationError(null);
                                        const nextId = value
                                            ? Number(value)
                                            : null;
                                        setCombineQuotationTargetId(nextId);
                                        setCombineQuotationMemberIds((prev) => {
                                            const targetQuotation = (
                                                combineQuotationGroup.quotations ??
                                                []
                                            ).find(
                                                (quotation) =>
                                                    quotation.id === nextId,
                                            );

                                            return prev.filter(
                                                (memberId) =>
                                                    !(
                                                        targetQuotation?.member_ids ??
                                                        []
                                                    ).includes(memberId),
                                            );
                                        });
                                    }}
                                    placeholder="Select the quotation to keep"
                                    searchable={false}
                                    disabled={combiningQuotation}
                                />
                            </FormField>

                            <div className="space-y-3">
                                <p className="text-sm text-muted-foreground">
                                    Select members to move into the target
                                    quotation:
                                </p>

                                {(combineQuotationGroup.quotations ?? [])
                                    .filter(
                                        (quotation) =>
                                            quotation.id !==
                                            combineQuotationTargetId,
                                    )
                                    .map((quotation) => (
                                        <div
                                            key={quotation.id}
                                            className="space-y-2 rounded-md border p-3"
                                        >
                                            <div className="flex items-center justify-between gap-2">
                                                <span className="text-sm font-medium">
                                                    {quotation.number ??
                                                        `#${quotation.id}`}{' '}
                                                    — {quotation.payer_name}
                                                </span>
                                                <Badge variant="outline">
                                                    {quotation.status_label}
                                                </Badge>
                                            </div>

                                            {quotation.member_ids.length ===
                                            0 ? (
                                                <p className="text-xs text-muted-foreground">
                                                    No members linked to this
                                                    quotation.
                                                </p>
                                            ) : (
                                                quotation.member_ids.map(
                                                    (memberId, index) => {
                                                        const checked =
                                                            combineQuotationMemberIds.includes(
                                                                memberId,
                                                            );

                                                        return (
                                                            <label
                                                                key={memberId}
                                                                className="flex items-center gap-2 rounded px-2 py-1"
                                                            >
                                                                <Checkbox
                                                                    checked={
                                                                        checked
                                                                    }
                                                                    disabled={
                                                                        combiningQuotation
                                                                    }
                                                                    onCheckedChange={(
                                                                        value,
                                                                    ) => {
                                                                        setCombineQuotationError(
                                                                            null,
                                                                        );

                                                                        if (
                                                                            !value
                                                                        ) {
                                                                            setCombineQuotationMemberIds(
                                                                                (
                                                                                    prev,
                                                                                ) =>
                                                                                    prev.filter(
                                                                                        (
                                                                                            id,
                                                                                        ) =>
                                                                                            id !==
                                                                                            memberId,
                                                                                    ),
                                                                            );

                                                                            return;
                                                                        }

                                                                        setCombineQuotationMemberIds(
                                                                            (
                                                                                prev,
                                                                            ) =>
                                                                                Array.from(
                                                                                    new Set(
                                                                                        [
                                                                                            ...prev,
                                                                                            memberId,
                                                                                        ],
                                                                                    ),
                                                                                ),
                                                                        );
                                                                    }}
                                                                />
                                                                <span className="text-sm">
                                                                    {quotation
                                                                        .member_names[
                                                                        index
                                                                    ] ??
                                                                        `Member #${memberId}`}
                                                                </span>
                                                            </label>
                                                        );
                                                    },
                                                )
                                            )}
                                        </div>
                                    ))}
                            </div>

                            <div className="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        setCombineQuotationOpen(false)
                                    }
                                    disabled={combiningQuotation}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="button"
                                    onClick={submitCombineQuotation}
                                    disabled={combiningQuotation}
                                >
                                    {combiningQuotation
                                        ? 'Combining...'
                                        : 'Combine Quotations'}
                                </Button>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog
                open={combineConfirmationOpen}
                onOpenChange={setCombineConfirmationOpen}
            >
                <DialogContent
                    className="flex max-h-[95%] max-w-[95%] flex-col"
                    onKeyDown={handleDialogTabKey}
                >
                    <DialogHeader>
                        <DialogTitle>Combine Confirmations</DialogTitle>
                        <DialogDescription>
                            Move members into another confirmation in the same
                            package. Their quotations and payments move with
                            them; the emptied confirmation is removed.
                        </DialogDescription>
                    </DialogHeader>

                    {combineConfirmationGroup && (
                        <div className="h-full w-full flex-1 space-y-4 overflow-y-auto pb-2">
                            {combineConfirmationError && (
                                <Alert variant="destructive">
                                    <AlertDescription>
                                        {combineConfirmationError}
                                    </AlertDescription>
                                </Alert>
                            )}

                            <FormField label="Combine Into">
                                <ProperInputSelect
                                    options={dataGroups
                                        .filter(
                                            (candidate) =>
                                                candidate.id !==
                                                    combineConfirmationGroup.id &&
                                                candidate.package_id != null &&
                                                candidate.package_id ===
                                                    combineConfirmationGroup.package_id,
                                        )
                                        .map((candidate) => {
                                            const leader =
                                                candidate.members.find(
                                                    (member) =>
                                                        member.is_leader,
                                                ) ?? candidate.members[0];

                                            return {
                                                value: String(candidate.id),
                                                label: `${candidate.number ?? `#${candidate.id}`} — ${leader?.name ?? '-'} — ${candidate.active_member_count} member(s)`,
                                            };
                                        })}
                                    value={
                                        combineConfirmationTargetCcId
                                            ? String(
                                                  combineConfirmationTargetCcId,
                                              )
                                            : ''
                                    }
                                    onValueChange={(value) => {
                                        setCombineConfirmationError(null);
                                        setCombineConfirmationTargetCcId(
                                            value ? Number(value) : null,
                                        );
                                        setCombineConfirmationTargetQuotationId(
                                            null,
                                        );
                                    }}
                                    placeholder="Select the destination confirmation"
                                    disabled={combiningConfirmation}
                                />
                            </FormField>

                            <div className="space-y-2 rounded-md border p-3">
                                <p className="text-sm text-muted-foreground">
                                    Members to move:
                                </p>
                                {combineConfirmationGroup.members.map(
                                    (member) => {
                                        const disabled =
                                            member.status === 'cancelled' ||
                                            combiningConfirmation;
                                        const checked =
                                            combineConfirmationMemberIds.includes(
                                                member.id,
                                            );

                                        return (
                                            <label
                                                key={member.id}
                                                className="flex items-center justify-between gap-2 rounded px-2 py-1"
                                            >
                                                <div className="flex items-center gap-2">
                                                    <Checkbox
                                                        checked={checked}
                                                        disabled={disabled}
                                                        onCheckedChange={(
                                                            value,
                                                        ) => {
                                                            setCombineConfirmationError(
                                                                null,
                                                            );

                                                            if (!value) {
                                                                setCombineConfirmationMemberIds(
                                                                    (prev) =>
                                                                        prev.filter(
                                                                            (
                                                                                id,
                                                                            ) =>
                                                                                id !==
                                                                                member.id,
                                                                        ),
                                                                );

                                                                return;
                                                            }

                                                            setCombineConfirmationMemberIds(
                                                                (prev) =>
                                                                    Array.from(
                                                                        new Set(
                                                                            [
                                                                                ...prev,
                                                                                member.id,
                                                                            ],
                                                                        ),
                                                                    ),
                                                            );
                                                        }}
                                                    />
                                                    <span>{member.name}</span>
                                                </div>

                                                <Badge variant="outline">
                                                    {confirmationMemberStatusLabels[
                                                        member.status ??
                                                            'pending_payment'
                                                    ] ?? member.status}
                                                </Badge>
                                            </label>
                                        );
                                    },
                                )}
                            </div>

                            <FormField label="Quotation Handling">
                                <ProperInputSelect
                                    options={[
                                        {
                                            value: 'keep',
                                            label: 'Keep their quotations (move as-is)',
                                        },
                                        {
                                            value: 'merge',
                                            label: 'Merge moved members into one quotation',
                                        },
                                    ]}
                                    value={combineConfirmationMode}
                                    onValueChange={(value) => {
                                        setCombineConfirmationError(null);
                                        setCombineConfirmationMode(
                                            value === 'merge'
                                                ? 'merge'
                                                : 'keep',
                                        );

                                        if (value !== 'merge') {
                                            setCombineConfirmationTargetQuotationId(
                                                null,
                                            );
                                        }
                                    }}
                                    searchable={false}
                                    disabled={combiningConfirmation}
                                />
                            </FormField>

                            {combineConfirmationMode === 'merge' && (
                                <FormField label="Merge Into Quotation">
                                    <ProperInputSelect
                                        options={(
                                            dataGroups.find(
                                                (candidate) =>
                                                    candidate.id ===
                                                    combineConfirmationTargetCcId,
                                            )?.quotations ?? []
                                        ).map((quotation) => ({
                                            value: String(quotation.id),
                                            label: `${quotation.number ?? `#${quotation.id}`} — ${quotation.payer_name} — ${quotation.member_count} member(s)`,
                                        }))}
                                        value={
                                            combineConfirmationTargetQuotationId
                                                ? String(
                                                      combineConfirmationTargetQuotationId,
                                                  )
                                                : ''
                                        }
                                        onValueChange={(value) => {
                                            setCombineConfirmationError(null);
                                            setCombineConfirmationTargetQuotationId(
                                                value ? Number(value) : null,
                                            );
                                        }}
                                        placeholder="Select the quotation to merge into"
                                        searchable={false}
                                        disabled={combiningConfirmation}
                                    />
                                </FormField>
                            )}

                            <div className="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        setCombineConfirmationOpen(false)
                                    }
                                    disabled={combiningConfirmation}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="button"
                                    onClick={submitCombineConfirmation}
                                    disabled={combiningConfirmation}
                                >
                                    {combiningConfirmation
                                        ? 'Combining...'
                                        : 'Combine Confirmations'}
                                </Button>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog open={memberDialogOpen} onOpenChange={setMemberDialogOpen}>
                <DialogContent
                    className="flex max-h-[95%] max-w-[95%] min-w-[95%] flex-col"
                    onKeyDown={handleDialogTabKey}
                >
                    <DialogHeader className="gap-0">
                        <DialogTitle className="text-xl">
                            {memberDialogMode === 'view'
                                ? 'View Customer Detail'
                                : 'Edit Customer Detail'}
                        </DialogTitle>
                        <DialogDescription>
                            {memberDialogMode === 'view'
                                ? 'View customer detail.'
                                : 'Edit customer detail.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="h-full w-full flex-1 overflow-y-auto pb-2">
                        {memberDialogData && (
                            <div className="space-y-4">
                                <Card>
                                    <CardHeader className="gap-0">
                                        <CardTitle className="text-xl">
                                            Customer Confirmation Information
                                        </CardTitle>
                                        <CardDescription>
                                            Customer confirmation details
                                            related to this member.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <ConfirmedCustomerFormFields
                                            customer={memberDialogData}
                                            isView={isMemberView}
                                            processing={isSavingMember}
                                            sharingPlanSelectOptions={
                                                memberSharingPlanOptions
                                            }
                                            getError={(path) =>
                                                memberDialogErrors[path]
                                            }
                                            onUpdateCustomer={updateMemberDraft}
                                        />
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader className="gap-0">
                                        <CardTitle className="text-xl">
                                            Customer Information
                                        </CardTitle>
                                        <CardDescription>
                                            Personal details of the customer
                                            member.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <CustomerFormFields
                                            customer={memberDialogData}
                                            isView={isMemberView}
                                            processing={isSavingMember}
                                            getError={(path) =>
                                                memberDialogErrors[path]
                                            }
                                            onUpdateCustomer={updateMemberDraft}
                                        />
                                    </CardContent>
                                </Card>

                                <div className="flex justify-end gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            setMemberDialogOpen(false)
                                        }
                                        disabled={isSavingMember}
                                    >
                                        Close
                                    </Button>
                                    {!isMemberView && (
                                        <Button
                                            type="button"
                                            onClick={submitMemberUpdate}
                                            disabled={isSavingMember}
                                        >
                                            Save
                                        </Button>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>

            <Dialog open={refundDialogOpen} onOpenChange={setRefundDialogOpen}>
                <DialogContent
                    className="flex max-h-[95%] max-w-[95%] flex-col md:min-w-3xl"
                    onKeyDown={handleDialogTabKey}
                >
                    <DialogHeader className="gap-0">
                        <DialogTitle className="text-xl">
                            Create Refund Receipt
                        </DialogTitle>
                        <DialogDescription>
                            Choose refund purpose first, then set refund amount
                            per selected member. Payment method uses payment
                            method master options.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="h-full w-full flex-1 overflow-y-auto pb-2">
                        {refundGroup &&
                            (() => {
                                const selectedRows = refundRows.filter(
                                    (row) => row.selected,
                                );

                                return (
                                    <div className="space-y-4">
                                        <Alert>
                                            <AlertDescription className="space-y-1 text-base">
                                                <p>
                                                    Refund purpose applies to
                                                    all selected members in this
                                                    submission.
                                                </p>
                                                <p>
                                                    Cancel refund will set
                                                    selected member status to
                                                    Trip Cancelled after receipt
                                                    is created.
                                                </p>
                                                <p>
                                                    Overpaid refund keeps member
                                                    status active and limits
                                                    refund amount to overpaid
                                                    balance.
                                                </p>
                                            </AlertDescription>
                                        </Alert>

                                        <div className="space-y-2 rounded-md border p-3">
                                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                                <FormField
                                                    label="Refund Purpose"
                                                    fieldRequirementsProps={{
                                                        required: true,
                                                        hint: 'Select the purpose of the refund (Cancellation or Overpayment Refund)',
                                                        example: 'Cancellation',
                                                    }}
                                                >
                                                    <ProperInputSelect
                                                        options={[
                                                            {
                                                                label: REFUND_PURPOSE_LABELS.cancel,
                                                                value: 'cancel',
                                                            },
                                                            {
                                                                label: REFUND_PURPOSE_LABELS.overpaid,
                                                                value: 'overpaid',
                                                            },
                                                        ]}
                                                        value={refundType}
                                                        onValueChange={(
                                                            value,
                                                        ) => {
                                                            const nextType =
                                                                (value as RefundType) ||
                                                                'cancel';
                                                            updateRefundType(
                                                                nextType,
                                                            );
                                                        }}
                                                        disabled={
                                                            isSubmittingRefund
                                                        }
                                                        searchable={false}
                                                    />
                                                </FormField>
                                            </div>

                                            <p className="text-base text-muted-foreground">
                                                Selected Members:{' '}
                                                {selectedRows.length} /{' '}
                                                {refundRows.length}
                                            </p>
                                        </div>

                                        <div className="space-y-3 rounded-md border p-3">
                                            {refundRows.map((row) => {
                                                const isSingleRow =
                                                    refundRows.length === 1;
                                                const computedAmount =
                                                    computeRefundAmount(
                                                        row,
                                                        refundType,
                                                    );
                                                const baseAmount =
                                                    getRefundBaseAmount(
                                                        row,
                                                        refundType,
                                                    );
                                                const isOverpaidEligible =
                                                    resolveOverpaidAmount(row) >
                                                    0;
                                                const disableSelection =
                                                    refundType === 'overpaid' &&
                                                    !isOverpaidEligible;
                                                const overpaidAmount =
                                                    resolveOverpaidAmount(row);

                                                return (
                                                    <div
                                                        key={row.member_id}
                                                        className="space-y-3 rounded-md border p-3"
                                                    >
                                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                                            <div className="flex items-center gap-2">
                                                                {!isSingleRow && (
                                                                    <Checkbox
                                                                        checked={
                                                                            row.selected
                                                                        }
                                                                        disabled={
                                                                            disableSelection ||
                                                                            isSubmittingRefund
                                                                        }
                                                                        onCheckedChange={(
                                                                            value,
                                                                        ) => {
                                                                            updateRefundRow(
                                                                                row.member_id,
                                                                                (
                                                                                    prev,
                                                                                ) => ({
                                                                                    ...prev,
                                                                                    selected:
                                                                                        Boolean(
                                                                                            value,
                                                                                        ),
                                                                                }),
                                                                            );
                                                                        }}
                                                                    />
                                                                )}
                                                                <span className="font-medium">
                                                                    {
                                                                        row.member_name
                                                                    }
                                                                </span>
                                                            </div>

                                                            <div className="flex flex-wrap items-center gap-2">
                                                                <Badge className="bg-emerald-100 px-3 py-1 text-base text-emerald-800">
                                                                    Paid:{' '}
                                                                    {formatCurrency(
                                                                        row.paid_amount,
                                                                    )}
                                                                </Badge>
                                                                <Badge className="bg-amber-100 px-3 py-1 text-base text-amber-800">
                                                                    Need to Pay:{' '}
                                                                    {formatCurrency(
                                                                        row.total_amount,
                                                                    )}
                                                                </Badge>
                                                                <Badge className="bg-sky-100 px-3 py-1 text-base text-sky-800">
                                                                    Overpaid:{' '}
                                                                    {formatCurrency(
                                                                        overpaidAmount,
                                                                    )}
                                                                </Badge>
                                                            </div>
                                                        </div>

                                                        {disableSelection && (
                                                            <p className="text-sm text-muted-foreground">
                                                                Not eligible for
                                                                overpaid refund.
                                                            </p>
                                                        )}

                                                        <div className="grid grid-cols-1 items-start gap-3 md:grid-cols-2">
                                                            <FormField
                                                                label="Refund Mode"
                                                                fieldRequirementsProps={{
                                                                    required: true,
                                                                    hint: 'Select whether the refund is calculated by percentage or a fixed amount',
                                                                    example: 'Fixed Amount',
                                                                }}
                                                            >
                                                                <ProperInputSelect
                                                                    options={[
                                                                        {
                                                                            label: 'Percentage',
                                                                            value: 'percentage',
                                                                        },
                                                                        {
                                                                            label: 'Fixed Amount',
                                                                            value: 'fixed',
                                                                        },
                                                                    ]}
                                                                    value={
                                                                        row.mode
                                                                    }
                                                                    onValueChange={(
                                                                        value,
                                                                    ) => {
                                                                        const nextMode =
                                                                            (value as RefundMode) ||
                                                                            'fixed';

                                                                        updateRefundRow(
                                                                            row.member_id,
                                                                            (
                                                                                prev,
                                                                            ) => ({
                                                                                ...prev,
                                                                                mode: nextMode,
                                                                                percentage:
                                                                                    nextMode ===
                                                                                    'percentage'
                                                                                        ? prev.percentage
                                                                                        : '',
                                                                                amount:
                                                                                    nextMode ===
                                                                                    'fixed'
                                                                                        ? prev.amount
                                                                                        : '',
                                                                            }),
                                                                        );
                                                                    }}
                                                                    disabled={
                                                                        !row.selected ||
                                                                        disableSelection ||
                                                                        isSubmittingRefund
                                                                    }
                                                                    searchable={
                                                                        false
                                                                    }
                                                                />
                                                            </FormField>

                                                            {row.mode ===
                                                                'percentage' && (
                                                                <FormField
                                                                    label="Percentage (%)"
                                                                    fieldRequirementsProps={{
                                                                        required: true,
                                                                        hint: 'Specify the refund percentage',
                                                                        example: '50',
                                                                        format: 'Decimal number between 0 and 100',
                                                                    }}
                                                                >
                                                                    <ProperInput
                                                                        value={
                                                                            row.percentage
                                                                        }
                                                                        onCommit={(
                                                                            value,
                                                                        ) => {
                                                                            updateRefundRow(
                                                                                row.member_id,
                                                                                (
                                                                                    prev,
                                                                                ) => ({
                                                                                    ...prev,
                                                                                    percentage:
                                                                                        value,
                                                                                }),
                                                                            );
                                                                        }}
                                                                        placeholder="Enter percentage"
                                                                        disabled={
                                                                            !row.selected ||
                                                                            disableSelection ||
                                                                            isSubmittingRefund
                                                                        }
                                                                        type="number"
                                                                        inputProps={{
                                                                            min: 0,
                                                                            max: 100,
                                                                            step: '0.01',
                                                                        }}
                                                                    />
                                                                </FormField>
                                                            )}

                                                            <FormField
                                                                label={`Amount (Max ${formatCurrency(baseAmount)})`}
                                                                fieldRequirementsProps={{
                                                                    required: true,
                                                                    hint: 'Specify the refund amount (calculated automatically if using Percentage mode)',
                                                                    example: '250.00',
                                                                    format: 'Decimal number up to max base amount',
                                                                }}
                                                            >
                                                                <ProperInput
                                                                    value={
                                                                        row.mode ===
                                                                        'percentage'
                                                                            ? computedAmount ===
                                                                              null
                                                                                ? ''
                                                                                : computedAmount.toFixed(
                                                                                      2,
                                                                                  )
                                                                            : row.amount
                                                                    }
                                                                    onCommit={(
                                                                        value,
                                                                    ) => {
                                                                        if (
                                                                            row.mode ===
                                                                            'percentage'
                                                                        ) {
                                                                            return;
                                                                        }

                                                                        updateRefundRow(
                                                                            row.member_id,
                                                                            (
                                                                                prev,
                                                                            ) => ({
                                                                                ...prev,
                                                                                amount: value,
                                                                            }),
                                                                        );
                                                                    }}
                                                                    placeholder="Enter amount"
                                                                    disabled={
                                                                        !row.selected ||
                                                                        disableSelection ||
                                                                        isSubmittingRefund ||
                                                                        row.mode ===
                                                                            'percentage'
                                                                    }
                                                                    type="number"
                                                                    inputProps={{
                                                                        min: 0,
                                                                        max: baseAmount,
                                                                        step: '0.01',
                                                                    }}
                                                                />
                                                            </FormField>

                                                            <FormField
                                                                label="Payment Method"
                                                                fieldRequirementsProps={{
                                                                    required: true,
                                                                    hint: 'Select the payment method used for issuing the refund',
                                                                    example: 'Bank Transfer',
                                                                }}
                                                            >
                                                                <ProperInputSelect
                                                                    options={
                                                                        paymentMethods
                                                                    }
                                                                    value={
                                                                        row.payment_method
                                                                    }
                                                                    onValueChange={(
                                                                        value,
                                                                    ) => {
                                                                        updateRefundRow(
                                                                            row.member_id,
                                                                            (
                                                                                prev,
                                                                            ) => ({
                                                                                ...prev,
                                                                                payment_method:
                                                                                    String(
                                                                                        value,
                                                                                    ),
                                                                            }),
                                                                        );
                                                                    }}
                                                                    disabled={
                                                                        !row.selected ||
                                                                        disableSelection ||
                                                                        isSubmittingRefund
                                                                    }
                                                                />
                                                            </FormField>

                                                            <FormField
                                                                label="Refund To"
                                                                fieldRequirementsProps={{
                                                                    required: false,
                                                                    hint: 'Recipient contact details or account info for the refund receipt (defaults to customer contact number)',
                                                                    example: '08123456789',
                                                                    format: 'Up to 255 characters',
                                                                }}
                                                            >
                                                                <ProperInput
                                                                    value={
                                                                        row.refund_to
                                                                    }
                                                                    onCommit={(
                                                                        value,
                                                                    ) => {
                                                                        updateRefundRow(
                                                                            row.member_id,
                                                                            (
                                                                                prev,
                                                                            ) => ({
                                                                                ...prev,
                                                                                refund_to:
                                                                                    value,
                                                                            }),
                                                                        );
                                                                    }}
                                                                    placeholder="Refund to contact/info"
                                                                    disabled={
                                                                        !row.selected ||
                                                                        disableSelection ||
                                                                        isSubmittingRefund
                                                                    }
                                                                />
                                                            </FormField>

                                                            <FormField
                                                                label="Description"
                                                                fieldRequirementsProps={{
                                                                    required: false,
                                                                    hint: 'Additional notes or description for this refund',
                                                                    example: 'Refund for flight cancellation',
                                                                    format: 'Up to 1000 characters',
                                                                }}
                                                            >
                                                                <ProperInput
                                                                    value={
                                                                        row.description
                                                                    }
                                                                    onCommit={(
                                                                        value,
                                                                    ) => {
                                                                        updateRefundRow(
                                                                            row.member_id,
                                                                            (
                                                                                prev,
                                                                            ) => ({
                                                                                ...prev,
                                                                                description:
                                                                                    value,
                                                                            }),
                                                                        );
                                                                    }}
                                                                    placeholder="Enter description"
                                                                    disabled={
                                                                        !row.selected ||
                                                                        disableSelection ||
                                                                        isSubmittingRefund
                                                                    }
                                                                    textarea
                                                                />
                                                            </FormField>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>

                                        <div className="flex justify-end gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() =>
                                                    setRefundDialogOpen(false)
                                                }
                                                disabled={isSubmittingRefund}
                                            >
                                                Cancel
                                            </Button>
                                            <Button
                                                type="button"
                                                onClick={submitRefunds}
                                                disabled={isSubmittingRefund}
                                            >
                                                {isSubmittingRefund
                                                    ? 'Creating...'
                                                    : 'Create Refund Receipt'}
                                            </Button>
                                        </div>
                                    </div>
                                );
                            })()}
                    </div>
                </DialogContent>
            </Dialog>

            <Dialog
                open={quotationDialogOpen}
                onOpenChange={setQuotationDialogOpen}
            >
                <DialogContent
                    className="flex max-h-[95%] max-w-[95%] flex-col md:min-w-3xl"
                    onKeyDown={handleDialogTabKey}
                >
                    <DialogHeader className="items-start gap-0 text-left">
                        <DialogTitle className="text-xl">
                            Create Quotation
                        </DialogTitle>
                        <DialogDescription>
                            Assign payment responsibility before generating
                            quotation. Each row is a member to be quoted, and
                            Payer determines who will be billed for that member.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="h-full w-full flex-1 overflow-y-auto pb-2">
                        {quotationGroup && (
                            <div className="space-y-4">
                                {quotationGenerationError && (
                                    <Alert variant="destructive">
                                        <AlertDescription>
                                            {quotationGenerationError}
                                        </AlertDescription>
                                    </Alert>
                                )}
                                {(() => {
                                    const activeMembers =
                                        quotationGroup.members.filter(
                                            (member) =>
                                                member.status !== 'cancelled' &&
                                                !member.has_quotation,
                                        );
                                    const payerOptions = activeMembers.map(
                                        (member) => ({
                                            value: String(member.id),
                                            label: member.name,
                                        }),
                                    );
                                    const assignments = activeMembers.reduce<
                                        Record<
                                            number,
                                            {
                                                payerName: string;
                                                payerId: number;
                                                members: string[];
                                            }
                                        >
                                    >((accumulator, member) => {
                                        const payerId = payerMapping[member.id];

                                        if (!payerId) {
                                            return accumulator;
                                        }

                                        const payerMember =
                                            activeMembers.find(
                                                (candidate) =>
                                                    candidate.id === payerId,
                                            ) ?? member;

                                        if (!accumulator[payerId]) {
                                            accumulator[payerId] = {
                                                payerId,
                                                payerName: payerMember.name,
                                                members: [],
                                            };
                                        }

                                        accumulator[payerId].members.push(
                                            member.name,
                                        );

                                        return accumulator;
                                    }, {});
                                    const assignmentRows =
                                        Object.values(assignments);
                                    const totalToBeCreated =
                                        assignmentRows.length;

                                    return (
                                        <>
                                            <Card className="gap-3 py-3">
                                                <CardHeader className="gap-0">
                                                    <CardTitle className="text-lg">
                                                        Information
                                                    </CardTitle>
                                                    <CardDescription>
                                                        Overview of the selected
                                                        members and their
                                                        payment assignments.
                                                    </CardDescription>
                                                </CardHeader>
                                                <CardContent>
                                                    <table className="w-full">
                                                        <tbody className="space-y-2">
                                                            <tr className="grid grid-cols-1 items-start md:grid-cols-12">
                                                                <td className="font-medium md:col-span-3">
                                                                    Members to
                                                                    quote
                                                                </td>
                                                                <td className="md:col-span-9">
                                                                    Members
                                                                    without a
                                                                    quotation
                                                                    yet and not
                                                                    cancelled.
                                                                    Only these
                                                                    members will
                                                                    be included
                                                                    in quotation
                                                                    creation.
                                                                </td>
                                                            </tr>
                                                            <tr className="grid grid-cols-1 items-start md:grid-cols-12">
                                                                <td className="font-medium md:col-span-3">
                                                                    Payer
                                                                    assignment
                                                                </td>
                                                                <td className="md:col-span-9">
                                                                    Select who
                                                                    pays for
                                                                    each member.
                                                                    Empty payer
                                                                    means member
                                                                    is not
                                                                    included in
                                                                    quotation
                                                                    creation
                                                                    yet.
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </CardContent>
                                            </Card>

                                            <Card className="gap-3 py-3">
                                                <CardHeader className="gap-0">
                                                    <CardTitle className="text-lg">
                                                        Member Payer Assignment
                                                    </CardTitle>
                                                    <CardDescription>
                                                        Assign a payer to each
                                                        member.
                                                    </CardDescription>
                                                </CardHeader>
                                                <CardContent className="space-y-2">
                                                    <div className="overflow-x-auto">
                                                        <table className="w-full min-w-[640px] space-y-2 text-base">
                                                            <thead>
                                                                <tr className="border-b text-left">
                                                                    <th className="py-2 font-semibold text-foreground">
                                                                        Member
                                                                    </th>
                                                                    <th className="w-72 py-2 font-semibold text-foreground">
                                                                        Payer
                                                                    </th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                {activeMembers.map(
                                                                    (
                                                                        member,
                                                                    ) => (
                                                                        <tr
                                                                            key={
                                                                                member.id
                                                                            }
                                                                            className="border-b align-top last:border-0"
                                                                        >
                                                                            <td className="py-2 font-medium">
                                                                                {
                                                                                    member.name
                                                                                }
                                                                            </td>
                                                                            <td className="py-2">
                                                                                <ProperInputSelect
                                                                                    options={[
                                                                                        {
                                                                                            value: '',
                                                                                            label: 'Not selected',
                                                                                        },
                                                                                        ...payerOptions,
                                                                                    ]}
                                                                                    value={String(
                                                                                        payerMapping[
                                                                                            member
                                                                                                .id
                                                                                        ] ??
                                                                                            '',
                                                                                    )}
                                                                                    onValueChange={(
                                                                                        value,
                                                                                    ) => {
                                                                                        setPayerMapping(
                                                                                            (
                                                                                                prev,
                                                                                            ) => ({
                                                                                                ...prev,
                                                                                                [member.id]:
                                                                                                    value
                                                                                                        ? Number(
                                                                                                              value,
                                                                                                          )
                                                                                                        : null,
                                                                                            }),
                                                                                        );
                                                                                    }}
                                                                                    placeholder="Select payer"
                                                                                />
                                                                            </td>
                                                                        </tr>
                                                                    ),
                                                                )}
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </CardContent>
                                            </Card>

                                            <Card className="gap-3 py-3">
                                                <CardHeader className="gap-0">
                                                    <CardTitle className="text-lg">
                                                        Quotation Preview
                                                    </CardTitle>
                                                    <CardDescription>
                                                        {totalToBeCreated}{' '}
                                                        quotation(s) will be
                                                        created based on current
                                                        payer assignment.
                                                    </CardDescription>
                                                </CardHeader>
                                                <CardContent className="space-y-2">
                                                    <div className="overflow-x-auto">
                                                        <table className="w-full min-w-[640px] text-base">
                                                            <thead>
                                                                <tr className="border-b text-left">
                                                                    <th className="w-36 py-2 font-semibold text-foreground">
                                                                        Quotation
                                                                    </th>
                                                                    <th className="py-2 font-semibold text-foreground">
                                                                        Payer
                                                                    </th>
                                                                    <th className="py-2 font-semibold text-foreground">
                                                                        Members
                                                                        Covered
                                                                    </th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                {assignmentRows.map(
                                                                    (
                                                                        assignment,
                                                                        index,
                                                                    ) => (
                                                                        <tr
                                                                            key={`${assignment.payerId}-${index}`}
                                                                            className="border-b align-top last:border-0"
                                                                        >
                                                                            <td className="py-2 font-medium">
                                                                                Quotation
                                                                                #
                                                                                {index +
                                                                                    1}
                                                                            </td>
                                                                            <td className="py-2">
                                                                                {
                                                                                    assignment.payerName
                                                                                }
                                                                            </td>
                                                                            <td className="py-2">
                                                                                {assignment.members.join(
                                                                                    ', ',
                                                                                )}
                                                                            </td>
                                                                        </tr>
                                                                    ),
                                                                )}
                                                                {assignmentRows.length ===
                                                                    0 && (
                                                                    <tr>
                                                                        <td
                                                                            colSpan={
                                                                                3
                                                                            }
                                                                            className="px-2 py-4 text-center text-muted-foreground"
                                                                        >
                                                                            No
                                                                            quotation
                                                                            will
                                                                            be
                                                                            created
                                                                            until
                                                                            a
                                                                            payer
                                                                            is
                                                                            selected.
                                                                        </td>
                                                                    </tr>
                                                                )}
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </CardContent>
                                            </Card>
                                        </>
                                    );
                                })()}

                                <div className="flex justify-end gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            setQuotationDialogOpen(false)
                                        }
                                        disabled={isGeneratingQuotations}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="button"
                                        onClick={submitGenerateQuotations}
                                        disabled={isGeneratingQuotations}
                                    >
                                        {isGeneratingQuotations
                                            ? 'Creating...'
                                            : 'Create Quotation'}
                                    </Button>
                                </div>
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>

            <ConfirmDialog />
        </>
    );
}
