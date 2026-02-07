import { DatePickerField } from '@/components/date-picker';
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
import { ArrowLeft, Minus, Plus, RotateCcw } from 'lucide-react';
import { FormEvent, useCallback, useState } from 'react';
import {
    manifestSchema,
    type ManifestSchema,
    type TravelerSchema,
} from './schema';

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
    const isViewMode = mode === 'view';
    const defaults = getDefaultValues(initialData);

    const { data, setData, post, put, processing, errors, reset } =
        useForm<ManifestSchema>(defaults);

    const [clientErrors, setClientErrors] = useState<Record<string, string>>(
        {},
    );

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
                (_, i) => i !== index,
            );
            // Re-number
            const reNumbered = updated.map((t, i) => ({ ...t, sn: i + 1 }));
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

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        setClientErrors({});

        const result = manifestSchema.safeParse(data);
        if (!result.success) {
            const fieldErrors: Record<string, string> = {};
            result.error.issues.forEach((err) => {
                const path = err.path.join('.');
                fieldErrors[path] = err.message;
            });
            setClientErrors(fieldErrors);
            return;
        }

        if (mode === 'create') {
            post(store().url);
        } else if (mode === 'edit' && data.id) {
            put(update(data.id).url);
        }
    };

    const allErrors = { ...clientErrors };
    Object.entries(errors).forEach(([key, value]) => {
        if (value) allErrors[key] = value;
    });
    const hasErrors = Object.keys(allErrors).length > 0;

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            {hasErrors && (
                <div className="rounded-md border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950">
                    <h3 className="text-sm font-medium text-red-800 dark:text-red-200">
                        Please fix the following errors:
                    </h3>
                    <ul className="mt-2 list-inside list-disc text-sm text-red-700 dark:text-red-300">
                        {Object.entries(allErrors).map(([key, msg]) => (
                            <li key={key}>{msg}</li>
                        ))}
                    </ul>
                </div>
            )}

            {/* Manifest Information */}
            <Card>
                <CardHeader>
                    <CardTitle>Manifest Information</CardTitle>
                </CardHeader>
                <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div className="space-y-2">
                        <Label htmlFor="package_id">Package *</Label>
                        <Select
                            value={String(data.package_id || '')}
                            onValueChange={(v) =>
                                setData('package_id', Number(v))
                            }
                            disabled={isViewMode}
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
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="reference_number">
                            Reference Number *
                        </Label>
                        <Input
                            id="reference_number"
                            value={data.reference_number}
                            onChange={(e) =>
                                setData('reference_number', e.target.value)
                            }
                            disabled={isViewMode}
                        />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="status">Status</Label>
                        <Select
                            value={data.status}
                            onValueChange={(v) => setData('status', v)}
                            disabled={isViewMode}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select Status" />
                            </SelectTrigger>
                            <SelectContent>
                                {STATUS_OPTIONS.map((s) => (
                                    <SelectItem key={s} value={s}>
                                        {s.charAt(0).toUpperCase() + s.slice(1)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="company_address">Company Address</Label>
                        <Input
                            id="company_address"
                            value={data.company_address}
                            onChange={(e) =>
                                setData('company_address', e.target.value)
                            }
                            disabled={isViewMode}
                        />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="company_phone">Company Phone</Label>
                        <Input
                            id="company_phone"
                            value={data.company_phone}
                            onChange={(e) =>
                                setData('company_phone', e.target.value)
                            }
                            disabled={isViewMode}
                        />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="duration">Duration</Label>
                        <Input
                            id="duration"
                            value={data.duration}
                            onChange={(e) =>
                                setData('duration', e.target.value)
                            }
                            disabled={isViewMode}
                            placeholder="e.g. 14 Days / 13 Nights"
                        />
                    </div>

                    <DatePickerField
                        label="Departure Date *"
                        value={data.departure_date}
                        onChange={(v) => setData('departure_date', v)}
                        disabled={isViewMode}
                    />

                    <DatePickerField
                        label="Return Date *"
                        value={data.return_date}
                        onChange={(v) => setData('return_date', v)}
                        disabled={isViewMode}
                    />
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
                        <h4 className="mb-3 text-sm font-medium text-muted-foreground">
                            Makkah Hotel
                        </h4>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div className="space-y-2">
                                <Label htmlFor="makkah_hotel">Hotel Name</Label>
                                <Input
                                    id="makkah_hotel"
                                    value={data.makkah_hotel}
                                    onChange={(e) =>
                                        setData('makkah_hotel', e.target.value)
                                    }
                                    disabled={isViewMode}
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="check_in">Check In</Label>
                                <DatePickerField
                                    id="check_in"
                                    value={data.makkah_check_in}
                                    onChange={(v) =>
                                        setData('makkah_check_in', v)
                                    }
                                    disabled={isViewMode}
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="check_out">Check Out</Label>
                                <DatePickerField
                                    id="check_out"
                                    value={data.makkah_check_out}
                                    onChange={(v) =>
                                        setData('makkah_check_out', v)
                                    }
                                    disabled={isViewMode}
                                />
                            </div>
                        </div>
                    </div>
                    {/* Madinah */}
                    <div>
                        <h4 className="mb-3 text-sm font-medium text-muted-foreground">
                            Madinah Hotel
                        </h4>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div className="space-y-2">
                                <Label htmlFor="madinah_hotel">
                                    Hotel Name
                                </Label>
                                <Input
                                    id="madinah_hotel"
                                    value={data.madinah_hotel}
                                    onChange={(e) =>
                                        setData('madinah_hotel', e.target.value)
                                    }
                                    disabled={isViewMode}
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="check_in">Check In</Label>
                                <DatePickerField
                                    id="check_in"
                                    value={data.madinah_check_in}
                                    onChange={(v) =>
                                        setData('madinah_check_in', v)
                                    }
                                    disabled={isViewMode}
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="check_out">Check Out</Label>
                                <DatePickerField
                                    id="check_out"
                                    value={data.madinah_check_out}
                                    onChange={(v) =>
                                        setData('madinah_check_out', v)
                                    }
                                    disabled={isViewMode}
                                />
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
                    <div className="space-y-2">
                        <Label htmlFor="first_meal">First Meal</Label>
                        <Select
                            value={data.first_meal}
                            onValueChange={(v) => setData('first_meal', v)}
                            disabled={isViewMode}
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
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="last_meal">Last Meal</Label>
                        <Select
                            value={data.last_meal}
                            onValueChange={(v) => setData('last_meal', v)}
                            disabled={isViewMode}
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
                    </div>

                    <div className="space-y-2 md:col-span-3">
                        <Label htmlFor="notes">Notes</Label>
                        <Textarea
                            id="notes"
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                            disabled={isViewMode}
                            rows={3}
                        />
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
                        </TabsList>
                        <TabsContent
                            value="travelers"
                            className="mt-4 space-y-4"
                        >
                            {!isViewMode && (
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
                                    <table className="w-full border text-sm">
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
                                                {!isViewMode && (
                                                    <th className="p-2 text-left">
                                                        Actions
                                                    </th>
                                                )}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {(data.travelers ?? []).map(
                                                (traveler, idx) => (
                                                    <tr
                                                        key={idx}
                                                        className="border-b"
                                                    >
                                                        <td className="p-2">
                                                            {traveler.sn}
                                                        </td>
                                                        <td className="p-2">
                                                            <Input
                                                                value={
                                                                    traveler.name_as_per_passport
                                                                }
                                                                onChange={(e) =>
                                                                    updateTraveler(
                                                                        idx,
                                                                        'name_as_per_passport',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                                disabled={
                                                                    isViewMode
                                                                }
                                                                className="min-w-[180px]"
                                                            />
                                                        </td>
                                                        <td className="p-2">
                                                            <Input
                                                                value={
                                                                    traveler.relationship
                                                                }
                                                                onChange={(e) =>
                                                                    updateTraveler(
                                                                        idx,
                                                                        'relationship',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                                disabled={
                                                                    isViewMode
                                                                }
                                                                className="min-w-[100px]"
                                                            />
                                                        </td>
                                                        <td className="p-2">
                                                            <Input
                                                                value={
                                                                    traveler.passport_no
                                                                }
                                                                onChange={(e) =>
                                                                    updateTraveler(
                                                                        idx,
                                                                        'passport_no',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                                disabled={
                                                                    isViewMode
                                                                }
                                                                className="min-w-[120px]"
                                                            />
                                                        </td>
                                                        <td className="p-2">
                                                            <Input
                                                                value={
                                                                    traveler.room_no
                                                                }
                                                                onChange={(e) =>
                                                                    updateTraveler(
                                                                        idx,
                                                                        'room_no',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                                disabled={
                                                                    isViewMode
                                                                }
                                                                className="min-w-[80px]"
                                                            />
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
                                                                    isViewMode
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
                                                                    isViewMode
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
                                                                    isViewMode
                                                                }
                                                                className="min-w-[130px]"
                                                            />
                                                        </td>
                                                        <td className="p-2">
                                                            <Input
                                                                type="number"
                                                                value={
                                                                    traveler.age
                                                                }
                                                                onChange={(e) =>
                                                                    updateTraveler(
                                                                        idx,
                                                                        'age',
                                                                        Number(
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        ),
                                                                    )
                                                                }
                                                                disabled={
                                                                    isViewMode
                                                                }
                                                                className="min-w-[60px]"
                                                            />
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
                                                                    isViewMode
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
                                                            <Input
                                                                type="number"
                                                                step="0.01"
                                                                value={
                                                                    traveler.total_cost
                                                                }
                                                                onChange={(e) =>
                                                                    updateTraveler(
                                                                        idx,
                                                                        'total_cost',
                                                                        Number(
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        ),
                                                                    )
                                                                }
                                                                disabled={
                                                                    isViewMode
                                                                }
                                                                className="min-w-[100px]"
                                                            />
                                                        </td>
                                                        <td className="p-2">
                                                            <Input
                                                                type="number"
                                                                step="0.01"
                                                                value={
                                                                    traveler.total_paid
                                                                }
                                                                onChange={(e) =>
                                                                    updateTraveler(
                                                                        idx,
                                                                        'total_paid',
                                                                        Number(
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        ),
                                                                    )
                                                                }
                                                                disabled={
                                                                    isViewMode
                                                                }
                                                                className="min-w-[100px]"
                                                            />
                                                        </td>
                                                        <td className="p-2">
                                                            <span className="font-medium">
                                                                {Number(
                                                                    traveler.outstanding_amount,
                                                                ).toFixed(2)}
                                                            </span>
                                                        </td>
                                                        <td className="p-2">
                                                            <Input
                                                                value={
                                                                    traveler.remarks
                                                                }
                                                                onChange={(e) =>
                                                                    updateTraveler(
                                                                        idx,
                                                                        'remarks',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                                disabled={
                                                                    isViewMode
                                                                }
                                                                className="min-w-[120px]"
                                                            />
                                                        </td>
                                                        {!isViewMode && (
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
                                <p className="py-4 text-center text-sm text-muted-foreground">
                                    No travelers added yet.
                                </p>
                            )}
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
                {!isViewMode && (
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
                            {mode === 'create'
                                ? 'Create Manifest'
                                : 'Update Manifest'}
                        </Button>
                    </>
                )}
            </div>
        </form>
    );
}
