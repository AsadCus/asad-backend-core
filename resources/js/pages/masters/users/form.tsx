import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import CustomerFormFields from '@/pages/customer/form-fields';
import { OptionType, SharedData } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { AdminFormFields } from './components/admin-form-fields';
import { UserPasswordFields } from './components/password-fields';
import { SalesFormFields } from './components/sales-form-fields';
import { SupplierFormFields } from './components/supplier-form-fields';
import { UserSchema, validateUserData } from './schema';

type UserFormData = UserSchema & {
    _method?: 'put';
};

interface UserFormProps {
    mode: 'create' | 'edit' | 'view';
    initialData?: UserSchema;
    onSubmit?: (values: UserSchema) => void;
    onCancel?: () => void;
    branches?: OptionType[];
    roles?: OptionType[];
    salesList?: OptionType[];
    isAdmin?: boolean;
    isSales?: boolean;
    isSupplier?: boolean;
    isCustomer?: boolean;
    submitUrl?: string;
}

export function UserForm({
    mode,
    initialData,
    onCancel,
    branches = [],
    roles = [],
    salesList = [],
    isAdmin,
    isSales,
    isSupplier,
    isCustomer,
    submitUrl,
}: UserFormProps) {
    const isView = mode === 'view';
    const isEdit = mode === 'edit';
    const isCreate = mode === 'create';

    const getDeterminedRole = () => {
        if (isAdmin) return 'admin';
        if (isSales) return 'sales';
        if (isSupplier) return 'supplier';
        if (isCustomer) return 'customer';
        return 'admin';
    };

    const determinedRole = getDeterminedRole();

    const initialFormState: UserSchema = {
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        send_email: false,
        contact: '',
        role: determinedRole,
        branch_id: '',
        company_name: '',
        nric_number: '',
        address: '',
        nationality: '',
        passport_number: '',
        passport_issue_date: '',
        passport_expiry_date: '',
        passport_place_of_issue: '',
        gender: '',
        marital_status: '',
        date_of_birth: '',
        place_of_birth: '',
        first_time_umrah: null,
        has_chronic_disease: false,
        chronic_disease_details: '',
        passport_file: undefined,
        photo_file: undefined,
        passport_path: null,
        photo_path: null,
        handled_by: '',
        registration_number: '',
    };

    const { auth } = usePage<SharedData>().props;
    if (auth?.roles?.includes('sales')) {
        initialFormState.branch_id = String(auth?.user?.sales?.branch_id ?? '');
        initialFormState.handled_by = String(auth?.user?.id ?? '');
    }

    let defaultData: UserSchema;

    if (initialData) {
        defaultData = {
            ...initialData,
            password: '',
            password_confirmation: '',
        };

        if (auth?.roles?.includes('sales')) {
            defaultData.branch_id = String(auth?.user?.sales?.branch_id ?? '');
            defaultData.registration_number = String(
                auth?.user?.sales?.registration_number ?? '',
            );
            defaultData.handled_by = String(auth?.user?.id ?? '');
        }
    } else {
        defaultData = initialFormState;
    }

    const form = useForm<UserFormData>(defaultData as UserFormData);
    const {
        data,
        setData,
        post,
        processing,
        errors,
        setError,
        clearErrors,
        transform,
    } = form;
    const selectedBranchId = data.branch_id;
    const filteredSalesList = salesList.filter(
        (sale: OptionType & { branch_id?: string | number | null }) => {
            if (!selectedBranchId) return false;
            return (
                !sale.branch_id ||
                String(sale.branch_id) === String(selectedBranchId)
            );
        },
    );

    const [role, setRole] = useState(data?.role ?? 'admin');

    useEffect(() => {
        if (
            data.role === 'customer' &&
            data.handled_by &&
            selectedBranchId &&
            !filteredSalesList.find(
                (sale) => String(sale.value) === String(data.handled_by),
            )
        ) {
            setData('handled_by', '');
        }
    }, [
        selectedBranchId,
        data.handled_by,
        data.role,
        filteredSalesList,
        setData,
    ]);

    const validatePassword = (password: string) => {
        if (!password) {
            return '';
        }

        const minLength = /.{6,}/;
        const hasNumber = /\d/;
        const hasSymbol = /[^A-Za-z0-9]/;

        if (!minLength.test(password))
            return 'Password must be at least 6 characters.';
        if (!hasNumber.test(password))
            return 'Password must contain at least one number.';
        if (!hasSymbol.test(password))
            return 'Password must contain at least one symbol.';
        return '';
    };

    const generatePassword = () => {
        const length = 10;
        const chars =
            'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()_+';
        let password = '';
        do {
            password = Array.from(
                { length },
                () => chars[Math.floor(Math.random() * chars.length)],
            ).join('');
        } while (validatePassword(password));
        setData('password', password);
        setData('password_confirmation', password);
        clearErrors('password');
        clearErrors('password_confirmation');
    };

    function validateClientSide() {
        const result = validateUserData(data, mode);

        clearErrors();

        if (!result.success) {
            result.error.issues.forEach((issue) => {
                const key = String(issue.path[0]) as keyof UserSchema;
                setError(key, issue.message);
            });
            return false;
        }

        return true;
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();

        if (!validateClientSide()) return;

        const isPasswordProvided = !!data.password;

        if (isCreate || (isEdit && isPasswordProvided)) {
            if (data.password !== data.password_confirmation) {
                setError('password_confirmation', 'Passwords do not match.');
                return;
            }

            const passwordError = validatePassword(data.password ?? '');
            if (passwordError) {
                setError('password', passwordError);
                return;
            }
        }

        if (isEdit && !isPasswordProvided) {
            clearErrors('password');
            clearErrors('password_confirmation');
        }

        let url = submitUrl || '/master/user';

        if (!submitUrl) {
            if (isSales) {
                url = '/sales';
            } else if (isSupplier) {
                url = '/supplier';
            } else if (isCustomer) {
                url = '/customer';
            }
        }

        if (isCreate) {
            post(url, {
                forceFormData: true,
                onError: (validationErrors: Record<string, string>) => {
                    setError(validationErrors);
                    console.error(validationErrors);
                },
            });
        } else if (isEdit) {
            const editUrl = `${url}/${data.id}`;
            transform((currentData: UserFormData) => ({
                ...currentData,
                _method: 'put',
            }));
            post(editUrl, {
                forceFormData: true,
                onError: (validationErrors: Record<string, string>) => {
                    setError(validationErrors);
                    console.error(validationErrors);
                },
                onFinish: () => {
                    transform((currentData: UserFormData) => currentData);
                },
            });
        }
    }

    const getCustomerFieldError = (path: string): string | undefined => {
        return errors[path as keyof UserSchema] as string | undefined;
    };

    const customerData = {
        customer_number: data.customer_number ?? '',
        is_leader: true,
        name: data.name ?? '',
        email: data.email ?? '',
        contact_number: data.contact ?? '',
        nric_number: data.nric_number ?? '',
        address: data.address ?? '',
        nationality: data.nationality ?? '',
        passport_number: data.passport_number ?? '',
        passport_issue_date: data.passport_issue_date ?? '',
        passport_expiry_date: data.passport_expiry_date ?? '',
        passport_place_of_issue: data.passport_place_of_issue ?? '',
        gender: data.gender ?? '',
        marital_status: data.marital_status ?? '',
        date_of_birth: data.date_of_birth ?? '',
        place_of_birth: data.place_of_birth ?? '',
        first_time_umrah: data.first_time_umrah ?? null,
        has_chronic_disease: data.has_chronic_disease ?? false,
        chronic_disease_details: data.chronic_disease_details ?? '',
        passport_file: data.passport_file,
        photo_file: data.photo_file,
        passport_path: data.passport_path ?? null,
        photo_path: data.photo_path ?? null,
    };

    const updateCustomerField = (
        field: string,
        value: string | boolean | File | null,
    ) => {
        if (field === 'contact_number') {
            setData('contact', String(value ?? ''));

            return;
        }

        if (field === 'is_leader') {
            return;
        }

        const normalizedField = field as keyof UserFormData;
        setData(
            normalizedField,
            value as unknown as UserFormData[typeof normalizedField],
        );
    };

    return (
        <div className="mx-auto w-full">
            <form onSubmit={submit} className="space-y-4">
                <Card>
                    <CardContent className="space-y-6">
                        {isAdmin === false &&
                            isSales === false &&
                            isSupplier === false &&
                            isCustomer === false && (
                                <div className="space-y-4">
                                    <h3 className="text-xl font-semibold">
                                        User Role
                                    </h3>
                                    <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-3">
                                        <FormField
                                            label="Role"
                                            error={errors.role}
                                        >
                                            <Select
                                                disabled={
                                                    isView ||
                                                    isEdit ||
                                                    isAdmin ||
                                                    isSales ||
                                                    isSupplier ||
                                                    isCustomer
                                                }
                                                value={data.role}
                                                onValueChange={(value) => {
                                                    const nextRole =
                                                        value as UserSchema['role'];
                                                    setData('role', nextRole);
                                                    setRole(nextRole);
                                                }}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Select role" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {roles.map((r) => {
                                                        return (
                                                            <SelectItem
                                                                key={r.value}
                                                                value={String(
                                                                    r.value,
                                                                )}
                                                            >
                                                                {r.label}
                                                            </SelectItem>
                                                        );
                                                    })}
                                                </SelectContent>
                                            </Select>
                                        </FormField>
                                    </div>
                                </div>
                            )}

                        {role === 'admin' && (
                            <AdminFormFields
                                data={{
                                    name: data.name,
                                    email: data.email,
                                    contact: data.contact,
                                }}
                                errors={
                                    errors as Partial<
                                        Record<keyof UserSchema, string>
                                    >
                                }
                                isView={isView}
                                onChange={(field, value) =>
                                    setData(field, value)
                                }
                            />
                        )}

                        {role === 'sales' && (
                            <SalesFormFields
                                data={{
                                    name: data.name,
                                    email: data.email,
                                    contact: data.contact,
                                    branch_id: data.branch_id,
                                }}
                                errors={
                                    errors as Partial<
                                        Record<keyof UserSchema, string>
                                    >
                                }
                                branches={branches}
                                isView={isView}
                                isSalesUser={auth.roles.includes('sales')}
                                onChange={(field, value) => {
                                    setData(field, value);

                                    if (field === 'branch_id') {
                                        setData('handled_by', '');
                                    }
                                }}
                            />
                        )}

                        {role === 'supplier' && (
                            <SupplierFormFields
                                data={{
                                    name: data.name,
                                    email: data.email,
                                    contact: data.contact,
                                    company_name: data.company_name,
                                    address: data.address,
                                }}
                                errors={
                                    errors as Partial<
                                        Record<keyof UserSchema, string>
                                    >
                                }
                                isView={isView}
                                onChange={(field, value) =>
                                    setData(field, value)
                                }
                            />
                        )}

                        {role === 'customer' && (
                            <CustomerFormFields
                                customer={customerData}
                                isView={isView}
                                processing={processing}
                                getError={getCustomerFieldError}
                                onUpdateCustomer={(field, value) =>
                                    updateCustomerField(String(field), value)
                                }
                            />
                        )}

                        {/* Password & Confirm Password */}
                        {!isView &&
                            role !== 'supplier' &&
                            role !== 'customer' && (
                                <UserPasswordFields
                                    password={data.password ?? ''}
                                    passwordConfirmation={
                                        data.password_confirmation ?? ''
                                    }
                                    sendEmail={data.send_email ?? false}
                                    isView={isView}
                                    onGenerateRandom={generatePassword}
                                    onPasswordChange={(value) => {
                                        clearErrors('password');
                                        setData('password', value);
                                    }}
                                    onPasswordConfirmationChange={(value) => {
                                        clearErrors('password_confirmation');
                                        setData('password_confirmation', value);
                                    }}
                                    onSendEmailChange={(checked) =>
                                        setData('send_email', checked)
                                    }
                                    passwordError={errors.password}
                                    passwordConfirmationError={
                                        errors.password_confirmation
                                    }
                                />
                            )}
                    </CardContent>
                </Card>

                {/* Buttons */}
                <div className="flex justify-end gap-4">
                    {onCancel && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onCancel}
                        >
                            Back
                        </Button>
                    )}
                    {!isView && (
                        <Button
                            type="submit"
                            className="min-w-[140px]"
                            disabled={processing}
                        >
                            {isEdit ? 'Update' : 'Create'}
                        </Button>
                    )}
                </div>
            </form>
        </div>
    );
}
