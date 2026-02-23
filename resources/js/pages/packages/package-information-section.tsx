import { FormField } from '@/components/form-field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { ExternalLink } from 'lucide-react';
import { ReactNode } from 'react';
import { packageStatusLabels } from './schema';

export interface PackageInformationSectionData {
    id: number;
    name: string;
    status?: string;
    airline?: string | null;
    departure_date?: string | null;
    arrival_date?: string | null;
}

interface PackageInformationSectionProps {
    description: string;
    packageInfo: PackageInformationSectionData | null;
    isLoading?: boolean;
    renderPackageSelector?: ReactNode;
    onViewDetails?: () => void;
}

export default function PackageInformationSection({
    description,
    packageInfo,
    isLoading = false,
    renderPackageSelector,
    onViewDetails,
}: PackageInformationSectionProps) {
    const packageStatusKey = (packageInfo?.status ?? '').toLowerCase();
    const packageStatusLabel = packageStatusLabels[packageStatusKey] ?? '-';

    return (
        <Card>
            <CardHeader className="gap-0 pb-3">
                <div className="flex items-center justify-between">
                    <CardTitle className="text-xl">
                        Package Information
                    </CardTitle>
                    {onViewDetails && packageInfo && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={onViewDetails}
                        >
                            <ExternalLink className="h-4 w-4 md:mr-1" />
                            <span className="hidden md:block">
                                View Details
                            </span>
                        </Button>
                    )}
                </div>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {renderPackageSelector}

                {isLoading && !packageInfo ? (
                    <div className="text-sm text-muted-foreground">
                        Loading linked package details...
                    </div>
                ) : (
                    packageInfo && (
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                            <FormField label="Package ID">
                                <div className="rounded-md border bg-muted/30 px-3 py-1.25 text-base select-text">
                                    #{packageInfo.id}
                                </div>
                            </FormField>
                            <FormField label="Package Name">
                                <div className="rounded-md border bg-muted/30 px-3 py-1.25 text-base select-text">
                                    {packageInfo.name}
                                </div>
                            </FormField>
                            <FormField label="Status">
                                <div className="rounded-md border bg-muted/30 px-3 py-1 text-base">
                                    <Badge variant="secondary">
                                        {packageStatusLabel}
                                    </Badge>
                                </div>
                            </FormField>
                            <FormField label="Airline">
                                <div className="rounded-md border bg-muted/30 px-3 py-1.25 text-base select-text">
                                    {packageInfo.airline || '-'}
                                </div>
                            </FormField>
                            <FormField label="Departure Date">
                                <div className="rounded-md border bg-muted/30 px-3 py-1.25 text-base select-text">
                                    {packageInfo.departure_date || '-'}
                                </div>
                            </FormField>
                            <FormField label="Arrival Date">
                                <div className="rounded-md border bg-muted/30 px-3 py-1.25 text-base select-text">
                                    {packageInfo.arrival_date || '-'}
                                </div>
                            </FormField>
                        </div>
                    )
                )}
            </CardContent>
        </Card>
    );
}
