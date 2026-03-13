import { FormField } from '@/components/form-field';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
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
        <Card>
            <CardHeader className="gap-0">
                <CardTitle className="text-xl">{title}</CardTitle>
                <CardDescription>
                    {description ??
                        'Read-only package accommodation details for this room list tab.'}
                </CardDescription>
            </CardHeader>
            <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-5">
                <FormField label="Hotel">
                    <Input value={accommodation.hotel_name ?? ''} disabled />
                </FormField>
                <FormField label="Location">
                    <Input value={accommodation.location ?? ''} disabled />
                </FormField>
                <FormField label="Check In">
                    <Input value={accommodation.check_in ?? ''} disabled />
                </FormField>
                <FormField label="Check Out">
                    <Input value={accommodation.check_out ?? ''} disabled />
                </FormField>
                <FormField label="Meal Plan">
                    <Input value={accommodation.type_of_meal ?? ''} disabled />
                </FormField>
            </CardContent>
        </Card>
    );
}
