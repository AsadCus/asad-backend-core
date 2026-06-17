import { DatePickerField } from '@/components/date-picker';
import { FormField } from '@/components/form-field';
import { ProperInput } from '@/components/proper-input';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { type PackageAccommodationOption } from '../types';

interface AccommodationInformationCardProps {
    title: string;
    description?: string;
    accommodation: PackageAccommodationOption;
}

export default function AccommodationInformationCard({
    title,
    description,
    accommodation,
}: AccommodationInformationCardProps) {
    return (
        <Card className="bg-transparent">
            <CardHeader className="gap-0">
                <CardTitle className="text-xl">{title}</CardTitle>
                <CardDescription>
                    {description ??
                        'Read-only package accommodation details for this room list tab.'}
                </CardDescription>
            </CardHeader>
            <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-5">
                <FormField label="Hotel">
                    <ProperInput
                        value={accommodation.hotel_name ?? ''}
                        onCommit={() => undefined}
                        disabled
                    />
                </FormField>
                <FormField label="Location">
                    <ProperInput
                        value={accommodation.location ?? ''}
                        onCommit={() => undefined}
                        disabled
                    />
                </FormField>
                <FormField label="Check In">
                    <DatePickerField
                        id={`accommodation-check-in-${
                            accommodation.id ??
                            String(
                                accommodation.location ??
                                    accommodation.hotel_name ??
                                    'row',
                            )
                                .toLowerCase()
                                .replace(/\s+/g, '-')
                        }`}
                        value={accommodation.check_in ?? ''}
                        disabled
                        onChange={() => undefined}
                    />
                </FormField>
                <FormField label="Check Out">
                    <DatePickerField
                        id={`accommodation-check-out-${
                            accommodation.id ??
                            String(
                                accommodation.location ??
                                    accommodation.hotel_name ??
                                    'row',
                            )
                                .toLowerCase()
                                .replace(/\s+/g, '-')
                        }`}
                        value={accommodation.check_out ?? ''}
                        disabled
                        onChange={() => undefined}
                    />
                </FormField>
                <FormField label="Meal Plan">
                    <ProperInput
                        value={accommodation.type_of_meal ?? ''}
                        onCommit={() => undefined}
                        disabled
                    />
                </FormField>
            </CardContent>
        </Card>
    );
}
