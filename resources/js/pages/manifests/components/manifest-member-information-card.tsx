import { FormField } from '@/components/form-field';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { type TravelerWithUI } from '../types';

interface ManifestMemberInformationCardProps {
    travelers: TravelerWithUI[];
}

function resolveTravelerAge(traveler: TravelerWithUI): number | null {
    if (typeof traveler.age === 'number' && Number.isFinite(traveler.age)) {
        return traveler.age;
    }

    if (!traveler.date_of_birth) {
        return null;
    }

    const parsedDate = new Date(traveler.date_of_birth);

    if (Number.isNaN(parsedDate.getTime())) {
        return null;
    }

    const now = new Date();
    let age = now.getFullYear() - parsedDate.getFullYear();
    const monthDiff = now.getMonth() - parsedDate.getMonth();

    if (
        monthDiff < 0 ||
        (monthDiff === 0 && now.getDate() < parsedDate.getDate())
    ) {
        age -= 1;
    }

    return age >= 0 ? age : null;
}

export default function ManifestMemberInformationCard({
    travelers,
}: ManifestMemberInformationCardProps) {
    const activeTravelers = travelers.filter(
        (traveler) => traveler.status !== 'cancelled',
    );

    const jemaahTravelers = activeTravelers.filter(
        (traveler) => !traveler.package_official_id,
    );
    const officialCount = activeTravelers.length - jemaahTravelers.length;

    const counts = jemaahTravelers.reduce(
        (acc, traveler) => {
            const age = resolveTravelerAge(traveler);
            const gender = String(traveler.gender ?? '').toLowerCase();

            if (age === null) {
                return acc;
            }

            if (age < 2) {
                acc.infant += 1;

                return acc;
            }

            if (age >= 18) {
                acc.adults += 1;

                if (gender === 'male') {
                    acc.maleAdults += 1;
                }

                if (gender === 'female') {
                    acc.femaleAdults += 1;
                }

                return acc;
            }

            if (gender === 'male') {
                acc.boy += 1;
            }

            if (gender === 'female') {
                acc.girl += 1;
            }

            return acc;
        },
        {
            adults: 0,
            maleAdults: 0,
            femaleAdults: 0,
            boy: 0,
            girl: 0,
            infant: 0,
        },
    );

    const infoItems = [
        { label: 'Total Adults', value: counts.adults },
        { label: 'Male (Adults)', value: counts.maleAdults },
        { label: 'Female (Adults)', value: counts.femaleAdults },
        { label: 'Boy (below 18)', value: counts.boy },
        { label: 'Girl (below 18)', value: counts.girl },
        { label: 'Infant (below 2)', value: counts.infant },
        { label: 'Total Jemaah', value: jemaahTravelers.length },
        { label: 'Official', value: officialCount },
        { label: 'Grand Total', value: jemaahTravelers.length + officialCount },
    ];

    return (
        <Card className="bg-transparent">
            <CardHeader className="gap-0">
                <CardTitle className="text-xl">
                    Manifest Member Information
                </CardTitle>
                <CardDescription>
                    Auto-calculated summary based on active travelers in this
                    manifest.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-3">
                {infoItems.map((item) => (
                    <FormField key={item.label} label={item.label}>
                        <Input value={String(item.value)} disabled />
                    </FormField>
                ))}
            </CardContent>
        </Card>
    );
}
