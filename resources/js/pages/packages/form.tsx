import { DatePickerField } from '@/components/date-picker';
import { FormField } from '@/components/form-field';
import ModelNumberInput from '@/components/model-number-input';
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
import { isBeforeToday, parseDisplayDate } from '@/lib/utils';
import { store, update } from '@/routes/packages';
import { OptionType } from '@/types';
import { useForm } from '@inertiajs/react';
import { AlertCircle, Loader2, Plus, Trash2 } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { genderOptions } from '../customer/schema';
import {
    infantAndChildPriceLabels,
    officialTypeOptions,
    packageMealPlanOptions,
    packageTrainTicketTypeOptions,
    sharingPlanPriceLabels,
    type AccommodationSchema,
    type FlightSchema,
    type OfficialSchema,
    type PackageSchema,
    type RawdahTasreehSchema,
    type TrainTicketSchema,
    type TransportationPlanSchema,
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

const defaultTrainTickets: TrainTicketSchema[] = [
    {
        from: '',
        to: '',
        travel_date: '',
        travel_time: '',
        remarks: '',
    },
    {
        from: '',
        to: '',
        travel_date: '',
        travel_time: '',
        remarks: '',
    },
];

const defaultOfficials: OfficialSchema[] = [
    {
        type: 'mutawif',
        name: '',
        hotel: '',
        contact_number: '',
        nationality: '',
        passport_number: '',
        gender: '',
        date_of_birth: '',
        passport_issue_date: '',
        passport_expiry_date: '',
        passport_place_of_issue: '',
        place_of_birth: '',
    },
    {
        type: 'mutawifah',
        name: '',
        hotel: '',
        contact_number: '',
        nationality: '',
        passport_number: '',
        gender: '',
        date_of_birth: '',
        passport_issue_date: '',
        passport_expiry_date: '',
        passport_place_of_issue: '',
        place_of_birth: '',
    },
    {
        type: 'official',
        name: '',
        hotel: '',
        contact_number: '',
        nationality: '',
        passport_number: '',
        gender: '',
        date_of_birth: '',
        passport_issue_date: '',
        passport_expiry_date: '',
        passport_place_of_issue: '',
        place_of_birth: '',
    },
];

const officialTypeLabels = Object.fromEntries(
    officialTypeOptions.map((option) => [option.value, option.label]),
) as Record<string, string>;

interface PackageFormProps {
    mode: 'create' | 'edit' | 'view';
    initialData?: PackageSchema;
    prefillData?: Partial<PackageSchema>;
    countries?: OptionType[];
    onCancel?: () => void;
    onSuccess?: (data: PackageSchema) => void;
}

export default function PackageForm({
    mode,
    initialData,
    prefillData,
    countries = [],
    onCancel,
    onSuccess,
}: PackageFormProps) {
    const isView = mode === 'view';
    const isEdit = mode === 'edit';
    const isCreate = mode === 'create';

    const defaultData: PackageSchema = initialData || {
        package_number: '',
        package_number_format_id: null,
        name: '',
        status: 'open',
        country_id: '',
        price_single: null,
        price_double: null,
        price_triple: null,
        price_quad: null,
        child_with_bed_price: null,
        child_no_bed_price: null,
        infant_price: null,
        departure_date: '',
        return_date: '',
        total_seats: null,
        seats_left: null,
        occupied_seats: 0,
        visa_type: '',
        vehicle_type: '',
        vehicle_driver_name: '',
        vehicle_driver_contact_number: '',
        ticket_type: '',
        train_description: '',
        included: '',
        not_included: '',
        offer: '',
        remarks: '',
        accommodations: [],
        flights: [...defaultFlights],
        train_tickets: [],
        transportation_plans: [],
        rawdah_tasreehs: [],
        rawdah_member_counts: {
            total: 0,
            women: 0,
            men: 0,
        },
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
    const errorBannerRef = useRef<HTMLDivElement | null>(null);

    useEffect(() => {
        if (Object.keys(errors).length > 0 && !isView) {
            errorBannerRef.current?.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
        }
    }, [errors, isView]);

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

    useEffect(() => {
        const ticketType = data.ticket_type;

        if (ticketType !== 'one_way' && ticketType !== 'two_way') {
            if ((data.train_tickets ?? []).length > 0) {
                setData('train_tickets', []);
            }

            return;
        }

        const desiredCount = ticketType === 'two_way' ? 2 : 1;

        setData((currentData) => {
            const currentTickets = currentData.train_tickets ?? [];

            if (currentTickets.length === desiredCount) {
                return currentData;
            }

            const nextTickets =
                desiredCount === 1
                    ? [currentTickets[0] ?? defaultTrainTickets[0]]
                    : [
                          currentTickets[0] ?? defaultTrainTickets[0],
                          currentTickets[1] ?? defaultTrainTickets[1],
                      ];

            return {
                ...currentData,
                train_tickets: nextTickets,
            };
        });
    }, [data.ticket_type, data.train_tickets, setData]);

    const rawdahWomenPassengers = Number(data.rawdah_member_counts?.women ?? 0);
    const rawdahMenPassengers = Number(data.rawdah_member_counts?.men ?? 0);

    useEffect(() => {
        const currentRows = data.rawdah_tasreehs ?? [];

        if (currentRows.length === 0) {
            return;
        }

        let hasChanges = false;
        const nextRows = currentRows.map((row) => {
            if (
                row.women_passengers === rawdahWomenPassengers &&
                row.men_passengers === rawdahMenPassengers
            ) {
                return row;
            }

            hasChanges = true;

            return {
                ...row,
                women_passengers: rawdahWomenPassengers,
                men_passengers: rawdahMenPassengers,
            };
        });

        if (!hasChanges) {
            return;
        }

        setData('rawdah_tasreehs', nextRows);
    }, [
        data.rawdah_tasreehs,
        rawdahWomenPassengers,
        rawdahMenPassengers,
        setData,
    ]);

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
                ic: '',
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

    // --- Train ticket helpers ---
    const updateTrainTicket = (
        index: number,
        field: keyof TrainTicketSchema,
        value: string | number | null,
    ) => {
        const current = [...(data.train_tickets || [])];
        current[index] = { ...current[index], [field]: value };
        setData('train_tickets', current);
    };

    // --- Transportation plan helpers ---
    const addTransportationPlan = () => {
        const current = data.transportation_plans || [];
        setData('transportation_plans', [
            ...current,
            {
                from: '',
                to: '',
                travel_date: '',
                travel_time: '',
                remarks: '',
            },
        ]);
    };

    const removeTransportationPlan = (index: number) => {
        const current = data.transportation_plans || [];
        setData(
            'transportation_plans',
            current.filter((_, i) => i !== index),
        );
    };

    const updateTransportationPlan = (
        index: number,
        field: keyof TransportationPlanSchema,
        value: string | number | null,
    ) => {
        const current = [...(data.transportation_plans || [])];
        current[index] = { ...current[index], [field]: value };
        setData('transportation_plans', current);
    };

    // --- Rawdah tasreeh helpers ---
    const addRawdahTasreeh = () => {
        const current = data.rawdah_tasreehs || [];
        setData('rawdah_tasreehs', [
            ...current,
            {
                date: '',
                women_passengers: rawdahWomenPassengers,
                women_time: '',
                men_passengers: rawdahMenPassengers,
                men_time: '',
                remarks: '',
            },
        ]);
    };

    const removeRawdahTasreeh = (index: number) => {
        const current = data.rawdah_tasreehs || [];
        setData(
            'rawdah_tasreehs',
            current.filter((_, i) => i !== index),
        );
    };

    const updateRawdahTasreeh = (
        index: number,
        field: keyof RawdahTasreehSchema,
        value: string | number | null,
    ) => {
        const current = [...(data.rawdah_tasreehs || [])];
        current[index] = { ...current[index], [field]: value };
        setData('rawdah_tasreehs', current);
    };

    // --- Official helpers ---
    const addOfficial = () => {
        const current = data.officials || [];
        setData('officials', [
            ...current,
            {
                type: '',
                name: '',
                hotel: '',
                contact_number: '',
                nationality: '',
                passport_number: '',
                gender: '',
                date_of_birth: '',
                passport_issue_date: '',
                passport_expiry_date: '',
                passport_place_of_issue: '',
                place_of_birth: '',
            },
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
    const showPackageNumberField = isView && Boolean(data.package_number);

    return (
        <div className="mx-auto w-full">
            <form onSubmit={submit} className="space-y-4">
                {/* Error Alert */}
                {Object.keys(errors).length > 0 && !isView && (
                    <div ref={errorBannerRef}>
                        <Alert variant="destructive">
                            <AlertCircle className="h-4 w-4" />
                            <AlertDescription>
                                Please fix the errors below and try again.
                            </AlertDescription>
                        </Alert>
                    </div>
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
                        <div
                            className={`grid grid-cols-1 items-start gap-4 ${
                                showPackageNumberField
                                    ? 'md:grid-cols-4'
                                    : 'md:grid-cols-3'
                            }`}
                        >
                            {!isView && (
                                <ModelNumberInput
                                    modelKey="package"
                                    label="Package Number"
                                    value={data.package_number ?? ''}
                                    formatId={
                                        data.package_number_format_id ?? null
                                    }
                                    onValueChange={(nextValue) =>
                                        setData('package_number', nextValue)
                                    }
                                    onFormatIdChange={(nextFormatId) =>
                                        setData(
                                            'package_number_format_id',
                                            nextFormatId,
                                        )
                                    }
                                    disabled={processing}
                                    error={getError('package_number')}
                                />
                            )}

                            {/* Group Number (view only) */}
                            {showPackageNumberField && (
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

                            <FormField
                                label="Package Location"
                                fieldRequirementsProps={{
                                    hint: 'Location scope for this package',
                                }}
                                error={getError('country_id')}
                            >
                                <ProperInputSelect
                                    disabled={isView || processing || isEdit}
                                    options={countries}
                                    value={data.country_id ?? ''}
                                    onValueChange={(value) =>
                                        setData('country_id', String(value))
                                    }
                                    placeholder="Select package location"
                                />
                            </FormField>
                        </div>

                        <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-4">
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
                                                : Math.max(
                                                      0,
                                                      val - occupiedSeats,
                                                  );

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
                        <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-4">
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
                                                parseFloat(v),
                                            )
                                        }
                                        inputProps={{
                                            min: '0',
                                            step: '0.01',
                                        }}
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
                                                parseFloat(v),
                                            )
                                        }
                                        inputProps={{
                                            min: '0',
                                            step: '0.01',
                                        }}
                                        placeholder="0"
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
                                        <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-3">
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
                                        <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-4">
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

                {/* Transportation Plan */}
                <Card>
                    <CardHeader className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <CardTitle className="text-xl">
                                Transportation Plan
                            </CardTitle>
                            <CardDescription>
                                Add transportation arrangements for this
                                package.
                            </CardDescription>
                        </div>
                        {!isView && (
                            <Button
                                type="button"
                                variant="default"
                                className="w-full sm:w-auto"
                                onClick={addTransportationPlan}
                                disabled={processing}
                            >
                                <Plus className="mr-1 h-4 w-4" />
                                Add Transportation
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent>
                        {(data.transportation_plans || []).length === 0 ? (
                            <p className="text-base text-muted-foreground">
                                No transportation plans added yet. Click "Add
                                Transportation" to add details.
                            </p>
                        ) : (
                            <div className="space-y-4">
                                {(data.transportation_plans || []).map(
                                    (plan, index) => (
                                        <div
                                            key={index}
                                            className="space-y-4 rounded-lg border p-4"
                                        >
                                            <div className="flex items-center justify-between">
                                                <span className="font-semibold">
                                                    Transportation {index + 1}
                                                </span>
                                                {!isView && (
                                                    <Button
                                                        type="button"
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() =>
                                                            removeTransportationPlan(
                                                                index,
                                                            )
                                                        }
                                                        disabled={processing}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                )}
                                            </div>
                                            <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-2">
                                                <FormField
                                                    label="From"
                                                    fieldRequirementsProps={{
                                                        hint: 'Departure location',
                                                    }}
                                                    error={getError(
                                                        `transportation_plans.${index}.from`,
                                                    )}
                                                >
                                                    <ProperInput
                                                        value={plan.from ?? ''}
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onCommit={(v) =>
                                                            updateTransportationPlan(
                                                                index,
                                                                'from',
                                                                v || null,
                                                            )
                                                        }
                                                        placeholder="e.g., Airport"
                                                    />
                                                </FormField>
                                                <FormField
                                                    label="To"
                                                    fieldRequirementsProps={{
                                                        hint: 'Arrival location',
                                                    }}
                                                    error={getError(
                                                        `transportation_plans.${index}.to`,
                                                    )}
                                                >
                                                    <ProperInput
                                                        value={plan.to ?? ''}
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onCommit={(v) =>
                                                            updateTransportationPlan(
                                                                index,
                                                                'to',
                                                                v || null,
                                                            )
                                                        }
                                                        placeholder="e.g., Hotel"
                                                    />
                                                </FormField>
                                                <FormField
                                                    label="Date"
                                                    fieldRequirementsProps={{
                                                        hint: 'Travel date',
                                                    }}
                                                    error={getError(
                                                        `transportation_plans.${index}.travel_date`,
                                                    )}
                                                >
                                                    <DatePickerField
                                                        id={`transport_date_${index}`}
                                                        value={
                                                            plan.travel_date ||
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
                                                        onChange={(v) =>
                                                            updateTransportationPlan(
                                                                index,
                                                                'travel_date',
                                                                v || null,
                                                            )
                                                        }
                                                    />
                                                </FormField>
                                                <FormField
                                                    label="Time"
                                                    fieldRequirementsProps={{
                                                        hint: 'Travel time',
                                                    }}
                                                    error={getError(
                                                        `transportation_plans.${index}.travel_time`,
                                                    )}
                                                >
                                                    <ProperInput
                                                        type="time"
                                                        timeFormat={24}
                                                        value={
                                                            plan.travel_time ??
                                                            ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onCommit={(v) =>
                                                            updateTransportationPlan(
                                                                index,
                                                                'travel_time',
                                                                v || null,
                                                            )
                                                        }
                                                    />
                                                </FormField>
                                            </div>
                                            <FormField
                                                label="Remarks"
                                                fieldRequirementsProps={{
                                                    hint: 'Optional notes',
                                                }}
                                                error={getError(
                                                    `transportation_plans.${index}.remarks`,
                                                )}
                                            >
                                                <ProperInput
                                                    value={plan.remarks ?? ''}
                                                    disabled={
                                                        isView || processing
                                                    }
                                                    textarea
                                                    onCommit={(v) =>
                                                        updateTransportationPlan(
                                                            index,
                                                            'remarks',
                                                            v || null,
                                                        )
                                                    }
                                                    placeholder="Optional remarks"
                                                />
                                            </FormField>
                                        </div>
                                    ),
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Visa & Vehicle */}
                <Card>
                    <CardHeader className="gap-0">
                        <CardTitle className="text-xl">
                            Visa & Vehicle
                        </CardTitle>
                        <CardDescription>
                            Capture travel support and transport setup.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-1">
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
                        </div>
                        <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-3">
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
                                label="Driver Name"
                                htmlFor="vehicle_driver_name"
                                fieldRequirementsProps={{
                                    hint: 'Enter driver name',
                                }}
                                error={getError('vehicle_driver_name')}
                            >
                                <ProperInput
                                    id="vehicle_driver_name"
                                    value={data.vehicle_driver_name || ''}
                                    disabled={isView || processing}
                                    onCommit={(v) =>
                                        setData(
                                            'vehicle_driver_name',
                                            v || null,
                                        )
                                    }
                                    placeholder="Enter driver name"
                                />
                            </FormField>
                            <FormField
                                label="Driver Contact Number"
                                htmlFor="vehicle_driver_contact_number"
                                fieldRequirementsProps={{
                                    hint: 'Enter driver contact number',
                                }}
                                error={getError(
                                    'vehicle_driver_contact_number',
                                )}
                            >
                                <ProperInput
                                    id="vehicle_driver_contact_number"
                                    value={
                                        data.vehicle_driver_contact_number || ''
                                    }
                                    disabled={isView || processing}
                                    onCommit={(v) =>
                                        setData(
                                            'vehicle_driver_contact_number',
                                            v || null,
                                        )
                                    }
                                    placeholder="Enter contact number"
                                />
                            </FormField>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="gap-0">
                        <CardTitle className="text-xl">
                            Train Ticket Details
                        </CardTitle>
                        <CardDescription>
                            Provide train ticket itinerary details.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-1">
                            <FormField
                                label="Train Description"
                                htmlFor="train_description"
                                fieldRequirementsProps={{
                                    hint: 'General notes shown for train operations',
                                }}
                                error={getError('train_description')}
                            >
                                <ProperInput
                                    id="train_description"
                                    value={data.train_description || ''}
                                    disabled={isView || processing}
                                    textarea
                                    onCommit={(v) =>
                                        setData('train_description', v || null)
                                    }
                                    placeholder="Enter train operation description"
                                />
                            </FormField>
                            <FormField
                                label="Train Ticket Type"
                                htmlFor="ticket_type"
                                fieldRequirementsProps={{
                                    hint: 'Select a train ticket type to add itinerary details.',
                                }}
                                error={getError('ticket_type')}
                            >
                                <ProperInputSelect
                                    options={packageTrainTicketTypeOptions}
                                    value={data.ticket_type || ''}
                                    disabled={isView || processing}
                                    onValueChange={(v) =>
                                        setData('ticket_type', String(v || ''))
                                    }
                                    placeholder="Select ticket type"
                                />
                            </FormField>
                        </div>

                        {(data.train_tickets || []).length === 0 ? (
                            <p className="text-base text-muted-foreground">
                                Select a train ticket type to add itinerary
                                details.
                            </p>
                        ) : (
                            <div className="space-y-4">
                                {(data.train_tickets || []).map(
                                    (ticket, index) => (
                                        <div
                                            key={index}
                                            className="space-y-4 rounded-lg border p-4"
                                        >
                                            <div className="flex items-center justify-between">
                                                <span className="font-semibold">
                                                    Train Trip {index + 1}
                                                </span>
                                            </div>
                                            <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-2">
                                                <FormField
                                                    label="From"
                                                    fieldRequirementsProps={{
                                                        hint: 'Departure location',
                                                    }}
                                                    error={getError(
                                                        `train_tickets.${index}.from`,
                                                    )}
                                                >
                                                    <ProperInput
                                                        value={
                                                            ticket.from ?? ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onCommit={(v) =>
                                                            updateTrainTicket(
                                                                index,
                                                                'from',
                                                                v || null,
                                                            )
                                                        }
                                                        placeholder="e.g., Makkah"
                                                    />
                                                </FormField>
                                                <FormField
                                                    label="To"
                                                    fieldRequirementsProps={{
                                                        hint: 'Arrival location',
                                                    }}
                                                    error={getError(
                                                        `train_tickets.${index}.to`,
                                                    )}
                                                >
                                                    <ProperInput
                                                        value={ticket.to ?? ''}
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onCommit={(v) =>
                                                            updateTrainTicket(
                                                                index,
                                                                'to',
                                                                v || null,
                                                            )
                                                        }
                                                        placeholder="e.g., Madinah"
                                                    />
                                                </FormField>
                                            </div>
                                            <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-2">
                                                <FormField
                                                    label="Date"
                                                    fieldRequirementsProps={{
                                                        hint: 'Travel date',
                                                    }}
                                                    error={getError(
                                                        `train_tickets.${index}.travel_date`,
                                                    )}
                                                >
                                                    <DatePickerField
                                                        id={`train_date_${index}`}
                                                        value={
                                                            ticket.travel_date ||
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
                                                        onChange={(v) =>
                                                            updateTrainTicket(
                                                                index,
                                                                'travel_date',
                                                                v || null,
                                                            )
                                                        }
                                                    />
                                                </FormField>
                                                <FormField
                                                    label="Time"
                                                    fieldRequirementsProps={{
                                                        hint: 'Travel time',
                                                    }}
                                                    error={getError(
                                                        `train_tickets.${index}.travel_time`,
                                                    )}
                                                >
                                                    <ProperInput
                                                        type="time"
                                                        timeFormat={24}
                                                        value={
                                                            ticket.travel_time ??
                                                            ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onCommit={(v) =>
                                                            updateTrainTicket(
                                                                index,
                                                                'travel_time',
                                                                v || null,
                                                            )
                                                        }
                                                    />
                                                </FormField>
                                            </div>
                                            <FormField
                                                label="Remarks"
                                                fieldRequirementsProps={{
                                                    hint: 'Optional notes',
                                                }}
                                                error={getError(
                                                    `train_tickets.${index}.remarks`,
                                                )}
                                            >
                                                <ProperInput
                                                    value={ticket.remarks ?? ''}
                                                    disabled={
                                                        isView || processing
                                                    }
                                                    textarea
                                                    onCommit={(v) =>
                                                        updateTrainTicket(
                                                            index,
                                                            'remarks',
                                                            v || null,
                                                        )
                                                    }
                                                    placeholder="Optional remarks"
                                                />
                                            </FormField>
                                        </div>
                                    ),
                                )}
                            </div>
                        )}
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
                                Accommodation" to add places like Makkah,
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

                                            <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-4">
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
                                                        placeholder="e.g., Makkah"
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
                                                    label="IC"
                                                    fieldRequirementsProps={{
                                                        hint: 'Free-text in charge reference',
                                                    }}
                                                    error={getError(
                                                        `accommodations.${index}.ic`,
                                                    )}
                                                >
                                                    <ProperInput
                                                        value={
                                                            accommodation.ic ??
                                                            ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onCommit={(v) =>
                                                            updateAccommodation(
                                                                index,
                                                                'ic',
                                                                v,
                                                            )
                                                        }
                                                        placeholder="Enter IC"
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

                                            <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-2">
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
                                                        disabledDates={(
                                                            date,
                                                        ) => {
                                                            if (
                                                                isBeforeToday(
                                                                    date,
                                                                )
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

                {/* Rawdah Tasreeh */}
                <Card>
                    <CardHeader className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <CardTitle className="text-xl">
                                Rawdah Tasreeh
                            </CardTitle>
                            <CardDescription>
                                Add Rawdah tasreeh schedules and passenger
                                counts.
                            </CardDescription>
                        </div>
                        {!isView && (
                            <Button
                                type="button"
                                variant="default"
                                className="w-full sm:w-auto"
                                onClick={addRawdahTasreeh}
                                disabled={processing}
                            >
                                <Plus className="mr-1 h-4 w-4" />
                                Add Rawdah
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent>
                        {(data.rawdah_tasreehs || []).length === 0 ? (
                            <p className="text-base text-muted-foreground">
                                No Rawdah tasreeh entries yet. Click "Add
                                Rawdah" to add schedules.
                            </p>
                        ) : (
                            <div className="space-y-4">
                                {(data.rawdah_tasreehs || []).map(
                                    (tasreeh, index) => {
                                        const womenCount =
                                            rawdahWomenPassengers;
                                        const menCount = rawdahMenPassengers;
                                        const totalCount =
                                            (Number.isFinite(womenCount)
                                                ? womenCount
                                                : 0) +
                                            (Number.isFinite(menCount)
                                                ? menCount
                                                : 0);

                                        return (
                                            <div
                                                key={index}
                                                className="space-y-4 rounded-lg border p-4"
                                            >
                                                <div className="flex items-center justify-between">
                                                    <span className="font-semibold">
                                                        Rawdah {index + 1}
                                                    </span>
                                                    {!isView && (
                                                        <Button
                                                            type="button"
                                                            variant="destructive"
                                                            size="sm"
                                                            onClick={() =>
                                                                removeRawdahTasreeh(
                                                                    index,
                                                                )
                                                            }
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                </div>

                                                <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-2">
                                                    <FormField
                                                        label="Date"
                                                        fieldRequirementsProps={{
                                                            hint: 'Tasreeh date',
                                                        }}
                                                        error={getError(
                                                            `rawdah_tasreehs.${index}.date`,
                                                        )}
                                                    >
                                                        <DatePickerField
                                                            id={`rawdah_date_${index}`}
                                                            value={
                                                                tasreeh.date ||
                                                                ''
                                                            }
                                                            fromYear={new Date().getFullYear()}
                                                            toYear={
                                                                new Date().getFullYear() +
                                                                5
                                                            }
                                                            disabled={
                                                                isView ||
                                                                processing
                                                            }
                                                            onChange={(v) =>
                                                                updateRawdahTasreeh(
                                                                    index,
                                                                    'date',
                                                                    v || null,
                                                                )
                                                            }
                                                        />
                                                    </FormField>
                                                    <FormField
                                                        label="Total Passengers"
                                                        fieldRequirementsProps={{
                                                            hint: 'Auto-calculated total',
                                                        }}
                                                    >
                                                        <Input
                                                            value={totalCount}
                                                            disabled={true}
                                                            className="bg-muted"
                                                        />
                                                    </FormField>
                                                </div>

                                                <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-2">
                                                    <FormField
                                                        label="Women Passengers"
                                                        fieldRequirementsProps={{
                                                            hint: 'Auto-filled from non-official manifest members',
                                                        }}
                                                        error={getError(
                                                            `rawdah_tasreehs.${index}.women_passengers`,
                                                        )}
                                                    >
                                                        <Input
                                                            type="number"
                                                            value={womenCount}
                                                            disabled={true}
                                                            className="bg-muted"
                                                        />
                                                    </FormField>
                                                    <FormField
                                                        label="Women Time"
                                                        fieldRequirementsProps={{
                                                            hint: 'Women time slot',
                                                        }}
                                                        error={getError(
                                                            `rawdah_tasreehs.${index}.women_time`,
                                                        )}
                                                    >
                                                        <ProperInput
                                                            type="time"
                                                            timeFormat={24}
                                                            value={
                                                                tasreeh.women_time ??
                                                                ''
                                                            }
                                                            disabled={
                                                                isView ||
                                                                processing
                                                            }
                                                            onCommit={(v) =>
                                                                updateRawdahTasreeh(
                                                                    index,
                                                                    'women_time',
                                                                    v || null,
                                                                )
                                                            }
                                                        />
                                                    </FormField>
                                                </div>

                                                <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-2">
                                                    <FormField
                                                        label="Men Passengers"
                                                        fieldRequirementsProps={{
                                                            hint: 'Auto-filled from non-official manifest members',
                                                        }}
                                                        error={getError(
                                                            `rawdah_tasreehs.${index}.men_passengers`,
                                                        )}
                                                    >
                                                        <Input
                                                            type="number"
                                                            value={menCount}
                                                            disabled={true}
                                                            className="bg-muted"
                                                        />
                                                    </FormField>
                                                    <FormField
                                                        label="Men Time"
                                                        fieldRequirementsProps={{
                                                            hint: 'Men time slot',
                                                        }}
                                                        error={getError(
                                                            `rawdah_tasreehs.${index}.men_time`,
                                                        )}
                                                    >
                                                        <ProperInput
                                                            type="time"
                                                            timeFormat={24}
                                                            value={
                                                                tasreeh.men_time ??
                                                                ''
                                                            }
                                                            disabled={
                                                                isView ||
                                                                processing
                                                            }
                                                            onCommit={(v) =>
                                                                updateRawdahTasreeh(
                                                                    index,
                                                                    'men_time',
                                                                    v || null,
                                                                )
                                                            }
                                                        />
                                                    </FormField>
                                                </div>

                                                <FormField
                                                    label="Remarks"
                                                    fieldRequirementsProps={{
                                                        hint: 'Additional notes',
                                                    }}
                                                    error={getError(
                                                        `rawdah_tasreehs.${index}.remarks`,
                                                    )}
                                                >
                                                    <ProperInput
                                                        value={
                                                            tasreeh.remarks ??
                                                            ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        textarea
                                                        onCommit={(v) =>
                                                            updateRawdahTasreeh(
                                                                index,
                                                                'remarks',
                                                                v || null,
                                                            )
                                                        }
                                                        placeholder="Optional remarks"
                                                    />
                                                </FormField>
                                            </div>
                                        );
                                    },
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
                                                        ? ` - ${officialTypeLabels[official.type] ?? official.type}`
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

                                            <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-4">
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
                                                    <ProperInputSelect
                                                        id={`officials.${index}.type`}
                                                        mode="classic"
                                                        value={
                                                            official.type || ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onValueChange={(v) =>
                                                            updateOfficial(
                                                                index,
                                                                'type',
                                                                v,
                                                            )
                                                        }
                                                        options={
                                                            officialTypeOptions
                                                        }
                                                        placeholder="Select type"
                                                    />
                                                </FormField>
                                                <FormField
                                                    label="Name"
                                                    fieldRequirementsProps={{
                                                        required: true,
                                                        hint: 'Enter official name',
                                                    }}
                                                    error={getError(
                                                        `officials.${index}.name`,
                                                    )}
                                                >
                                                    <ProperInput
                                                        value={
                                                            official.name ?? ''
                                                        }
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
                                                    label="Hotel"
                                                    fieldRequirementsProps={{
                                                        hint: 'Hotel remark for official (official hotel or follows jemaah hotel)',
                                                    }}
                                                    error={getError(
                                                        `officials.${index}.hotel`,
                                                    )}
                                                >
                                                    <ProperInput
                                                        value={
                                                            official.hotel ?? ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onCommit={(v) =>
                                                            updateOfficial(
                                                                index,
                                                                'hotel',
                                                                v || null,
                                                            )
                                                        }
                                                        placeholder="Enter hotel"
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

                                            <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-3">
                                                <FormField
                                                    label="Nationality"
                                                    fieldRequirementsProps={{
                                                        hint: 'Enter nationality',
                                                    }}
                                                    error={getError(
                                                        `officials.${index}.nationality`,
                                                    )}
                                                >
                                                    <ProperInput
                                                        value={
                                                            official.nationality ??
                                                            ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onCommit={(v) =>
                                                            updateOfficial(
                                                                index,
                                                                'nationality',
                                                                v || null,
                                                            )
                                                        }
                                                        placeholder="e.g., Singaporean"
                                                    />
                                                </FormField>
                                                <FormField
                                                    label="Passport Number"
                                                    fieldRequirementsProps={{
                                                        hint: 'Enter passport number',
                                                    }}
                                                    error={getError(
                                                        `officials.${index}.passport_number`,
                                                    )}
                                                >
                                                    <ProperInput
                                                        value={
                                                            official.passport_number ??
                                                            ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onCommit={(v) =>
                                                            updateOfficial(
                                                                index,
                                                                'passport_number',
                                                                v || null,
                                                            )
                                                        }
                                                        placeholder="Enter passport number"
                                                    />
                                                </FormField>
                                                <FormField
                                                    label="Gender"
                                                    fieldRequirementsProps={{
                                                        hint: 'Select gender',
                                                    }}
                                                    error={getError(
                                                        `officials.${index}.gender`,
                                                    )}
                                                >
                                                    <ProperInputSelect
                                                        id={`officials.${index}.gender`}
                                                        mode="classic"
                                                        value={
                                                            official.gender ||
                                                            ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onValueChange={(v) =>
                                                            updateOfficial(
                                                                index,
                                                                'gender',
                                                                v,
                                                            )
                                                        }
                                                        options={genderOptions}
                                                        placeholder="Select gender"
                                                    />
                                                </FormField>
                                            </div>

                                            <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-3">
                                                <FormField
                                                    label="Date of Birth"
                                                    fieldRequirementsProps={{
                                                        hint: 'Official date of birth',
                                                    }}
                                                    error={getError(
                                                        `officials.${index}.date_of_birth`,
                                                    )}
                                                >
                                                    <DatePickerField
                                                        id={`official_dob_${index}`}
                                                        value={
                                                            official.date_of_birth ||
                                                            ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        fromYear={
                                                            new Date().getFullYear() -
                                                            100
                                                        }
                                                        toYear={new Date().getFullYear()}
                                                        onChange={(v) =>
                                                            updateOfficial(
                                                                index,
                                                                'date_of_birth',
                                                                v || null,
                                                            )
                                                        }
                                                    />
                                                </FormField>
                                                <FormField
                                                    label="Passport Issue Date"
                                                    fieldRequirementsProps={{
                                                        hint: 'Issue date in passport',
                                                    }}
                                                    error={getError(
                                                        `officials.${index}.passport_issue_date`,
                                                    )}
                                                >
                                                    <DatePickerField
                                                        id={`official_issue_${index}`}
                                                        value={
                                                            official.passport_issue_date ||
                                                            ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        fromYear={
                                                            new Date().getFullYear() -
                                                            10
                                                        }
                                                        toYear={
                                                            new Date().getFullYear() +
                                                            10
                                                        }
                                                        onChange={(v) =>
                                                            updateOfficial(
                                                                index,
                                                                'passport_issue_date',
                                                                v || null,
                                                            )
                                                        }
                                                    />
                                                </FormField>
                                                <FormField
                                                    label="Passport Expiry Date"
                                                    fieldRequirementsProps={{
                                                        hint: 'Expiry date in passport',
                                                    }}
                                                    error={getError(
                                                        `officials.${index}.passport_expiry_date`,
                                                    )}
                                                >
                                                    <DatePickerField
                                                        id={`official_expiry_${index}`}
                                                        value={
                                                            official.passport_expiry_date ||
                                                            ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        fromYear={
                                                            new Date().getFullYear() -
                                                            10
                                                        }
                                                        toYear={
                                                            new Date().getFullYear() +
                                                            10
                                                        }
                                                        onChange={(v) =>
                                                            updateOfficial(
                                                                index,
                                                                'passport_expiry_date',
                                                                v || null,
                                                            )
                                                        }
                                                    />
                                                </FormField>
                                            </div>

                                            <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-2">
                                                <FormField
                                                    label="Passport Place of Issue"
                                                    fieldRequirementsProps={{
                                                        hint: 'City or office of issue',
                                                    }}
                                                    error={getError(
                                                        `officials.${index}.passport_place_of_issue`,
                                                    )}
                                                >
                                                    <ProperInput
                                                        value={
                                                            official.passport_place_of_issue ??
                                                            ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onCommit={(v) =>
                                                            updateOfficial(
                                                                index,
                                                                'passport_place_of_issue',
                                                                v || null,
                                                            )
                                                        }
                                                        placeholder="e.g., Kuala Lumpur"
                                                    />
                                                </FormField>
                                                <FormField
                                                    label="Place of Birth"
                                                    fieldRequirementsProps={{
                                                        hint: 'City of birth',
                                                    }}
                                                    error={getError(
                                                        `officials.${index}.place_of_birth`,
                                                    )}
                                                >
                                                    <ProperInput
                                                        value={
                                                            official.place_of_birth ??
                                                            ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onCommit={(v) =>
                                                            updateOfficial(
                                                                index,
                                                                'place_of_birth',
                                                                v || null,
                                                            )
                                                        }
                                                        placeholder="e.g., Johor Bahru"
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
                                <ProperInput
                                    id="not_included"
                                    value={data.not_included || ''}
                                    disabled={isView || processing}
                                    textarea
                                    onCommit={(e) =>
                                        setData('not_included', e || null)
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
                                <ProperInput
                                    id="offer"
                                    value={data.offer || ''}
                                    disabled={isView || processing}
                                    textarea
                                    onCommit={(e) =>
                                        setData('offer', e || null)
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
                            <ProperInput
                                id="remarks"
                                value={data.remarks || ''}
                                disabled={isView || processing}
                                textarea
                                onCommit={(v) => setData('remarks', v || null)}
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
                                {processing && (
                                    <Loader2 className="mr-1 h-4 w-4 animate-spin" />
                                )}
                                {processing
                                    ? isEdit
                                        ? 'Updating...'
                                        : onSuccess
                                          ? 'Creating...'
                                          : 'Creating...'
                                    : isEdit
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
