import { DatePickerField } from '@/components/date-picker';
import { FormField } from '@/components/form-field';
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
import { Label } from '@/components/ui/label';
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
    availableEnquiries,
    confirm as confirmEnquiry,
    createCustomerGroup,
    listCustomers,
} from '@/routes/enquiries';
import { OptionType } from '@/types';
import { useForm } from '@inertiajs/react';
import { AlertCircle, Plus, Trash2 } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';
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

export interface CustomerConfirmationFormProps {
    mode?: 'create' | 'edit' | 'view';
    enquiryId?: number;
    prefillName?: string;
    prefillEmail?: string;
    prefillContact?: string;
    isPublic?: boolean;
    initialData?: CustomerGroupFormSchema;
    packageOptions?: OptionType[];
    onSuccess?: () => void;
    onCancel?: () => void;
}

export default function CustomerConfirmationForm({
    mode = 'create',
    enquiryId,
    prefillName = '',
    prefillEmail = '',
    prefillContact = '',
    isPublic = false,
    initialData,
    packageOptions = [],
    onSuccess,
    onCancel,
}: CustomerConfirmationFormProps) {
    const isView = mode === 'view';
    const isEdit = mode === 'edit';
    const isCreate = mode === 'create';

    // Enquiry selector state (internal only, when no enquiryId prop)
    const [enquiryOptions, setEnquiryOptions] = useState<OptionType[]>([]);
    const [selectedEnquiryId, setSelectedEnquiryId] = useState<number | null>(
        enquiryId ?? initialData?.enquiry_id ?? null,
    );

    // Customer search state (internal only)
    const [customerOptions, setCustomerOptions] = useState<CustomerOption[]>(
        [],
    );

    // Active member tab
    const [activeTab, setActiveTab] = useState('member-0');

    // Load available enquiries and customers on mount (internal only)
    useEffect(() => {
        if (isPublic || isView) return;

        if (!enquiryId && isCreate) {
            fetch(availableEnquiries().url)
                .then((res) => res.json())
                .then((data) => setEnquiryOptions(data))
                .catch(() => {});
        }

        fetch(listCustomers().url)
            .then((res) => res.json())
            .then((data) => setCustomerOptions(data))
            .catch(() => {});
    }, [isPublic, isView, enquiryId, isCreate]);

    // Build default form data
    const defaultData: CustomerGroupFormSchema = initialData ?? {
        enquiry_id: enquiryId ?? null,
        package_id: null,
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

        if (isEdit && initialData?.id) {
            put(updateGroup(initialData.id).url, {
                onSuccess: () => onSuccess?.(),
                onError: (errs) => setError(errs),
            });
        } else {
            // Determine URL: enquiry confirm vs standalone
            const effectiveEnquiryId = enquiryId ?? selectedEnquiryId;
            const submitUrl = enquiryId
                ? confirmEnquiry(enquiryId).url
                : createCustomerGroup().url;

            // Sync enquiry_id before posting
            const payload = {
                ...data,
                enquiry_id: effectiveEnquiryId,
            };
            setData(payload);

            post(submitUrl, {
                onSuccess: () => onSuccess?.(),
                onError: (errs) => setError(errs),
            });
        }
    }

    return (
        <div className="mx-auto w-full">
            <form onSubmit={submit} className="space-y-4 py-2">
                {/* Error Alert */}
                {hasErrors && !isView && (
                    <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>
                            Please fix the errors below and try again.
                        </AlertDescription>
                    </Alert>
                )}

                {/* ── Group-level fields ── */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-xl">
                            {isView
                                ? 'Customer Group Details'
                                : isEdit
                                  ? 'Edit Customer Group'
                                  : 'Customer Confirmation Form'}
                        </CardTitle>
                        <CardDescription>
                            {isView
                                ? 'View the details of this customer group.'
                                : isEdit
                                  ? 'Update the details of this customer group.'
                                  : 'Fill in the details of the customer group and its members.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 items-start gap-6 md:grid-cols-2">
                            {/* Enquiry Selector (create only, no enquiryId prop) */}
                            {!isPublic && !enquiryId && (
                                <FormField
                                    label="Link to Enquiry"
                                    fieldRequirementsProps={{
                                        hint: 'Connect this group to a confirmed enquiry (optional)',
                                    }}
                                    htmlFor="enquiry_id"
                                    error={getError('enquiry_id')}
                                >
                                    <ProperInputSelect
                                        options={enquiryOptions}
                                        value={selectedEnquiryId ?? ''}
                                        onValueChange={(v) =>
                                            setSelectedEnquiryId(
                                                v ? Number(v) : null,
                                            )
                                        }
                                        placeholder="Select a confirmed enquiry..."
                                        disabled={isView || processing}
                                        truncate={30}
                                    />
                                </FormField>
                            )}

                            {/* Package */}
                            <FormField
                                label="Package"
                                fieldRequirementsProps={{
                                    hint: 'Select the travel package for this group',
                                }}
                                htmlFor="package_id"
                                error={getError('package_id')}
                            >
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
                    <CardHeader className="gap-0">
                        <div className="flex items-center justify-between">
                            <CardTitle className="text-xl">
                                Group Members ({data.members?.length ?? 0})
                            </CardTitle>
                            {!isView && (
                                <div className="flex gap-2">
                                    {!isPublic &&
                                        customerOptions.length > 0 && (
                                            <ProperInputSelect
                                                options={customerOptions.filter(
                                                    (c) =>
                                                        !(
                                                            data.members ?? []
                                                        ).some(
                                                            (m) =>
                                                                m.email ===
                                                                c.email,
                                                        ),
                                                )}
                                                value=""
                                                onValueChange={(v) => {
                                                    if (v)
                                                        addCustomerAsMember(v);
                                                }}
                                                placeholder="Search customer..."
                                                className="w-[300px]"
                                                truncate={10}
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
                        </div>
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
                                <TabsList className="max-w-full overflow-hidden">
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
            </form>
        </div>
    );
}
