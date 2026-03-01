import { DatePickerField } from '@/components/date-picker';
import { FormField } from '@/components/form-field';
import { ProperInput } from '@/components/proper-input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { type ManifestFormData, type PackageForManifestOption } from '../types';

const STATUS_OPTIONS = ['draft', 'confirmed', 'completed', 'cancelled'];

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
    return (
        <Card>
            <CardHeader>
                <CardTitle>Manifest Information</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
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
                            onValueChange={(value) =>
                                setData('package_id', Number(value))
                            }
                            disabled={isView}
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

                    <FormField
                        label="Reference Number"
                        htmlFor="reference_number"
                        fieldRequirementsProps={{ required: true }}
                    >
                        <ProperInput
                            id="reference_number"
                            value={data.reference_number ?? ''}
                            onCommit={(value) =>
                                setData('reference_number', value)
                            }
                            disabled={isView}
                        />
                        {renderError('reference_number')}
                    </FormField>

                    <FormField label="Status" htmlFor="status">
                        <Select
                            value={data.status ?? 'draft'}
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

                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <FormField
                        label="Departure Date"
                        htmlFor="departure_date"
                        fieldRequirementsProps={{ required: true }}
                    >
                        <DatePickerField
                            id="departure_date"
                            value={data.departure_date}
                            onChange={(value) =>
                                setData('departure_date', value)
                            }
                            disabled={isView}
                        />
                        {renderError('departure_date')}
                    </FormField>

                    <FormField
                        label="Return Date"
                        htmlFor="return_date"
                        fieldRequirementsProps={{ required: true }}
                    >
                        <DatePickerField
                            id="return_date"
                            value={data.return_date}
                            onChange={(value) => setData('return_date', value)}
                            disabled={isView}
                        />
                        {renderError('return_date')}
                    </FormField>

                    <FormField label="Duration" htmlFor="duration">
                        <ProperInput
                            id="duration"
                            value={data.duration ?? ''}
                            onCommit={(value) => setData('duration', value)}
                            disabled={isView}
                            placeholder="e.g. 14 Days / 13 Nights"
                        />
                        {renderError('duration')}
                    </FormField>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <FormField
                        label="Company Address"
                        htmlFor="company_address"
                    >
                        <ProperInput
                            id="company_address"
                            value={data.company_address ?? ''}
                            onCommit={(value) =>
                                setData('company_address', value)
                            }
                            disabled={isView}
                        />
                    </FormField>

                    <FormField label="Company Phone" htmlFor="company_phone">
                        <ProperInput
                            id="company_phone"
                            value={data.company_phone ?? ''}
                            onCommit={(value) =>
                                setData('company_phone', value)
                            }
                            disabled={isView}
                        />
                    </FormField>

                    <FormField label="First Meal" htmlFor="first_meal">
                        <ProperInput
                            id="first_meal"
                            value={data.first_meal ?? ''}
                            onCommit={(value) => setData('first_meal', value)}
                            disabled={isView}
                        />
                    </FormField>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <FormField label="Last Meal" htmlFor="last_meal">
                        <ProperInput
                            id="last_meal"
                            value={data.last_meal ?? ''}
                            onCommit={(value) => setData('last_meal', value)}
                            disabled={isView}
                        />
                    </FormField>
                    <FormField label="Notes" htmlFor="notes">
                        <Textarea
                            id="notes"
                            value={data.notes ?? ''}
                            onChange={(event) =>
                                setData('notes', event.target.value)
                            }
                            disabled={isView}
                        />
                    </FormField>
                </div>
            </CardContent>
        </Card>
    );
}
