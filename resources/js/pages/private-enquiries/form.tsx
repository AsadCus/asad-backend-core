import { FormField } from '@/components/form-field';
import { ProperInputSelect } from '@/components/proper-input-select';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { listCustomers } from '@/routes/enquiries';
import { OptionType } from '@/types';
import { useForm } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import { type CustomerOption } from '../customer/schema';
import EnquiryScopeCard from '../enquiries/components/enquiry-scope-card';
import PrivateEnquiryFormFields, { internalFieldOptions } from './form-fields';
import { PrivateEnquirySchema } from './schema';
import { privateEnquiryValidationSchema } from './validation';

export type PrivateEnquiryFormSchema = PrivateEnquirySchema;

interface PrivateEnquiryFormProps {
    mode: 'create' | 'edit' | 'view';
    initialData?: PrivateEnquirySchema;
    onCancel?: () => void;
    branchOptions?: OptionType[];
    countryOptions?: OptionType[];
    scopeMode?: 'country' | 'branch';
}

export default function PrivateEnquiryForm({
    mode,
    initialData,
    onCancel,
    branchOptions = [],
    countryOptions = [],
    scopeMode = 'country',
}: PrivateEnquiryFormProps) {
    const isView = mode === 'view';
    const isEdit = mode === 'edit';
    const isCreate = mode === 'create';

    const defaultData: PrivateEnquirySchema = initialData || {
        enquiry_number: '',
        number_format_id: null,
        name: '',
        contact_number: '',
        email: '',
        branch_id: null,
        country_id: null,
        passport_expiry_date: '',
        departure_date: '',
        return_date: '',
        no_of_pax: 1,
        no_of_children: 0,
        airline: '',
        class: '',
        require_mutawif: false,
        require_umrah_course: false,
        require_umrah_official: false,
        makkah_or_madinah_first: '',
        no_of_nights_makkah: '',
        hotel_makkah: '',
        meals_makkah: '',
        no_of_nights_madinah: '',
        hotel_madinah: '',
        meals_madinah: '',
        land_transfer: '',
        add_on_speed_train: false,
        require_meet_greet: false,
        require_mutawiffah_ustazah_rawdah: false,
        madinah_tour_with_mutawif: false,
        makkah_tour_with_mutawif: false,
        has_chronic_disease: false,
        chronic_disease_details: '',
        need_wheelchair: '',
        other_remarks: '',
    };

    const {
        data,
        setData,
        post,
        put,
        processing,
        errors,
        reset,
        setError,
        clearErrors,
    } = useForm<PrivateEnquirySchema>(defaultData);

    const [customerOptions, setCustomerOptions] = useState<CustomerOption[]>(
        [],
    );
    const [selectedExistingCustomerId, setSelectedExistingCustomerId] =
        useState<string>('');

    useEffect(() => {
        if (!isCreate || isView) {
            return;
        }

        fetch(listCustomers().url)
            .then((response) => response.json())
            .then((rows) => {
                setCustomerOptions(rows as CustomerOption[]);
            })
            .catch(() => {
                setCustomerOptions([]);
            });
    }, [isCreate, isView]);

    function validateClientSide(): boolean {
        clearErrors();
        let valid = true;

        const result = privateEnquiryValidationSchema.safeParse(data);
        if (!result.success) {
            result.error.issues.forEach((issue) => {
                const key = issue.path.join('.') as keyof PrivateEnquirySchema;
                if (typeof key === 'string') {
                    setError(key, issue.message);
                }
            });
            valid = false;
        }

        if (scopeMode === 'branch' && !data.branch_id) {
            setError('branch_id', 'Branch is required.');
            valid = false;
        }

        if (scopeMode === 'country' && !data.country_id) {
            setError('country_id', 'Country is required.');
            valid = false;
        }

        return valid;
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();

        if (!validateClientSide()) return;

        const url = '/private-enquiries';

        if (isCreate) {
            post(url, {
                onError: (errors) => setError(errors),
            });
        } else if (isEdit) {
            put(`${url}/${data.id}`, {
                onError: (errors) => setError(errors),
            });
        }
    }

    const renderError = (path: string) => {
        const errorMap = errors as Record<string, string | undefined>;
        const message = errorMap[path];
        if (!message) return null;
        return <p className="mt-1 text-sm text-red-500">{message}</p>;
    };

    const handleReset = () => {
        reset();
    };

    return (
        <div className="mx-auto w-full">
            <form onSubmit={submit} className="space-y-4">
                {/* Error Summary Banner */}
                {/* Error Alert */}
                {Object.keys(errors).length > 0 && !isView && (
                    <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>
                            Please fix the errors below and try again
                        </AlertDescription>
                    </Alert>
                )}

                {isView && data.enquiry_number && (
                    <div className="rounded-lg border border-primary/20 bg-primary/5 p-4">
                        <p className="text-base text-muted-foreground">
                            Enquiry Number
                        </p>
                        <p className="text-2xl font-bold text-primary">
                            {data.enquiry_number}
                        </p>
                    </div>
                )}

                <EnquiryScopeCard
                    scopeMode={scopeMode}
                    branchOptions={branchOptions}
                    countryOptions={countryOptions}
                    branchId={data.branch_id ?? null}
                    countryId={data.country_id ?? null}
                    isView={isView}
                    processing={processing}
                    onBranchChange={(branchId) =>
                        setData('branch_id', branchId)
                    }
                    onCountryChange={(countryId) =>
                        setData('country_id', countryId)
                    }
                    renderError={renderError}
                />

                <Card>
                    <CardHeader className="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div className="grid grid-cols-1 gap-0">
                            <CardTitle className="text-xl">
                                {isView
                                    ? 'View Private Enquiry'
                                    : isEdit
                                      ? 'Edit Private Enquiry'
                                      : 'Create Private Enquiry'}
                            </CardTitle>
                            <CardDescription>
                                {isView
                                    ? 'Details of the private enquiry.'
                                    : isEdit
                                      ? 'Modify the details of the private enquiry and submit to save changes.'
                                      : 'Fill in the details of the private enquiry and submit.'}
                            </CardDescription>
                        </div>

                        {isCreate && !isView && customerOptions.length > 0 && (
                            <div className="flex flex-col justify-start md:flex-row md:justify-end">
                                <div className="w-full md:w-auto md:max-w-[320px]">
                                    <FormField
                                        label="Existing Customer"
                                        fieldRequirementsProps={{
                                            hint: 'Optional: select a customer to auto-fill contact details.',
                                        }}
                                    >
                                        <ProperInputSelect
                                            id="existing_customer_id"
                                            options={customerOptions.map(
                                                (customer) => ({
                                                    value: String(
                                                        customer.value,
                                                    ),
                                                    label: customer.label,
                                                }),
                                            )}
                                            value={selectedExistingCustomerId}
                                            onValueChange={(nextValue) => {
                                                if (Array.isArray(nextValue)) {
                                                    return;
                                                }

                                                const nextId = String(
                                                    nextValue ?? '',
                                                ).trim();
                                                setSelectedExistingCustomerId(
                                                    nextId,
                                                );

                                                if (nextId.length === 0) {
                                                    setData('name', '');
                                                    setData(
                                                        'contact_number',
                                                        '',
                                                    );
                                                    setData('email', '');

                                                    return;
                                                }

                                                const selectedCustomer =
                                                    customerOptions.find(
                                                        (customer) =>
                                                            String(
                                                                customer.value,
                                                            ) === nextId,
                                                    );

                                                if (!selectedCustomer) {
                                                    return;
                                                }

                                                setData(
                                                    'name',
                                                    selectedCustomer.name ?? '',
                                                );
                                                setData(
                                                    'contact_number',
                                                    selectedCustomer.contact_number ??
                                                        '',
                                                );
                                                setData(
                                                    'email',
                                                    selectedCustomer.email ??
                                                        '',
                                                );
                                            }}
                                            placeholder="Search & select customer..."
                                            maxWidth="320px"
                                            responsive={true}
                                            disabled={processing}
                                        />
                                    </FormField>
                                </div>
                            </div>
                        )}
                    </CardHeader>
                    <CardContent>
                        <PrivateEnquiryFormFields
                            data={data}
                            setData={setData}
                            renderError={renderError}
                            isView={isView}
                            processing={processing}
                            options={internalFieldOptions}
                        />
                    </CardContent>
                </Card>

                {/* Action Buttons */}
                <div className="flex justify-end gap-4">
                    {onCancel && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onCancel}
                            disabled={processing}
                        >
                            Back
                        </Button>
                    )}
                    {!isView && (
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={handleReset}
                                disabled={processing}
                            >
                                Reset
                            </Button>
                            <Button
                                type="submit"
                                className="min-w-[140px]"
                                disabled={processing}
                            >
                                {isEdit ? 'Update' : 'Create'}
                            </Button>
                        </>
                    )}
                </div>
            </form>
        </div>
    );
}
