import { FormField } from '@/components/form-field';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { type PackageFlightOption } from '../types';

interface FlightDetailInformationCardProps {
    flights: PackageFlightOption[];
}

export default function FlightDetailInformationCard({
    flights,
}: FlightDetailInformationCardProps) {
    return (
        <Card>
            <CardHeader className="gap-0">
                <CardTitle className="text-xl">Flight Detail Information</CardTitle>
                <CardDescription>
                    Flight legs from the selected package. Read-only reference for airline list validation.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {flights.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        No flight details available for the selected package.
                    </p>
                )}

                {flights.map((flight, index) => (
                    <div
                        key={`flight-${flight.id ?? index}`}
                        className="rounded-md border p-4"
                    >
                        <p className="mb-3 text-sm font-medium text-muted-foreground">
                            Flight {index + 1}
                        </p>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                            <FormField label="From">
                                <Input value={flight.from ?? ''} disabled />
                            </FormField>
                            <FormField label="To">
                                <Input value={flight.to ?? ''} disabled />
                            </FormField>
                            <FormField label="Description">
                                <Input value={flight.description ?? ''} disabled />
                            </FormField>
                            <FormField label="Airline">
                                <Input value={flight.airline ?? ''} disabled />
                            </FormField>
                            <FormField label="PNR">
                                <Input value={flight.pnr ?? ''} disabled />
                            </FormField>
                            <FormField label="Departure Datetime">
                                <Input
                                    value={flight.departure_datetime ?? ''}
                                    disabled
                                />
                            </FormField>
                            <FormField label="Arrival Datetime">
                                <Input value={flight.arrival_datetime ?? ''} disabled />
                            </FormField>
                        </div>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}
