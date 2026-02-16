import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { index } from '@/routes/ops-movements';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useCallback } from 'react';

interface Accommodation {
    location: string;
    hotel_name: string;
    type_of_meal: string | null;
    check_in: string | null;
    check_out: string | null;
}

interface Traveler {
    id: number;
    name: string;
    passport_number: string | null;
    nationality: string | null;
    traveler_type: string | null;
}

interface ManifestInfo {
    id: number;
    reference_number: string;
    company_name: string | null;
    status: string;
    travelers_count: number;
    travelers: Traveler[];
}

interface OpsMovementData {
    id: number;
    group_number: string;
    name: string;
    status: string;
    launched: boolean;
    price_single: number;
    price_double: number;
    price_triple: number;
    price_quad: number;
    child_with_bed_price: number;
    child_no_bed_price: number;
    infant_price: number;
    airline: string | null;
    pnr: string | null;
    departure_date: string | null;
    arrival_date: string | null;
    total_seats: number | null;
    seats_left: number | null;
    visa_type: string | null;
    vehicle_type: string | null;
    ticket_type: string | null;
    included: string | null;
    not_included: string | null;
    remarks: string | null;
    accommodations: Accommodation[];
    manifests: ManifestInfo[];
}

interface ShowOpsMovementProps {
    data: OpsMovementData;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Ops Movements',
        href: index().url,
    },
];

function InfoRow({
    label,
    value,
}: {
    label: string;
    value: string | number | null | undefined;
}) {
    return (
        <div className="grid gap-1">
            <span className="text-base font-medium text-muted-foreground">
                {label}
            </span>
            <span className="text-base">{value || '-'}</span>
        </div>
    );
}

export default function ShowOpsMovement({ data }: ShowOpsMovementProps) {
    const handleBack = useCallback(() => {
        window.history.back();
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Ops Movement - ${data.group_number}`} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">
                        Ops Movement - {data.group_number}
                    </h2>
                    <span
                        className={`inline-flex rounded-full px-3 py-1 text-base font-semibold ${
                            data.status === 'open'
                                ? 'bg-green-100 text-green-800'
                                : 'bg-red-100 text-red-800'
                        }`}
                    >
                        {data.status === 'open' ? 'Open' : 'Closed'}
                    </span>
                </div>

                <div className="space-y-6">
                    {/* Package Info */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Package Information</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                                <InfoRow
                                    label="Group Number"
                                    value={data.group_number}
                                />
                                <InfoRow
                                    label="Package Name"
                                    value={data.name}
                                />
                                <InfoRow
                                    label="Status"
                                    value={
                                        data.status === 'open'
                                            ? 'Open'
                                            : 'Closed'
                                    }
                                />
                                <InfoRow
                                    label="Launched"
                                    value={data.launched ? 'Yes' : 'No'}
                                />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Flight Details */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Flight Details</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                                <InfoRow label="Airline" value={data.airline} />
                                <InfoRow label="PNR" value={data.pnr} />
                                <InfoRow
                                    label="Departure"
                                    value={data.departure_date}
                                />
                                <InfoRow
                                    label="Arrival"
                                    value={data.arrival_date}
                                />
                                <InfoRow
                                    label="Total Seats"
                                    value={data.total_seats}
                                />
                                <InfoRow
                                    label="Seats Left"
                                    value={data.seats_left}
                                />
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
                                <InfoRow
                                    label="Visa Type"
                                    value={data.visa_type}
                                />
                                <InfoRow
                                    label="Vehicle Type"
                                    value={data.vehicle_type}
                                />
                                <InfoRow
                                    label="Train Ticket Type"
                                    value={data.ticket_type}
                                />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Accommodations */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Accommodations</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {data.accommodations.length === 0 ? (
                                <p className="text-base text-muted-foreground">
                                    No accommodations.
                                </p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-base">
                                        <thead>
                                            <tr className="border-b">
                                                <th className="px-3 py-2 text-left font-medium">
                                                    Location
                                                </th>
                                                <th className="px-3 py-2 text-left font-medium">
                                                    Hotel
                                                </th>
                                                <th className="px-3 py-2 text-left font-medium">
                                                    Meal Type
                                                </th>
                                                <th className="px-3 py-2 text-left font-medium">
                                                    Check In
                                                </th>
                                                <th className="px-3 py-2 text-left font-medium">
                                                    Check Out
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {data.accommodations.map(
                                                (acc, i) => (
                                                    <tr
                                                        key={i}
                                                        className="border-b last:border-0"
                                                    >
                                                        <td className="px-3 py-2">
                                                            {acc.location}
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            {acc.hotel_name}
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            {acc.type_of_meal ||
                                                                '-'}
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            {acc.check_in ||
                                                                '-'}
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            {acc.check_out ||
                                                                '-'}
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Pricing */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Pricing</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                                <InfoRow
                                    label="Single"
                                    value={`$${Number(data.price_single).toFixed(2)}`}
                                />
                                <InfoRow
                                    label="Double"
                                    value={`$${Number(data.price_double).toFixed(2)}`}
                                />
                                <InfoRow
                                    label="Triple"
                                    value={`$${Number(data.price_triple).toFixed(2)}`}
                                />
                                <InfoRow
                                    label="Quad"
                                    value={`$${Number(data.price_quad).toFixed(2)}`}
                                />
                                <InfoRow
                                    label="Child (with bed)"
                                    value={`$${Number(data.child_with_bed_price).toFixed(2)}`}
                                />
                                <InfoRow
                                    label="Child (no bed)"
                                    value={`$${Number(data.child_no_bed_price).toFixed(2)}`}
                                />
                                <InfoRow
                                    label="Infant"
                                    value={`$${Number(data.infant_price).toFixed(2)}`}
                                />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Inclusions & Remarks */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Inclusions & Remarks</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <div>
                                    <span className="text-base font-medium text-muted-foreground">
                                        Included
                                    </span>
                                    <p className="mt-1 text-base whitespace-pre-wrap">
                                        {data.included || '-'}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-base font-medium text-muted-foreground">
                                        Not Included
                                    </span>
                                    <p className="mt-1 text-base whitespace-pre-wrap">
                                        {data.not_included || '-'}
                                    </p>
                                </div>
                            </div>
                            {data.remarks && (
                                <div className="mt-4">
                                    <span className="text-base font-medium text-muted-foreground">
                                        Remarks
                                    </span>
                                    <p className="mt-1 text-base whitespace-pre-wrap">
                                        {data.remarks}
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Manifests & Travelers */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Manifests & Travelers</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {data.manifests.length === 0 ? (
                                <p className="text-base text-muted-foreground">
                                    No manifests linked to this package.
                                </p>
                            ) : (
                                <div className="space-y-4">
                                    {data.manifests.map((manifest) => (
                                        <div
                                            key={manifest.id}
                                            className="rounded-lg border p-4"
                                        >
                                            <div className="mb-3 flex items-center justify-between">
                                                <div>
                                                    <span className="font-medium">
                                                        {
                                                            manifest.reference_number
                                                        }
                                                    </span>
                                                    {manifest.company_name && (
                                                        <span className="ml-2 text-base text-muted-foreground">
                                                            —{' '}
                                                            {
                                                                manifest.company_name
                                                            }
                                                        </span>
                                                    )}
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <span className="text-base text-muted-foreground">
                                                        {
                                                            manifest.travelers_count
                                                        }{' '}
                                                        traveler(s)
                                                    </span>
                                                    <span
                                                        className={`inline-flex rounded-full px-2 py-0.5 text-sm font-semibold ${
                                                            manifest.status ===
                                                            'active'
                                                                ? 'bg-green-100 text-green-800'
                                                                : 'bg-gray-100 text-gray-800'
                                                        }`}
                                                    >
                                                        {manifest.status}
                                                    </span>
                                                </div>
                                            </div>
                                            {manifest.travelers.length > 0 && (
                                                <div className="overflow-x-auto">
                                                    <table className="w-full text-base">
                                                        <thead>
                                                            <tr className="border-b bg-muted/50">
                                                                <th className="px-3 py-1.5 text-left font-medium">
                                                                    Name
                                                                </th>
                                                                <th className="px-3 py-1.5 text-left font-medium">
                                                                    Passport
                                                                </th>
                                                                <th className="px-3 py-1.5 text-left font-medium">
                                                                    Nationality
                                                                </th>
                                                                <th className="px-3 py-1.5 text-left font-medium">
                                                                    Type
                                                                </th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            {manifest.travelers.map(
                                                                (t) => (
                                                                    <tr
                                                                        key={
                                                                            t.id
                                                                        }
                                                                        className="border-b last:border-0"
                                                                    >
                                                                        <td className="px-3 py-1.5">
                                                                            {
                                                                                t.name
                                                                            }
                                                                        </td>
                                                                        <td className="px-3 py-1.5">
                                                                            {t.passport_number ||
                                                                                '-'}
                                                                        </td>
                                                                        <td className="px-3 py-1.5">
                                                                            {t.nationality ||
                                                                                '-'}
                                                                        </td>
                                                                        <td className="px-3 py-1.5">
                                                                            {t.traveler_type ||
                                                                                '-'}
                                                                        </td>
                                                                    </tr>
                                                                ),
                                                            )}
                                                        </tbody>
                                                    </table>
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Back Button */}
                    <div className="flex justify-end">
                        <Button variant="outline" onClick={handleBack}>
                            Back
                        </Button>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
