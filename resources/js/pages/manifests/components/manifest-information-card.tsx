import { DatePickerField } from '@/components/date-picker';
import { FormField } from '@/components/form-field';
import { ProperInput } from '@/components/proper-input';
import { ProperInputSelect } from '@/components/proper-input-select';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { type ManifestFormData, type PackageForManifestOption } from '../types';

const STATUS_OPTIONS = ['open', 'full', 'closed', 'completed'];

interface ManifestInformationCardProps {
    isView: boolean;
    data: ManifestFormData;
    dataPackage: PackageForManifestOption[];
    setData: (key: string, value: unknown) => void;
    renderError: (path: string) => React.ReactNode;
    errors?: Record<string, string | undefined>;
}

export default function ManifestInformationCard({
    isView,
    data,
    dataPackage,
    setData,
    renderError,
    errors,
}: ManifestInformationCardProps) {
    const selectedPackage = dataPackage.find(
        (p) => Number(p.value) === Number(data.package_id),
    );
    const selectedPackageId = Number(data.package_id ?? 0);
    const packageOptions = dataPackage.filter((item) => {
        const isCurrentSelection = Number(item.value) === selectedPackageId;

        if (isCurrentSelection) {
            return true;
        }

        return !Boolean(item.is_private);
    });

    void errors;

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
                        <ProperInputSelect
                            mode="classic"
                            options={packageOptions.map((item) => ({
                                label: item.label,
                                value: String(item.value),
                            }))}
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
                            placeholder="Select package"
                            disabled={isView || !!data.id}
                        />
                        {renderError('package_id')}
                    </FormField>

                    <FormField
                        label="Package Number"
                        htmlFor="package_number"
                        fieldRequirementsProps={{
                            hint: 'Auto-filled from selected package',
                        }}
                    >
                        <ProperInput
                            id="package_number"
                            value={selectedPackage?.package_number ?? '-'}
                            onCommit={() => undefined}
                            disabled
                        />
                    </FormField>

                    <FormField label="Status" htmlFor="status">
                        <ProperInputSelect
                            mode="classic"
                            options={STATUS_OPTIONS.map((status) => ({
                                label:
                                    status.charAt(0).toUpperCase() +
                                    status.slice(1),
                                value: status,
                            }))}
                            value={
                                data.status ?? selectedPackage?.status ?? 'open'
                            }
                            onValueChange={(value) => setData('status', value)}
                            placeholder="Select status"
                            disabled={isView}
                        />
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
                        <DatePickerField
                            id="departure_date"
                            value={selectedPackage?.departure_date || '-'}
                            disabled
                            onChange={() => undefined}
                        />
                    </FormField>

                    <FormField
                        label="Return Date"
                        htmlFor="return_date"
                        fieldRequirementsProps={{
                            hint: 'From package',
                        }}
                    >
                        <DatePickerField
                            id="return_date"
                            value={selectedPackage?.return_date || '-'}
                            disabled
                            onChange={() => undefined}
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
