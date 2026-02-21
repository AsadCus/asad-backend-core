import { DatePickerField } from '@/components/date-picker';
import { FormField } from '@/components/form-field';
import { MultiSelect } from '@/components/multi-select';
import { ProperInputSelect } from '@/components/proper-input-select';
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
import { Label } from '@/components/ui/label';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { update as updateGroup } from '@/routes/customer-groups';
import {
    confirm as confirmEnquiry,
    createCustomerGroup,
    getForShow,
    listCustomers,
} from '@/routes/enquiries';
import { OptionType } from '@/types';
import { useForm } from '@inertiajs/react';
import {
    AlertCircle,
    CheckCircle,
    ExternalLink,
    Plus,
    Trash2,
} from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';
import GeneralEnquiryForm from '../general-enquiries/form';
import type { GeneralEnquirySchema } from '../general-enquiries/schema';
import type { PackageSchema } from '../packages/schema';
import PrivateEnquiryForm from '../private-enquiries/form';
import type { PrivateEnquirySchema } from '../private-enquiries/schema';
import MemberFormFields from './form-fields';
import {
    emptyMember,
    packageCategoryOptions,
    packageRoomTypeOptions,
    type CustomerGroupFormSchema,
    type CustomerMemberSchema,
} from './schema';
import { customerGroupFormValidationSchema } from './validation';

interface CustomerOption extends OptionType {
    name: string;
    email: string;
    contact_number: string;
    nric_number: string;
    address: string;
}

export interface EnquiryDetails {
    id: number;
    type: string;
    name: string;
    email: string;
    contact: string;
    status: string;
    package_name?: string | null;
}

export interface CustomerConfirmationFormProps {
    mode?: 'create' | 'edit' | 'view';
    enquiryId?: number;
    enquiryType?: 'general' | 'private';
    /** Summary of the linked enquiry – displayed in a read-only info card. */
    enquiryDetails?: EnquiryDetails;
    prefillName?: string;
    prefillEmail?: string;
    prefillContact?: string;
    prefillPackageId?: number | null;
    isPublic?: boolean;
    publicSubmitUrl?: string;
    initialData?: CustomerGroupFormSchema;
    packageOptions?: OptionType[];
    /** Package data collected in step 1 (private flow). Sent to server alongside customer data. */
    packageData?: PackageSchema;
    onSuccess?: () => void;
    onCancel?: () => void;
}

export default function CustomerConfirmationForm({
    mode = 'create',
    enquiryId,
    enquiryType,
    enquiryDetails,
    prefillName = '',
    prefillEmail = '',
    prefillContact = '',
    prefillPackageId,
    isPublic = false,
    publicSubmitUrl,
    initialData,
    packageOptions = [],
    packageData,
    onSuccess,
    onCancel,
}: CustomerConfirmationFormProps) {
    const isView = mode === 'view';
    const isEdit = mode === 'edit';
    const isCreate = mode === 'create';

    // Customer search state (internal only)
    const [customerOptions, setCustomerOptions] = useState<CustomerOption[]>(
        [],
    );

    // Linked enquiry info (fetched in view/edit mode when not passed as prop)
    const [linkedEnquiryInfo, setLinkedEnquiryInfo] =
        useState<EnquiryDetails | null>(null);
    const [enquiryDialogOpen, setEnquiryDialogOpen] = useState(false);
    // Full child enquiry data (GeneralEnquiry / PrivateEnquiry) – loaded lazily when dialog opens
    const [linkedEnquiryChild, setLinkedEnquiryChild] = useState<
        GeneralEnquirySchema | PrivateEnquirySchema | null
    >(null);
    const [isLoadingEnquiryChild, setIsLoadingEnquiryChild] = useState(false);

    // Active member tab
    const [activeTab, setActiveTab] = useState('member-0');

    // Success state for public forms
    const [isSubmitted, setIsSubmitted] = useState(false);

    // Load customers and (in view/edit) linked enquiry details on mount
    useEffect(() => {
        if (isPublic) return;

        if (!isView) {
            fetch(listCustomers().url)
                .then((res) => res.json())
                .then((data) => setCustomerOptions(data))
                .catch(() => {});
        }

        const linkedId = initialData?.enquiry_id;
        if ((isView || isEdit) && linkedId && !enquiryDetails) {
            fetch(getForShow(linkedId).url)
                .then((res) => res.json())
                .then((json) => {
                    const eq = json?.enquiry;
                    if (!eq) return;
                    setLinkedEnquiryInfo({
                        id: eq.id,
                        type: eq.type,
                        name: eq.name,
                        email: eq.email,
                        contact: eq.contact,
                        status: eq.status_label ?? eq.status,
                        package_name: eq.package_name ?? null,
                    });
                })
                .catch(() => {});
        }
    }, [isPublic, isView, isEdit, enquiryDetails, initialData?.enquiry_id]);

    // Fetch full child enquiry data (general/private form fields) when dialog opens
    useEffect(() => {
        if (!enquiryDialogOpen) return;
        if (linkedEnquiryChild) return;

        const enquiryId = enquiryDetails?.id ?? linkedEnquiryInfo?.id ?? null;
        if (!enquiryId) return;

        setIsLoadingEnquiryChild(true);
        fetch(getForShow(enquiryId).url)
            .then((res) => res.json())
            .then((json) => setLinkedEnquiryChild(json?.child ?? null))
            .catch(() => {})
            .finally(() => setIsLoadingEnquiryChild(false));
    }, [
        enquiryDialogOpen,
        enquiryDetails?.id,
        linkedEnquiryInfo?.id,
        linkedEnquiryChild,
    ]);

    // Build default form data
    const defaultData: CustomerGroupFormSchema = initialData ?? {
        enquiry_id: enquiryId ?? null,
        package_id: prefillPackageId ?? null,
        package_room_type: '',
        package_category: '',
        date_of_application: '',
        members: [
            {
                ...emptyMember(true),
                name: prefillName,
                email: prefillEmail,
                contact_number: prefillContact,
            },
        ],
        terms_accepted: isPublic && isEdit ? true : !isPublic,
    };

    const {
        data,
        setData,
        post,
        put,
        processing,
        errors,
        clearErrors,
        setError,
    } = useForm<CustomerGroupFormSchema>(defaultData);

    // ── Helpers ──

    const getError = (path: string): string | undefined => {
        const errorMap = errors as Record<string, string | undefined>;
        return errorMap[path];
    };

    const hasErrors = Object.keys(errors).length > 0;

    // ── Member management ──

    const addMember = () => {
        const next = [...(data.members ?? []), emptyMember(false)];
        setData('members', next);
        setActiveTab(`member-${next.length - 1}`);
    };

    const addCustomerAsMember = (customerValue: string | number) => {
        const customer = customerOptions.find(
            (c) => String(c.value) === String(customerValue),
        );
        if (!customer) return;

        // Prevent duplicates by email
        if (data.members?.some((m) => m.email === customer.email)) return;

        const next = [
            ...(data.members ?? []),
            {
                ...emptyMember(false),
                name: customer.name,
                email: customer.email,
                contact_number: customer.contact_number,
                nric_number: customer.nric_number,
                address: customer.address,
            },
        ];
        setData('members', next);
        setActiveTab(`member-${next.length - 1}`);
    };

    // ── MultiSelect sync ──

    /** Current member emails that match a customer record. */
    const selectedCustomerValues = customerOptions
        .filter((c) => data.members?.some((m) => m.email === c.email))
        .map((c) => String(c.value));

    /**
     * Called when the MultiSelect value changes.
     * Adds newly selected customers and removes deselected ones in one batched update.
     */
    const handleMultiSelectChange = (newValues: string[]) => {
        const currentMembers = data.members ?? [];

        // Emails of the new selection
        const newSelectedEmails = new Set(
            customerOptions
                .filter((c) => newValues.includes(String(c.value)))
                .map((c) => c.email),
        );

        // Remove members that came from customerOptions but are now deselected.
        // Always keep the group leader regardless of selection.
        let nextMembers = currentMembers.filter((m) => {
            if (m.is_leader) return true;
            const isCustomerMember = customerOptions.some(
                (c) => c.email === m.email,
            );
            return !isCustomerMember || newSelectedEmails.has(m.email);
        });

        // Add newly selected customers
        for (const v of newValues) {
            const customer = customerOptions.find((c) => String(c.value) === v);
            if (!customer) continue;
            if (nextMembers.some((m) => m.email === customer.email)) continue;
            nextMembers.push({
                ...emptyMember(false),
                name: customer.name,
                email: customer.email,
                contact_number: customer.contact_number,
                nric_number: customer.nric_number,
                address: customer.address,
            });
        }

        // Ensure a leader is assigned
        if (nextMembers.length > 0 && !nextMembers.some((m) => m.is_leader)) {
            nextMembers[0] = { ...nextMembers[0], is_leader: true };
        }

        setData('members', nextMembers);
        setActiveTab(`member-${Math.max(0, nextMembers.length - 1)}`);
    };

    const removeMember = (index: number) => {
        const next = (data.members ?? []).filter((_, i) => i !== index);
        // If removed member was leader, assign first as leader
        if (next.length > 0 && !next.some((m) => m.is_leader)) {
            next[0] = { ...next[0], is_leader: true };
        }
        setData('members', next);
        // Switch to the first tab if we removed the active one
        const newIdx = Math.min(index, next.length - 1);
        setActiveTab(`member-${Math.max(0, newIdx)}`);
    };

    const updateMember = (
        index: number,
        field: keyof CustomerMemberSchema,
        value: string | boolean | null,
    ) => {
        const next = [...(data.members ?? [])];

        if (field === 'is_leader' && value === true) {
            // Unset all others
            for (let i = 0; i < next.length; i++) {
                next[i] = { ...next[i], is_leader: i === index };
            }
        } else {
            next[index] = { ...next[index], [field]: value };
        }

        setData('members', next);
    };

    // ── Validation + Submit ──

    function validateClientSide(): boolean {
        clearErrors();
        const result = customerGroupFormValidationSchema.safeParse(data);

        if (!result.success) {
            result.error.issues.forEach((issue) => {
                const key = issue.path.join('.');
                setError(key as keyof CustomerGroupFormSchema, issue.message);
            });
            return false;
        }

        return true;
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        if (isView) return;
        if (!validateClientSide()) return;

        const handleSuccess = () => {
            if (isPublic) {
                setIsSubmitted(true);
                window.scrollTo({ top: 0, behavior: 'smooth' });
                setTimeout(() => setIsSubmitted(false), 8000);
            }
            onSuccess?.();
        };

        const handleError = (errs: Record<string, string>) => {
            setError(errs);
            if (isPublic) {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        };

        if (isEdit && initialData?.id) {
            const editUrl = publicSubmitUrl ?? updateGroup(initialData.id).url;
            const method = publicSubmitUrl ? post : put;
            method(editUrl, {
                onSuccess: handleSuccess,
                onError: handleError,
            });
        } else {
            // Determine URL: public → publicSubmitUrl, enquiry confirm → confirmEnquiry, standalone → createCustomerGroup
            const effectiveEnquiryId =
                enquiryId ?? initialData?.enquiry_id ?? null;
            const submitUrl = publicSubmitUrl
                ? publicSubmitUrl
                : enquiryId
                  ? confirmEnquiry(enquiryId).url
                  : createCustomerGroup().url;

            // Sync enquiry_id before posting, and include package_data for private flow
            const payload: Record<string, unknown> = {
                ...data,
                enquiry_id: effectiveEnquiryId,
            };

            if (packageData && enquiryType === 'private') {
                payload.package_data = packageData;
            }

            setData(payload as CustomerGroupFormSchema);

            post(submitUrl, {
                onSuccess: handleSuccess,
                onError: handleError,
            });
        }
    }

    // Effective linked enquiry details (prop takes priority, else fetched)
    const effectiveLinkedEnquiry = enquiryDetails ?? linkedEnquiryInfo;

    return (
        <div className="mx-auto w-full">
            <form onSubmit={submit} className="space-y-4 py-2">
                {/* Success Alert (public forms) */}
                {isSubmitted && isPublic && (
                    <Alert className="border-green-600 bg-green-50 shadow-sm">
                        <CheckCircle className="h-5 w-5 text-green-600" />
                        <AlertDescription className="font-medium text-green-900">
                            {isEdit
                                ? 'Your application has been updated successfully.'
                                : 'Your application has been submitted successfully. We will contact you soon.'}
                        </AlertDescription>
                    </Alert>
                )}

                {/* Error Alert */}
                {hasErrors && !isView && (
                    <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>
                            Please fix the errors below and try again.
                        </AlertDescription>
                    </Alert>
                )}

                {/* ── Linked Enquiry Details ── */}
                {(isView || isEdit) && !isPublic && effectiveLinkedEnquiry && (
                    <Card>
                        <CardHeader className="gap-0 pb-3">
                            <div className="flex items-center justify-between">
                                <CardTitle className="text-xl">
                                    Linked Enquiry
                                </CardTitle>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setEnquiryDialogOpen(true)}
                                >
                                    <ExternalLink className="h-4 w-4 md:mr-1" />
                                    <span className="hidden md:block">
                                        View Details
                                    </span>
                                </Button>
                            </div>
                            <CardDescription>
                                Details of the linked enquiry.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-2 gap-3 md:grid-cols-3">
                                <div>
                                    <span className="text-muted-foreground">Enquiry ID:</span>{' '}
                                    <span className="font-medium">
                                        #{effectiveLinkedEnquiry.id}
                                    </span>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">Type:</span>{' '}
                                    <Badge variant="outline" className="ml-1">
                                        {effectiveLinkedEnquiry.type}
                                    </Badge>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">
                                        Status:
                                    </span>{' '}
                                    <Badge variant="secondary" className="ml-1">
                                        {effectiveLinkedEnquiry.status}
                                    </Badge>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">
                                        Name:
                                    </span>{' '}
                                    <span className="font-medium">
                                        {effectiveLinkedEnquiry.name}
                                    </span>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">
                                        Email:
                                    </span>{' '}
                                    <span className="font-medium">
                                        {effectiveLinkedEnquiry.email}
                                    </span>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">
                                        Contact:
                                    </span>{' '}
                                    <span className="font-medium">
                                        {effectiveLinkedEnquiry.contact}
                                    </span>
                                </div>
                                {effectiveLinkedEnquiry.package_name && (
                                    <div className="col-span-full">
                                        <span className="text-muted-foreground">
                                            Package:
                                        </span>{' '}
                                        <span className="font-medium">
                                            {
                                                effectiveLinkedEnquiry.package_name
                                            }
                                        </span>
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* ── Group-level fields ── */}
                <Card>
                    <CardHeader className="gap-0">
                        <CardTitle className="text-xl">Group Details</CardTitle>
                        <CardDescription>
                            {isView
                                ? 'View the details of this customer group.'
                                : isEdit
                                  ? 'Update the details of this customer group.'
                                  : 'Fill in the details of the customer group.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 items-start gap-6 md:grid-cols-2">
                            {/* Package */}
                            <FormField
                                label="Package"
                                fieldRequirementsProps={{
                                    hint:
                                        enquiryType === 'private'
                                            ? 'Package was created in the previous step'
                                            : 'Select the travel package for this group',
                                }}
                                htmlFor="package_id"
                                error={getError('package_id')}
                            >
                                {enquiryType === 'private' && packageData ? (
                                    <div className="flex h-9 items-center rounded-md border bg-muted px-3 text-sm">
                                        {packageData.name ||
                                            'Package (from step 1)'}
                                    </div>
                                ) : (
                                    <ProperInputSelect
                                        options={packageOptions}
                                        value={
                                            data.package_id
                                                ? String(data.package_id)
                                                : ''
                                        }
                                        onValueChange={(v) =>
                                            setData(
                                                'package_id',
                                                v ? Number(v) : null,
                                            )
                                        }
                                        placeholder="Select package..."
                                        disabled={isView || processing}
                                        truncate={30}
                                    />
                                )}
                            </FormField>

                            {/* Package Room Type */}
                            <FormField
                                label="Room Type"
                                fieldRequirementsProps={{
                                    hint: 'Select the preferred room accommodation type',
                                    example: 'Double, Triple, Quad',
                                }}
                                htmlFor="package_room_type"
                                error={getError('package_room_type')}
                            >
                                <Select
                                    value={data.package_room_type ?? ''}
                                    onValueChange={(v) =>
                                        setData('package_room_type', v || null)
                                    }
                                    disabled={isView || processing}
                                >
                                    <SelectTrigger id="package_room_type">
                                        <SelectValue placeholder="Select room type..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {packageRoomTypeOptions.map(
                                            (option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={String(option.value)}
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                            </FormField>

                            {/* Package Category */}
                            <FormField
                                label="Category"
                                fieldRequirementsProps={{
                                    hint: 'Select the package category based on services',
                                    example: 'Economy, Standard, Premium, VIP',
                                }}
                                htmlFor="package_category"
                                error={getError('package_category')}
                            >
                                <Select
                                    value={data.package_category ?? ''}
                                    onValueChange={(v) =>
                                        setData('package_category', v || null)
                                    }
                                    disabled={isView || processing}
                                >
                                    <SelectTrigger id="package_category">
                                        <SelectValue placeholder="Select category..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {packageCategoryOptions.map(
                                            (option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={String(option.value)}
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                            </FormField>

                            {/* Date of Application */}
                            <FormField
                                label="Date of Application"
                                fieldRequirementsProps={{
                                    required: true,
                                    hint: 'Date when this group application is being submitted',
                                }}
                                htmlFor="date_of_application"
                                error={getError('date_of_application')}
                            >
                                <DatePickerField
                                    id="date_of_application"
                                    value={data.date_of_application}
                                    disabled={isView || processing}
                                    onChange={(v) =>
                                        setData('date_of_application', v)
                                    }
                                />
                            </FormField>
                        </div>
                    </CardContent>
                </Card>

                {/* ── Members (tab-based) ── */}
                <Card>
                    <CardHeader className="grid grid-cols-1 md:grid-cols-2">
                        <div className="grid grid-cols-1 gap-0">
                            <CardTitle className="text-xl">
                                Group Members ({data.members?.length ?? 0})
                            </CardTitle>
                            <CardDescription>
                                {isView
                                    ? 'View the details of the group members.'
                                    : isEdit
                                      ? 'Update the details of the group members.'
                                      : 'Fill in the details of the group members.'}
                            </CardDescription>
                        </div>
                        {!isView && (
                            <div className="flex flex-col justify-end gap-2 md:flex-row">
                                {!isPublic && customerOptions.length > 0 && (
                                    <MultiSelect
                                        options={customerOptions.map((c) => ({
                                            label: c.label,
                                            value: String(c.value),
                                        }))}
                                        defaultValue={selectedCustomerValues}
                                        onValueChange={handleMultiSelectChange}
                                        placeholder="Search & select customers..."
                                        maxWidth="300px"
                                        responsive={true}
                                        disabled={processing}
                                        maxCount={0}
                                    />
                                )}
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={addMember}
                                >
                                    <Plus className="mr-1 h-4 w-4" />
                                    Add Member
                                </Button>
                            </div>
                        )}
                    </CardHeader>
                    <CardContent>
                        {getError('members') && (
                            <p className="mb-3 text-base font-medium text-red-600">
                                {getError('members')}
                            </p>
                        )}

                        {(data.members?.length ?? 0) === 0 ? (
                            <p className="text-base text-muted-foreground">
                                No members added. Click &quot;Add Member&quot;
                                to start building the group.
                            </p>
                        ) : (
                            <Tabs
                                value={activeTab}
                                onValueChange={setActiveTab}
                            >
                                <ScrollArea className="w-full whitespace-nowrap">
                                    <TabsList>
                                        {data.members?.map((member, idx) => (
                                            <TabsTrigger
                                                key={idx}
                                                value={`member-${idx}`}
                                                className="relative"
                                            >
                                                <span className="mr-1">
                                                    {member.name ||
                                                        `Member ${idx + 1}`}
                                                </span>
                                                {member.is_leader && (
                                                    <Badge
                                                        variant="default"
                                                        className="ml-1 text-xs"
                                                    >
                                                        Leader
                                                    </Badge>
                                                )}
                                            </TabsTrigger>
                                        ))}
                                    </TabsList>
                                    <ScrollBar orientation="horizontal" />
                                </ScrollArea>

                                {data.members?.map((member, idx) => (
                                    <TabsContent
                                        key={idx}
                                        value={`member-${idx}`}
                                        className="space-y-2"
                                    >
                                        {/* Member header with leader toggle & delete */}
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-4">
                                                <div className="flex items-center gap-2">
                                                    <input
                                                        type="radio"
                                                        name="leader"
                                                        checked={
                                                            member.is_leader
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onChange={() =>
                                                            updateMember(
                                                                idx,
                                                                'is_leader',
                                                                true,
                                                            )
                                                        }
                                                        className="h-4 w-4 accent-primary"
                                                    />
                                                    <Label className="text-base">
                                                        Group Leader
                                                    </Label>
                                                </div>
                                            </div>
                                            {!isView &&
                                                (data.members?.length ?? 0) >
                                                    1 && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            removeMember(idx)
                                                        }
                                                        className="text-red-500 hover:text-red-700"
                                                    >
                                                        <Trash2 className="mr-1 h-4 w-4" />
                                                        Remove
                                                    </Button>
                                                )}
                                        </div>

                                        <MemberFormFields
                                            member={member}
                                            index={idx}
                                            isView={isView}
                                            processing={processing}
                                            getError={getError}
                                            onUpdate={(field, value) =>
                                                updateMember(idx, field, value)
                                            }
                                        />
                                    </TabsContent>
                                ))}
                            </Tabs>
                        )}
                    </CardContent>
                </Card>

                {/* Terms & Conditions (public create only) */}
                {isPublic && isCreate && (
                    <Card>
                        <CardContent>
                            <div className="flex items-start gap-3 pt-4">
                                <Checkbox
                                    id="terms_accepted"
                                    checked={data.terms_accepted}
                                    disabled={processing}
                                    onCheckedChange={(checked) =>
                                        setData(
                                            'terms_accepted',
                                            checked === true,
                                        )
                                    }
                                />
                                <div>
                                    <Label
                                        htmlFor="terms_accepted"
                                        className="cursor-pointer text-base"
                                    >
                                        I agree to the Terms and Conditions{' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        By checking this box, you confirm that
                                        all the information provided is accurate
                                        and you agree to our terms of service.
                                    </p>
                                </div>
                            </div>
                            {getError('terms_accepted') && (
                                <p className="mt-1 text-base font-medium text-red-600">
                                    {getError('terms_accepted')}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Action Buttons */}
                <div className="flex items-center justify-end gap-3">
                    {onCancel && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onCancel}
                            disabled={processing}
                        >
                            {isView ? 'Close' : 'Cancel'}
                        </Button>
                    )}
                    {!isView && (
                        <Button
                            type="submit"
                            className="min-w-[140px]"
                            disabled={
                                processing ||
                                (isPublic && isCreate && !data.terms_accepted)
                            }
                        >
                            {processing
                                ? 'Submitting...'
                                : isEdit
                                  ? 'Update'
                                  : enquiryId
                                    ? 'Confirm Enquiry'
                                    : 'Create'}
                        </Button>
                    )}
                </div>
                {/* ── Enquiry Details Dialog ── */}
                <Dialog
                    open={enquiryDialogOpen}
                    onOpenChange={setEnquiryDialogOpen}
                >
                    <DialogContent className="flex max-h-[95%] max-w-[95%] min-w-[95%] flex-col overflow-y-hidden">
                        <DialogHeader>
                            <div className="flex items-center gap-3">
                                <div>
                                    <DialogTitle>Enquiry Details</DialogTitle>
                                    <DialogDescription className="sr-only">
                                        Full enquiry information
                                    </DialogDescription>
                                </div>
                                {effectiveLinkedEnquiry && (
                                    <div className="flex items-center gap-2">
                                        <Badge variant="outline">
                                            {effectiveLinkedEnquiry.type}
                                        </Badge>
                                        <Badge variant="secondary">
                                            {effectiveLinkedEnquiry.status}
                                        </Badge>
                                    </div>
                                )}
                            </div>
                        </DialogHeader>
                        <div className="h-full w-full flex-1 overflow-y-auto">
                            {isLoadingEnquiryChild && (
                                <div className="flex h-full items-center justify-center text-muted-foreground">
                                    Loading enquiry details...
                                </div>
                            )}
                            {!isLoadingEnquiryChild &&
                                linkedEnquiryChild &&
                                effectiveLinkedEnquiry &&
                                (effectiveLinkedEnquiry.type === 'general' ? (
                                    <GeneralEnquiryForm
                                        mode="view"
                                        initialData={
                                            linkedEnquiryChild as GeneralEnquirySchema
                                        }
                                    />
                                ) : (
                                    <PrivateEnquiryForm
                                        mode="view"
                                        initialData={
                                            linkedEnquiryChild as PrivateEnquirySchema
                                        }
                                    />
                                ))}
                            {!isLoadingEnquiryChild && !linkedEnquiryChild && (
                                <div className="flex h-full items-center justify-center text-muted-foreground">
                                    Failed to load enquiry details.
                                </div>
                            )}
                        </div>
                    </DialogContent>
                </Dialog>
            </form>
        </div>
    );
}
