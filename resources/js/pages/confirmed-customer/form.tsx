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
import { Input } from '@/components/ui/input';
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
import CustomerFormFields from '../customer/form-fields';
import {
    emptyMember,
    packageCategoryOptions,
    packageRoomTypeOptions,
    type CustomerGroupFormData,
    type CustomerGroupFormSchema,
    type CustomerMemberFormData,
    type CustomerOption,
} from '../customer/schema';
import { customerGroupFormValidationSchema } from '../customer/validation';
import EnquiryViewDialog from '../enquiries/components/enquiry-view-dialog';
import { EnquiryDetails } from '../enquiries/schema';
import type { PackageSchema } from '../packages/schema';

type ClientValidationErrors = Record<string, string>;

export interface CustomerConfirmationFormProps {
    mode?: 'create' | 'edit' | 'view';
    enquiryId?: number;
    enquiryType?: string;
    enquiryDetails?: EnquiryDetails;
    prefillName?: string;
    prefillEmail?: string;
    prefillContact?: string;
    prefillPackageId?: number | null;
    isPublic?: boolean;
    publicSubmitUrl?: string;
    initialData?: CustomerGroupFormSchema;
    packageOptions?: OptionType[];
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

    // State
    const [customerOptions, setCustomerOptions] = useState<CustomerOption[]>(
        [],
    );

    const [linkedEnquiryInfo, setLinkedEnquiryInfo] =
        useState<EnquiryDetails | null>(null);
    const [enquiryDialogOpen, setEnquiryDialogOpen] = useState(false);
    const [linkedEnquiryChild, setLinkedEnquiryChild] = useState<Record<
        string,
        unknown
    > | null>(null);
    const [isLoadingEnquiryChild, setIsLoadingEnquiryChild] = useState(false);
    const [activeTab, setActiveTab] = useState('customer-0');
    const [isSubmitted, setIsSubmitted] = useState(false);

    // Bootstrap data
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
                        contact: eq.contact_number ?? eq.contact,
                        status: eq.status_label ?? eq.status,
                        package_name: eq.package_name ?? null,
                    });
                })
                .catch(() => {});
        }
    }, [isPublic, isView, isEdit, enquiryDetails, initialData?.enquiry_id]);

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

    // Form
    const defaultData: CustomerGroupFormData = (initialData ?? {
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
    }) as CustomerGroupFormData;

    const form = useForm<CustomerGroupFormData>(defaultData);
    const { data, setData, post, processing, clearErrors, setError } = form;
    const errors: Record<string, string | undefined> = form.errors;

    // Helpers

    const getError = (path: string): string | undefined => {
        return errors[path];
    };

    const hasErrors = Object.keys(errors).length > 0;

    // Customer actions

    const addCustomer = () => {
        const next = [...(data.members ?? []), emptyMember(false)];
        setData('members', next);
        setActiveTab(`customer-${next.length - 1}`);
    };

    // Customer selection
    const selectedCustomerValues = customerOptions
        .filter((c) => data.members?.some((m) => m.email === c.email))
        .map((c) => String(c.value));

    const handleMultiSelectChange = (newValues: string[]) => {
        const currentCustomers = data.members ?? [];

        const newSelectedEmails = new Set(
            customerOptions
                .filter((c) => newValues.includes(String(c.value)))
                .map((c) => c.email),
        );

        const nextCustomers = currentCustomers.filter((customer) => {
            if (customer.is_leader) return true;
            const isFromExistingCustomer = customerOptions.some(
                (option) => option.email === customer.email,
            );
            return (
                !isFromExistingCustomer || newSelectedEmails.has(customer.email)
            );
        });

        for (const v of newValues) {
            const customer = customerOptions.find((c) => String(c.value) === v);
            if (!customer) continue;
            if (
                nextCustomers.some(
                    (existing) => existing.email === customer.email,
                )
            )
                continue;
            nextCustomers.push({
                ...emptyMember(false),
                name: customer.name,
                email: customer.email,
                contact_number: customer.contact_number,
                nric_number: customer.nric_number,
                address: customer.address,
                nationality: customer.nationality ?? '',
                passport_number: customer.passport_number ?? '',
                passport_issue_date: customer.passport_issue_date ?? '',
                passport_expiry_date: customer.passport_expiry_date ?? '',
                passport_place_of_issue: customer.passport_place_of_issue ?? '',
                gender: customer.gender ?? '',
                marital_status: customer.marital_status ?? '',
                date_of_birth: customer.date_of_birth ?? '',
                place_of_birth: customer.place_of_birth ?? '',
                first_time_umrah: customer.first_time_umrah ?? null,
                has_chronic_disease: customer.has_chronic_disease ?? false,
                chronic_disease_details: customer.chronic_disease_details ?? '',
                passport_path: customer.passport_path ?? null,
                photo_path: customer.photo_path ?? null,
            });
        }

        if (
            nextCustomers.length > 0 &&
            !nextCustomers.some((customer) => customer.is_leader)
        ) {
            nextCustomers[0] = { ...nextCustomers[0], is_leader: true };
        }

        setData('members', nextCustomers);
        setActiveTab(`customer-${Math.max(0, nextCustomers.length - 1)}`);
    };

    const removeCustomer = (index: number) => {
        const next = (data.members ?? []).filter((_, i) => i !== index);
        if (next.length > 0 && !next.some((customer) => customer.is_leader)) {
            next[0] = { ...next[0], is_leader: true };
        }
        setData('members', next);
        const newIdx = Math.min(index, next.length - 1);
        setActiveTab(`customer-${Math.max(0, newIdx)}`);
    };

    const updateCustomer = (
        index: number,
        field: keyof CustomerMemberFormData,
        value: string | boolean | File | null,
    ) => {
        setData((currentData) => {
            const next = [...(currentData.members ?? [])];

            if (field === 'is_leader' && value === true) {
                for (let i = 0; i < next.length; i++) {
                    next[i] = { ...next[i], is_leader: i === index };
                }
            } else {
                next[index] = { ...next[index], [field]: value };
            }

            return {
                ...currentData,
                members: next,
            };
        });
    };

    // Submit
    function validateClientSide(): boolean {
        clearErrors();
        const result = customerGroupFormValidationSchema.safeParse(data);

        if (!result.success) {
            const clientErrors: ClientValidationErrors = {};

            result.error.issues.forEach((issue) => {
                const key = issue.path.join('.');
                if (!clientErrors[key]) {
                    clientErrors[key] = issue.message;
                }
            });

            setError(clientErrors);

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
            const isPublicEdit = !!publicSubmitUrl;

            form.transform((currentData) => {
                if (isPublicEdit) {
                    return currentData;
                }

                return {
                    ...currentData,
                    _method: 'put',
                };
            });

            post(editUrl, {
                forceFormData: true,
                onSuccess: handleSuccess,
                onError: handleError,
                onFinish: () => {
                    form.transform((currentData) => currentData);
                },
            });
        } else {
            const effectiveEnquiryId =
                enquiryId ?? initialData?.enquiry_id ?? null;
            const submitUrl = publicSubmitUrl
                ? publicSubmitUrl
                : enquiryId
                  ? confirmEnquiry(enquiryId).url
                  : createCustomerGroup().url;

            form.transform((currentData) => ({
                ...currentData,
                enquiry_id: effectiveEnquiryId,
                ...(packageData && enquiryType === 'private'
                    ? { package_data: packageData }
                    : {}),
            }));

            post(submitUrl, {
                forceFormData: true,
                onSuccess: handleSuccess,
                onError: handleError,
                onFinish: () => {
                    form.transform((currentData) => currentData);
                },
            });
        }
    }

    // Derived data
    const effectiveLinkedEnquiry = enquiryDetails ?? linkedEnquiryInfo;

    return (
        <div className="mx-auto w-full">
            <form onSubmit={submit} className="space-y-4">
                {/* Success */}
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

                {/* Error */}
                {hasErrors && !isView && (
                    <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>
                            Please fix the errors below and try again.
                        </AlertDescription>
                    </Alert>
                )}

                {/* Linked enquiry */}
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
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                                <FormField label="Enquiry ID">
                                    <Input
                                        value={`#${effectiveLinkedEnquiry.id}`}
                                        disabled
                                    />
                                </FormField>
                                <FormField label="Type">
                                    <Input
                                        value={effectiveLinkedEnquiry.type}
                                        disabled
                                    />
                                </FormField>
                                <FormField label="Status">
                                    <Input
                                        value={effectiveLinkedEnquiry.status}
                                        disabled
                                    />
                                </FormField>
                                <FormField label="Name">
                                    <Input
                                        value={
                                            effectiveLinkedEnquiry.name || ''
                                        }
                                        disabled
                                    />
                                </FormField>
                                <FormField label="Email">
                                    <Input
                                        value={
                                            effectiveLinkedEnquiry.email || ''
                                        }
                                        disabled
                                    />
                                </FormField>
                                <FormField label="Contact">
                                    <Input
                                        value={
                                            effectiveLinkedEnquiry.contact || ''
                                        }
                                        disabled
                                    />
                                </FormField>
                                {effectiveLinkedEnquiry.package_name && (
                                    <FormField
                                        label="Package"
                                        className="md:col-span-2 lg:col-span-3"
                                    >
                                        <Input
                                            value={
                                                effectiveLinkedEnquiry.package_name
                                            }
                                            disabled
                                        />
                                    </FormField>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Group details */}
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

                {/* Members */}
                <Card>
                    <CardHeader className="grid grid-cols-1 md:grid-cols-2">
                        <div className="grid grid-cols-1 gap-0">
                            <CardTitle className="text-xl">
                                Group Customers ({data.members?.length ?? 0})
                            </CardTitle>
                            <CardDescription>
                                {isView
                                    ? 'View the details of the group customers.'
                                    : isEdit
                                      ? 'Update the details of the group customers.'
                                      : 'Fill in the details of the group customers.'}
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
                                    onClick={addCustomer}
                                >
                                    <Plus className="mr-1 h-4 w-4" />
                                    Add Customer
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
                                No customers added. Click &quot;Add
                                Customer&quot; to start building the group.
                            </p>
                        ) : (
                            <Tabs
                                value={activeTab}
                                onValueChange={setActiveTab}
                            >
                                <ScrollArea className="w-full whitespace-nowrap">
                                    <TabsList>
                                        {data.members?.map((customer, idx) => (
                                            <TabsTrigger
                                                key={idx}
                                                value={`customer-${idx}`}
                                                className="relative"
                                            >
                                                <span className="mr-1">
                                                    {customer.name ||
                                                        `Customer ${idx + 1}`}
                                                </span>
                                                {customer.is_leader && (
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

                                {data.members?.map((customer, idx) => (
                                    <TabsContent
                                        key={idx}
                                        value={`customer-${idx}`}
                                        className="space-y-2"
                                    >
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-4">
                                                <div className="flex items-center gap-2">
                                                    <input
                                                        type="radio"
                                                        name="leader"
                                                        checked={
                                                            customer.is_leader
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onChange={() =>
                                                            updateCustomer(
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
                                                            removeCustomer(idx)
                                                        }
                                                        className="text-red-500 hover:text-red-700"
                                                    >
                                                        <Trash2 className="mr-1 h-4 w-4" />
                                                        Remove
                                                    </Button>
                                                )}
                                        </div>

                                        <CustomerFormFields
                                            customer={customer}
                                            index={idx}
                                            isView={isView}
                                            processing={processing}
                                            getError={getError}
                                            onUpdateCustomer={(field, value) =>
                                                updateCustomer(
                                                    idx,
                                                    field,
                                                    value,
                                                )
                                            }
                                        />
                                    </TabsContent>
                                ))}
                            </Tabs>
                        )}
                    </CardContent>
                </Card>

                {/* Terms */}
                {isPublic && isCreate && (
                    <Card>
                        <CardContent>
                            <div className="flex items-start gap-3 pt-4">
                                <Checkbox
                                    id="terms_accepted"
                                    checked={data.terms_accepted}
                                    disabled={processing}
                                    onCheckedChange={(checked) => {
                                        const accepted = checked === true;
                                        setData('terms_accepted', accepted);
                                    }}
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

                {/* Actions */}
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
                {/* Enquiry dialog */}
                <EnquiryViewDialog
                    open={enquiryDialogOpen}
                    onOpenChange={setEnquiryDialogOpen}
                    enquiryId={effectiveLinkedEnquiry?.id}
                    enquiryType={effectiveLinkedEnquiry?.type}
                    statusLabel={effectiveLinkedEnquiry?.status}
                    childData={
                        linkedEnquiryChild as Record<string, unknown> | null
                    }
                    isLoadingChild={isLoadingEnquiryChild}
                    showStatusActions={false}
                />
            </form>
        </div>
    );
}
