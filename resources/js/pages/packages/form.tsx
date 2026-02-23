import { DatePickerField } from '@/components/date-picker';
import { FieldRequirements } from '@/components/field-requirements';
import { ProperInput } from '@/components/proper-input';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { store, update } from '@/routes/packages';
import { useForm } from '@inertiajs/react';
import { AlertCircle, Plus, Trash2 } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { type AccommodationSchema, type PackageSchema } from './schema';
import { packageValidationSchema } from './validation';

interface PackageFormProps {
    mode: 'create' | 'edit' | 'view';
    initialData?: PackageSchema;
    prefillData?: Partial<PackageSchema>;
    onCancel?: () => void;
    /** When provided, form collects data without submitting to server (dialog mode). */
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
        airline: '',
        pnr: '',
        departure_date: '',
        arrival_date: '',
        total_seats: null,
        seats_left: null,
        visa_type: '',
        vehicle_type: '',
        ticket_type: '',
        included: '',
        not_included: '',
        offer: '',
        remarks: '',
        accommodations: [],
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

    const renderError = (fieldName: string) => {
        const message = (errors as Record<string, string>)[fieldName];
        if (!message) return null;
        return <p className="mt-1 text-sm text-red-500">{message}</p>;
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

    return (
        <div className="mx-auto w-full">
            <form onSubmit={submit} className="space-y-6">
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
                    <CardHeader>
                        <CardTitle>Package Information</CardTitle>
                        <CardDescription>
                            Define the package identity and status.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            {/* Group Number (auto-generated, read-only) */}
                            {(isEdit || isView) && data.group_number && (
                                <div className="grid w-full items-center gap-3">
                                    <Label htmlFor="group_number">
                                        Group Number
                                        <FieldRequirements hint="Auto-generated group identifier" />
                                    </Label>
                                    <div className="relative">
                                        <Input
                                            id="group_number"
                                            type="text"
                                            value={data.group_number}
                                            disabled={true}
                                            className="bg-muted"
                                        />
                                    </div>
                                </div>
                            )}

                            {/* Package Name */}
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="name">
                                    Package Name
                                    <FieldRequirements
                                        required
                                        hint="Enter the name of the package"
                                    />
                                </Label>
                                <div className="relative">
                                    <ProperInput
                                        id="name"
                                        value={data.name ?? ''}
                                        disabled={isView || processing}
                                        onCommit={(v) => setData('name', v)}
                                        placeholder="Enter package name"
                                    />
                                    {renderError('name')}
                                </div>
                            </div>

                            {/* Status */}
                            <div className="grid w-full items-center gap-3">
                                <Label>
                                    Status
                                    <FieldRequirements
                                        required
                                        hint="Select package status"
                                    />
                                </Label>
                                <div className="relative">
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
                                    {renderError('status')}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Pricing */}
                <Card>
                    <CardHeader>
                        <CardTitle>Pricing</CardTitle>
                        <CardDescription>
                            Set occupancy and child/infant pricing.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                            {[
                                {
                                    key: 'price_single',
                                    label: 'Single Occupancy',
                                },
                                {
                                    key: 'price_double',
                                    label: 'Double Occupancy',
                                },
                                {
                                    key: 'price_triple',
                                    label: 'Triple Occupancy',
                                },
                                { key: 'price_quad', label: 'Quad Occupancy' },
                            ].map(({ key, label }) => (
                                <div
                                    key={key}
                                    className="grid w-full items-center gap-3"
                                >
                                    <Label htmlFor={key}>
                                        {label}
                                        <FieldRequirements hint="Enter price amount" />
                                    </Label>
                                    <div className="relative">
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
                                        {renderError(key)}
                                    </div>
                                </div>
                            ))}
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            {[
                                {
                                    key: 'child_with_bed_price',
                                    label: 'Child (with bed)',
                                },
                                {
                                    key: 'child_no_bed_price',
                                    label: 'Child (no bed)',
                                },
                                { key: 'infant_price', label: 'Infant' },
                            ].map(({ key, label }) => (
                                <div
                                    key={key}
                                    className="grid w-full items-center gap-3"
                                >
                                    <Label htmlFor={key}>
                                        {label}
                                        <FieldRequirements hint="Enter price amount" />
                                    </Label>
                                    <div className="relative">
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
                                        {renderError(key)}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                {/* Flight Details */}
                <Card>
                    <CardHeader>
                        <CardTitle>Flight Details</CardTitle>
                        <CardDescription>
                            Add flight information, travel dates, and seat
                            allocation.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="airline">
                                    Airline
                                    <FieldRequirements hint="Enter airline name" />
                                </Label>
                                <div className="relative">
                                    <ProperInput
                                        id="airline"
                                        value={data.airline || ''}
                                        disabled={isView || processing}
                                        onCommit={(v) =>
                                            setData('airline', v || null)
                                        }
                                        placeholder="e.g., Saudi Airlines"
                                    />
                                    {renderError('airline')}
                                </div>
                            </div>
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="pnr">
                                    PNR
                                    <FieldRequirements hint="Enter Passenger Name Record" />
                                </Label>
                                <div className="relative">
                                    <ProperInput
                                        id="pnr"
                                        value={data.pnr || ''}
                                        disabled={isView || processing}
                                        onCommit={(v) =>
                                            setData('pnr', v || null)
                                        }
                                        placeholder="Enter PNR"
                                    />
                                    {renderError('pnr')}
                                </div>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                            <div className="grid w-full items-center gap-3">
                                <Label>
                                    Departure Date
                                    <FieldRequirements hint="Select departure date" />
                                </Label>
                                <div className="relative">
                                    <DatePickerField
                                        id="departure_date"
                                        value={data.departure_date || ''}
                                        disabled={isView || processing}
                                        onChange={(v) =>
                                            setData('departure_date', v)
                                        }
                                    />
                                    {renderError('departure_date')}
                                </div>
                            </div>
                            <div className="grid w-full items-center gap-3">
                                <Label>
                                    Arrival Date
                                    <FieldRequirements hint="Select arrival date" />
                                </Label>
                                <div className="relative">
                                    <DatePickerField
                                        id="arrival_date"
                                        value={data.arrival_date || ''}
                                        disabled={isView || processing}
                                        onChange={(v) =>
                                            setData('arrival_date', v)
                                        }
                                    />
                                    {renderError('arrival_date')}
                                </div>
                            </div>
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="total_seats">
                                    Total Seats
                                    <FieldRequirements hint="Enter total number of seats" />
                                </Label>
                                <div className="relative">
                                    <ProperInput
                                        id="total_seats"
                                        type="number"
                                        value={data.total_seats ?? ''}
                                        disabled={isView || processing}
                                        onCommit={(v) =>
                                            setData(
                                                'total_seats',
                                                v ? parseInt(v) : null,
                                            )
                                        }
                                        inputProps={{ min: '0' }}
                                        placeholder="0"
                                    />
                                    {renderError('total_seats')}
                                </div>
                            </div>
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="seats_left">
                                    Seats Left
                                    <FieldRequirements hint="Enter remaining seats" />
                                </Label>
                                <div className="relative">
                                    <ProperInput
                                        id="seats_left"
                                        type="number"
                                        value={data.seats_left ?? ''}
                                        disabled={isView || processing}
                                        onCommit={(v) =>
                                            setData(
                                                'seats_left',
                                                v ? parseInt(v) : null,
                                            )
                                        }
                                        inputProps={{ min: '0' }}
                                        placeholder="0"
                                    />
                                    {renderError('seats_left')}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Visa, Vehicle & Train */}
                <Card>
                    <CardHeader>
                        <CardTitle>Visa, Vehicle & Train</CardTitle>
                        <CardDescription>
                            Capture travel support and transport setup.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="visa_type">
                                    Visa Type
                                    <FieldRequirements hint="Enter visa type" />
                                </Label>
                                <div className="relative">
                                    <ProperInput
                                        id="visa_type"
                                        value={data.visa_type || ''}
                                        disabled={isView || processing}
                                        onCommit={(v) =>
                                            setData('visa_type', v || null)
                                        }
                                        placeholder="e.g., Umrah Visa"
                                    />
                                    {renderError('visa_type')}
                                </div>
                            </div>
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="vehicle_type">
                                    Vehicle Type
                                    <FieldRequirements hint="Enter vehicle type" />
                                </Label>
                                <div className="relative">
                                    <ProperInput
                                        id="vehicle_type"
                                        value={data.vehicle_type || ''}
                                        disabled={isView || processing}
                                        onCommit={(v) =>
                                            setData('vehicle_type', v || null)
                                        }
                                        placeholder="e.g., Bus 45 Seater"
                                    />
                                    {renderError('vehicle_type')}
                                </div>
                            </div>
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="ticket_type">
                                    Train Ticket Type
                                    <FieldRequirements hint="Enter train ticket type" />
                                </Label>
                                <div className="relative">
                                    <ProperInput
                                        id="ticket_type"
                                        value={data.ticket_type || ''}
                                        disabled={isView || processing}
                                        onCommit={(v) =>
                                            setData('ticket_type', v || null)
                                        }
                                        placeholder="e.g., Economy, Business"
                                    />
                                    {renderError('ticket_type')}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Accommodations */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div className="space-y-1">
                            <CardTitle>Accommodations</CardTitle>
                            <CardDescription>
                                Add accommodation entries for each location.
                            </CardDescription>
                        </div>
                        {!isView && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
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
                                            className="grid grid-cols-1 gap-4 rounded-lg border p-4 md:grid-cols-6"
                                        >
                                            <div className="grid w-full items-center gap-3">
                                                <Label>
                                                    Location
                                                    <FieldRequirements
                                                        required
                                                        hint="Enter location"
                                                    />
                                                </Label>
                                                <div className="relative">
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
                                                </div>
                                            </div>
                                            <div className="grid w-full items-center gap-3">
                                                <Label>
                                                    Hotel Name
                                                    <FieldRequirements
                                                        required
                                                        hint="Enter hotel name"
                                                    />
                                                </Label>
                                                <div className="relative">
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
                                                </div>
                                            </div>
                                            <div className="grid w-full items-center gap-3">
                                                <Label>
                                                    Meal Type
                                                    <FieldRequirements hint="Enter meal type" />
                                                </Label>
                                                <div className="relative">
                                                    <ProperInput
                                                        value={
                                                            accommodation.type_of_meal ||
                                                            ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onCommit={(v) =>
                                                            updateAccommodation(
                                                                index,
                                                                'type_of_meal',
                                                                v || '',
                                                            )
                                                        }
                                                        placeholder="e.g., Full Board"
                                                    />
                                                </div>
                                            </div>
                                            <div className="grid w-full items-center gap-3">
                                                <Label>
                                                    Check In
                                                    <FieldRequirements hint="Select check-in date" />
                                                </Label>
                                                <div className="relative">
                                                    <DatePickerField
                                                        id={`acc_check_in_${index}`}
                                                        value={
                                                            accommodation.check_in ||
                                                            ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onChange={(v) =>
                                                            updateAccommodation(
                                                                index,
                                                                'check_in',
                                                                v,
                                                            )
                                                        }
                                                    />
                                                </div>
                                            </div>
                                            <div className="grid w-full items-center gap-3">
                                                <Label>
                                                    Check Out
                                                    <FieldRequirements hint="Select check-out date" />
                                                </Label>
                                                <div className="relative">
                                                    <DatePickerField
                                                        id={`acc_check_out_${index}`}
                                                        value={
                                                            accommodation.check_out ||
                                                            ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onChange={(v) =>
                                                            updateAccommodation(
                                                                index,
                                                                'check_out',
                                                                v,
                                                            )
                                                        }
                                                    />
                                                </div>
                                            </div>
                                            {!isView && (
                                                <div className="flex items-end">
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
                                                </div>
                                            )}
                                        </div>
                                    ),
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Package Inclusions */}
                <Card>
                    <CardHeader>
                        <CardTitle>Package Inclusions</CardTitle>
                        <CardDescription>
                            Describe included, excluded, and special offer
                            details.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="included">
                                    Included
                                    <FieldRequirements hint="List what's included in the package" />
                                </Label>
                                <div className="relative">
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
                                    {renderError('included')}
                                </div>
                            </div>
                            <div className="grid w-full items-start gap-3">
                                <Label htmlFor="not_included">
                                    Not Included
                                    <FieldRequirements hint="List what's not included" />
                                </Label>
                                <div className="relative">
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
                                    {renderError('not_included')}
                                </div>
                            </div>
                            <div className="grid w-full items-start gap-3">
                                <Label htmlFor="offer">
                                    Offer
                                    <FieldRequirements hint="Describe any special offers" />
                                </Label>
                                <div className="relative">
                                    <Textarea
                                        id="offer"
                                        value={data.offer || ''}
                                        disabled={isView || processing}
                                        onChange={(e) =>
                                            setData(
                                                'offer',
                                                e.target.value || null,
                                            )
                                        }
                                        placeholder="Describe any special offers or promotions..."
                                    />
                                    {renderError('offer')}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Remarks */}
                <Card>
                    <CardHeader>
                        <CardTitle>Remarks</CardTitle>
                        <CardDescription>
                            Add any additional internal or operational notes.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid w-full items-center gap-3">
                            <Label htmlFor="remarks">
                                Remarks
                                <FieldRequirements hint="Enter any additional remarks or notes" />
                            </Label>
                            <div className="relative">
                                <Textarea
                                    id="remarks"
                                    value={data.remarks || ''}
                                    disabled={isView || processing}
                                    onChange={(e) =>
                                        setData(
                                            'remarks',
                                            e.target.value || null,
                                        )
                                    }
                                    placeholder="Additional remarks or notes"
                                    rows={4}
                                />
                                {renderError('remarks')}
                            </div>
                        </div>
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
