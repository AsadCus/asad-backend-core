import { MultiSelect } from '@/components/multi-select';
import { ProperInputSelect } from '@/components/proper-input-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { ages, experiences } from '@/lib/utils';
import { OptionType, SharedData } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { Eye, EyeOff, RefreshCcw } from 'lucide-react';
import { useEffect, useState } from 'react';
import { userSchema, UserSchema } from './schema';

interface UserFormProps {
    mode: 'create' | 'edit' | 'view';
    initialData?: UserSchema;
    onSubmit?: (values: UserSchema) => void;
    onCancel?: () => void;
    branches?: OptionType[];
    countries?: OptionType[];
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
    countries = [],
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
        age_preferences: [],
        country_preferences: [],
        experience_preferences: [],
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

    const {
        data,
        setData,
        post,
        put,
        processing,
        errors,
        setError,
        clearErrors,
    } = useForm<UserSchema>(defaultData);

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

    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);

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
        const result = userSchema.safeParse(data);

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
                onError: (errors) => {
                    setError(errors);
                    console.error(errors);
                },
            });
        } else if (isEdit) {
            put(`${url}/${data.id}`, {
                onError: (errors) => {
                    setError(errors);
                    console.error(errors);
                },
            });
        }
    }

    const renderError = (field: keyof UserSchema) =>
        errors[field] && (
            <p className="absolute -bottom-4 left-0 text-xs text-red-500">
                {errors[field]}
            </p>
        );

    return (
        <div
            className="mx-auto max-h-[90vh] w-full overflow-y-auto p-2"
            style={{
                scrollbarWidth: 'none',
                msOverflowStyle: 'none',
            }}
        >
            <form onSubmit={submit} className="space-y-4">
                {/* Profile */}
                <p className="text-l border-b pb-2 font-semibold">Profile</p>
                <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-3">
                    {/* Name */}
                    <div className="grid w-full items-center gap-3">
                        <Label htmlFor="name">Name</Label>
                        <div className="relative">
                            <Input
                                type="text"
                                id="name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                placeholder="Name"
                                disabled={isView}
                            />
                            {renderError('name')}
                        </div>
                    </div>

                    {/* Email */}
                    <div className="grid w-full items-center gap-3">
                        <Label htmlFor="email">Email</Label>
                        <div className="relative">
                            <Input
                                type="email"
                                id="email"
                                value={data.email}
                                onChange={(e) =>
                                    setData('email', e.target.value)
                                }
                                placeholder="Email"
                                disabled={isView}
                            />
                            {renderError('email')}
                        </div>
                    </div>

                    {/* Contact */}
                    <div className="grid w-full items-center gap-3">
                        <Label htmlFor="contact">Contact</Label>
                        <div className="relative">
                            <Input
                                type="text"
                                id="contact"
                                value={data.contact}
                                onChange={(e) =>
                                    setData('contact', e.target.value)
                                }
                                placeholder="Contact"
                                disabled={isView}
                            />
                            {renderError('contact')}
                        </div>
                    </div>

                    {/* Role */}
                    {isAdmin === false &&
                        isSales === false &&
                        isSupplier === false &&
                        isCustomer === false && (
                            <div className="grid w-full items-center gap-3">
                                <Label>Role</Label>
                                <div className="relative">
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
                                            setData('role', value);
                                            setRole(value);
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
                                                        value={String(r.value)}
                                                    >
                                                        {r.label}
                                                    </SelectItem>
                                                );
                                            })}
                                        </SelectContent>
                                    </Select>
                                    {renderError('role')}
                                </div>
                            </div>
                        )}
                </div>

                {/* Conditional Fields */}
                {(role === 'sales' ||
                    role === 'supplier' ||
                    role === 'customer') && (
                    <>
                        <p className="text-l border-b py-2 font-semibold capitalize">
                            {role}
                            {role === 'customer' && data.customer_number && (
                                <>
                                    {' '}
                                    <span className="text-muted-foreground">
                                        (Customer No.{' '}
                                        <span className="text-primary">
                                            {data.customer_number}
                                        </span>
                                        )
                                    </span>
                                </>
                            )}
                        </p>
                        <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-3">
                            {/* branch */}
                            {(role === 'sales' ||
                                (role === 'customer' && !isCustomer)) && (
                                <div className="grid w-full items-center gap-3">
                                    <Label>Branch</Label>
                                    <div className="relative">
                                        <ProperInputSelect
                                            disabled={
                                                isView ||
                                                auth.roles.includes('sales')
                                            }
                                            options={branches}
                                            value={data.branch_id}
                                            onValueChange={(value) => {
                                                setData(
                                                    'branch_id',
                                                    String(value),
                                                );
                                                setData('handled_by', '');
                                            }}
                                            placeholder="Select branch"
                                        />
                                        {renderError('branch_id')}
                                    </div>
                                </div>
                            )}

                            {/* company name */}
                            {role === 'supplier' && (
                                <div className="grid w-full items-center gap-3">
                                    <Label htmlFor="company_name">
                                        Company Name
                                    </Label>
                                    <div className="relative">
                                        <Input
                                            type="text"
                                            id="company_name"
                                            value={data.company_name}
                                            onChange={(e) =>
                                                setData(
                                                    'company_name',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Enter company name"
                                            disabled={isView}
                                        />
                                        {renderError('company_name')}
                                    </div>
                                </div>
                            )}

                            {/* sales */}
                            {auth.roles.includes('admin') &&
                                role === 'customer' &&
                                !isCustomer && (
                                    <div className="grid w-full items-center gap-3">
                                        <Label>Sales</Label>
                                        <div className="relative">
                                            <ProperInputSelect
                                                disabled={
                                                    isView ||
                                                    !data.branch_id ||
                                                    auth.roles.includes('sales')
                                                }
                                                options={filteredSalesList}
                                                value={data.handled_by ?? ''}
                                                onValueChange={(value) => {
                                                    setData(
                                                        'handled_by',
                                                        String(value),
                                                    );
                                                }}
                                                placeholder="Select sales"
                                            />
                                            {renderError('handled_by')}
                                        </div>
                                    </div>
                                )}

                            {/* nric_number */}
                            {role === 'customer' && (
                                <div className="grid w-full items-center gap-3">
                                    <Label htmlFor="nric_number">
                                        NRIC No.
                                    </Label>
                                    <div className="relative">
                                        <Input
                                            type="text"
                                            id="nric_number"
                                            value={data.nric_number}
                                            onChange={(e) =>
                                                setData(
                                                    'nric_number',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Enter NRIC No."
                                            disabled={isView}
                                        />
                                        {renderError('nric_number')}
                                    </div>
                                </div>
                            )}

                            {/* address */}
                            {(role === 'supplier' || role === 'customer') && (
                                <div className="grid w-full items-center gap-3">
                                    <Label htmlFor="address">Address</Label>

                                    <div className="relative">
                                        <Textarea
                                            id="address"
                                            value={
                                                data.address
                                                    ? data.address.replace(
                                                          /<br>/g,
                                                          '\n',
                                                      )
                                                    : ''
                                            }
                                            onChange={(e) =>
                                                setData(
                                                    'address',
                                                    e.target.value.replace(
                                                        /\n/g,
                                                        '<br>',
                                                    ),
                                                )
                                            }
                                            onKeyDown={(e) => {
                                                if (
                                                    e.key === 'Enter' &&
                                                    !e.shiftKey
                                                ) {
                                                    e.preventDefault();
                                                    const textarea =
                                                        e.currentTarget;
                                                    const start =
                                                        textarea.selectionStart;
                                                    const currentValue =
                                                        textarea.value;
                                                    const newValue =
                                                        currentValue.substring(
                                                            0,
                                                            start,
                                                        ) +
                                                        '\n' +
                                                        currentValue.substring(
                                                            start,
                                                        );
                                                    setData(
                                                        'address',
                                                        newValue.replace(
                                                            /\n/g,
                                                            '<br>',
                                                        ),
                                                    );
                                                    setTimeout(() => {
                                                        textarea.selectionStart =
                                                            textarea.selectionEnd =
                                                                start + 1;
                                                    }, 0);
                                                } else if (
                                                    e.key === 'Enter' &&
                                                    e.shiftKey
                                                ) {
                                                    e.preventDefault();
                                                    const textarea =
                                                        e.currentTarget;
                                                    const start =
                                                        textarea.selectionStart;
                                                    const currentValue =
                                                        textarea.value;
                                                    const newValue =
                                                        currentValue.substring(
                                                            0,
                                                            start,
                                                        ) +
                                                        '<br>' +
                                                        currentValue.substring(
                                                            start,
                                                        );
                                                    setData(
                                                        'address',
                                                        newValue,
                                                    );
                                                    setTimeout(() => {
                                                        textarea.selectionStart =
                                                            textarea.selectionEnd =
                                                                start + 4;
                                                    }, 0);
                                                }
                                            }}
                                            placeholder="Enter address (Enter for line break, Shift+Enter for &lt;br&gt; tag)"
                                            disabled={isView}
                                        />
                                        {renderError('address')}
                                    </div>
                                </div>
                            )}

                            {/* preference */}
                            {role === 'customer' && !isCustomer && (
                                <>
                                    <div className="grid w-full items-center gap-3">
                                        <Label htmlFor="age_preferences">
                                            Age Preferences
                                        </Label>
                                        <div className="relative">
                                            <MultiSelect
                                                disabled={isView}
                                                options={ages}
                                                placeholder="Select ages"
                                                defaultValue={
                                                    data.age_preferences
                                                }
                                                onValueChange={(e) => {
                                                    return setData(
                                                        'age_preferences',
                                                        e,
                                                    );
                                                }}
                                                responsive={true}
                                                minWidth="0px"
                                            />
                                            {renderError('age_preferences')}
                                        </div>
                                    </div>
                                    <div className="grid w-full items-center gap-3">
                                        <Label htmlFor="country_preferences">
                                            Country Preferences
                                        </Label>
                                        <div className="relative">
                                            <MultiSelect
                                                disabled={isView}
                                                options={countries}
                                                placeholder="Select countries"
                                                defaultValue={
                                                    data.country_preferences
                                                }
                                                onValueChange={(e) => {
                                                    return setData(
                                                        'country_preferences',
                                                        e,
                                                    );
                                                }}
                                                responsive={true}
                                                minWidth="0px"
                                            />
                                            {renderError('country_preferences')}
                                        </div>
                                    </div>
                                    <div className="grid w-full items-center gap-3">
                                        <Label htmlFor="experience_preferences">
                                            Experience Preferences
                                        </Label>
                                        <div className="relative">
                                            <MultiSelect
                                                disabled={isView}
                                                options={experiences}
                                                placeholder="Select experiences"
                                                defaultValue={
                                                    data.experience_preferences
                                                }
                                                onValueChange={(e) => {
                                                    return setData(
                                                        'experience_preferences',
                                                        e,
                                                    );
                                                }}
                                                responsive={true}
                                                minWidth="0px"
                                            />
                                            {renderError(
                                                'experience_preferences',
                                            )}
                                        </div>
                                    </div>
                                </>
                            )}
                        </div>
                    </>
                )}

                {/* Password & Confirm Password */}
                {!isView && role !== 'supplier' && (
                    <>
                        <div className="flex flex-row items-center justify-between border-b py-2">
                            <p className="text-l font-semibold">Password</p>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={generatePassword}
                                className="flex w-fit items-center gap-1"
                            >
                                <RefreshCcw size={14} />
                                Generate Random
                            </Button>
                        </div>
                        <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-3">
                            {/* Password */}
                            <div className="relative grid w-full items-center gap-2">
                                <Label htmlFor="password">Password</Label>
                                <div className="relative">
                                    <Input
                                        type={
                                            showPassword ? 'text' : 'password'
                                        }
                                        id="password"
                                        value={data.password}
                                        onChange={(e) => {
                                            clearErrors('password');
                                            setData('password', e.target.value);
                                        }}
                                        placeholder="Password"
                                        disabled={isView}
                                        className="pr-10"
                                    />
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setShowPassword(!showPassword)
                                        }
                                        className="absolute top-2.5 right-2 text-gray-500 hover:text-gray-700"
                                    >
                                        {showPassword ? (
                                            <EyeOff size={18} />
                                        ) : (
                                            <Eye size={18} />
                                        )}
                                    </button>
                                </div>
                                {renderError('password')}
                            </div>

                            {/* Confirm Password */}
                            <div className="relative grid w-full items-center gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirm Password
                                </Label>
                                <div className="relative">
                                    <Input
                                        type={
                                            showConfirmPassword
                                                ? 'text'
                                                : 'password'
                                        }
                                        id="password_confirmation"
                                        value={data.password_confirmation}
                                        onChange={(e) => {
                                            clearErrors(
                                                'password_confirmation',
                                            );
                                            setData(
                                                'password_confirmation',
                                                e.target.value,
                                            );
                                        }}
                                        placeholder="Confirm Password"
                                        disabled={isView}
                                        className="pr-10"
                                    />
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setShowConfirmPassword(
                                                !showConfirmPassword,
                                            )
                                        }
                                        className="absolute top-2.5 right-2 text-gray-500 hover:text-gray-700"
                                    >
                                        {showConfirmPassword ? (
                                            <EyeOff size={18} />
                                        ) : (
                                            <Eye size={18} />
                                        )}
                                    </button>
                                </div>
                                {renderError('password_confirmation')}
                            </div>
                        </div>
                        <div className="col-span-3 flex items-center space-x-2">
                            <input
                                id="send_email"
                                type="checkbox"
                                checked={data.send_email ?? false}
                                onChange={(e) =>
                                    setData('send_email', e.target.checked)
                                }
                                disabled={isView}
                                aria-label="Send email access to this user"
                            />
                            <Label htmlFor="send_email">
                                Send email access to this user (optional)
                            </Label>
                        </div>
                    </>
                )}

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
