import { DatePickerField } from '@/components/date-picker';
import { FormField } from '@/components/form-field';
import { ProperInput } from '@/components/proper-input';
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
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { isBeforeToday, parseDisplayDate } from '@/lib/utils';
import { store, update } from '@/routes/packages';
import { useForm } from '@inertiajs/react';
import { AlertCircle, Plus, Trash2 } from 'lucide-react';
import { useEffect, useRef } from 'react';
import {
    infantAndChildPriceLabels,
    officialTypeOptions,
    packageMealPlanOptions,
    sharingPlanPriceLabels,
    type AccommodationSchema,
    type FlightSchema,
    type OfficialSchema,
    type PackageSchema,
} from './schema';
import { packageValidationSchema } from './validation';

const defaultFlights: FlightSchema[] = [
    {
        from: '',
        to: '',
        description: 'Departure',
        airline: '',
        pnr: '',
        departure_datetime: '',
        arrival_datetime: '',
    },
    {
        from: '',
        to: '',
        description: 'Return',
        airline: '',
        pnr: '',
        departure_datetime: '',
        arrival_datetime: '',
    },
];

const defaultOfficials: OfficialSchema[] = [
    { type: 'mutawif', name: '', contact_number: '' },
    { type: 'mutawifah', name: '', contact_number: '' },
    { type: 'official', name: '', contact_number: '' },
];

interface PackageFormProps {
    mode: 'create' | 'edit' | 'view';
    initialData?: PackageSchema;
    prefillData?: Partial<PackageSchema>;
    onCancel?: () => void;
    onSuccess?: (data: PackageSchema) => void;
}

export default function PackageForm({
    mode,
    initialData,
    prefillData,
    onCancel,
    onSuccess,
}: PackageFormProps) {
    const isView = mode === 'view';
    const isEdit = mode === 'edit';
    const isCreate = mode === 'create';

    const defaultData: PackageSchema = initialData || {
        name: '',
        status: 'open',
        price_single: 0,
        price_double: 0,
        price_triple: 0,
        price_quad: 0,
        child_with_bed_price: 0,
        child_no_bed_price: 0,
        infant_price: 0,
        departure_date: '',
        return_date: '',
        total_seats: null,
        seats_left: null,
        occupied_seats: 0,
        visa_type: '',
        vehicle_type: '',
        ticket_type: '',
        included: '',
        not_included: '',
        offer: '',
        remarks: '',
        accommodations: [],
        flights: [...defaultFlights],
        officials: [...defaultOfficials],
        ...prefillData,
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
    } = useForm<PackageSchema>(defaultData);

    const lastAppliedPrefillRef = useRef<string | null>(null);

    useEffect(() => {
        if (!isCreate || !prefillData) {
            return;
        }

        const prefillSignature = JSON.stringify(prefillData);

        if (lastAppliedPrefillRef.current === prefillSignature) {
            return;
        }

        setData((currentData) => ({
            ...currentData,
            ...prefillData,
        }));
        lastAppliedPrefillRef.current = prefillSignature;
    }, [isCreate, prefillData, setData]);

    function validateClientSide(): boolean {
        clearErrors();
        let valid = true;

        const result = packageValidationSchema.safeParse(data);

        if (!result.success) {
            result.error.issues.forEach((issue) => {
                const key = issue.path.join('.');
                if (typeof key === 'string') {
                    setError(key as keyof PackageSchema, issue.message);
                }
            });
            valid = false;
        }

        return valid;
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (!validateClientSide()) return;

        // Dialog mode: pass data back without submitting to server
        if (onSuccess) {
            onSuccess(data);
            return;
        }

        if (isCreate) {
            post(store().url, {
                onError: (errors) => setError(errors),
            });
        } else if (isEdit) {
            put(update(data.id!).url, {
                onError: (errors) => setError(errors),
            });
        }
    }

    const getError = (fieldName: string): string | undefined => {
        return (errors as Record<string, string>)[fieldName];
    };

    const addAccommodation = () => {
        const current = data.accommodations || [];
        setData('accommodations', [
            ...current,
            {
                location: '',
                hotel_name: '',
                type_of_meal: '',
                check_in: '',
                check_out: '',
            },
        ]);
    };

    const removeAccommodation = (index: number) => {
        const current = data.accommodations || [];
        setData(
            'accommodations',
            current.filter((_, i) => i !== index),
        );
    };

    const updateAccommodation = (
        index: number,
        field: keyof AccommodationSchema,
        value: string | number,
    ) => {
        const current = [...(data.accommodations || [])];
        current[index] = { ...current[index], [field]: value };
        setData('accommodations', current);
    };

    // --- Flight helpers ---
    const addFlight = () => {
        const current = data.flights || [];
        setData('flights', [
            ...current,
            {
                from: '',
                to: '',
                description: '',
                airline: '',
                pnr: '',
                departure_datetime: '',
                arrival_datetime: '',
            },
        ]);
    };

    const removeFlight = (index: number) => {
        const current = data.flights || [];
        setData(
            'flights',
            current.filter((_, i) => i !== index),
        );
    };

    const updateFlight = (
        index: number,
        field: keyof FlightSchema,
        value: string | number | null,
    ) => {
        const current = [...(data.flights || [])];
        current[index] = { ...current[index], [field]: value };
        setData('flights', current);
    };

    // --- Official helpers ---
    const addOfficial = () => {
        const current = data.officials || [];
        setData('officials', [
            ...current,
            { type: '', name: '', contact_number: '' },
        ]);
    };

    const removeOfficial = (index: number) => {
        const current = data.officials || [];
        setData(
            'officials',
            current.filter((_, i) => i !== index),
        );
    };

    const updateOfficial = (
        index: number,
        field: keyof OfficialSchema,
        value: string | number | null,
    ) => {
        const current = [...(data.officials || [])];
        current[index] = { ...current[index], [field]: value };
        setData('officials', current);
    };

    const toStartOfDay = (value?: string | null): Date | null => {
        const parsed = parseDisplayDate(value);

        if (!parsed) {
            return null;
        }

        const normalized = new Date(parsed);
        normalized.setHours(0, 0, 0, 0);

        return normalized;
    };

    const disabledReturnDates = (date: Date): boolean => {
        if (isBeforeToday(date)) {
            return true;
        }

        const departureDate = toStartOfDay(data.departure_date);
        if (!departureDate) {
            return false;
        }

        const candidateDate = new Date(date);
        candidateDate.setHours(0, 0, 0, 0);

        return candidateDate < departureDate;
    };

    const occupiedSeats = Number(data.occupied_seats ?? 0);

    return (
        <div className="mx-auto w-full">
            <form onSubmit={submit} className="space-y-4">
                {/* Error Alert */}
                {Object.keys(errors).length > 0 && !isView && (
                    <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>
                            Please fix the errors below and try again
                        </AlertDescription>
                    </Alert>
                )}

                {/* Package Information */}
                <Card>
                    <CardHeader className="gap-0">
                        <CardTitle className="text-xl">
                            Package Information
                        </CardTitle>
                        <CardDescription>
                            Define the package identity and status.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            {/* Group Number (auto-generated, read-only) */}
                            {(isEdit || isView) && data.package_number && (
                                <FormField
                                    label="Package Number"
                                    htmlFor="package_number"
                                    fieldRequirementsProps={{
                                        hint: 'Auto-generated package identifier',
                                    }}
                                >
                                    <Input
                                        id="package_number"
                                        type="text"
                                        value={data.package_number}
                                        disabled={true}
                                        className="bg-muted"
                                    />
                                </FormField>
                            )}

                            {/* Package Name */}
                            <FormField
                                label="Package Name"
                                htmlFor="name"
                                fieldRequirementsProps={{
                                    required: true,
                                    hint: 'Enter the name of the package',
                                }}
                                error={getError('name')}
                            >
                                <ProperInput
                                    id="name"
                                    value={data.name ?? ''}
                                    disabled={isView || processing}
                                    onCommit={(v) => setData('name', v)}
                                    placeholder="Enter package name"
                                />
                            </FormField>

                            {/* Status */}
                            <FormField
                                label="Status"
                                fieldRequirementsProps={{
                                    required: true,
                                    hint: 'Select package status',
                                }}
                                error={getError('status')}
                            >
                                <Select
                                    disabled={isView || processing}
                                    value={data.status}
                                    onValueChange={(value) =>
                                        setData(
                                            'status',
                                            value as 'open' | 'closed',
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="open">
                                            Open
                                        </SelectItem>
                                        <SelectItem value="closed">
                                            Closed
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                            <FormField
                                label="Departure Date"
                                fieldRequirementsProps={{
                                    hint: 'Select departure date',
                                }}
                                error={getError('departure_date')}
                            >
                                <DatePickerField
                                    id="departure_date"
                                    value={data.departure_date || ''}
                                    fromYear={new Date().getFullYear()}
                                    toYear={new Date().getFullYear() + 5}
                                    disabled={isView || processing}
                                    onChange={(v) =>
                                        setData('departure_date', v)
                                    }
                                />
                            </FormField>
                            <FormField
                                label="Return Date"
                                fieldRequirementsProps={{
                                    hint: 'Select return date',
                                }}
                                error={getError('return_date')}
                            >
                                <DatePickerField
                                    id="return_date"
                                    value={data.return_date || ''}
                                    fromYear={new Date().getFullYear()}
                                    toYear={new Date().getFullYear() + 5}
                                    disabled={isView || processing}
                                    disabledDates={disabledReturnDates}
                                    onChange={(v) => setData('return_date', v)}
                                />
                            </FormField>
                            <FormField
                                label="Total Seats"
                                htmlFor="total_seats"
                                fieldRequirementsProps={{
                                    hint: `Active paid members: ${occupiedSeats} (officials excluded)`,
                                }}
                                error={getError('total_seats')}
                            >
                                <ProperInput
                                    id="total_seats"
                                    type="number"
                                    value={data.total_seats ?? ''}
                                    disabled={isView || processing}
                                    onCommit={(v) => {
                                        const val = v ? parseInt(v) : null;
                                        const seatsLeft =
                                            val === null
                                                ? null
                                                : Math.max(0, val - occupiedSeats);

                                        setData((prev) => ({
                                            ...prev,
                                            total_seats: val,
                                            seats_left: seatsLeft,
                                        }));
                                    }}
                                    inputProps={{ min: '0' }}
                                    placeholder="0"
                                />
                            </FormField>
                            <FormField
                                label="Seats Left"
                                htmlFor="seats_left"
                                fieldRequirementsProps={{
                                    hint: 'Auto-filled from total seats',
                                }}
                                error={getError('seats_left')}
                            >
                                <Input
                                    id="seats_left"
                                    type="number"
                                    value={data.seats_left ?? ''}
                                    disabled={true}
                                    className="bg-muted"
                                />
                            </FormField>
                        </div>
                    </CardContent>
                </Card>

                {/* Pricing */}
                <Card>
                    <CardHeader className="gap-0">
                        <CardTitle className="text-xl">Pricing</CardTitle>
                        <CardDescription>
                            Set occupancy and child/infant pricing.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                            {sharingPlanPriceLabels.map(({ key, label }) => (
                                <FormField
                                    key={key}
                                    label={label}
                                    htmlFor={key}
                                    fieldRequirementsProps={{
                                        hint: 'Enter price amount',
                                    }}
                                    error={getError(key)}
                                >
                                    <ProperInput
                                        id={key}
                                        type="number"
                                        value={
                                            data[
                                                key as keyof PackageSchema
                                            ] as number
                                        }
                                        disabled={isView || processing}
                                        onCommit={(v) =>
                                            setData(
                                                key as keyof PackageSchema,
                                                parseFloat(v) || 0,
                                            )
                                        }
                                        inputProps={{
                                            min: '0',
                                            step: '0.01',
                                        }}
                                    />
                                </FormField>
                            ))}
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            {infantAndChildPriceLabels.map(({ key, label }) => (
                                <FormField
                                    key={key}
                                    label={label}
                                    htmlFor={key}
                                    fieldRequirementsProps={{
                                        hint: 'Enter price amount',
                                    }}
                                    error={getError(key)}
                                >
                                    <ProperInput
                                        id={key}
                                        type="number"
                                        value={
                                            data[
                                                key as keyof PackageSchema
                                            ] as number
                                        }
                                        disabled={isView || processing}
                                        onCommit={(v) =>
                                            setData(
                                                key as keyof PackageSchema,
                                                parseFloat(v) || 0,
                                            )
                                        }
                                        inputProps={{
                                            min: '0',
                                            step: '0.01',
                                        }}
                                    />
                                </FormField>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                {/* Flight Details */}
                <Card>
                    <CardHeader className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <CardTitle className="text-xl">
                                Flight Details
                            </CardTitle>
                            <CardDescription>
                                Add flight information for this package.
                            </CardDescription>
                        </div>
                        {!isView && (
                            <Button
                                type="button"
                                variant="default"
                                className="w-full sm:w-auto"
                                onClick={addFlight}
                                disabled={processing}
                            >
                                <Plus className="mr-1 h-4 w-4" />
                                Add Flight
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent>
                        {(data.flights || []).length === 0 ? (
                            <p className="text-base text-muted-foreground">
                                No flights added yet. Click "Add Flight" to add
                                flight details.
                            </p>
                        ) : (
                            <div className="space-y-4">
                                {(data.flights || []).map((flight, index) => (
                                    <div
                                        key={index}
                                        className="space-y-4 rounded-lg border p-4"
                                    >
                                        <div className="flex items-center justify-between">
                                            <span className="font-semibold">
                                                Flight {index + 1}
                                                {flight.description
                                                    ? ` — ${flight.description}`
                                                    : ''}
                                            </span>
                                            {!isView && (
                                                <Button
                                                    type="button"
                                                    variant="destructive"
                                                    size="sm"
                                                    onClick={() =>
                                                        removeFlight(index)
                                                    }
                                                    disabled={processing}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            )}
                                        </div>
                                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                            <FormField
                                                label="Description"
                                                fieldRequirementsProps={{
                                                    hint: 'e.g., Departure, Return, Transit',
                                                }}
                                                error={getError(
                                                    `flights.${index}.description`,
                                                )}
                                            >
                                                <ProperInput
                                                    value={
                                                        flight.description ?? ''
                                                    }
                                                    disabled={
                                                        isView || processing
                                                    }
                                                    onCommit={(v) =>
                                                        updateFlight(
                                                            index,
                                                            'description',
                                                            v || null,
                                                        )
                                                    }
                                                    placeholder="e.g., Departure"
                                                />
                                            </FormField>
                                            <FormField
                                                label="From"
                                                fieldRequirementsProps={{
                                                    required: true,
                                                    hint: 'Departure location',
                                                }}
                                                error={getError(
                                                    `flights.${index}.from`,
                                                )}
                                            >
                                                <ProperInput
                                                    value={flight.from ?? ''}
                                                    disabled={
                                                        isView || processing
                                                    }
                                                    onCommit={(v) =>
                                                        updateFlight(
                                                            index,
                                                            'from',
                                                            v || null,
                                                        )
                                                    }
                                                    placeholder="e.g., KLIA"
                                                />
                                            </FormField>
                                            <FormField
                                                label="To"
                                                fieldRequirementsProps={{
                                                    required: true,
                                                    hint: 'Arrival location',
                                                }}
                                                error={getError(
                                                    `flights.${index}.to`,
                                                )}
                                            >
                                                <ProperInput
                                                    value={flight.to ?? ''}
                                                    disabled={
                                                        isView || processing
                                                    }
                                                    onCommit={(v) =>
                                                        updateFlight(
                                                            index,
                                                            'to',
                                                            v || null,
                                                        )
                                                    }
                                                    placeholder="e.g., Jeddah"
                                                />
                                            </FormField>
                                        </div>
                                        <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                                            <FormField
                                                label="Airline"
                                                fieldRequirementsProps={{
                                                    hint: 'Enter airline name',
                                                }}
                                                error={getError(
                                                    `flights.${index}.airline`,
                                                )}
                                            >
                                                <ProperInput
                                                    value={flight.airline ?? ''}
                                                    disabled={
                                                        isView || processing
                                                    }
                                                    onCommit={(v) =>
                                                        updateFlight(
                                                            index,
                                                            'airline',
                                                            v || null,
                                                        )
                                                    }
                                                    placeholder="e.g., Saudi Airlines"
                                                />
                                            </FormField>
                                            <FormField
                                                label="PNR"
                                                fieldRequirementsProps={{
                                                    hint: 'Passenger Name Record',
                                                }}
                                                error={getError(
                                                    `flights.${index}.pnr`,
                                                )}
                                            >
                                                <ProperInput
                                                    value={flight.pnr ?? ''}
                                                    disabled={
                                                        isView || processing
                                                    }
                                                    onCommit={(v) =>
                                                        updateFlight(
                                                            index,
                                                            'pnr',
                                                            v || null,
                                                        )
                                                    }
                                                    placeholder="Enter PNR"
                                                />
                                            </FormField>
                                            <FormField
                                                label="Departure"
                                                fieldRequirementsProps={{
                                                    hint: 'Departure date & time',
                                                }}
                                                error={getError(
                                                    `flights.${index}.departure_datetime`,
                                                )}
                                            >
                                                <DatePickerField
                                                    id={`flight_departure_${index}`}
                                                    value={
                                                        flight.departure_datetime ||
                                                        ''
                                                    }
                                                    fromYear={new Date().getFullYear()}
                                                    toYear={
                                                        new Date().getFullYear() +
                                                        5
                                                    }
                                                    disabled={
                                                        isView || processing
                                                    }
                                                    useTime
                                                    onChange={(v) =>
                                                        updateFlight(
                                                            index,
                                                            'departure_datetime',
                                                            v || null,
                                                        )
                                                    }
                                                />
                                            </FormField>
                                            <FormField
                                                label="Arrival"
                                                fieldRequirementsProps={{
                                                    hint: 'Arrival date & time',
                                                }}
                                                error={getError(
                                                    `flights.${index}.arrival_datetime`,
                                                )}
                                            >
                                                <DatePickerField
                                                    id={`flight_arrival_${index}`}
                                                    value={
                                                        flight.arrival_datetime ||
                                                        ''
                                                    }
                                                    fromYear={new Date().getFullYear()}
                                                    toYear={
                                                        new Date().getFullYear() +
                                                        5
                                                    }
                                                    disabled={
                                                        isView || processing
                                                    }
                                                    useTime
                                                    onChange={(v) =>
                                                        updateFlight(
                                                            index,
                                                            'arrival_datetime',
                                                            v || null,
                                                        )
                                                    }
                                                />
                                            </FormField>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Visa, Vehicle & Train */}
                <Card>
                    <CardHeader className="gap-0">
                        <CardTitle className="text-xl">
                            Visa, Vehicle & Train
                        </CardTitle>
                        <CardDescription>
                            Capture travel support and transport setup.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <FormField
                                label="Visa Type"
                                htmlFor="visa_type"
                                fieldRequirementsProps={{
                                    hint: 'Enter visa type',
                                }}
                                error={getError('visa_type')}
                            >
                                <ProperInput
                                    id="visa_type"
                                    value={data.visa_type || ''}
                                    disabled={isView || processing}
                                    onCommit={(v) =>
                                        setData('visa_type', v || null)
                                    }
                                    placeholder="e.g., Umrah Visa"
                                />
                            </FormField>
                            <FormField
                                label="Vehicle Type"
                                htmlFor="vehicle_type"
                                fieldRequirementsProps={{
                                    hint: 'Enter vehicle type',
                                }}
                                error={getError('vehicle_type')}
                            >
                                <ProperInput
                                    id="vehicle_type"
                                    value={data.vehicle_type || ''}
                                    disabled={isView || processing}
                                    onCommit={(v) =>
                                        setData('vehicle_type', v || null)
                                    }
                                    placeholder="e.g., Bus 45 Seater"
                                />
                            </FormField>
                            <FormField
                                label="Train Ticket Type"
                                htmlFor="ticket_type"
                                fieldRequirementsProps={{
                                    hint: 'Enter train ticket type',
                                }}
                                error={getError('ticket_type')}
                            >
                                <ProperInput
                                    id="ticket_type"
                                    value={data.ticket_type || ''}
                                    disabled={isView || processing}
                                    onCommit={(v) =>
                                        setData('ticket_type', v || null)
                                    }
                                    placeholder="e.g., Economy, Business"
                                />
                            </FormField>
                        </div>
                    </CardContent>
                </Card>

                {/* Accommodations */}
                <Card>
                    <CardHeader className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <CardTitle className="text-xl">
                                Accommodations
                            </CardTitle>
                            <CardDescription>
                                Add accommodation entries for each location.
                            </CardDescription>
                        </div>
                        {!isView && (
                            <Button
                                type="button"
                                variant="default"
                                className="w-full sm:w-auto"
                                onClick={addAccommodation}
                                disabled={processing}
                            >
                                <Plus className="mr-1 h-4 w-4" />
                                Add Accommodation
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent>
                        {(data.accommodations || []).length === 0 ? (
                            <p className="text-base text-muted-foreground">
                                No accommodations added yet. Click "Add
                                Accommodation" to add places like Mekkah,
                                Madinah, Taif, etc.
                            </p>
                        ) : (
                            <div className="space-y-4">
                                {(data.accommodations || []).map(
                                    (accommodation, index) => (
                                        <div
                                            key={index}
                                            className="space-y-4 rounded-lg border p-4"
                                        >
                                            <div className="flex items-center justify-between">
                                                <span className="font-semibold">
                                                    Accommodation {index + 1}
                                                    {accommodation.location
                                                        ? ` - ${accommodation.location}`
                                                        : ''}
                                                </span>
                                                {!isView && (
                                                    <Button
                                                        type="button"
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() =>
                                                            removeAccommodation(
                                                                index,
                                                            )
                                                        }
                                                        disabled={processing}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                )}
                                            </div>

                                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                            <FormField
                                                label="Location"
                                                fieldRequirementsProps={{
                                                    required: true,
                                                    hint: 'Enter location',
                                                }}
                                                error={getError(
                                                    `accommodations.${index}.location`,
                                                )}
                                            >
                                                <ProperInput
                                                    value={
                                                        accommodation.location ??
                                                        ''
                                                    }
                                                    disabled={
                                                        isView || processing
                                                    }
                                                    onCommit={(v) =>
                                                        updateAccommodation(
                                                            index,
                                                            'location',
                                                            v,
                                                        )
                                                    }
                                                    placeholder="e.g., Mekkah"
                                                />
                                            </FormField>
                                            <FormField
                                                label="Hotel Name"
                                                fieldRequirementsProps={{
                                                    required: true,
                                                    hint: 'Enter hotel name',
                                                }}
                                                error={getError(
                                                    `accommodations.${index}.hotel_name`,
                                                )}
                                            >
                                                <ProperInput
                                                    value={
                                                        accommodation.hotel_name ??
                                                        ''
                                                    }
                                                    disabled={
                                                        isView || processing
                                                    }
                                                    onCommit={(v) =>
                                                        updateAccommodation(
                                                            index,
                                                            'hotel_name',
                                                            v,
                                                        )
                                                    }
                                                    placeholder="Hotel name"
                                                />
                                            </FormField>
                                            <FormField
                                                label="Meal Type"
                                                fieldRequirementsProps={{
                                                    hint: 'Enter meal type',
                                                }}
                                                error={getError(
                                                    `accommodations.${index}.type_of_meal`,
                                                )}
                                            >
                                                <ProperInputSelect
                                                    options={
                                                        packageMealPlanOptions
                                                    }
                                                    value={
                                                        accommodation.type_of_meal ??
                                                        ''
                                                    }
                                                    disabled={
                                                        isView || processing
                                                    }
                                                    onValueChange={(v) =>
                                                        updateAccommodation(
                                                            index,
                                                            'type_of_meal',
                                                            String(v || ''),
                                                        )
                                                    }
                                                    placeholder="Select meal plan"
                                                />
                                            </FormField>
                                            </div>

                                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <FormField
                                                label="Check In"
                                                fieldRequirementsProps={{
                                                    hint: 'Select check-in date',
                                                }}
                                                error={getError(
                                                    `accommodations.${index}.check_in`,
                                                )}
                                            >
                                                <DatePickerField
                                                    id={`acc_check_in_${index}`}
                                                    value={
                                                        accommodation.check_in ||
                                                        ''
                                                    }
                                                    fromYear={new Date().getFullYear()}
                                                    toYear={
                                                        new Date().getFullYear() +
                                                        5
                                                    }
                                                    disabled={
                                                        isView || processing
                                                    }
                                                    disabledDates={
                                                        isBeforeToday
                                                    }
                                                    onChange={(v) =>
                                                        updateAccommodation(
                                                            index,
                                                            'check_in',
                                                            v,
                                                        )
                                                    }
                                                />
                                            </FormField>
                                            <FormField
                                                label="Check Out"
                                                fieldRequirementsProps={{
                                                    hint: 'Select check-out date',
                                                }}
                                                error={getError(
                                                    `accommodations.${index}.check_out`,
                                                )}
                                            >
                                                <DatePickerField
                                                    id={`acc_check_out_${index}`}
                                                    value={
                                                        accommodation.check_out ||
                                                        ''
                                                    }
                                                    fromYear={new Date().getFullYear()}
                                                    toYear={
                                                        new Date().getFullYear() +
                                                        5
                                                    }
                                                    disabled={
                                                        isView || processing
                                                    }
                                                    disabledDates={(date) => {
                                                        if (
                                                            isBeforeToday(date)
                                                        ) {
                                                            return true;
                                                        }

                                                        const checkInDate =
                                                            toStartOfDay(
                                                                accommodation.check_in,
                                                            );

                                                        if (!checkInDate) {
                                                            return false;
                                                        }

                                                        const candidateDate =
                                                            new Date(date);
                                                        candidateDate.setHours(
                                                            0,
                                                            0,
                                                            0,
                                                            0,
                                                        );

                                                        return (
                                                            candidateDate <=
                                                            checkInDate
                                                        );
                                                    }}
                                                    onChange={(v) =>
                                                        updateAccommodation(
                                                            index,
                                                            'check_out',
                                                            v,
                                                        )
                                                    }
                                                />
                                            </FormField>
                                            </div>
                                        </div>
                                    ),
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Officials */}
                <Card>
                    <CardHeader className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <CardTitle className="text-xl">Officials</CardTitle>
                            <CardDescription>
                                Assign officials for this package.
                            </CardDescription>
                        </div>
                        {!isView && (
                            <Button
                                type="button"
                                variant="default"
                                className="w-full sm:w-auto"
                                onClick={addOfficial}
                                disabled={processing}
                            >
                                <Plus className="mr-1 h-4 w-4" />
                                Add Official
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent>
                        {(data.officials || []).length === 0 ? (
                            <p className="text-base text-muted-foreground">
                                No officials added yet. Click "Add Official" to
                                assign officials.
                            </p>
                        ) : (
                            <div className="space-y-4">
                                {(data.officials || []).map(
                                    (official, index) => (
                                        <div
                                            key={index}
                                            className="space-y-4 rounded-lg border p-4"
                                        >
                                            <div className="flex items-center justify-between">
                                                <span className="font-semibold">
                                                    Official {index + 1}
                                                    {official.type
                                                        ? ` - ${official.type}`
                                                        : ''}
                                                </span>
                                                {!isView && (
                                                    <Button
                                                        type="button"
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() =>
                                                            removeOfficial(
                                                                index,
                                                            )
                                                        }
                                                        disabled={processing}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                )}
                                            </div>

                                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                            <FormField
                                                label="Type"
                                                fieldRequirementsProps={{
                                                    required: true,
                                                    hint: 'Select official type',
                                                }}
                                                error={getError(
                                                    `officials.${index}.type`,
                                                )}
                                            >
                                                <Select
                                                    disabled={
                                                        isView || processing
                                                    }
                                                    value={official.type || ''}
                                                    onValueChange={(v) =>
                                                        updateOfficial(
                                                            index,
                                                            'type',
                                                            v,
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="Select type" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {officialTypeOptions.map(
                                                            (option) => (
                                                                <SelectItem
                                                                    key={
                                                                        option.value
                                                                    }
                                                                    value={
                                                                        option.value
                                                                    }
                                                                >
                                                                    {
                                                                        option.label
                                                                    }
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                            </FormField>
                                            <FormField
                                                label="Name"
                                                fieldRequirementsProps={{
                                                    hint: 'Enter official name',
                                                }}
                                                error={getError(
                                                    `officials.${index}.name`,
                                                )}
                                            >
                                                <ProperInput
                                                    value={official.name ?? ''}
                                                    disabled={
                                                        isView || processing
                                                    }
                                                    onCommit={(v) =>
                                                        updateOfficial(
                                                            index,
                                                            'name',
                                                            v || null,
                                                        )
                                                    }
                                                    placeholder="Enter name"
                                                />
                                            </FormField>
                                            <FormField
                                                label="Contact Number"
                                                fieldRequirementsProps={{
                                                    hint: 'Enter contact number',
                                                }}
                                                error={getError(
                                                    `officials.${index}.contact_number`,
                                                )}
                                            >
                                                <ProperInput
                                                    value={
                                                        official.contact_number ??
                                                        ''
                                                    }
                                                    disabled={
                                                        isView || processing
                                                    }
                                                    onCommit={(v) =>
                                                        updateOfficial(
                                                            index,
                                                            'contact_number',
                                                            v || null,
                                                        )
                                                    }
                                                    placeholder="Enter contact number"
                                                />
                                            </FormField>
                                            </div>
                                        </div>
                                    ),
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Package Inclusions */}
                <Card>
                    <CardHeader className="gap-0">
                        <CardTitle className="text-xl">
                            Package Inclusions
                        </CardTitle>
                        <CardDescription>
                            Describe included, excluded, and special offer
                            details.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-3">
                            <FormField
                                label="Included"
                                htmlFor="included"
                                fieldRequirementsProps={{
                                    hint: "List what's included in the package",
                                }}
                                error={getError('included')}
                            >
                                <ProperInput
                                    id="included"
                                    value={data.included || ''}
                                    disabled={isView || processing}
                                    textarea
                                    onCommit={(e) =>
                                        setData('included', e || null)
                                    }
                                    placeholder="List included items (e.g., flights, hotels, meals, visa, transport...)"
                                />
                            </FormField>
                            <FormField
                                label="Not Included"
                                htmlFor="not_included"
                                fieldRequirementsProps={{
                                    hint: "List what's not included",
                                }}
                                error={getError('not_included')}
                            >
                                <Textarea
                                    id="not_included"
                                    value={data.not_included || ''}
                                    disabled={isView || processing}
                                    onChange={(e) =>
                                        setData(
                                            'not_included',
                                            e.target.value || null,
                                        )
                                    }
                                    placeholder="List excluded items (e.g., personal expenses, tips...)"
                                />
                            </FormField>
                            <FormField
                                label="Offer"
                                htmlFor="offer"
                                fieldRequirementsProps={{
                                    hint: 'Describe any special offers',
                                }}
                                error={getError('offer')}
                            >
                                <Textarea
                                    id="offer"
                                    value={data.offer || ''}
                                    disabled={isView || processing}
                                    onChange={(e) =>
                                        setData('offer', e.target.value || null)
                                    }
                                    placeholder="Describe any special offers or promotions..."
                                />
                            </FormField>
                        </div>
                    </CardContent>
                </Card>

                {/* Remarks */}
                <Card>
                    <CardHeader className="gap-0">
                        <CardTitle className="text-xl">Remarks</CardTitle>
                        <CardDescription>
                            Add any additional internal or operational notes.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <FormField
                            label="Remarks"
                            htmlFor="remarks"
                            fieldRequirementsProps={{
                                hint: 'Enter any additional remarks or notes',
                            }}
                            error={getError('remarks')}
                        >
                            <Textarea
                                id="remarks"
                                value={data.remarks || ''}
                                disabled={isView || processing}
                                onChange={(e) =>
                                    setData('remarks', e.target.value || null)
                                }
                                placeholder="Additional remarks or notes"
                                rows={4}
                            />
                        </FormField>
                    </CardContent>
                </Card>

                {/* Action Buttons */}
                <div className="flex justify-end gap-3">
                    {onCancel && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onCancel}
                            disabled={processing}
                        >
                            {onSuccess ? 'Cancel' : 'Back'}
                        </Button>
                    )}
                    {!isView && (
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => reset()}
                                disabled={processing}
                            >
                                Reset
                            </Button>
                            <Button
                                type="submit"
                                className="min-w-[140px]"
                                disabled={processing}
                            >
                                {isEdit
                                    ? 'Update'
                                    : onSuccess
                                      ? 'Create & Continue'
                                      : 'Create'}
                            </Button>
                        </>
                    )}
                </div>
            </form>
        </div>
    );
}
