import { FormField } from '@/components/form-field';
import { ProperInput } from '@/components/proper-input';
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
import { type ManifestFormData, type PackageForManifestOption } from '../types';

const STATUS_OPTIONS = ['open', 'closed'];

interface ManifestInformationCardProps {
    isView: boolean;
    data: ManifestFormData;
    dataPackage: PackageForManifestOption[];
    setData: (key: string, value: unknown) => void;
    renderError: (path: string) => React.ReactNode;
}

export default function ManifestInformationCard({
    isView,
    data,
    dataPackage,
    setData,
    renderError,
}: ManifestInformationCardProps) {
    const selectedPackage = dataPackage.find(
        (p) => Number(p.value) === Number(data.package_id),
    );

    return (
        <Card className="bg-transparent">
            <CardHeader className="gap-0">
                <CardTitle className="text-xl">Manifest Information</CardTitle>
                <CardDescription>
                    Provide the necessary details for the manifest, including
                    package selection, package status, and any additional notes.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-3">
                    <FormField
                        label="Package"
                        htmlFor="package_id"
                        fieldRequirementsProps={{
                            required: true,
                            hint: 'Select package',
                        }}
                    >
                        <Select
                            value={String(data.package_id || '')}
                            onValueChange={(value) => {
                                const nextPackageId = Number(value);
                                const nextPackage = dataPackage.find(
                                    (item) =>
                                        Number(item.value) === nextPackageId,
                                );

                                setData('package_id', nextPackageId);

                                if (
                                    typeof nextPackage?.status === 'string' &&
                                    nextPackage.status.length > 0
                                ) {
                                    setData('status', nextPackage.status);
                                }
                            }}
                            disabled={isView || !!data.id}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select package" />
                            </SelectTrigger>
                            <SelectContent>
                                {dataPackage.map((item) => (
                                    <SelectItem
                                        key={item.value}
                                        value={String(item.value)}
                                    >
                                        {item.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {renderError('package_id')}
                    </FormField>

                    {data.manifest_number && (
                        <FormField
                            label="Manifest Number"
                            htmlFor="manifest_number"
                            fieldRequirementsProps={{
                                hint: 'Auto-generated manifest identifier',
                            }}
                        >
                            <Input
                                id="manifest_number"
                                type="text"
                                value={data.manifest_number}
                                disabled={true}
                                className="bg-muted"
                            />
                        </FormField>
                    )}

                    <FormField label="Status" htmlFor="status">
                        <Select
                            value={
                                data.status ?? selectedPackage?.status ?? 'open'
                            }
                            onValueChange={(value) => setData('status', value)}
                            disabled={isView}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select status" />
                            </SelectTrigger>
                            <SelectContent>
                                {STATUS_OPTIONS.map((status) => (
                                    <SelectItem key={status} value={status}>
                                        {status.charAt(0).toUpperCase() +
                                            status.slice(1)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {renderError('status')}
                    </FormField>
                </div>

                <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-3">
                    <FormField
                        label="Departure Date"
                        htmlFor="departure_date"
                        fieldRequirementsProps={{
                            hint: 'From package',
                        }}
                    >
                        <Input
                            id="departure_date"
                            value={selectedPackage?.departure_date || '-'}
                            disabled={true}
                            className="bg-muted"
                        />
                    </FormField>

                    <FormField
                        label="Return Date"
                        htmlFor="return_date"
                        fieldRequirementsProps={{
                            hint: 'From package',
                        }}
                    >
                        <Input
                            id="return_date"
                            value={selectedPackage?.return_date || '-'}
                            disabled={true}
                            className="bg-muted"
                        />
                    </FormField>

                    <FormField
                        label="Notes"
                        htmlFor="notes"
                        fieldRequirementsProps={{
                            hint: 'Enter any additional information or remarks about the manifest',
                        }}
                    >
                        <ProperInput
                            id="notes"
                            value={data.notes ?? ''}
                            disabled={isView}
                            textarea
                            onCommit={(v) => setData('notes', v)}
                            placeholder="Enter any additional information or remarks about the manifest"
                        />
                    </FormField>
                </div>
            </CardContent>
        </Card>
    );
}
