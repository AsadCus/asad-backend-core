import { DatePickerField } from '@/components/date-picker';
import { FormField } from '@/components/form-field';
import { ProperInput } from '@/components/proper-input';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    officialTypeOptions,
    sharingPlanPriceLabels,
    infantAndChildPriceLabels,
} from '@/pages/packages/schema';
import { RejectDialog } from '@/pages/package-proposals/components/reject-dialog';
import { SubmitForApprovalDialog } from '@/pages/package-proposals/components/submit-dialog';
import {
    approve,
    createPackage,
    edit,
    store,
    update,
} from '@/routes/package-proposals';
import { OptionType, type SharedData } from '@/types';
import { router, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    Check,
    Copy,
    Package,
    Plus,
    Send,
    Trash2,
    TrendingDown,
    TrendingUp,
    X,
} from 'lucide-react';
import { useCallback, useMemo, useRef, useState } from 'react';
import {
    defaultExpenditure,
    type ExpenditureExtensionSchema,
    type ExpenditureSectionSchema,
    type PackageProposalSchema,
    proposalStatusColors,
    proposalStatusLabels,
    simulationLabels,
    simulationPriceKeyMap,
} from './schema';

interface ApproverOption {
    id: number;
    name: string;
    email: string;
}

interface ProposalFormProps {
    mode: 'create' | 'edit' | 'view';
    initialData?: PackageProposalSchema;
    countries: OptionType[];
    assignableCountryIds: number[];
    countryCurrencyMap: Record<string | number, string>;
    approverOptions: ApproverOption[];
    onCancel: () => void;
}

function toDecimal(v: unknown): number {
    if (v === null || v === undefined || v === '') return 0;
    const n = Number(v);
    return Number.isNaN(n) ? 0 : n;
}

function emptyItem() {
    return { item_name: '', unit_price: null, quantity: null, remarks: '', sort_order: 1 };
}

function emptySection(): ExpenditureSectionSchema {
    return { title: '', sort_order: 1, items: [emptyItem()], extensions: [] };
}

function calcSectionSubtotal(items: ExpenditureSectionSchema['items']): number {
    return (items ?? []).reduce(
        (sum, item) => sum + toDecimal(item.unit_price) * toDecimal(item.quantity),
        0,
    );
}

function calcExtensionAmount(ext: ExpenditureExtensionSchema, subtotal: number): number {
    if (ext.calculation_mode === 'percentage') {
        return (subtotal * toDecimal(ext.calculation_value)) / 100;
    }
    return toDecimal(ext.calculation_value);
}

function calcSectionTotal(section: ExpenditureSectionSchema): number {
    const subtotal = calcSectionSubtotal(section.items);
    const extTotal = (section.extensions ?? []).reduce(
        (sum, ext) => sum + calcExtensionAmount(ext, subtotal),
        0,
    );
    return subtotal + extTotal;
}

function formatNumber(value: number, symbol?: string | null): string {
    const formatted = value.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
    return symbol ? `${symbol} ${formatted}` : formatted;
}

export default function ProposalForm({
    mode,
    initialData,
    countries,
    assignableCountryIds,
    countryCurrencyMap,
    approverOptions,
    onCancel,
}: ProposalFormProps) {
    const countryOptions =
        assignableCountryIds.length > 0
            ? countries.filter((c) => assignableCountryIds.includes(Number(c.value)))
            : countries;

    const singleCountryId = countryOptions.length === 1 ? countryOptions[0].value : '';

    const { data, setData, post, put, processing, errors } =
        useForm<PackageProposalSchema>({
            name: initialData?.name ?? '',
            country_id: initialData?.country_id ?? singleCountryId,
            total_seats: initialData?.total_seats ?? null,
            departure_date: initialData?.departure_date ?? '',
            return_date: initialData?.return_date ?? '',
            price_single: initialData?.price_single ?? null,
            price_double: initialData?.price_double ?? null,
            price_triple: initialData?.price_triple ?? null,
            price_quad: initialData?.price_quad ?? null,
            child_with_bed_price: initialData?.child_with_bed_price ?? null,
            child_no_bed_price: initialData?.child_no_bed_price ?? null,
            infant_price: initialData?.infant_price ?? null,
            expenditure:
                initialData?.expenditure && initialData.expenditure.length > 0
                    ? initialData.expenditure
                    : defaultExpenditure,
            passenger_simulation: initialData?.passenger_simulation ?? {
                single: null, double: null, triple: null, quad: null,
                child_with_bed: null, child_no_bed: null, infant: null,
            },
            officials: initialData?.officials ?? [],
            remarks: initialData?.remarks ?? '',
        });

    const [submitDialogOpen, setSubmitDialogOpen] = useState(false);
    const [rejectDialogOpen, setRejectDialogOpen] = useState(false);
    const [createPkgLoading, setCreatePkgLoading] = useState(false);
    const errorBannerRef = useRef<HTMLDivElement>(null);

    const { auth } = usePage<SharedData>().props;
    const userPermissions = auth.permissions || [];
    const userId = auth.user?.id;

    const isCreate = mode === 'create';
    const isEdit = mode === 'edit';
    const isView = mode === 'view';
    const fieldDisabled = isView || processing;

    const status = String(initialData?.status ?? '').toLowerCase();
    const isApprover =
        initialData?.approver_user_ids?.includes(userId as number) ?? false;
    const canApprove =
        userPermissions.includes('package-proposal approve') &&
        isApprover &&
        status === 'pending_approval';
    const canCreatePackage =
        status === 'approved' &&
        !initialData?.package_id &&
        userPermissions.includes('package-proposal edit');
    const canEdit =
        (status === 'draft' || status === 'rejected') &&
        userPermissions.includes('package-proposal edit');
    const canSubmit =
        status === 'draft' &&
        userPermissions.includes('package-proposal edit');

    const headerTitle = isCreate
        ? 'Package PnL - Create Proposal'
        : isEdit
          ? 'Package PnL - Edit Proposal'
          : 'Package PnL - View Proposal';

    const cs = countryCurrencyMap[data.country_id as string | number] ?? null;

    const getError = useCallback(
        (key: string) => (errors as Record<string, string>)[key],
        [errors],
    );

    const getInputIdFromPath = useCallback((path: string): string => {
        return path.replace(/\./g, '_');
    }, []);

    const toFieldLabel = useCallback((path: string): string => {
        const expenditureItemMatch = path.match(/^expenditure\.(\d+)\.items\.(\d+)\.(.+)$/);
        if (expenditureItemMatch) {
            const field = expenditureItemMatch[3].replace(/_/g, ' ');
            return `Expenditure Section ${Number(expenditureItemMatch[1]) + 1} Item ${Number(expenditureItemMatch[2]) + 1} - ${field}`;
        }

        const expenditureSectionMatch = path.match(/^expenditure\.(\d+)\.(.+)$/);
        if (expenditureSectionMatch) {
            const field = expenditureSectionMatch[2].replace(/_/g, ' ');
            return `Expenditure Section ${Number(expenditureSectionMatch[1]) + 1} - ${field}`;
        }

        const officialMatch = path.match(/^officials\.(\d+)\.(.+)$/);
        if (officialMatch) {
            const field = officialMatch[2].replace(/_/g, ' ');
            return `Official ${Number(officialMatch[1]) + 1} - ${field}`;
        }

        const simMatch = path.match(/^passenger_simulation\.(.+)$/);
        if (simMatch) {
            const label = simulationLabels[simMatch[1]] ?? simMatch[1].replace(/_/g, ' ');
            return `Simulation - ${label}`;
        }

        return path.replace(/_/g, ' ');
    }, []);

    const scrollToErrorBanner = useCallback((): void => {
        errorBannerRef.current?.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
        });
    }, []);

    const focusErrorField = useCallback(
        (path: string): void => {
            const elementId = getInputIdFromPath(path);
            const target =
                document.getElementById(elementId) ??
                document.querySelector<HTMLElement>(`[name="${path}"]`);

            if (!target) return;

            target.focus();
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
            });
        },
        [getInputIdFromPath],
    );

    const errorSummaryItems = useMemo(() => {
        return Object.entries(errors)
            .filter(
                ([, message]) =>
                    typeof message === 'string' && message.trim().length > 0,
            )
            .map(([path, message]) => ({
                path,
                message: String(message),
                label: toFieldLabel(path),
            }));
    }, [errors, toFieldLabel]);

    const handleCountryChange = useCallback(
        (countryId: string) => {
            setData((prev) => ({ ...prev, country_id: countryId }));
        },
        [setData],
    );

    const onSubmitError = useCallback(() => {
        scrollToErrorBanner();
    }, [scrollToErrorBanner]);

    const handleSubmit = useCallback(
        (e: React.FormEvent) => {
            e.preventDefault();
            if (mode === 'view') {
                return;
            }
            if (mode === 'create') {
                post(store().url, { onError: onSubmitError });
            } else if (initialData?.id) {
                put(update(initialData.id).url, { onError: onSubmitError });
            }
        },
        [mode, post, put, initialData?.id, onSubmitError],
    );

    const handleApprove = useCallback(() => {
        if (!initialData?.id) return;
        router.post(approve(initialData.id).url);
    }, [initialData?.id]);

    const handleCreatePackage = useCallback(() => {
        if (!initialData?.id) return;
        setCreatePkgLoading(true);
        router.post(createPackage(initialData.id).url, {}, {
            onFinish: () => setCreatePkgLoading(false),
        });
    }, [initialData?.id]);

    // --- Expenditure helpers ---
    const updateExpenditure = (sections: ExpenditureSectionSchema[]) =>
        setData('expenditure', sections);

    const addSection = () => {
        const sections = [...(data.expenditure ?? []), emptySection()];
        sections.forEach((s, i) => (s.sort_order = i + 1));
        updateExpenditure(sections);
    };

    const removeSection = (idx: number) => {
        const sections = (data.expenditure ?? []).filter((_, i) => i !== idx);
        sections.forEach((s, i) => (s.sort_order = i + 1));
        updateExpenditure(sections);
    };

    const updateSectionTitle = (idx: number, title: string) => {
        const sections = [...(data.expenditure ?? [])];
        sections[idx] = { ...sections[idx], title };
        updateExpenditure(sections);
    };

    const addItem = (sectionIdx: number) => {
        const sections = [...(data.expenditure ?? [])];
        const items = [...(sections[sectionIdx].items ?? []), emptyItem()];
        items.forEach((item, i) => (item.sort_order = i + 1));
        sections[sectionIdx] = { ...sections[sectionIdx], items };
        updateExpenditure(sections);
    };

    const removeItem = (sectionIdx: number, itemIdx: number) => {
        const sections = [...(data.expenditure ?? [])];
        const items = (sections[sectionIdx].items ?? []).filter((_, i) => i !== itemIdx);
        items.forEach((item, i) => (item.sort_order = i + 1));
        sections[sectionIdx] = { ...sections[sectionIdx], items };
        updateExpenditure(sections);
    };

    const duplicateItem = (sectionIdx: number, itemIdx: number) => {
        const sections = [...(data.expenditure ?? [])];
        const items = [...(sections[sectionIdx].items ?? [])];
        items.splice(itemIdx + 1, 0, { ...items[itemIdx] });
        items.forEach((item, i) => (item.sort_order = i + 1));
        sections[sectionIdx] = { ...sections[sectionIdx], items };
        updateExpenditure(sections);
    };

    const updateBudgetItem = (
        sectionIdx: number,
        itemIdx: number,
        patch: Record<string, unknown>,
    ) => {
        const sections = [...(data.expenditure ?? [])];
        const items = [...(sections[sectionIdx].items ?? [])];
        items[itemIdx] = { ...items[itemIdx], ...patch };
        sections[sectionIdx] = { ...sections[sectionIdx], items };
        updateExpenditure(sections);
    };

    const addExtension = (sectionIdx: number) => {
        const sections = [...(data.expenditure ?? [])];
        const extensions = [
            ...(sections[sectionIdx].extensions ?? []),
            { name: '', calculation_mode: 'fixed', calculation_value: null, sort_order: (sections[sectionIdx].extensions?.length ?? 0) + 1 },
        ];
        sections[sectionIdx] = { ...sections[sectionIdx], extensions };
        updateExpenditure(sections);
    };

    const removeExtension = (sectionIdx: number, extIdx: number) => {
        const sections = [...(data.expenditure ?? [])];
        const extensions = (sections[sectionIdx].extensions ?? []).filter((_, i) => i !== extIdx);
        sections[sectionIdx] = { ...sections[sectionIdx], extensions };
        updateExpenditure(sections);
    };

    const updateExtension = (
        sectionIdx: number,
        extIdx: number,
        patch: Record<string, unknown>,
    ) => {
        const sections = [...(data.expenditure ?? [])];
        const extensions = [...(sections[sectionIdx].extensions ?? [])];
        extensions[extIdx] = { ...extensions[extIdx], ...patch };
        sections[sectionIdx] = { ...sections[sectionIdx], extensions };
        updateExpenditure(sections);
    };

    // --- Officials helpers ---
    const addOfficial = () =>
        setData('officials', [...(data.officials ?? []), { type: '', name: '' }]);

    const removeOfficial = (idx: number) =>
        setData('officials', (data.officials ?? []).filter((_, i) => i !== idx));

    const updateOfficial = (idx: number, patch: Record<string, string>) => {
        const officials = [...(data.officials ?? [])];
        officials[idx] = { ...officials[idx], ...patch };
        setData('officials', officials);
    };

    // --- Simulation helper ---
    const updateSimulation = (key: string, value: string) => {
        const sim = { ...(data.passenger_simulation ?? {
            single: null, double: null, triple: null, quad: null,
            child_with_bed: null, child_no_bed: null, infant: null,
        }) };
        (sim as Record<string, unknown>)[key] = value === '' ? null : parseInt(value) || 0;
        setData('passenger_simulation', sim);
    };

    // --- PnL Indicators ---
    const grandTotal = (data.expenditure ?? []).reduce(
        (sum, s) => sum + calcSectionTotal(s), 0,
    );

    const pnlIndicators = useMemo(() => {
        const totalCost = grandTotal;

        const prices = [
            toDecimal(data.price_single),
            toDecimal(data.price_double),
            toDecimal(data.price_triple),
            toDecimal(data.price_quad),
            toDecimal(data.child_with_bed_price),
            toDecimal(data.child_no_bed_price),
            toDecimal(data.infant_price),
        ].filter((p) => p > 0);

        const totalSeats = toDecimal(data.total_seats);
        const minPrice = prices.length > 0 ? Math.min(...prices) : 0;
        const maxPrice = prices.length > 0 ? Math.max(...prices) : 0;
        const minRevenue = minPrice * totalSeats;
        const maxRevenue = maxPrice * totalSeats;
        const minProfit = minRevenue - totalCost;
        const maxProfit = maxRevenue - totalCost;
        const minMargin = minRevenue > 0 ? (minProfit / minRevenue) * 100 : 0;
        const maxMargin = maxRevenue > 0 ? (maxProfit / maxRevenue) * 100 : 0;

        const sim = data.passenger_simulation ?? {};
        let simRevenue = 0;
        Object.entries(simulationPriceKeyMap).forEach(([simKey, priceKey]) => {
            const count = toDecimal(sim[simKey as keyof typeof sim]);
            const price = toDecimal(data[priceKey as keyof typeof data]);
            simRevenue += count * price;
        });
        const simProfit = simRevenue - totalCost;
        const simMargin = simRevenue > 0 ? (simProfit / simRevenue) * 100 : 0;

        return { totalCost, minRevenue, maxRevenue, minProfit, maxProfit, minMargin, maxMargin, simRevenue, simProfit, simMargin };
    }, [data, grandTotal]);

    return (
        <>
            <form onSubmit={handleSubmit} className="space-y-6">
                {/* Header */}
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <h2 className="text-lg font-semibold">{headerTitle}</h2>
                        {!isCreate && status && (
                            <Badge
                                className={`${proposalStatusColors[status] ?? 'bg-gray-100 text-gray-800'} rounded-full px-3 py-1`}
                            >
                                {proposalStatusLabels[status] ?? status}
                            </Badge>
                        )}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button type="button" variant="outline" onClick={onCancel}>
                            Cancel
                        </Button>

                        {isCreate && (
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Saving...' : 'Save as Draft'}
                            </Button>
                        )}

                        {isEdit && (
                            <>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Saving...' : 'Save Changes'}
                                </Button>
                                {status === 'draft' && (
                                    <Button
                                        type="button"
                                        className="bg-blue-600 hover:bg-blue-700"
                                        onClick={() => {
                                            if (initialData?.id) {
                                                put(`${update(initialData.id).url}?stay=1`, {
                                                    preserveState: true,
                                                    preserveScroll: true,
                                                    onSuccess: () => setSubmitDialogOpen(true),
                                                    onError: () => scrollToErrorBanner(),
                                                });
                                            }
                                        }}
                                    >
                                        <Send className="mr-1 h-4 w-4" />
                                        Submit for Approval
                                    </Button>
                                )}
                            </>
                        )}

                        {isView && (
                            <>
                                {canEdit && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            initialData?.id &&
                                            router.get(edit(initialData.id).url)
                                        }
                                    >
                                        {status === 'rejected' ? 'Revise' : 'Edit'}
                                    </Button>
                                )}
                                {canSubmit && (
                                    <Button
                                        type="button"
                                        className="bg-blue-600 hover:bg-blue-700"
                                        onClick={() => setSubmitDialogOpen(true)}
                                    >
                                        <Send className="mr-1 h-4 w-4" />
                                        Submit for Approval
                                    </Button>
                                )}
                                {canApprove && (
                                    <>
                                        <Button
                                            type="button"
                                            variant="default"
                                            onClick={handleApprove}
                                        >
                                            <Check className="h-4 w-4" />
                                            Approve
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            onClick={() => setRejectDialogOpen(true)}
                                        >
                                            <X className="h-4 w-4" />
                                            Reject
                                        </Button>
                                    </>
                                )}
                                {canCreatePackage && (
                                    <Button
                                        type="button"
                                        variant="default"
                                        disabled={createPkgLoading}
                                        onClick={handleCreatePackage}
                                    >
                                        <Package className="mr-1 h-4 w-4" />
                                        {createPkgLoading ? 'Creating...' : 'Create Package'}
                                    </Button>
                                )}
                            </>
                        )}
                    </div>
                </div>

                {/* Rejection reason banner */}
                {isView && status === 'rejected' && initialData?.rejection_reason && (
                    <div className="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                        <p className="text-sm font-medium text-red-800 dark:text-red-200">
                            Rejected by{' '}
                            {initialData.approved_rejected_by_name ?? 'Unknown'}
                        </p>
                        <p className="mt-1 text-sm text-red-700 dark:text-red-300">
                            {initialData.rejection_reason}
                        </p>
                    </div>
                )}

                {/* Linked package banner */}
                {isView && initialData?.package_id && (
                    <div className="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20">
                        <p className="text-sm text-green-800 dark:text-green-200">
                            Package created:{' '}
                            <button
                                type="button"
                                className="font-medium underline"
                                onClick={() =>
                                    router.get(`/packages/${initialData.package_id}/edit`)
                                }
                            >
                                {initialData.package_number ??
                                    `#${initialData.package_id}`}
                            </button>
                        </p>
                    </div>
                )}

                {/* Error Banner */}
                {errorSummaryItems.length > 0 && (
                    <div ref={errorBannerRef}>
                        <Alert variant="destructive">
                            <AlertCircle className="h-4 w-4" />
                            <AlertDescription>
                                <div className="space-y-2">
                                    <p>Please fix the errors below and try again.</p>
                                    <ul className="list-disc space-y-1 pl-5">
                                        {errorSummaryItems.map((item) => (
                                            <li key={`${item.path}:${item.message}`}>
                                                <button
                                                    type="button"
                                                    onClick={() => focusErrorField(item.path)}
                                                    className="text-left underline underline-offset-2"
                                                >
                                                    {item.label}: {item.message}
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            </AlertDescription>
                        </Alert>
                    </div>
                )}

                {/* Proposal Details */}
                <Card>
                    <CardHeader className='gap-0'>
                        <CardTitle className="text-lg">Proposal Details</CardTitle>
                        <CardDescription>
                            Basic details of the proposed package
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 items-start gap-3 md:grid-cols-3">
                            <FormField
                                label="Package Name"
                                htmlFor="name"
                                fieldRequirementsProps={{ required: true, hint: 'Name of the proposed package' }}
                                error={getError('name')}
                            >
                                <ProperInput
                                    id="name"
                                    value={data.name ?? ''}
                                    disabled={fieldDisabled}
                                    onCommit={(v) => setData('name', v)}
                                    placeholder="Enter package name"
                                />
                            </FormField>

                            <FormField
                                label="Country"
                                fieldRequirementsProps={{ required: true, hint: 'Package destination country. Currency follows this selection.' }}
                                error={getError('country_id')}
                            >
                                <ProperInputSelect
                                    id="country_id"
                                    options={countryOptions}
                                    value={data.country_id ?? ''}
                                    onValueChange={(v) => handleCountryChange(String(v))}
                                    disabled={countryOptions.length === 1 || fieldDisabled}
                                    placeholder="Select country"
                                />
                            </FormField>

                            {cs && (
                                <FormField
                                    label="Currency"
                                    fieldRequirementsProps={{ hint: 'Auto-set from selected country' }}
                                >
                                    <ProperInput
                                        value={cs}
                                        disabled={true}
                                        onCommit={() => undefined}
                                    />
                                </FormField>
                            )}

                            <FormField
                                label="Total Seats"
                                htmlFor="total_seats"
                                fieldRequirementsProps={{ required: true, hint: 'Total passenger seats for this package' }}
                                error={getError('total_seats')}
                            >
                                <ProperInput
                                    id="total_seats"
                                    type="number"
                                    value={data.total_seats ?? ''}
                                    disabled={fieldDisabled}
                                    onCommit={(v) => setData('total_seats', v === '' ? null : parseInt(v) || 0)}
                                    inputProps={{ min: '0' }}
                                    placeholder="0"
                                />
                            </FormField>

                            <FormField
                                label="Departure Date"
                                fieldRequirementsProps={{ hint: 'Select departure date' }}
                                error={getError('departure_date')}
                            >
                                <DatePickerField
                                    id="departure_date"
                                    value={data.departure_date || ''}
                                    fromYear={new Date().getFullYear()}
                                    toYear={new Date().getFullYear() + 5}
                                    disabled={fieldDisabled}
                                    onChange={(v) => setData('departure_date', v)}
                                />
                            </FormField>

                            <FormField
                                label="Return Date"
                                fieldRequirementsProps={{ hint: 'Select return date' }}
                                error={getError('return_date')}
                            >
                                <DatePickerField
                                    id="return_date"
                                    value={data.return_date || ''}
                                    fromYear={new Date().getFullYear()}
                                    toYear={new Date().getFullYear() + 5}
                                    disabled={fieldDisabled}
                                    onChange={(v) => setData('return_date', v)}
                                />
                            </FormField>
                        </div>
                    </CardContent>
                </Card>

                {/* Officials */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle className="text-lg">Officials</CardTitle>
                        <Button type="button" variant="outline" size="sm" onClick={addOfficial} disabled={fieldDisabled}>
                            <Plus className="mr-1 h-4 w-4" /> Add Official
                        </Button>
                    </CardHeader>
                    <CardContent>
                        {(data.officials ?? []).length === 0 ? (
                            <p className="text-base text-muted-foreground">
                                No officials added yet. Click "Add Official" to add.
                            </p>
                        ) : (
                            <div className="space-y-3">
                                {(data.officials ?? []).map((official, idx) => (
                                    <div key={idx} className="grid grid-cols-1 items-start gap-4 rounded-lg border px-4 py-2 md:grid-cols-[200px_1fr_auto]">
                                        <FormField label="Type" error={getError(`officials.${idx}.type`)}>
                                            <Select
                                                value={official.type ?? ''}
                                                onValueChange={(v) => updateOfficial(idx, { type: v })}
                                                disabled={fieldDisabled}
                                            >
                                                <SelectTrigger id={`officials_${idx}_type`}>
                                                    <SelectValue placeholder="Select type" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {officialTypeOptions.map((o) => (
                                                        <SelectItem key={o.value} value={o.value}>
                                                            {o.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </FormField>
                                        <FormField label="Name" error={getError(`officials.${idx}.name`)}>
                                            <ProperInput
                                                id={`officials_${idx}_name`}
                                                value={official.name ?? ''}
                                                disabled={fieldDisabled}
                                                onCommit={(v) => updateOfficial(idx, { name: v })}
                                                placeholder="Enter name"
                                            />
                                        </FormField>
                                        <div className="flex items-center justify-end">
                                            <Button type="button" variant="ghost" size="sm" disabled={fieldDisabled} onClick={() => removeOfficial(idx)} className="text-destructive">
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Expenditure (Cost) */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle className="text-lg">Expenditure (Cost)</CardTitle>
                        <Button type="button" variant="outline" size="sm" onClick={addSection} disabled={fieldDisabled}>
                            <Plus className="mr-1 h-4 w-4" /> Add Section
                        </Button>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {(data.expenditure ?? []).map((section, sIdx) => {
                            const subtotal = calcSectionSubtotal(section.items);
                            const sectionTotal = calcSectionTotal(section);

                            return (
                                <Card key={sIdx} className="border-border/70">
                                    <CardHeader className="gap-0">
                                        <div className="flex items-center justify-between gap-3">
                                            <CardTitle className="text-lg">
                                                <FormField
                                                    label={`Title ${sIdx + 1}`}
                                                    layout="inline"
                                                    fieldRequirementsProps={{ hint: 'Budget section title grouping related cost items.' }}
                                                    error={getError(`expenditure.${sIdx}.title`)}
                                                >
                                                    <ProperInput
                                                        id={`expenditure_${sIdx}_title`}
                                                        value={section.title ?? ''}
                                                        disabled={fieldDisabled}
                                                        onCommit={(v) => updateSectionTitle(sIdx, v)}
                                                        placeholder="Enter title"
                                                        className="md:min-w-[300px]"
                                                    />
                                                </FormField>
                                            </CardTitle>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                disabled={fieldDisabled || (data.expenditure ?? []).length <= 1}
                                                onClick={() => removeSection(sIdx)}
                                                className="text-destructive"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="space-y-3">
                                        {(section.items ?? []).map((item, iIdx) => {
                                            const lineTotal = toDecimal(item.unit_price) * toDecimal(item.quantity);
                                            return (
                                                <div
                                                    key={iIdx}
                                                    className="grid grid-cols-1 items-start gap-4 rounded-lg border px-4 py-2 md:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto]"
                                                >
                                                    <FormField label="Item Name" error={getError(`expenditure.${sIdx}.items.${iIdx}.item_name`)}>
                                                        <ProperInput
                                                            id={`expenditure_${sIdx}_items_${iIdx}_item_name`}
                                                            value={item.item_name ?? ''}
                                                            disabled={fieldDisabled}
                                                            onCommit={(v) => updateBudgetItem(sIdx, iIdx, { item_name: v })}
                                                            placeholder="Enter item"
                                                        />
                                                    </FormField>
                                                    <FormField
                                                        label="Unit Price"
                                                        error={getError(`expenditure.${sIdx}.items.${iIdx}.unit_price`)}
                                                    >
                                                        <ProperInput
                                                            id={`expenditure_${sIdx}_items_${iIdx}_unit_price`}
                                                            value={
                                                                item.unit_price === null || item.unit_price === undefined || toDecimal(item.unit_price) === 0
                                                                    ? ''
                                                                    : String(item.unit_price)
                                                            }
                                                            type="number"
                                                            inputProps={{ step: 'any' }}
                                                            disabled={fieldDisabled}
                                                            onCommit={(v) => updateBudgetItem(sIdx, iIdx, { unit_price: v === '' ? null : toDecimal(v) })}
                                                            placeholder="0"
                                                        />
                                                    </FormField>
                                                    <FormField
                                                        label="Quantity"
                                                        error={getError(`expenditure.${sIdx}.items.${iIdx}.quantity`)}
                                                    >
                                                        <ProperInput
                                                            id={`expenditure_${sIdx}_items_${iIdx}_quantity`}
                                                            value={toDecimal(item.quantity) === 0 ? '' : String(item.quantity ?? '')}
                                                            type="number"
                                                            inputProps={{ min: 0, step: 'any' }}
                                                            disabled={fieldDisabled}
                                                            onCommit={(v) => updateBudgetItem(sIdx, iIdx, { quantity: toDecimal(v) })}
                                                            placeholder="0"
                                                        />
                                                    </FormField>
                                                    <FormField label="Amount">
                                                        <ProperInput value={formatNumber(lineTotal, cs)} disabled={true} onCommit={() => undefined} />
                                                    </FormField>
                                                    <div className="flex items-center justify-end">
                                                        <Button type="button" variant="ghost" size="sm" disabled={fieldDisabled} onClick={() => duplicateItem(sIdx, iIdx)}>
                                                            <Copy className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            disabled={fieldDisabled || (section.items?.length ?? 0) <= 1}
                                                            onClick={() => removeItem(sIdx, iIdx)}
                                                            className="text-destructive"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </div>
                                            );
                                        })}

                                        <div className="flex flex-col items-end gap-3 border-t pt-3">
                                            <Button type="button" variant="outline" disabled={fieldDisabled} onClick={() => addItem(sIdx)}>
                                                <Plus className="h-4 w-4" /> Add Item
                                            </Button>
                                            <table className="w-full max-w-sm table-fixed text-base">
                                                <tbody className="[&>tr>td]:py-1.5">
                                                    <tr>
                                                        <td className="w-3/4 text-right text-lg font-medium">Sub Total{cs ? ` (${cs})` : ''}:</td>
                                                        <td className="w-1/4 text-right text-lg font-semibold text-primary">{formatNumber(subtotal, null)}</td>
                                                    </tr>
                                                    {(section.extensions ?? []).map((ext, eIdx) => {
                                                        const amount = calcExtensionAmount(ext, subtotal);
                                                        return (
                                                            <tr key={eIdx}>
                                                                <td className="text-right">
                                                                    <div className="flex items-center justify-end gap-2">
                                                                        <ProperInput
                                                                            value={ext.name ?? ''}
                                                                            disabled={fieldDisabled}
                                                                            onCommit={(v) => updateExtension(sIdx, eIdx, { name: v })}
                                                                            placeholder="Name (e.g. GST)"
                                                                            className="w-36 text-right"
                                                                        />
                                                                        <Select
                                                                            value={ext.calculation_mode ?? 'fixed'}
                                                                            onValueChange={(v) => updateExtension(sIdx, eIdx, { calculation_mode: v })}
                                                                            disabled={fieldDisabled}
                                                                        >
                                                                            <SelectTrigger className="w-20">
                                                                                <SelectValue />
                                                                            </SelectTrigger>
                                                                            <SelectContent>
                                                                                <SelectItem value="fixed">Fixed</SelectItem>
                                                                                <SelectItem value="percentage">%</SelectItem>
                                                                            </SelectContent>
                                                                        </Select>
                                                                        <ProperInput
                                                                            value={toDecimal(ext.calculation_value) === 0 ? '' : String(ext.calculation_value ?? '')}
                                                                            type="number"
                                                                            inputProps={{ step: 'any' }}
                                                                            disabled={fieldDisabled}
                                                                            onCommit={(v) => updateExtension(sIdx, eIdx, { calculation_value: v === '' ? null : toDecimal(v) })}
                                                                            placeholder="0"
                                                                            className="w-24"
                                                                        />
                                                                        <Button type="button" variant="ghost" size="sm" disabled={fieldDisabled} onClick={() => removeExtension(sIdx, eIdx)} className="text-destructive">
                                                                            <Trash2 className="h-3 w-3" />
                                                                        </Button>
                                                                    </div>
                                                                </td>
                                                                <td className="text-right font-medium">{formatNumber(amount, null)}</td>
                                                            </tr>
                                                        );
                                                    })}
                                                    <tr>
                                                        <td className="text-right">
                                                            <Button type="button" variant="ghost" size="sm" onClick={() => addExtension(sIdx)} disabled={fieldDisabled}>
                                                                <Plus className="mr-1 h-3 w-3" /> Add Surcharge/Discount
                                                            </Button>
                                                        </td>
                                                        <td />
                                                    </tr>
                                                    <tr className="border-t">
                                                        <td className="w-3/4 text-right text-lg font-semibold">Section Total{cs ? ` (${cs})` : ''}:</td>
                                                        <td className="w-1/4 text-right text-lg font-bold text-primary">{formatNumber(sectionTotal, null)}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </CardContent>
                                </Card>
                            );
                        })}

                        <div className="flex justify-end rounded-lg bg-muted/50 p-4">
                            <span className="text-xl font-bold">
                                Grand Total: {formatNumber(grandTotal, cs)}
                            </span>
                        </div>
                    </CardContent>
                </Card>

                {/* Revenue (Pricing) */}
                <Card>
                    <CardHeader className='gap-0'>
                        <CardTitle className="text-lg">Revenue (Pricing Per Pax)</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-6">
                            <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-4">
                                {sharingPlanPriceLabels.map(({ key, label }) => (
                                    <FormField
                                        key={key}
                                        label={label}
                                        htmlFor={key}
                                        fieldRequirementsProps={{ hint: 'Enter price amount' }}
                                        error={getError(key)}
                                    >
                                        <ProperInput
                                            id={key}
                                            type="number"
                                            value={data[key as keyof PackageProposalSchema] as number}
                                            disabled={fieldDisabled}
                                            onCommit={(v) => setData(key as keyof PackageProposalSchema, v === '' ? null : parseFloat(v))}
                                            inputProps={{ min: '0', step: '0.01' }}
                                            placeholder="0"
                                        />
                                    </FormField>
                                ))}
                            </div>
                            <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-3">
                                {infantAndChildPriceLabels.map(({ key, label }) => (
                                    <FormField
                                        key={key}
                                        label={label}
                                        htmlFor={key}
                                        fieldRequirementsProps={{ hint: 'Enter price amount' }}
                                        error={getError(key)}
                                    >
                                        <ProperInput
                                            id={key}
                                            type="number"
                                            value={data[key as keyof PackageProposalSchema] as number}
                                            disabled={fieldDisabled}
                                            onCommit={(v) => setData(key as keyof PackageProposalSchema, v === '' ? null : parseFloat(v))}
                                            inputProps={{ min: '0', step: '0.01' }}
                                            placeholder="0"
                                        />
                                    </FormField>
                                ))}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Passenger Simulation */}
                <Card>
                    <CardHeader className='gap-0'>
                        <CardTitle className="text-lg">Passenger Simulation</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {Object.entries(simulationLabels).map(([key, label]) => {
                                const priceKey = simulationPriceKeyMap[key];
                                const count = toDecimal(data.passenger_simulation?.[key as keyof typeof data.passenger_simulation]);
                                const price = toDecimal(data[priceKey as keyof typeof data]);
                                return (
                                    <div key={key} className="grid grid-cols-1 items-start gap-4 md:grid-cols-4">
                                        <FormField label={label} fieldRequirementsProps={{ hint: `Number of ${label.toLowerCase()} passengers` }} error={getError(`passenger_simulation.${key}`)}>
                                            <ProperInput
                                                id={`passenger_simulation_${key}`}
                                                type="number"
                                                value={count === 0 ? '' : String(count)}
                                                disabled={processing}
                                                onCommit={(v) => updateSimulation(key, v)}
                                                inputProps={{ min: '0' }}
                                                placeholder="0"
                                            />
                                        </FormField>
                                        <FormField label="Price/Pax">
                                            <ProperInput value={formatNumber(price, cs)} disabled={true} onCommit={() => undefined} />
                                        </FormField>
                                        <FormField label="Subtotal">
                                            <ProperInput value={formatNumber(count * price, cs)} disabled={true} onCommit={() => undefined} />
                                        </FormField>
                                    </div>
                                );
                            })}
                            <div className="flex justify-end border-t pt-3">
                                <span className="text-lg font-bold">
                                    Simulated Revenue: {formatNumber(pnlIndicators.simRevenue, cs)}
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* PnL Indicators */}
                <Card>
                    <CardHeader className='gap-0'>
                        <CardTitle className="text-lg">PnL Indicators</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div className="space-y-3 rounded-lg border p-4">
                                <h4 className="font-semibold">Range (All Pricing Tiers)</h4>
                                <div className="space-y-2 text-sm">
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Total Cost</span>
                                        <span>{formatNumber(pnlIndicators.totalCost, cs)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Revenue Range</span>
                                        <span>{formatNumber(pnlIndicators.minRevenue, cs)} - {formatNumber(pnlIndicators.maxRevenue, cs)}</span>
                                    </div>
                                    <div className="flex items-center justify-between border-t pt-2">
                                        <span className="font-medium">Profit/Loss Range</span>
                                        <div className="flex items-center gap-2">
                                            {pnlIndicators.minProfit >= 0 ? <TrendingUp className="h-4 w-4 text-green-600" /> : <TrendingDown className="h-4 w-4 text-red-600" />}
                                            <span className={pnlIndicators.minProfit >= 0 ? 'text-green-600' : 'text-red-600'}>
                                                {formatNumber(pnlIndicators.minProfit, cs)}
                                            </span>
                                            <span>to</span>
                                            <span className={pnlIndicators.maxProfit >= 0 ? 'text-green-600' : 'text-red-600'}>
                                                {formatNumber(pnlIndicators.maxProfit, cs)}
                                            </span>
                                        </div>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Margin Range</span>
                                        <span>{pnlIndicators.minMargin.toFixed(1)}% - {pnlIndicators.maxMargin.toFixed(1)}%</span>
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-3 rounded-lg border p-4">
                                <h4 className="font-semibold">Simulation</h4>
                                <div className="space-y-2 text-sm">
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Total Cost</span>
                                        <span>{formatNumber(pnlIndicators.totalCost, cs)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Simulated Revenue</span>
                                        <span>{formatNumber(pnlIndicators.simRevenue, cs)}</span>
                                    </div>
                                    <div className="flex items-center justify-between border-t pt-2">
                                        <span className="font-medium">Profit/Loss</span>
                                        <div className="flex items-center gap-2">
                                            {pnlIndicators.simProfit >= 0 ? <TrendingUp className="h-4 w-4 text-green-600" /> : <TrendingDown className="h-4 w-4 text-red-600" />}
                                            <span className={`text-lg font-bold ${pnlIndicators.simProfit >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                                                {formatNumber(pnlIndicators.simProfit, cs)}
                                            </span>
                                        </div>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Margin</span>
                                        <span className={pnlIndicators.simMargin >= 0 ? 'text-green-600' : 'text-red-600'}>
                                            {pnlIndicators.simMargin.toFixed(1)}%
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Remarks */}
                <Card>
                    <CardHeader className='gap-0'>
                        <CardTitle className="text-lg">Remarks</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <FormField label="Notes" fieldRequirementsProps={{ hint: 'Any additional notes for this proposal' }} error={getError('remarks')}>
                            <ProperInput
                                id="remarks"
                                value={data.remarks ?? ''}
                                disabled={fieldDisabled}
                                textarea
                                onCommit={(v) => setData('remarks', v)}
                                placeholder="Additional notes..."
                            />
                        </FormField>
                    </CardContent>
                </Card>

            </form>

            <SubmitForApprovalDialog
                open={submitDialogOpen}
                onOpenChange={setSubmitDialogOpen}
                proposalId={initialData?.id}
                approverOptions={approverOptions}
            />

            <RejectDialog
                open={rejectDialogOpen}
                onOpenChange={setRejectDialogOpen}
                proposalId={initialData?.id}
            />
        </>
    );
}
