import { ActionType } from '@/components/action-column';
import { ColumnFilter } from '@/components/column-filter';
import useConfirmDialog from '@/components/confirm-popup';
import { DataTable } from '@/components/data-table';
import { FormField } from '@/components/form-field';
import { ProperInputSelect } from '@/components/proper-input-select';
import { createSelectColumn } from '@/components/select-column';
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
import AppLayout from '@/layouts/app-layout';
import {
    index as confirmedCustomerIndex,
    destroy as destroyConfirmedCustomer,
} from '@/routes/confirmed-customer';
import {
    generateEditLink,
    generateQuotations,
    show as showGroup,
} from '@/routes/customer-confirmations';
import { OptionType, SharedData, type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef, Row } from '@tanstack/react-table';
import { useState } from 'react';
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
    type CustomerMemberFormData,
} from '../customer/schema';
import { customerValidationSchema } from '../customer/validation';
import { statusColors, typeColors } from '../enquiries/schema';
import { sharingPlanOptions } from '../packages/schema';
import CustomerConfirmationForm from './form';

const formatCurrency = (value: number): string => {
    return new Intl.NumberFormat('en-SG', {
        style: 'currency',
        currency: 'SGD',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value || 0);
};

const groupColumns: ColumnDef<CustomerConfirmationDatatableSchema>[] = [
    createSelectColumn<CustomerConfirmationDatatableSchema>(),
    {
        accessorKey: 'number',
        header: 'CC No',
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
                <Badge
                    variant="outline"
                    className="rounded-full px-3 py-1 text-base"
                >
                    Active: {row.original.active_member_count}
                </Badge>
            </div>
        ),
    },
    {
        accessorKey: 'package_name',
        header: 'Package',
        meta: { exportable: true },
    },
    {
        accessorKey: 'date_of_application',
        header: 'Applied Date',
        meta: { exportable: true },
        filterFn: 'dateRangeFilter',
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
        accessorKey: 'created_at',
        header: 'Created At',
        meta: { exportable: true },
        filterFn: 'dateRangeFilter',
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
        header: 'Sharing Plan',
        meta: { exportable: true },
        cell: ({ row }) => {
            const sharingPlan = row.original.sharing_plan;

            if (!sharingPlan) {
                return <span className="text-muted-foreground">-</span>;
            }

            return (
                <Badge variant="outline">
                    {sharingPlan.charAt(0).toUpperCase() + sharingPlan.slice(1)}
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
        header: 'Status',
        meta: { exportable: true },
        cell: ({ row }) => {
            const status = row.original.status ?? 'draft';
            const statusColor =
                confirmationMemberStatusColors[status] ??
                'bg-gray-100 text-gray-800';
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
];

interface ConfirmedCustomerProps {
    dataGroups: CustomerConfirmationDatatableSchema[];
    packageOptions?: OptionType[];
    pageTitle?: string;
    indexUrl?: string;
}

export default function ConfirmedCustomerIndex({
    dataGroups,
    packageOptions = [],
    pageTitle = 'Confirmed Customers',
    indexUrl = confirmedCustomerIndex().url,
}: ConfirmedCustomerProps) {
    const { auth } = usePage<SharedData>().props;
    const userPermissions = auth.permissions || [];
    const { confirm, ConfirmDialog } = useConfirmDialog();

    const actions: ActionType[] = [];
    if (userPermissions.includes('customer create')) actions.push('add');
    if (userPermissions.includes('customer view')) actions.push('view');
    if (userPermissions.includes('customer edit')) actions.push('edit');
    if (userPermissions.includes('customer edit')) actions.push('delete');

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

    const [moveDialogOpen, setMoveDialogOpen] = useState(false);
    const [selectedMoveGroup, setSelectedMoveGroup] =
        useState<CustomerConfirmationDatatableSchema | null>(null);
    const [selectedMoveMemberIds, setSelectedMoveMemberIds] = useState<
        number[]
    >([]);
    const [targetPackageId, setTargetPackageId] = useState<number | null>(null);
    const [movingMembers, setMovingMembers] = useState(false);

    const [memberDialogOpen, setMemberDialogOpen] = useState(false);
    const [memberDialogMode, setMemberDialogMode] = useState<'view' | 'edit'>(
        'view',
    );
    const [memberDialogData, setMemberDialogData] =
        useState<CustomerMemberFormData | null>(null);
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
    const [payerMapping, setPayerMapping] = useState<Record<number, number>>(
        {},
    );
    const [isGeneratingQuotations, setIsGeneratingQuotations] = useState(false);

    const isMemberView = memberDialogMode === 'view';

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: pageTitle,
            href: indexUrl,
        },
    ];

    const buildSharingPlanOptions = (
        packageData:
            | {
                  price_single?: number | null;
                  price_double?: number | null;
                  price_triple?: number | null;
                  price_quad?: number | null;
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
        ]
            .filter((item) => item.price > 0)
            .map((item) => ({
                value: item.value,
                label: `${item.label} (${item.price.toFixed(2)})`,
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
        setMoveDialogOpen(true);
    };

    const submitMoveMembers = () => {
        if (!selectedMoveGroup || selectedMoveMemberIds.length === 0) {
            return;
        }

        setMovingMembers(true);

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
                    toast.success('Members moved to holding confirmation.');
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
        linkType: 'one_time' | 'continuous',
    ) => {
        if (!selectedPublicLinkGroupId) {
            return;
        }

        try {
            const response = await fetch(
                generateEditLink(selectedPublicLinkGroupId, {
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
            setPublicLinkDialogOpen(false);
            setSelectedPublicLinkGroupId(null);
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

            setMemberDialogMemberId(memberId);
            setMemberDialogData({
                ...emptyMember(false),
                ...selectedMember,
                member_id: memberId,
            });
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
        value: string | boolean | File | null,
    ) => {
        setMemberDialogData((prev) => {
            if (!prev) {
                return prev;
            }

            return {
                ...prev,
                [field]: value,
            };
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
            errors.status = 'Status is required.';
        }

        setMemberDialogErrors(errors);

        return Object.keys(errors).length === 0;
    };

    const submitMemberUpdate = () => {
        if (!memberDialogData || !memberDialogMemberId) {
            return;
        }

        if (!validateMemberDraft()) {
            return;
        }

        setIsSavingMember(true);

        const payload: Record<string, string | boolean | File | undefined> = {
            _method: 'PUT',
            name: memberDialogData.name ?? '',
            email: memberDialogData.email ?? '',
            contact_number: memberDialogData.contact_number ?? '',
            nric_number: memberDialogData.nric_number ?? '',
            address: memberDialogData.address ?? '',
            nationality: memberDialogData.nationality ?? '',
            passport_number: memberDialogData.passport_number ?? '',
            passport_issue_date: memberDialogData.passport_issue_date ?? '',
            passport_expiry_date: memberDialogData.passport_expiry_date ?? '',
            passport_place_of_issue:
                memberDialogData.passport_place_of_issue ?? '',
            gender: memberDialogData.gender ?? '',
            marital_status: memberDialogData.marital_status ?? '',
            date_of_birth: memberDialogData.date_of_birth ?? '',
            place_of_birth: memberDialogData.place_of_birth ?? '',
            first_time_umrah: Boolean(memberDialogData.first_time_umrah),
            has_chronic_disease: Boolean(memberDialogData.has_chronic_disease),
            chronic_disease_details:
                memberDialogData.chronic_disease_details ?? '',
            status: String(
                (memberDialogData as { status?: string }).status ?? '',
            ),
            sharing_plan: String(memberDialogData.sharing_plan ?? ''),
            role: String(memberDialogData.role ?? ''),
            passport_file:
                memberDialogData.passport_file instanceof File
                    ? memberDialogData.passport_file
                    : undefined,
            photo_file:
                memberDialogData.photo_file instanceof File
                    ? memberDialogData.photo_file
                    : undefined,
            passport_file_name: memberDialogData.passport_file_name ?? '',
            photo_file_name: memberDialogData.photo_file_name ?? '',
            passport_file_removed: Boolean(
                memberDialogData.passport_file_removed,
            ),
            photo_file_removed: Boolean(memberDialogData.photo_file_removed),
        };

        router.post(
            `/customer-confirmations/members/${memberDialogMemberId}`,
            payload,
            {
                forceFormData: true,
                preserveScroll: true,
                preserveState: false,
                onSuccess: () => {
                    toast.success('Member updated successfully.');
                    setMemberDialogOpen(false);
                },
                onError: () => {
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
        setQuotationDialogOpen(true);
    };

    const submitGenerateQuotations = () => {
        if (!quotationGroup) return;

        const payerToMembers: Record<number, number[]> = {};
        for (const [memberIdStr, payerId] of Object.entries(payerMapping)) {
            const memberId = Number(memberIdStr);
            if (!payerToMembers[payerId]) {
                payerToMembers[payerId] = [];
            }
            payerToMembers[payerId].push(memberId);
        }

        if (Object.keys(payerToMembers).length === 0) {
            toast.error('No payment assignments found.');
            return;
        }

        setIsGeneratingQuotations(true);

        const route = generateQuotations(quotationGroup.id);

        router.post(
            route.url,
            {
                payer_to_members: payerToMembers,
            },
            {
                preserveScroll: true,
                preserveState: false,
                onSuccess: () => {
                    toast.success('Quotation created successfully.');
                    setQuotationDialogOpen(false);
                },
                onError: () => {
                    toast.error('Failed to generate quotations.');
                },
                onFinish: () => {
                    setIsGeneratingQuotations(false);
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
                columns={memberColumns}
                data={data}
                actions={['view']}
                getRowActions={(member) => {
                    const rowActions: ActionType[] = ['edit'];

                    if (member.status !== 'cancelled') {
                        rowActions.push('move-members', 'cancel-member');
                    }

                    return rowActions;
                }}
                addButtonText=""
                onAction={(action, payload) => {
                    if (!payload) {
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

                    if (action === 'move-members') {
                        openMoveDialog(row.original, [member.id]);
                        return;
                    }

                    if (action === 'cancel-member') {
                        confirm({
                            title: 'Cancel Member',
                            message: `Cancel ${member.name}?`,
                            confirmText: 'Cancel Member',
                            cancelText: 'Back',
                            onConfirm: () => cancelMember(member.id),
                        });
                    }
                }}
                initialState={{
                    columnVisibility: {
                        nric_number: false,
                        nationality: false,
                        passport_number: false,
                        customer_number: false,
                        contact: false,
                        email: false,
                    },
                    pagination: {
                        pageIndex: 0,
                        pageSize: 10,
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
                            columns={groupColumns}
                            data={dataGroups}
                            actions={actions}
                            addButtonText="Create Customer Confirmation"
                            enableExpand
                            getRowActions={(group) => {
                                const rowActions: ActionType[] = [
                                    'copy-customer-confirmation-public-edit-link',
                                    'move-members',
                                ];

                                if (group.can_create_quotation) {
                                    rowActions.push('create-quotation');
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
                                        setSelectedPublicLinkGroupId(groupId);
                                        setPublicLinkDialogOpen(true);
                                    } else if (
                                        action === 'move-members' &&
                                        row
                                    ) {
                                        openMoveDialog(row.original);
                                    } else if (
                                        action === 'create-quotation' &&
                                        row
                                    ) {
                                        openQuotationDialog(row.original);
                                    } else if (action === 'delete') {
                                        confirm({
                                            title: 'Delete Customer Confirmation',
                                            message: `Delete confirmation #${groupId}? This removes the confirmation and its members only.`,
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
                                if (row.id) {
                                    handleOpenGroupDialog(row.id, 'view');
                                }
                            }}
                            initialState={{
                                columnVisibility: {
                                    id: false,
                                    customer_number: false,
                                    enquiry_email: false,
                                    enquiry_contact: false,
                                    enquiry_status: false,
                                    created_at: false,
                                    quoted_member_count: false,
                                    can_create_quotation: false,
                                },
                            }}
                            renderFilter={(table) => (
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
                            )}
                        />
                    </div>
                </div>
            </AppLayout>

            <Dialog open={groupDialogOpen} onOpenChange={setGroupDialogOpen}>
                <DialogContent className="flex max-h-[95%] max-w-[95%] min-w-[95%] flex-col">
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
                        <Button
                            type="button"
                            variant="default"
                            onClick={() =>
                                handleCopyPublicEditLink('continuous')
                            }
                        >
                            Copy Continuous Link
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => handleCopyPublicEditLink('one_time')}
                        >
                            Copy One-Time Link
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>

            <Dialog open={moveDialogOpen} onOpenChange={setMoveDialogOpen}>
                <DialogContent className="flex max-h-[95%] max-w-[95%] flex-col">
                    <DialogHeader>
                        <DialogTitle>Move Members to Holding</DialogTitle>
                        <DialogDescription>
                            Select members to cancel from this confirmation and
                            move into a new holding confirmation.
                        </DialogDescription>
                    </DialogHeader>

                    {selectedMoveGroup && (
                        <div className="space-y-4">
                            <FormField label="Target Package (Optional)">
                                <ProperInputSelect
                                    options={packageOptions}
                                    value={targetPackageId ?? ''}
                                    onValueChange={(value) => {
                                        if (!value) {
                                            setTargetPackageId(null);

                                            return;
                                        }

                                        setTargetPackageId(Number(value));
                                    }}
                                    placeholder="Leave empty to discuss package later"
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
                                                    member.status ?? 'draft'
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
                                    disabled={
                                        movingMembers ||
                                        selectedMoveMemberIds.length === 0
                                    }
                                >
                                    Move Selected Members
                                </Button>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog open={memberDialogOpen} onOpenChange={setMemberDialogOpen}>
                <DialogContent className="flex max-h-[95%] max-w-[95%] min-w-[95%] flex-col">
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
                                            useGeneratedDocumentName
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

            <Dialog
                open={quotationDialogOpen}
                onOpenChange={setQuotationDialogOpen}
            >
                <DialogContent className="flex max-h-[95%] max-w-[95%] flex-col md:min-w-3xl">
                    <DialogHeader className="gap-0">
                        <DialogTitle className="text-xl">
                            Create Quotation
                        </DialogTitle>
                        <DialogDescription>
                            Assign a payer for each member. The main member pays
                            for everyone by default — change individual payers
                            as needed.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="h-full w-full flex-1 overflow-y-auto pb-2">
                        {quotationGroup && (
                            <div className="space-y-4">
                                <div className="space-y-2 rounded-md border p-3">
                                    {quotationGroup.members
                                        .filter(
                                            (m) =>
                                                m.status !== 'cancelled' &&
                                                !m.has_quotation,
                                        )
                                        .map((member) => {
                                            const activeMembers =
                                                quotationGroup.members.filter(
                                                    (m) =>
                                                        m.status !==
                                                            'cancelled' &&
                                                        !m.has_quotation,
                                                );

                                            return (
                                                <div
                                                    key={member.id}
                                                    className="grid grid-cols-1 items-center justify-between gap-3 rounded px-2 py-2 md:grid-cols-2"
                                                >
                                                    <div className="flex min-w-0 flex-1 items-center gap-2">
                                                        <span className="truncate font-medium">
                                                            {member.name}
                                                        </span>
                                                        <Badge
                                                            variant={
                                                                member.is_leader
                                                                    ? 'default'
                                                                    : 'secondary'
                                                            }
                                                            className="shrink-0"
                                                        >
                                                            {member.is_leader
                                                                ? 'Main'
                                                                : 'Participant'}
                                                        </Badge>
                                                        {member.sharing_plan && (
                                                            <Badge
                                                                variant="outline"
                                                                className="shrink-0"
                                                            >
                                                                {member.sharing_plan
                                                                    .charAt(0)
                                                                    .toUpperCase() +
                                                                    member.sharing_plan.slice(
                                                                        1,
                                                                    )}
                                                            </Badge>
                                                        )}
                                                    </div>

                                                    <div className="w-full shrink-0">
                                                        <ProperInputSelect
                                                            options={activeMembers.map(
                                                                (m) => ({
                                                                    value: String(
                                                                        m.id,
                                                                    ),
                                                                    label: m.is_leader
                                                                        ? `${m.name} (Main)`
                                                                        : m.name,
                                                                }),
                                                            )}
                                                            value={String(
                                                                payerMapping[
                                                                    member.id
                                                                ] ?? '',
                                                            )}
                                                            onValueChange={(
                                                                value,
                                                            ) => {
                                                                setPayerMapping(
                                                                    (prev) => ({
                                                                        ...prev,
                                                                        [member.id]:
                                                                            Number(
                                                                                value,
                                                                            ),
                                                                    }),
                                                                );
                                                            }}
                                                            placeholder="Select payer"
                                                        />
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
