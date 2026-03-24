import { FormField } from '@/components/form-field';
import { ProperInput } from '@/components/proper-input';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { type PackageFlightOption } from '../types';

interface FlightDetailInformationCardProps {
    flights: PackageFlightOption[];
}

export default function FlightDetailInformationCard({
    flights,
}: FlightDetailInformationCardProps) {
    return (
        <Card className="bg-transparent">
            <CardHeader className="gap-0">
                <CardTitle className="text-xl">
                    Flight Detail Information
                </CardTitle>
                <CardDescription>
                    Flight legs from the selected package. Read-only reference
                    for airline list validation.
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
                                <ProperInput
                                    value={flight.from ?? ''}
                                    onCommit={() => undefined}
                                    disabled
                                />
                            </FormField>
                            <FormField label="To">
                                <ProperInput
                                    value={flight.to ?? ''}
                                    onCommit={() => undefined}
                                    disabled
                                />
                            </FormField>
                            <FormField label="Description">
                                <ProperInput
                                    value={flight.description ?? ''}
                                    onCommit={() => undefined}
                                    disabled
                                />
                            </FormField>
                            <FormField label="Airline">
                                <ProperInput
                                    value={flight.airline ?? ''}
                                    onCommit={() => undefined}
                                    disabled
                                />
                            </FormField>
                            <FormField label="PNR">
                                <ProperInput
                                    value={flight.pnr ?? ''}
                                    onCommit={() => undefined}
                                    disabled
                                />
                            </FormField>
                            <FormField label="Departure Datetime">
                                <ProperInput
                                    value={flight.departure_datetime ?? ''}
                                    onCommit={() => undefined}
                                    disabled
                                />
                            </FormField>
                            <FormField label="Arrival Datetime">
                                <ProperInput
                                    value={flight.arrival_datetime ?? ''}
                                    onCommit={() => undefined}
                                    disabled
                                />
                            </FormField>
                        </div>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}
