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
    sharingPlanOptions,
    type CustomerConfirmationDatatableSchema,
    type CustomerConfirmationFormSchema,
    type CustomerConfirmationMemberDatatableSchema,
    type CustomerMemberFormData,
} from '../customer/schema';
import { customerValidationSchema } from '../customer/validation';
import { statusColors, typeColors } from '../enquiries/schema';
import CustomerConfirmationForm from './form';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Confirmed Customers',
        href: confirmedCustomerIndex().url,
    },
];

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
        accessorKey: 'enquiry_email',
        header: 'Enquiry Email',
        meta: { exportable: true },
    },
    {
        accessorKey: 'enquiry_contact',
        header: 'Enquiry Contact',
        meta: { exportable: true },
    },
    {
        accessorKey: 'member_count',
        header: 'Members',
        meta: { exportable: true },
        cell: ({ row }) => (
            <Badge variant="secondary" className="text-sm">
                {row.original.member_count}
            </Badge>
        ),
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
}

export default function ConfirmedCustomerIndex({
    dataGroups,
    packageOptions = [],
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

    const submitMoveMembers = async () => {
        if (!selectedMoveGroup || selectedMoveMemberIds.length === 0) {
            return;
        }

        setMovingMembers(true);

        try {
            const csrfToken = document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content');

            const response = await fetch(
                `/customer-confirmations/${selectedMoveGroup.id}/move-members`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                    },
                    body: JSON.stringify({
                        member_ids: selectedMoveMemberIds,
                        target_package_id: targetPackageId,
                    }),
                },
            );

            if (!response.ok) {
                throw new Error('Unable to move selected members.');
            }

            toast.success('Members moved to holding confirmation.');
            setMoveDialogOpen(false);
            router.visit(confirmedCustomerIndex().url, {
                preserveScroll: true,
                preserveState: false,
            });
        } catch {
            toast.error('Failed to move selected members.');
        } finally {
            setMovingMembers(false);
        }
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

    const cancelMember = async (memberId: number) => {
        try {
            const csrfToken = document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content');

            const response = await fetch(
                `/customer-confirmations/members/${memberId}/cancel`,
                {
                    method: 'POST',
                    headers: {
                        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                    },
                },
            );

            if (!response.ok) {
                throw new Error('Failed to cancel member');
            }

            toast.success('Member cancelled successfully.');
            router.visit(confirmedCustomerIndex().url, {
                preserveScroll: true,
                preserveState: true,
            });
        } catch {
            toast.error('Failed to cancel member.');
        }
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

    const submitMemberUpdate = async () => {
        if (!memberDialogData || !memberDialogMemberId) {
            return;
        }

        if (!validateMemberDraft()) {
            return;
        }

        setIsSavingMember(true);

        try {
            const csrfToken = document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content');

            const formData = new FormData();
            formData.append('_method', 'PUT');
            formData.append('name', memberDialogData.name ?? '');
            formData.append('email', memberDialogData.email ?? '');
            formData.append(
                'contact_number',
                memberDialogData.contact_number ?? '',
            );
            formData.append('nric_number', memberDialogData.nric_number ?? '');
            formData.append('address', memberDialogData.address ?? '');
            formData.append('nationality', memberDialogData.nationality ?? '');
            formData.append(
                'passport_number',
                memberDialogData.passport_number ?? '',
            );
            formData.append(
                'passport_issue_date',
                memberDialogData.passport_issue_date ?? '',
            );
            formData.append(
                'passport_expiry_date',
                memberDialogData.passport_expiry_date ?? '',
            );
            formData.append(
                'passport_place_of_issue',
                memberDialogData.passport_place_of_issue ?? '',
            );
            formData.append('gender', memberDialogData.gender ?? '');
            formData.append(
                'marital_status',
                memberDialogData.marital_status ?? '',
            );
            formData.append(
                'date_of_birth',
                memberDialogData.date_of_birth ?? '',
            );
            formData.append(
                'place_of_birth',
                memberDialogData.place_of_birth ?? '',
            );
            formData.append(
                'first_time_umrah',
                String(Boolean(memberDialogData.first_time_umrah)),
            );
            formData.append(
                'has_chronic_disease',
                String(Boolean(memberDialogData.has_chronic_disease)),
            );
            formData.append(
                'chronic_disease_details',
                memberDialogData.chronic_disease_details ?? '',
            );
            formData.append(
                'status',
                String((memberDialogData as { status?: string }).status ?? ''),
            );
            formData.append(
                'sharing_plan',
                String(memberDialogData.sharing_plan ?? ''),
            );
            formData.append('role', String(memberDialogData.role ?? ''));
            formData.append(
                'passport_path',
                String(memberDialogData.passport_path ?? ''),
            );
            formData.append(
                'photo_path',
                String(memberDialogData.photo_path ?? ''),
            );

            if (memberDialogData.passport_file instanceof File) {
                formData.append(
                    'passport_file',
                    memberDialogData.passport_file,
                );
            }
            if (memberDialogData.photo_file instanceof File) {
                formData.append('photo_file', memberDialogData.photo_file);
            }

            const response = await fetch(
                `/customer-confirmations/members/${memberDialogMemberId}`,
                {
                    method: 'POST',
                    headers: {
                        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                    },
                    body: formData,
                },
            );

            if (!response.ok) {
                throw new Error('Failed to update member');
            }

            toast.success('Member updated successfully.');
            setMemberDialogOpen(false);
            router.visit(confirmedCustomerIndex().url, {
                preserveScroll: true,
                preserveState: true,
            });
        } catch {
            toast.error('Failed to update member.');
        } finally {
            setIsSavingMember(false);
        }
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

    const submitGenerateQuotations = async () => {
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

        try {
            const csrfToken = document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content');

            const route = generateQuotations(quotationGroup.id);

            const response = await fetch(route.url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                },
                body: JSON.stringify({
                    payer_to_members: payerToMembers,
                }),
            });

            if (!response.ok) {
                const errorData = (await response.json().catch(() => null)) as {
                    message?: string;
                } | null;
                throw new Error(
                    errorData?.message ?? 'Failed to generate quotations.',
                );
            }

            const data = (await response.json()) as {
                message: string;
                quotation_ids: number[];
            };
            toast.success(data.message);
            setQuotationDialogOpen(false);
            router.visit(confirmedCustomerIndex().url, {
                preserveScroll: true,
                preserveState: false,
            });
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Failed to generate quotations.',
            );
        } finally {
            setIsGeneratingQuotations(false);
        }
    };

    const renderGroupSubComponent = (
        row: Row<CustomerConfirmationDatatableSchema>,
    ) => {
        const data = row.original.members;

        return (
            <div className="bg-muted/20 p-4">
                <DataTable
                    columns={memberColumns}
                    data={data}
                    actions={['view-member']}
                    getRowActions={() => [
                        'edit-member',
                        'move-members',
                        'cancel-member',
                    ]}
                    addButtonText=""
                    onAction={(action, payload) => {
                        if (!payload) {
                            return;
                        }

                        const tableRow =
                            payload as Row<CustomerConfirmationMemberDatatableSchema>;
                        const member = tableRow.original;

                        if (action === 'view-member') {
                            openMemberDialog(
                                member.group_id,
                                member.id,
                                'view',
                            );
                            return;
                        }

                        if (action === 'edit-member') {
                            openMemberDialog(
                                member.group_id,
                                member.id,
                                'edit',
                            );
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
                        pagination: {
                            pageIndex: 0,
                            pageSize: 10,
                        },
                    }}
                />
            </div>
        );
    };

    return (
        <>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Confirmed Customers" />
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">
                            Confirmed Customers
                        </h2>
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
                                    'copy-public-edit-link',
                                    'move-members',
                                ];

                                if (group.can_create_quotation) {
                                    rowActions.push('create-quotation');
                                }

                                return rowActions;
                            }}
                            renderSubComponent={renderGroupSubComponent}
                            url={confirmedCustomerIndex().url}
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
                                        action === 'copy-public-edit-link'
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
                                    enquiry_status: false,
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
                <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
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
                <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Create Quotation</DialogTitle>
                        <DialogDescription>
                            Assign a payer for each member. The main member pays
                            for everyone by default — change individual payers
                            as needed.
                        </DialogDescription>
                    </DialogHeader>

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
                                                className="flex items-center justify-between gap-3 rounded px-2 py-2"
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

                                                <div className="w-48 shrink-0">
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
                </DialogContent>
            </Dialog>

            <ConfirmDialog />
        </>
    );
}
