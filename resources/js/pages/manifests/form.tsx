import { DatePickerField } from '@/components/date-picker';
import { FieldRequirements } from '@/components/field-requirements';
import { ProperInput } from '@/components/proper-input';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { store, update } from '@/routes/manifests';
import { type ValueNumberOptionType } from '@/types';
import { useForm } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, Minus, Plus, RotateCcw } from 'lucide-react';
import { FormEvent, useCallback } from 'react';
import { type ManifestSchema, type TravelerSchema } from './schema';
import { manifestValidationSchema } from './validation';

interface ManifestFormProps {
    mode: 'create' | 'edit' | 'view';
    initialData?: ManifestSchema;
    dataPackage?: ValueNumberOptionType[];
    onCancel: () => void;
}

const STATUS_OPTIONS = ['draft', 'confirmed', 'completed', 'cancelled'];

const MEAL_OPTIONS = ['Breakfast', 'Lunch', 'Dinner', 'None'];

const ROOM_TYPE_OPTIONS = ['Quad', 'Triple', 'Double', 'Single'];

const BED_TYPE_OPTIONS = ['Single', 'Double', 'Twin'];

const defaultTraveler: TravelerSchema = {
    sn: 1,
    name_as_per_passport: '',
    relationship: '',
    passport_no: '',
    room_no: '',
    room_type: '',
    bed_type: '',
    date_of_birth: '',
    age: 0,
    no_of_beds_checked: 0,
    meal: '',
    remarks: '',
    total_cost: 0,
    total_paid: 0,
    outstanding_amount: 0,
};

const getDefaultValues = (data?: ManifestSchema): ManifestSchema => ({
    id: data?.id ?? undefined,
    package_id: data?.package_id ?? 0,
    reference_number: data?.reference_number ?? '',
    company_address: data?.company_address ?? '',
    company_phone: data?.company_phone ?? '',
    departure_date: data?.departure_date ?? '',
    return_date: data?.return_date ?? '',
    duration: data?.duration ?? '',
    makkah_hotel: data?.makkah_hotel ?? '',
    makkah_check_in: data?.makkah_check_in ?? '',
    makkah_check_out: data?.makkah_check_out ?? '',
    madinah_hotel: data?.madinah_hotel ?? '',
    madinah_check_in: data?.madinah_check_in ?? '',
    madinah_check_out: data?.madinah_check_out ?? '',
    flight_details: data?.flight_details ?? null,
    notes: data?.notes ?? '',
    first_meal: data?.first_meal ?? '',
    last_meal: data?.last_meal ?? '',
    status: data?.status ?? 'draft',
    travelers: data?.travelers ?? [],
});

export default function ManifestForm({
    mode,
    initialData,
    dataPackage = [],
    onCancel,
}: ManifestFormProps) {
    const isView = mode === 'view';
    const isEdit = mode === 'edit';
    const isCreate = mode === 'create';

    const defaults = getDefaultValues(initialData);

    const form = useForm(defaults);
    const {
        data,
        setData,
        post,
        put,
        processing,
        reset,
        errors,
        setError,
        clearErrors,
    } = form;

    const addTraveler = useCallback(() => {
        const newSn = (data.travelers?.length ?? 0) + 1;
        setData('travelers', [
            ...(data.travelers ?? []),
            { ...defaultTraveler, sn: newSn },
        ]);
    }, [data.travelers, setData]);

    const removeTraveler = useCallback(
        (index: number) => {
            const updated = (data.travelers ?? []).filter(
                (_: TravelerSchema, i: number) => i !== index,
            );
            // Re-number
            const reNumbered = updated.map((t: TravelerSchema, i: number) => ({
                ...t,
                sn: i + 1,
            }));
            setData('travelers', reNumbered);
        },
        [data.travelers, setData],
    );

    const updateTraveler = useCallback(
        (
            index: number,
            field: keyof TravelerSchema,
            value: string | number,
        ) => {
            const updated = [...(data.travelers ?? [])];
            updated[index] = { ...updated[index], [field]: value };

            // Auto-calculate outstanding
            if (field === 'total_cost' || field === 'total_paid') {
                const cost =
                    field === 'total_cost'
                        ? Number(value)
                        : Number(updated[index].total_cost);
                const paid =
                    field === 'total_paid'
                        ? Number(value)
                        : Number(updated[index].total_paid);
                updated[index].outstanding_amount = Math.max(0, cost - paid);
            }

            setData('travelers', updated);
        },
        [data.travelers, setData],
    );

    const validateClientSide = (): boolean => {
        clearErrors();
        let valid = true;

        const result = manifestValidationSchema.safeParse(data);
        if (!result.success) {
            result.error.issues.forEach((issue) => {
                const key = issue.path.join('.') as keyof ManifestSchema;
                setError(key, issue.message);
            });
            valid = false;
        }

        return valid;
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();

        if (!validateClientSide()) return;

        if (isCreate) {
            post(store().url, {
                onError: (errors: Record<string, string>) => setError(errors),
            });
        } else if (isEdit && data.id) {
            put(update(data.id).url, {
                onError: (errors: Record<string, string>) => setError(errors),
            });
        }
    };

    const renderError = (path: string) => {
        const errorMap = errors as Record<string, string | undefined>;
        const message = errorMap[path];
        if (!message) return null;
        return <p className="mt-1 text-sm text-red-500">{message}</p>;
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            {/* Error Alert */}
            {Object.keys(errors).length > 0 && !isView && (
                <Alert variant="destructive">
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>
                        Please fix the errors below and try again
                    </AlertDescription>
                </Alert>
            )}

            {/* Manifest Information */}
            <Card>
                <CardHeader>
                    <CardTitle>Manifest Information</CardTitle>
                </CardHeader>
                <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div className="grid w-full items-center gap-3">
                        <Label htmlFor="package_id">
                            Package
                            <FieldRequirements
                                required
                                hint="Select the package for this manifest"
                            />
                        </Label>
                        <div className="relative">
                            <Select
                                value={String(data.package_id || '')}
                                onValueChange={(v) =>
                                    setData('package_id', Number(v))
                                }
                                disabled={isView}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select Package" />
                                </SelectTrigger>
                                <SelectContent>
                                    {dataPackage.map((pkg) => (
                                        <SelectItem
                                            key={pkg.value}
                                            value={String(pkg.value)}
                                        >
                                            {pkg.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {renderError('package_id')}
                        </div>
                    </div>

                    <div className="grid w-full items-center gap-3">
                        <Label htmlFor="reference_number">
                            Reference Number
                            <FieldRequirements
                                required
                                hint="Enter the manifest reference number"
                            />
                        </Label>
                        <div className="relative">
                            <ProperInput
                                id="reference_number"
                                value={data.reference_number ?? ''}
                                disabled={isView}
                                onCommit={(v) => setData('reference_number', v)}
                                placeholder="Enter reference number"
                            />
                            {renderError('reference_number')}
                        </div>
                    </div>

                    <div className="grid w-full items-center gap-3">
                        <Label htmlFor="status">
                            Status
                            <FieldRequirements hint="Select the manifest status" />
                        </Label>
                        <div className="relative">
                            <Select
                                value={data.status}
                                onValueChange={(v) => setData('status', v)}
                                disabled={isView}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    {STATUS_OPTIONS.map((s) => (
                                        <SelectItem key={s} value={s}>
                                            {s.charAt(0).toUpperCase() +
                                                s.slice(1)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {renderError('status')}
                        </div>
                    </div>

                    <div className="grid w-full items-center gap-3">
                        <Label htmlFor="company_address">
                            Company Address
                            <FieldRequirements hint="Enter company address" />
                        </Label>
                        <div className="relative">
                            <ProperInput
                                id="company_address"
                                value={data.company_address ?? ''}
                                disabled={isView}
                                onCommit={(v) => setData('company_address', v)}
                                placeholder="Enter company address"
                            />
                            {renderError('company_address')}
                        </div>
                    </div>

                    <div className="grid w-full items-center gap-3">
                        <Label htmlFor="company_phone">
                            Company Phone
                            <FieldRequirements hint="Enter company phone number" />
                        </Label>
                        <div className="relative">
                            <ProperInput
                                id="company_phone"
                                value={data.company_phone ?? ''}
                                disabled={isView}
                                onCommit={(v) => setData('company_phone', v)}
                                placeholder="Enter company phone"
                            />
                            {renderError('company_phone')}
                        </div>
                    </div>

                    <div className="grid w-full items-center gap-3">
                        <Label htmlFor="duration">
                            Duration
                            <FieldRequirements hint="Enter duration (e.g. 14 Days / 13 Nights)" />
                        </Label>
                        <div className="relative">
                            <ProperInput
                                id="duration"
                                value={data.duration ?? ''}
                                disabled={isView}
                                onCommit={(v) => setData('duration', v)}
                                placeholder="e.g. 14 Days / 13 Nights"
                            />
                            {renderError('duration')}
                        </div>
                    </div>

                    <div className="grid w-full items-center gap-3">
                        <Label htmlFor="departure_date">
                            Departure Date
                            <FieldRequirements
                                required
                                hint="Select departure date"
                            />
                        </Label>
                        <div className="relative">
                            <DatePickerField
                                id="departure_date"
                                value={data.departure_date}
                                onChange={(v) => setData('departure_date', v)}
                                disabled={isView}
                            />
                            {renderError('departure_date')}
                        </div>
                    </div>

                    <div className="grid w-full items-center gap-3">
                        <Label htmlFor="return_date">
                            Return Date
                            <FieldRequirements
                                required
                                hint="Select return date"
                            />
                        </Label>
                        <div className="relative">
                            <DatePickerField
                                id="return_date"
                                value={data.return_date}
                                onChange={(v) => setData('return_date', v)}
                                disabled={isView}
                            />
                            {renderError('return_date')}
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Hotel Details */}
            <Card>
                <CardHeader>
                    <CardTitle>Hotel Details</CardTitle>
                </CardHeader>
                <CardContent className="space-y-6">
                    {/* Makkah */}
                    <div>
                        <h4 className="mb-3 text-base font-medium text-muted-foreground">
                            Makkah Hotel
                        </h4>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="makkah_hotel">
                                    Hotel Name
                                    <FieldRequirements hint="Enter Makkah hotel name" />
                                </Label>
                                <div className="relative">
                                    <ProperInput
                                        id="makkah_hotel"
                                        value={data.makkah_hotel ?? ''}
                                        disabled={isView}
                                        onCommit={(v) =>
                                            setData('makkah_hotel', v)
                                        }
                                        placeholder="Enter hotel name"
                                    />
                                    {renderError('makkah_hotel')}
                                </div>
                            </div>

                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="makkah_check_in">
                                    Check In
                                    <FieldRequirements hint="Select check-in date" />
                                </Label>
                                <div className="relative">
                                    <DatePickerField
                                        id="makkah_check_in"
                                        value={data.makkah_check_in}
                                        onChange={(v) =>
                                            setData('makkah_check_in', v)
                                        }
                                        disabled={isView}
                                    />
                                    {renderError('makkah_check_in')}
                                </div>
                            </div>

                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="makkah_check_out">
                                    Check Out
                                    <FieldRequirements hint="Select check-out date" />
                                </Label>
                                <div className="relative">
                                    <DatePickerField
                                        id="makkah_check_out"
                                        value={data.makkah_check_out}
                                        onChange={(v) =>
                                            setData('makkah_check_out', v)
                                        }
                                        disabled={isView}
                                    />
                                    {renderError('makkah_check_out')}
                                </div>
                            </div>
                        </div>
                    </div>
                    {/* Madinah */}
                    <div>
                        <h4 className="mb-3 text-base font-medium text-muted-foreground">
                            Madinah Hotel
                        </h4>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="madinah_hotel">
                                    Hotel Name
                                    <FieldRequirements hint="Enter Madinah hotel name" />
                                </Label>
                                <div className="relative">
                                    <ProperInput
                                        id="madinah_hotel"
                                        value={data.madinah_hotel ?? ''}
                                        disabled={isView}
                                        onCommit={(v) =>
                                            setData('madinah_hotel', v)
                                        }
                                        placeholder="Enter hotel name"
                                    />
                                    {renderError('madinah_hotel')}
                                </div>
                            </div>

                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="madinah_check_in">
                                    Check In
                                    <FieldRequirements hint="Select check-in date" />
                                </Label>
                                <div className="relative">
                                    <DatePickerField
                                        id="madinah_check_in"
                                        value={data.madinah_check_in}
                                        onChange={(v) =>
                                            setData('madinah_check_in', v)
                                        }
                                        disabled={isView}
                                    />
                                    {renderError('madinah_check_in')}
                                </div>
                            </div>

                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="madinah_check_out">
                                    Check Out
                                    <FieldRequirements hint="Select check-out date" />
                                </Label>
                                <div className="relative">
                                    <DatePickerField
                                        id="madinah_check_out"
                                        value={data.madinah_check_out}
                                        onChange={(v) =>
                                            setData('madinah_check_out', v)
                                        }
                                        disabled={isView}
                                    />
                                    {renderError('madinah_check_out')}
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Meals & Notes */}
            <Card>
                <CardHeader>
                    <CardTitle>Meals & Notes</CardTitle>
                </CardHeader>
                <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div className="grid w-full items-center gap-3">
                        <Label htmlFor="first_meal">
                            First Meal
                            <FieldRequirements hint="Select first meal" />
                        </Label>
                        <div className="relative">
                            <Select
                                value={data.first_meal}
                                onValueChange={(v) => setData('first_meal', v)}
                                disabled={isView}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select Meal" />
                                </SelectTrigger>
                                <SelectContent>
                                    {MEAL_OPTIONS.map((m) => (
                                        <SelectItem key={m} value={m}>
                                            {m}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {renderError('first_meal')}
                        </div>
                    </div>

                    <div className="grid w-full items-center gap-3">
                        <Label htmlFor="last_meal">
                            Last Meal
                            <FieldRequirements hint="Select last meal" />
                        </Label>
                        <div className="relative">
                            <Select
                                value={data.last_meal}
                                onValueChange={(v) => setData('last_meal', v)}
                                disabled={isView}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select Meal" />
                                </SelectTrigger>
                                <SelectContent>
                                    {MEAL_OPTIONS.map((m) => (
                                        <SelectItem key={m} value={m}>
                                            {m}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {renderError('last_meal')}
                        </div>
                    </div>

                    <div className="grid w-full items-center gap-3 md:col-span-3">
                        <Label htmlFor="notes">
                            Notes
                            <FieldRequirements hint="Add any additional notes" />
                        </Label>
                        <div className="relative">
                            <Textarea
                                id="notes"
                                value={data.notes}
                                onChange={(e) =>
                                    setData('notes', e.target.value)
                                }
                                disabled={isView}
                                rows={3}
                            />
                            {renderError('notes')}
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Travelers Tab Section */}
            <Card>
                <CardHeader>
                    <CardTitle>Travelers</CardTitle>
                </CardHeader>
                <CardContent>
                    <Tabs defaultValue="travelers" className="w-full">
                        <TabsList>
                            <TabsTrigger value="travelers">
                                Travelers ({data.travelers?.length ?? 0})
                            </TabsTrigger>
                            <TabsTrigger value="travelers1">
                                Travelers1
                            </TabsTrigger>
                        </TabsList>
                        <TabsContent
                            value="travelers"
                            className="mt-4 space-y-4"
                        >
                            {!isView && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={addTraveler}
                                >
                                    <Plus className="mr-1 h-4 w-4" />
                                    Add Traveler
                                </Button>
                            )}

                            {(data.travelers ?? []).length > 0 && (
                                <div className="overflow-x-auto">
                                    <table className="w-full border text-base">
                                        <thead>
                                            <tr className="border-b bg-muted/50">
                                                <th className="p-2 text-left">
                                                    S/N
                                                </th>
                                                <th className="p-2 text-left">
                                                    Name (as per passport)
                                                </th>
                                                <th className="p-2 text-left">
                                                    Relationship
                                                </th>
                                                <th className="p-2 text-left">
                                                    Passport No
                                                </th>
                                                <th className="p-2 text-left">
                                                    Room No
                                                </th>
                                                <th className="p-2 text-left">
                                                    Room Type
                                                </th>
                                                <th className="p-2 text-left">
                                                    Bed Type
                                                </th>
                                                <th className="p-2 text-left">
                                                    DOB
                                                </th>
                                                <th className="p-2 text-left">
                                                    Age
                                                </th>
                                                <th className="p-2 text-left">
                                                    Meal
                                                </th>
                                                <th className="p-2 text-left">
                                                    Total Cost
                                                </th>
                                                <th className="p-2 text-left">
                                                    Total Paid
                                                </th>
                                                <th className="p-2 text-left">
                                                    Outstanding
                                                </th>
                                                <th className="p-2 text-left">
                                                    Remarks
                                                </th>
                                                {!isView && (
                                                    <th className="p-2 text-left">
                                                        Actions
                                                    </th>
                                                )}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {(data.travelers ?? []).map(
                                                (
                                                    traveler: TravelerSchema,
                                                    idx: number,
                                                ) => (
                                                    <tr
                                                        key={idx}
                                                        className="border-b"
                                                    >
                                                        <td className="p-2">
                                                            {traveler.sn}
                                                        </td>
                                                        <td className="p-2">
                                                            <div className="relative">
                                                                <ProperInput
                                                                    value={
                                                                        traveler.name_as_per_passport ??
                                                                        ''
                                                                    }
                                                                    onCommit={(
                                                                        v,
                                                                    ) =>
                                                                        updateTraveler(
                                                                            idx,
                                                                            'name_as_per_passport',
                                                                            v,
                                                                        )
                                                                    }
                                                                    disabled={
                                                                        isView
                                                                    }
                                                                    className="min-w-[180px]"
                                                                />
                                                                {renderError(
                                                                    `travelers.${idx}.name_as_per_passport`,
                                                                )}
                                                            </div>
                                                        </td>
                                                        <td className="p-2">
                                                            <div className="relative">
                                                                <ProperInput
                                                                    value={
                                                                        traveler.relationship ??
                                                                        ''
                                                                    }
                                                                    onCommit={(
                                                                        v,
                                                                    ) =>
                                                                        updateTraveler(
                                                                            idx,
                                                                            'relationship',
                                                                            v,
                                                                        )
                                                                    }
                                                                    disabled={
                                                                        isView
                                                                    }
                                                                    className="min-w-[100px]"
                                                                />
                                                                {renderError(
                                                                    `travelers.${idx}.relationship`,
                                                                )}
                                                            </div>
                                                        </td>
                                                        <td className="p-2">
                                                            <div className="relative">
                                                                <ProperInput
                                                                    value={
                                                                        traveler.passport_no ??
                                                                        ''
                                                                    }
                                                                    onCommit={(
                                                                        v,
                                                                    ) =>
                                                                        updateTraveler(
                                                                            idx,
                                                                            'passport_no',
                                                                            v,
                                                                        )
                                                                    }
                                                                    disabled={
                                                                        isView
                                                                    }
                                                                    className="min-w-[120px]"
                                                                />
                                                                {renderError(
                                                                    `travelers.${idx}.passport_no`,
                                                                )}
                                                            </div>
                                                        </td>
                                                        <td className="p-2">
                                                            <div className="relative">
                                                                <ProperInput
                                                                    value={
                                                                        traveler.room_no ??
                                                                        ''
                                                                    }
                                                                    onCommit={(
                                                                        v,
                                                                    ) =>
                                                                        updateTraveler(
                                                                            idx,
                                                                            'room_no',
                                                                            v,
                                                                        )
                                                                    }
                                                                    disabled={
                                                                        isView
                                                                    }
                                                                    className="min-w-[80px]"
                                                                />
                                                                {renderError(
                                                                    `travelers.${idx}.room_no`,
                                                                )}
                                                            </div>
                                                        </td>
                                                        <td className="p-2">
                                                            <Select
                                                                value={
                                                                    traveler.room_type
                                                                }
                                                                onValueChange={(
                                                                    v,
                                                                ) =>
                                                                    updateTraveler(
                                                                        idx,
                                                                        'room_type',
                                                                        v,
                                                                    )
                                                                }
                                                                disabled={
                                                                    isView
                                                                }
                                                            >
                                                                <SelectTrigger className="min-w-[100px]">
                                                                    <SelectValue placeholder="-" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {ROOM_TYPE_OPTIONS.map(
                                                                        (r) => (
                                                                            <SelectItem
                                                                                key={
                                                                                    r
                                                                                }
                                                                                value={
                                                                                    r
                                                                                }
                                                                            >
                                                                                {
                                                                                    r
                                                                                }
                                                                            </SelectItem>
                                                                        ),
                                                                    )}
                                                                </SelectContent>
                                                            </Select>
                                                        </td>
                                                        <td className="p-2">
                                                            <Select
                                                                value={
                                                                    traveler.bed_type
                                                                }
                                                                onValueChange={(
                                                                    v,
                                                                ) =>
                                                                    updateTraveler(
                                                                        idx,
                                                                        'bed_type',
                                                                        v,
                                                                    )
                                                                }
                                                                disabled={
                                                                    isView
                                                                }
                                                            >
                                                                <SelectTrigger className="min-w-[100px]">
                                                                    <SelectValue placeholder="-" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {BED_TYPE_OPTIONS.map(
                                                                        (b) => (
                                                                            <SelectItem
                                                                                key={
                                                                                    b
                                                                                }
                                                                                value={
                                                                                    b
                                                                                }
                                                                            >
                                                                                {
                                                                                    b
                                                                                }
                                                                            </SelectItem>
                                                                        ),
                                                                    )}
                                                                </SelectContent>
                                                            </Select>
                                                        </td>
                                                        <td className="p-2">
                                                            <Input
                                                                type="date"
                                                                value={
                                                                    traveler.date_of_birth
                                                                }
                                                                onChange={(e) =>
                                                                    updateTraveler(
                                                                        idx,
                                                                        'date_of_birth',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                                disabled={
                                                                    isView
                                                                }
                                                                className="min-w-[130px]"
                                                            />
                                                        </td>
                                                        <td className="p-2">
                                                            <div className="relative">
                                                                <ProperInput
                                                                    type="number"
                                                                    value={String(
                                                                        traveler.age,
                                                                    )}
                                                                    onCommit={(
                                                                        v,
                                                                    ) =>
                                                                        updateTraveler(
                                                                            idx,
                                                                            'age',
                                                                            Number(
                                                                                v,
                                                                            ) ||
                                                                                0,
                                                                        )
                                                                    }
                                                                    disabled={
                                                                        isView
                                                                    }
                                                                    className="min-w-[60px]"
                                                                />
                                                                {renderError(
                                                                    `travelers.${idx}.age`,
                                                                )}
                                                            </div>
                                                        </td>
                                                        <td className="p-2">
                                                            <Select
                                                                value={
                                                                    traveler.meal
                                                                }
                                                                onValueChange={(
                                                                    v,
                                                                ) =>
                                                                    updateTraveler(
                                                                        idx,
                                                                        'meal',
                                                                        v,
                                                                    )
                                                                }
                                                                disabled={
                                                                    isView
                                                                }
                                                            >
                                                                <SelectTrigger className="min-w-[100px]">
                                                                    <SelectValue placeholder="-" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {MEAL_OPTIONS.map(
                                                                        (m) => (
                                                                            <SelectItem
                                                                                key={
                                                                                    m
                                                                                }
                                                                                value={
                                                                                    m
                                                                                }
                                                                            >
                                                                                {
                                                                                    m
                                                                                }
                                                                            </SelectItem>
                                                                        ),
                                                                    )}
                                                                </SelectContent>
                                                            </Select>
                                                        </td>
                                                        <td className="p-2">
                                                            <div className="relative">
                                                                <ProperInput
                                                                    type="number"
                                                                    value={String(
                                                                        traveler.total_cost,
                                                                    )}
                                                                    onCommit={(
                                                                        v,
                                                                    ) =>
                                                                        updateTraveler(
                                                                            idx,
                                                                            'total_cost',
                                                                            Number(
                                                                                v,
                                                                            ) ||
                                                                                0,
                                                                        )
                                                                    }
                                                                    disabled={
                                                                        isView
                                                                    }
                                                                    className="min-w-[100px]"
                                                                />
                                                                {renderError(
                                                                    `travelers.${idx}.total_cost`,
                                                                )}
                                                            </div>
                                                        </td>
                                                        <td className="p-2">
                                                            <div className="relative">
                                                                <ProperInput
                                                                    type="number"
                                                                    value={String(
                                                                        traveler.total_paid,
                                                                    )}
                                                                    onCommit={(
                                                                        v,
                                                                    ) =>
                                                                        updateTraveler(
                                                                            idx,
                                                                            'total_paid',
                                                                            Number(
                                                                                v,
                                                                            ) ||
                                                                                0,
                                                                        )
                                                                    }
                                                                    disabled={
                                                                        isView
                                                                    }
                                                                    className="min-w-[100px]"
                                                                />
                                                                {renderError(
                                                                    `travelers.${idx}.total_paid`,
                                                                )}
                                                            </div>
                                                        </td>
                                                        <td className="p-2">
                                                            <span className="font-medium">
                                                                {Number(
                                                                    traveler.outstanding_amount,
                                                                ).toFixed(2)}
                                                            </span>
                                                        </td>
                                                        <td className="p-2">
                                                            <div className="relative">
                                                                <ProperInput
                                                                    value={
                                                                        traveler.remarks ??
                                                                        ''
                                                                    }
                                                                    onCommit={(
                                                                        v,
                                                                    ) =>
                                                                        updateTraveler(
                                                                            idx,
                                                                            'remarks',
                                                                            v,
                                                                        )
                                                                    }
                                                                    disabled={
                                                                        isView
                                                                    }
                                                                    className="min-w-[120px]"
                                                                />
                                                                {renderError(
                                                                    `travelers.${idx}.remarks`,
                                                                )}
                                                            </div>
                                                        </td>
                                                        {!isView && (
                                                            <td className="p-2">
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        removeTraveler(
                                                                            idx,
                                                                        )
                                                                    }
                                                                >
                                                                    <Minus className="h-4 w-4 text-destructive" />
                                                                </Button>
                                                            </td>
                                                        )}
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            )}

                            {(data.travelers ?? []).length === 0 && (
                                <p className="py-4 text-center text-base text-muted-foreground">
                                    No travelers added yet.
                                </p>
                            )}
                        </TabsContent>
                        <TabsContent
                            value="travelers1"
                            className="mt-4 space-y-4"
                        >
                            <p className="py-4 text-center text-base text-muted-foreground">
                                No travelers added yet.
                            </p>
                        </TabsContent>
                    </Tabs>
                </CardContent>
            </Card>

            {/* Action Buttons */}
            <div className="flex items-center justify-end gap-2">
                <Button type="button" variant="outline" onClick={onCancel}>
                    <ArrowLeft className="mr-1 h-4 w-4" />
                    Back
                </Button>
                {!isView && (
                    <>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => reset()}
                        >
                            <RotateCcw className="mr-1 h-4 w-4" />
                            Reset
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {isCreate ? 'Create Manifest' : 'Update Manifest'}
                        </Button>
                    </>
                )}
            </div>
        </form>
    );
}
