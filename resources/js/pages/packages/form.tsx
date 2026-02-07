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
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/react';
import { AlertCircle, Plus, Trash2 } from 'lucide-react';
import {
    packageSchema,
    type AccommodationSchema,
    type PackageSchema,
} from './schema';

interface PackageFormProps {
    mode: 'create' | 'edit' | 'view';
    initialData?: PackageSchema;
    onCancel?: () => void;
}

export default function PackageForm({
    mode,
    initialData,
    onCancel,
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
        remarks: '',
        accommodations: [],
    };

    const { data, setData, post, put, processing, errors, reset } =
        useForm<PackageSchema>(defaultData);

    function validateClientSide(): boolean {
        const result = packageSchema.safeParse(data);
        if (!result.success) {
            const validationErrors = result.error.flatten().fieldErrors;
            Object.entries(validationErrors).forEach(([key, messages]) => {
                if (messages && messages.length > 0) {
                    errors[key as keyof PackageSchema] = messages[0] as any;
                }
            });
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return false;
        }
        return true;
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (!validateClientSide()) return;

        const url = '/packages';

        if (isCreate) {
            post(url, {
                preserveState: true,
                preserveScroll: true,
                onError: () => window.scrollTo({ top: 0, behavior: 'smooth' }),
            });
        } else if (isEdit) {
            put(`${url}/${data.id}`, {
                preserveState: true,
                preserveScroll: true,
                onError: () => window.scrollTo({ top: 0, behavior: 'smooth' }),
            });
        }
    }

    const renderError = (fieldName: string) => {
        const message = (errors as any)[fieldName];
        if (!message) return null;
        return <p className="mt-1 text-xs text-red-500">{message}</p>;
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
                {/* Error Summary Banner */}
                {Object.keys(errors).length > 0 && (
                    <div className="rounded-lg border border-red-200 bg-red-50 p-4">
                        <div className="flex items-start gap-3">
                            <AlertCircle className="mt-0.5 h-5 w-5 flex-shrink-0 text-red-600" />
                            <div className="flex-1">
                                <h3 className="font-semibold text-red-900">
                                    Please fix the following errors:
                                </h3>
                                <ul className="mt-2 space-y-1 text-sm text-red-800">
                                    {Object.entries(errors).map(
                                        ([key, message]) => (
                                            <li key={key}>
                                                • {String(message)}
                                            </li>
                                        ),
                                    )}
                                </ul>
                            </div>
                        </div>
                    </div>
                )}

                {/* Package Information */}
                <Card>
                    <CardHeader>
                        <CardTitle>Package Information</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            {/* Group Number (auto-generated, read-only) */}
                            {(isEdit || isView) && data.group_number && (
                                <div className="grid gap-2">
                                    <Label htmlFor="group_number">
                                        Group Number
                                    </Label>
                                    <Input
                                        id="group_number"
                                        type="text"
                                        value={data.group_number}
                                        disabled={true}
                                        className="bg-muted"
                                    />
                                </div>
                            )}

                            {/* Package Name */}
                            <div className="grid gap-2">
                                <Label htmlFor="name">Package Name *</Label>
                                <Input
                                    id="name"
                                    type="text"
                                    value={data.name}
                                    disabled={isView || processing}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    placeholder="Enter package name"
                                />
                                {renderError('name')}
                            </div>

                            {/* Status */}
                            <div className="grid gap-2">
                                <Label>Status *</Label>
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
                    </CardContent>
                </Card>

                {/* Pricing */}
                <Card>
                    <CardHeader>
                        <CardTitle>Pricing</CardTitle>
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
                                <div key={key} className="grid gap-2">
                                    <Label htmlFor={key}>{label}</Label>
                                    <Input
                                        id={key}
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={(data as any)[key]}
                                        disabled={isView || processing}
                                        onChange={(e) =>
                                            setData(
                                                key as keyof PackageSchema,
                                                parseFloat(e.target.value) || 0,
                                            )
                                        }
                                    />
                                    {renderError(key)}
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
                                <div key={key} className="grid gap-2">
                                    <Label htmlFor={key}>{label}</Label>
                                    <Input
                                        id={key}
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={(data as any)[key]}
                                        disabled={isView || processing}
                                        onChange={(e) =>
                                            setData(
                                                key as keyof PackageSchema,
                                                parseFloat(e.target.value) || 0,
                                            )
                                        }
                                    />
                                    {renderError(key)}
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                {/* Flight Details */}
                <Card>
                    <CardHeader>
                        <CardTitle>Flight Details</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div className="grid gap-2">
                                <Label htmlFor="airline">Airline</Label>
                                <Input
                                    id="airline"
                                    type="text"
                                    value={data.airline || ''}
                                    disabled={isView || processing}
                                    onChange={(e) =>
                                        setData('airline', e.target.value)
                                    }
                                    placeholder="e.g., Saudi Airlines"
                                />
                                {renderError('airline')}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="pnr">PNR</Label>
                                <Input
                                    id="pnr"
                                    type="text"
                                    value={data.pnr || ''}
                                    disabled={isView || processing}
                                    onChange={(e) =>
                                        setData('pnr', e.target.value)
                                    }
                                    placeholder="Enter PNR"
                                />
                                {renderError('pnr')}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                            <div className="grid gap-2">
                                <Label>Departure Date</Label>
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
                            <div className="grid gap-2">
                                <Label>Arrival Date</Label>
                                <DatePickerField
                                    id="arrival_date"
                                    value={data.arrival_date || ''}
                                    disabled={isView || processing}
                                    onChange={(v) => setData('arrival_date', v)}
                                />
                                {renderError('arrival_date')}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="total_seats">Total Seats</Label>
                                <Input
                                    id="total_seats"
                                    type="number"
                                    min="0"
                                    value={data.total_seats ?? ''}
                                    disabled={isView || processing}
                                    onChange={(e) =>
                                        setData(
                                            'total_seats',
                                            e.target.value
                                                ? parseInt(e.target.value)
                                                : null,
                                        )
                                    }
                                    placeholder="0"
                                />
                                {renderError('total_seats')}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="seats_left">Seats Left</Label>
                                <Input
                                    id="seats_left"
                                    type="number"
                                    min="0"
                                    value={data.seats_left ?? ''}
                                    disabled={isView || processing}
                                    onChange={(e) =>
                                        setData(
                                            'seats_left',
                                            e.target.value
                                                ? parseInt(e.target.value)
                                                : null,
                                        )
                                    }
                                    placeholder="0"
                                />
                                {renderError('seats_left')}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Visa, Vehicle & Train */}
                <Card>
                    <CardHeader>
                        <CardTitle>Visa, Vehicle & Train</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div className="grid gap-2">
                                <Label htmlFor="visa_type">Visa Type</Label>
                                <Input
                                    id="visa_type"
                                    type="text"
                                    value={data.visa_type || ''}
                                    disabled={isView || processing}
                                    onChange={(e) =>
                                        setData('visa_type', e.target.value)
                                    }
                                    placeholder="e.g., Umrah Visa"
                                />
                                {renderError('visa_type')}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="vehicle_type">
                                    Vehicle Type
                                </Label>
                                <Input
                                    id="vehicle_type"
                                    type="text"
                                    value={data.vehicle_type || ''}
                                    disabled={isView || processing}
                                    onChange={(e) =>
                                        setData('vehicle_type', e.target.value)
                                    }
                                    placeholder="e.g., Bus 45 Seater"
                                />
                                {renderError('vehicle_type')}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="ticket_type">
                                    Train Ticket Type
                                </Label>
                                <Input
                                    id="ticket_type"
                                    type="text"
                                    value={data.ticket_type || ''}
                                    disabled={isView || processing}
                                    onChange={(e) =>
                                        setData('ticket_type', e.target.value)
                                    }
                                    placeholder="e.g., Economy, Business"
                                />
                                {renderError('ticket_type')}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Accommodations */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Accommodations</CardTitle>
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
                            <p className="text-sm text-muted-foreground">
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
                                            <div className="grid gap-2">
                                                <Label>Location *</Label>
                                                <Input
                                                    value={
                                                        accommodation.location
                                                    }
                                                    disabled={
                                                        isView || processing
                                                    }
                                                    onChange={(e) =>
                                                        updateAccommodation(
                                                            index,
                                                            'location',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="e.g., Mekkah"
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label>Hotel Name *</Label>
                                                <Input
                                                    value={
                                                        accommodation.hotel_name
                                                    }
                                                    disabled={
                                                        isView || processing
                                                    }
                                                    onChange={(e) =>
                                                        updateAccommodation(
                                                            index,
                                                            'hotel_name',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="Hotel name"
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label>Meal Type</Label>
                                                <Input
                                                    value={
                                                        accommodation.type_of_meal ||
                                                        ''
                                                    }
                                                    disabled={
                                                        isView || processing
                                                    }
                                                    onChange={(e) =>
                                                        updateAccommodation(
                                                            index,
                                                            'type_of_meal',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="e.g., Full Board"
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label>Check In</Label>
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
                                            <div className="grid gap-2">
                                                <Label>Check Out</Label>
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
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="included">Included</Label>
                                <Textarea
                                    id="included"
                                    value={data.included || ''}
                                    disabled={isView || processing}
                                    onChange={(e) =>
                                        setData('included', e.target.value)
                                    }
                                    placeholder="List included items (e.g., flights, hotels, meals, visa, transport...)"
                                    rows={5}
                                />
                                {renderError('included')}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="not_included">
                                    Not Included
                                </Label>
                                <Textarea
                                    id="not_included"
                                    value={data.not_included || ''}
                                    disabled={isView || processing}
                                    onChange={(e) =>
                                        setData('not_included', e.target.value)
                                    }
                                    placeholder="List excluded items (e.g., personal expenses, tips...)"
                                    rows={5}
                                />
                                {renderError('not_included')}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Remarks */}
                <Card>
                    <CardHeader>
                        <CardTitle>Remarks</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-2">
                            <Label htmlFor="remarks">Remarks</Label>
                            <Textarea
                                id="remarks"
                                value={data.remarks || ''}
                                disabled={isView || processing}
                                onChange={(e) =>
                                    setData('remarks', e.target.value)
                                }
                                placeholder="Additional remarks or notes"
                                rows={4}
                            />
                            {renderError('remarks')}
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
                            Back
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
                                {isEdit ? 'Update' : 'Create'}
                            </Button>
                        </>
                    )}
                </div>
            </form>
        </div>
    );
}
