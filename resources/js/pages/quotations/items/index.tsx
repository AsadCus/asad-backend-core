import { FormField } from '@/components/form-field';
import { ProperInput } from '@/components/proper-input';
import { ProperInputSelect } from '@/components/proper-input-select';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/app-layout';
import NoteForm from '@/pages/notes/form';
import { NoteSchema } from '@/pages/notes/schema';
import QuotationItemTableForm from '@/pages/quotations/items/form';
import {
    QuotationItemSchema,
    quotationItemsSchema,
} from '@/pages/quotations/items/schema';
import { quotationExtensionSchema } from '@/pages/quotations/schema';
import master, { index as masterIndex } from '@/routes/master';
import quotationItemRoutes, { index } from '@/routes/quotation-items';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, Trash2 } from 'lucide-react';
import { nanoid } from 'nanoid';
import { toast } from 'sonner';
import { z } from 'zod';

type QuotationExtensionMasterSchema = z.infer<
    typeof quotationExtensionSchema
> & {
    payment_methods?: string[];
    is_active?: boolean;
};

interface PaymentMethodMasterSchema {
    _key?: string;
    id?: number;
    name: string;
    value?: string;
    is_active?: boolean;
    is_default?: boolean;
    sort_order?: number;
}

interface MastersQuotationIndexProps {
    quotationItems?: QuotationItemSchema[];
    quotationMasterNote?: NoteSchema[];
    quotationExtensionMasters?: QuotationExtensionMasterSchema[];
    paymentMethods?: { label: string; value: string }[];
    paymentMethodMasters?: PaymentMethodMasterSchema[];
}

const extensionTypeOrder = ['tax', 'discount', 'credit_card', 'other'];

const extensionTypeLabels: Record<string, string> = {
    tax: 'Tax Extensions',
    discount: 'Discount Extensions',
    credit_card: 'Credit Card Extensions',
    other: 'Other Extensions',
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Master',
        href: masterIndex().url,
    },
    {
        title: 'Products & Services',
        href: index().url,
    },
];

export default function MastersQuotationIndex({
    quotationItems = [],
    quotationMasterNote = [],
    quotationExtensionMasters = [],
    paymentMethods = [],
    paymentMethodMasters = [],
}: MastersQuotationIndexProps) {
    const initialItems = quotationItems.map((item) => ({
        ...item,
        _key: item.id ? `id-${item.id}` : nanoid(),
    }));

    const initialNotes = (quotationMasterNote ?? []).map((note) => ({
        ...note,
        _key: note.id ? `id-${note.id}` : nanoid(),
    }));

    const initialExtensionMasters = (quotationExtensionMasters ?? []).map(
        (extension, index) => ({
            ...extension,
            _key:
                extension._key ??
                (extension.id ? `id-${extension.id}` : nanoid()),
            type: extension.type ?? 'discount',
            calculation_mode: extension.calculation_mode ?? 'fixed',
            calculation_value: extension.calculation_value ?? null,
            payment_methods: extension.payment_methods ?? [],
            is_active: extension.is_active ?? true,
            sort_order: extension.sort_order ?? index + 1,
        }),
    );

    const initialPaymentMethodMasters = (paymentMethodMasters ?? []).map(
        (paymentMethod, index) => ({
            ...paymentMethod,
            _key:
                paymentMethod._key ??
                (paymentMethod.id ? `id-${paymentMethod.id}` : nanoid()),
            is_active: paymentMethod.is_active ?? true,
            is_default: paymentMethod.is_default ?? false,
            sort_order: paymentMethod.sort_order ?? index + 1,
        }),
    );

    const {
        data,
        setData,
        post,
        processing,
        errors,
        reset,
        setError,
        clearErrors,
    } = useForm<{
        model: string;
        items: QuotationItemSchema[];
        notes: NoteSchema[];
    }>({
        model: 'master',
        items: initialItems,
        notes: initialNotes,
    });

    const {
        data: extensionData,
        setData: setExtensionData,
        post: postExtensions,
        processing: processingExtensions,
        errors: extensionErrors,
    } = useForm<{
        extensions: QuotationExtensionMasterSchema[];
    }>({
        extensions: initialExtensionMasters,
    });

    const {
        data: paymentMethodData,
        setData: setPaymentMethodData,
        post: postPaymentMethods,
        processing: processingPaymentMethods,
        errors: paymentMethodErrors,
    } = useForm<{
        payment_methods: PaymentMethodMasterSchema[];
    }>({
        payment_methods: initialPaymentMethodMasters,
    });

    const handleChange = (next: QuotationItemSchema[]) => {
        setData('items', next);
    };

    const handleNoteChange = (next: NoteSchema[]) => {
        setData('notes', next);
    };

    const handleReset = () => {
        reset();
    };

    const handleExtensionChange = (
        key: string,
        patch: Partial<QuotationExtensionMasterSchema>,
    ) => {
        setExtensionData((prev) => ({
            ...prev,
            extensions: (prev.extensions ?? []).map((extension, index) => {
                if (extension._key !== key) {
                    return extension;
                }

                const nextType = String(
                    patch.type ?? extension.type ?? 'discount',
                );
                const shouldClearPaymentMethods =
                    nextType === 'discount' || nextType === 'tax';

                return {
                    ...extension,
                    ...patch,
                    payment_methods: shouldClearPaymentMethods
                        ? []
                        : (patch.payment_methods ??
                          extension.payment_methods ??
                          []),
                    sort_order: index + 1,
                };
            }),
        }));
    };

    const addExtensionMaster = () => {
        setExtensionData((prev) => ({
            ...prev,
            extensions: [
                ...(prev.extensions ?? []),
                {
                    _key: nanoid(),
                    id: undefined,
                    name: '',
                    type: 'discount',
                    calculation_mode: 'fixed',
                    calculation_value: null,
                    amount: 0,
                    payment_methods: [],
                    is_active: true,
                    sort_order: (prev.extensions?.length ?? 0) + 1,
                },
            ],
        }));
    };

    const handlePaymentMethodChange = (
        key: string,
        patch: Partial<PaymentMethodMasterSchema>,
    ) => {
        setPaymentMethodData((prev) => ({
            ...prev,
            payment_methods: (prev.payment_methods ?? []).map(
                (paymentMethod, index) => {
                    if (paymentMethod._key !== key) {
                        return paymentMethod;
                    }

                    return {
                        ...paymentMethod,
                        ...patch,
                        sort_order: index + 1,
                    };
                },
            ),
        }));
    };

    const handleDefaultPaymentMethodChange = (
        key: string,
        isDefault: boolean,
    ) => {
        setPaymentMethodData((prev) => ({
            ...prev,
            payment_methods: (prev.payment_methods ?? []).map(
                (paymentMethod, index) => {
                    return {
                        ...paymentMethod,
                        is_default: isDefault && paymentMethod._key === key,
                        sort_order: index + 1,
                    };
                },
            ),
        }));
    };

    const addPaymentMethodMaster = () => {
        setPaymentMethodData((prev) => ({
            ...prev,
            payment_methods: [
                ...(prev.payment_methods ?? []),
                {
                    _key: nanoid(),
                    id: undefined,
                    name: '',
                    value: '',
                    is_active: true,
                    is_default: false,
                    sort_order: (prev.payment_methods?.length ?? 0) + 1,
                },
            ],
        }));
    };

    const removePaymentMethodMaster = (key: string) => {
        setPaymentMethodData((prev) => ({
            ...prev,
            payment_methods: (prev.payment_methods ?? [])
                .filter((paymentMethod) => paymentMethod._key !== key)
                .map((paymentMethod, index) => ({
                    ...paymentMethod,
                    sort_order: index + 1,
                })),
        }));
    };

    const removeExtensionMaster = (key: string) => {
        setExtensionData((prev) => ({
            ...prev,
            extensions: (prev.extensions ?? [])
                .filter((extension) => extension._key !== key)
                .map((extension, index) => ({
                    ...extension,
                    sort_order: index + 1,
                })),
        }));
    };

    const groupedExtensionMasters = (extensionData.extensions ?? []).reduce(
        (groups, extension) => {
            const normalizedType = extensionTypeOrder.includes(
                String(extension.type ?? ''),
            )
                ? String(extension.type)
                : 'other';

            if (!groups[normalizedType]) {
                groups[normalizedType] = [];
            }

            groups[normalizedType].push({
                ...extension,
                type: normalizedType,
            });

            return groups;
        },
        {} as Record<string, QuotationExtensionMasterSchema[]>,
    );

    const togglePaymentMethod = (key: string, paymentMethod: string) => {
        setExtensionData((prev) => ({
            ...prev,
            extensions: (prev.extensions ?? []).map((extension, index) => {
                if (extension._key !== key) {
                    return extension;
                }

                const methods = extension.payment_methods ?? [];
                const nextMethods = methods.includes(paymentMethod)
                    ? methods.filter((method) => method !== paymentMethod)
                    : [...methods, paymentMethod];

                return {
                    ...extension,
                    payment_methods: nextMethods,
                    sort_order: index + 1,
                };
            }),
        }));
    };

    function validateClientSide() {
        clearErrors();

        const result = quotationItemsSchema.safeParse(data);

        if (!result.success) {
            result.error.issues.forEach((issue) => {
                const path = issue.path.join('.');
                setError(path as unknown as keyof typeof errors, issue.message);
            });
            return false;
        }

        return true;
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (!validateClientSide()) return;

        const url = index().url;

        post(url, {
            preserveScroll: true,
            onSuccess: () => {
                window.location.reload();
            },
            onError: (errors) => setError(errors),
        });
    }

    function submitNotes(e: React.FormEvent) {
        e.preventDefault();

        const cleanNotes = data.notes
            .filter((n) => n.description?.trim().length)
            .map((n, i) => ({
                ...n,
                model: 'quotation',
                sort_order: i + 1,
            }));

        if (!cleanNotes.length) {
            toast.error('At least one note is required.');
            return;
        }

        setData((prev) => ({
            ...prev,
            notes: cleanNotes,
        }));

        post(master.note.store.url(), {
            preserveScroll: true,
            onStart: () => {
                toast.loading('Saving notes...');
            },
            onSuccess: () => {
                toast.success('Quotation master notes updated.');
            },
            onError: (e) => {
                console.error(e);
                toast.error('Failed to save notes.');
            },
            onFinish: () => {
                toast.dismiss();
            },
        });
    }

    function submitExtensions(e: React.FormEvent) {
        e.preventDefault();

        postExtensions(quotationItemRoutes.extensions.store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Quotation extension defaults updated.');
            },
            onError: () => {
                toast.error('Failed to save quotation extension defaults.');
            },
        });
    }

    function submitPaymentMethods(e: React.FormEvent) {
        e.preventDefault();

        postPaymentMethods('/product-services/payment-methods', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Payment method defaults updated.');
                window.location.reload();
            },
            onError: () => {
                toast.error('Failed to save payment method defaults.');
            },
        });
    }

    // format err
    function cleanMessage(message: string) {
        return message
            .replace(/^The\s.+?\s/, '')
            .replace(
                /when\s.+?\.is_header\sis\sfalse\.?/i,
                'when header is false',
            )
            .replace(/\.$/, '');
    }

    function formatError(path: string, message: string) {
        const parts = path.split('.');
        const clean = cleanMessage(message);

        if (parts[0] === 'items' && parts.length >= 3) {
            const itemIndex = Number(parts[1]) + 1;
            const field = parts[2];

            return `Itemd #${itemIndex} ${field} ${clean}`;
        }

        return path;
    }

    const renderError = (path: string) => {
        const errorMap = errors as Record<string, string | undefined>;
        const message = errorMap[path];

        if (!message) return null;

        return (
            <p className="mt-1 text-sm text-red-500">
                {formatError(path, message)}
            </p>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Quotation Item Masters" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                {/* <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">
                        Products & Services
                    </h2>
                </div> */}

                {/* items */}
                <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                    <div className="mx-auto w-full">
                        <form onSubmit={submit} className="space-y-6 py-2">
                            <h3 className="text-lg font-semibold">
                                Products & Services
                            </h3>

                            <QuotationItemTableForm
                                mode={'master'}
                                items={data.items}
                                onChange={handleChange}
                                renderError={renderError}
                                disabled={processing}
                                showOptionalColumn={true}
                            />

                            <div className="flex justify-end gap-2">
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
                                    {processing ? (
                                        <>
                                            <LoaderCircle className="h-4 w-4 animate-spin" />{' '}
                                            Saving...
                                        </>
                                    ) : (
                                        'Save'
                                    )}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>

                <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                    <div className="mx-auto w-full">
                        <form
                            onSubmit={submitPaymentMethods}
                            className="space-y-6 py-2"
                        >
                            <div className="flex items-center justify-between">
                                <h3 className="text-lg font-semibold">
                                    Payment Method
                                </h3>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={addPaymentMethodMaster}
                                    disabled={processingPaymentMethods}
                                >
                                    Add
                                </Button>
                            </div>

                            <div className="space-y-4">
                                {(paymentMethodData.payment_methods ?? []).map(
                                    (paymentMethod, index) => {
                                        const key =
                                            paymentMethod._key ??
                                            `payment-method-${index}`;

                                        return (
                                            <div
                                                key={key}
                                                className="grid gap-3 rounded-lg border p-3 md:grid-cols-12"
                                            >
                                                <div className="md:col-span-6">
                                                    <FormField label="Name">
                                                        <ProperInput
                                                            value={
                                                                paymentMethod.name ??
                                                                ''
                                                            }
                                                            onCommit={(value) =>
                                                                handlePaymentMethodChange(
                                                                    key,
                                                                    {
                                                                        name: value,
                                                                    },
                                                                )
                                                            }
                                                            disabled={
                                                                processingPaymentMethods
                                                            }
                                                            placeholder="Payment method name"
                                                        />
                                                    </FormField>
                                                </div>

                                                <div className="md:col-span-2">
                                                    <FormField label="Default">
                                                        <label className="mt-2 inline-flex items-center gap-2 text-sm">
                                                            <Checkbox
                                                                checked={
                                                                    paymentMethod.is_default ??
                                                                    false
                                                                }
                                                                onCheckedChange={(
                                                                    checked,
                                                                ) =>
                                                                    handleDefaultPaymentMethodChange(
                                                                        key,
                                                                        Boolean(
                                                                            checked,
                                                                        ),
                                                                    )
                                                                }
                                                                disabled={
                                                                    processingPaymentMethods
                                                                }
                                                            />
                                                            Default
                                                        </label>
                                                    </FormField>
                                                </div>

                                                <div className="md:col-span-2">
                                                    <FormField label="Status">
                                                        <label className="mt-2 inline-flex items-center gap-2 text-sm">
                                                            <Checkbox
                                                                checked={
                                                                    paymentMethod.is_active ??
                                                                    true
                                                                }
                                                                onCheckedChange={(
                                                                    checked,
                                                                ) =>
                                                                    handlePaymentMethodChange(
                                                                        key,
                                                                        {
                                                                            is_active:
                                                                                Boolean(
                                                                                    checked,
                                                                                ),
                                                                        },
                                                                    )
                                                                }
                                                                disabled={
                                                                    processingPaymentMethods
                                                                }
                                                            />
                                                            Active
                                                        </label>
                                                    </FormField>
                                                </div>

                                                <div className="md:col-span-2 md:flex md:items-center md:justify-end">
                                                    <Button
                                                        type="button"
                                                        variant="destructive"
                                                        size="icon"
                                                        onClick={() =>
                                                            removePaymentMethodMaster(
                                                                key,
                                                            )
                                                        }
                                                        disabled={
                                                            processingPaymentMethods
                                                        }
                                                        aria-label="Remove payment method"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </div>
                                        );
                                    },
                                )}
                            </div>

                            {paymentMethodErrors.payment_methods && (
                                <p className="text-sm text-red-500">
                                    {String(
                                        paymentMethodErrors.payment_methods,
                                    )}
                                </p>
                            )}

                            <div className="flex justify-end gap-2">
                                <Button
                                    type="submit"
                                    className="min-w-[140px]"
                                    disabled={processingPaymentMethods}
                                >
                                    {processingPaymentMethods ? (
                                        <>
                                            <LoaderCircle className="h-4 w-4 animate-spin" />{' '}
                                            Saving...
                                        </>
                                    ) : (
                                        'Save'
                                    )}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>

                <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                    <div className="mx-auto w-full">
                        <form
                            onSubmit={submitExtensions}
                            className="space-y-6 py-2"
                        >
                            <div className="flex items-center justify-between">
                                <h3 className="text-lg font-semibold">
                                    Extensions
                                </h3>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={addExtensionMaster}
                                    disabled={processingExtensions}
                                >
                                    Add
                                </Button>
                            </div>

                            <div className="space-y-6">
                                {extensionTypeOrder.map((type) => {
                                    const typeRows =
                                        groupedExtensionMasters[type] ?? [];

                                    if (typeRows.length === 0) {
                                        return null;
                                    }

                                    return (
                                        <div key={type} className="space-y-3">
                                            <h4 className="text-base font-semibold text-muted-foreground">
                                                {extensionTypeLabels[type] ??
                                                    type}
                                            </h4>

                                            <div className="space-y-4">
                                                {typeRows.map(
                                                    (extension, index) => {
                                                        const key =
                                                            extension._key ??
                                                            `extension-${type}-${index}`;
                                                        const shouldShowPaymentMethods =
                                                            extension.type ===
                                                                'credit_card' ||
                                                            extension.type ===
                                                                'other';

                                                        return (
                                                            <div
                                                                key={key}
                                                                className="grid gap-3 rounded-lg border p-3 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,0.8fr)_auto]"
                                                            >
                                                                <FormField label="Name">
                                                                    <ProperInput
                                                                        value={
                                                                            extension.name ??
                                                                            ''
                                                                        }
                                                                        onCommit={(
                                                                            value,
                                                                        ) =>
                                                                            handleExtensionChange(
                                                                                key,
                                                                                {
                                                                                    name: value,
                                                                                },
                                                                            )
                                                                        }
                                                                        disabled={
                                                                            processingExtensions
                                                                        }
                                                                        placeholder="Extension name"
                                                                    />
                                                                </FormField>

                                                                <FormField label="Type">
                                                                    <ProperInputSelect
                                                                        mode="classic"
                                                                        disabled={
                                                                            processingExtensions
                                                                        }
                                                                        options={[
                                                                            {
                                                                                label: 'Discount',
                                                                                value: 'discount',
                                                                            },
                                                                            {
                                                                                label: 'Tax',
                                                                                value: 'tax',
                                                                            },
                                                                            {
                                                                                label: 'Credit Card',
                                                                                value: 'credit_card',
                                                                            },
                                                                            {
                                                                                label: 'Other',
                                                                                value: 'other',
                                                                            },
                                                                        ]}
                                                                        value={
                                                                            extension.type ??
                                                                            'discount'
                                                                        }
                                                                        onValueChange={(
                                                                            value,
                                                                        ) =>
                                                                            handleExtensionChange(
                                                                                key,
                                                                                {
                                                                                    type: String(
                                                                                        value,
                                                                                    ),
                                                                                },
                                                                            )
                                                                        }
                                                                    />
                                                                </FormField>

                                                                <FormField label="Calculation">
                                                                    <ProperInputSelect
                                                                        mode="classic"
                                                                        disabled={
                                                                            processingExtensions
                                                                        }
                                                                        options={[
                                                                            {
                                                                                label: 'Fixed Amount',
                                                                                value: 'fixed',
                                                                            },
                                                                            {
                                                                                label: 'Percentage',
                                                                                value: 'percentage',
                                                                            },
                                                                        ]}
                                                                        value={
                                                                            extension.calculation_mode ??
                                                                            'fixed'
                                                                        }
                                                                        onValueChange={(
                                                                            value,
                                                                        ) =>
                                                                            handleExtensionChange(
                                                                                key,
                                                                                {
                                                                                    calculation_mode:
                                                                                        String(
                                                                                            value,
                                                                                        ),
                                                                                },
                                                                            )
                                                                        }
                                                                    />
                                                                </FormField>

                                                                <FormField label="Value">
                                                                    <ProperInput
                                                                        type="number"
                                                                        value={
                                                                            extension.calculation_value ??
                                                                            null
                                                                        }
                                                                        onCommit={(
                                                                            value,
                                                                        ) =>
                                                                            handleExtensionChange(
                                                                                key,
                                                                                {
                                                                                    calculation_value:
                                                                                        value ===
                                                                                        ''
                                                                                            ? null
                                                                                            : Number(
                                                                                                  value,
                                                                                              ),
                                                                                },
                                                                            )
                                                                        }
                                                                        disabled={
                                                                            processingExtensions
                                                                        }
                                                                        placeholder="0"
                                                                        inputProps={{
                                                                            step: 'any',
                                                                        }}
                                                                    />
                                                                </FormField>

                                                                <div>
                                                                    <FormField label="Status">
                                                                        <label className="inline-flex items-center gap-2 text-base">
                                                                            <Checkbox
                                                                                checked={
                                                                                    extension.is_active ??
                                                                                    true
                                                                                }
                                                                                onCheckedChange={(
                                                                                    checked,
                                                                                ) =>
                                                                                    handleExtensionChange(
                                                                                        key,
                                                                                        {
                                                                                            is_active:
                                                                                                Boolean(
                                                                                                    checked,
                                                                                                ),
                                                                                        },
                                                                                    )
                                                                                }
                                                                                disabled={
                                                                                    processingExtensions
                                                                                }
                                                                                className="h-5 w-5"
                                                                            />
                                                                            Active
                                                                        </label>
                                                                    </FormField>
                                                                </div>

                                                                <div className="md:flex md:items-center md:justify-end">
                                                                    <Button
                                                                        type="button"
                                                                        variant="destructive"
                                                                        size="icon"
                                                                        onClick={() =>
                                                                            removeExtensionMaster(
                                                                                key,
                                                                            )
                                                                        }
                                                                        disabled={
                                                                            processingExtensions
                                                                        }
                                                                        aria-label="Remove extension"
                                                                    >
                                                                        <Trash2 className="h-4 w-4" />
                                                                    </Button>
                                                                </div>

                                                                {shouldShowPaymentMethods && (
                                                                    <div className="md:col-span-6">
                                                                        <FormField label="Add Only For Payment Methods">
                                                                            <div className="flex flex-wrap gap-3">
                                                                                {paymentMethods.map(
                                                                                    (
                                                                                        method,
                                                                                    ) => (
                                                                                        <label
                                                                                            key={`${key}-${method.value}`}
                                                                                            className="flex items-center gap-2 text-sm"
                                                                                        >
                                                                                            <Checkbox
                                                                                                checked={
                                                                                                    extension.payment_methods?.includes(
                                                                                                        method.value,
                                                                                                    ) ??
                                                                                                    false
                                                                                                }
                                                                                                onCheckedChange={() =>
                                                                                                    togglePaymentMethod(
                                                                                                        key,
                                                                                                        method.value,
                                                                                                    )
                                                                                                }
                                                                                                disabled={
                                                                                                    processingExtensions
                                                                                                }
                                                                                            />
                                                                                            {
                                                                                                method.label
                                                                                            }
                                                                                        </label>
                                                                                    ),
                                                                                )}
                                                                            </div>
                                                                        </FormField>
                                                                    </div>
                                                                )}
                                                            </div>
                                                        );
                                                    },
                                                )}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>

                            {extensionErrors.extensions && (
                                <p className="text-sm text-red-500">
                                    {String(extensionErrors.extensions)}
                                </p>
                            )}

                            <div className="flex justify-end gap-2">
                                <Button
                                    type="submit"
                                    className="min-w-[140px]"
                                    disabled={processingExtensions}
                                >
                                    {processingExtensions ? (
                                        <>
                                            <LoaderCircle className="h-4 w-4 animate-spin" />{' '}
                                            Saving...
                                        </>
                                    ) : (
                                        'Save'
                                    )}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>

                {/* notes */}
                <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                    <div className="mx-auto w-full">
                        <form onSubmit={submitNotes} className="space-y-6 py-2">
                            <NoteForm
                                mode="master"
                                model="quotation"
                                notes={data.notes}
                                onChange={handleNoteChange}
                                disabled={processing}
                            />

                            <div className="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        setData('notes', initialNotes)
                                    }
                                    disabled={processing}
                                >
                                    Reset
                                </Button>
                                <Button
                                    type="submit"
                                    className="min-w-[140px]"
                                    disabled={processing}
                                >
                                    {processing ? (
                                        <>
                                            <LoaderCircle className="h-4 w-4 animate-spin" />{' '}
                                            Saving...
                                        </>
                                    ) : (
                                        'Save'
                                    )}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
