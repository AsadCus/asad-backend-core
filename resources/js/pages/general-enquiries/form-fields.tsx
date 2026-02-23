import { DatePickerField } from '@/components/date-picker';
import { FieldRequirements } from '@/components/field-requirements';
import { ProperInput } from '@/components/proper-input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { isBeforeToday } from '@/lib/utils';
import { GeneralEnquirySchema } from './schema';

interface GeneralEnquiryFormFieldsProps {
    data: GeneralEnquirySchema;
    setData: <K extends keyof GeneralEnquirySchema>(
        key: K,
        value: GeneralEnquirySchema[K],
    ) => void;
    renderError: (path: string) => React.ReactNode;
    isView: boolean;
    processing: boolean;
}

export default function GeneralEnquiryFormFields({
    data,
    setData,
    renderError,
    isView,
    processing,
}: GeneralEnquiryFormFieldsProps) {
    return (
        <div className="grid grid-cols-1 items-start gap-6 md:grid-cols-2">
            {/* Full Name */}
            <div className="grid w-full items-center gap-3">
                <Label htmlFor="name">
                    Full Name
                    <FieldRequirements required hint="Enter your full name" />
                </Label>
                <div className="relative">
                    <ProperInput
                        id="name"
                        value={data.name ?? ''}
                        disabled={isView || processing}
                        onCommit={(v) => setData('name', v)}
                        placeholder="Enter full name"
                    />
                    {renderError('name')}
                </div>
            </div>

            {/* Contact Number */}
            <div className="grid w-full items-center gap-3">
                <Label htmlFor="contact_number">
                    Contact Number
                    <FieldRequirements
                        required
                        hint="Enter contact number with country code"
                        format="+65 8765 4321"
                    />
                </Label>
                <div className="relative">
                    <ProperInput
                        id="contact_number"
                        value={data.contact_number ?? ''}
                        disabled={isView || processing}
                        onCommit={(v) => setData('contact_number', v)}
                        placeholder="+65 8765 4321"
                    />
                    {renderError('contact_number')}
                </div>
            </div>

            {/* Email */}
            <div className="grid w-full items-center gap-3">
                <Label htmlFor="email">
                    Email Address
                    <FieldRequirements
                        required
                        hint="Enter email address"
                        format="email@example.com"
                    />
                </Label>
                <div className="relative">
                    <ProperInput
                        id="email"
                        value={data.email ?? ''}
                        disabled={isView || processing}
                        onCommit={(v) => setData('email', v)}
                        placeholder="email@example.com"
                    />
                    {renderError('email')}
                </div>
            </div>

            {/* Adults + Children */}
            <div className="grid w-full grid-cols-1 gap-6 md:grid-cols-2">
                {/* Number of Adults */}
                <div className="grid w-full items-center gap-3">
                    <Label htmlFor="no_of_adults">
                        Number of Adults
                        <FieldRequirements
                            required
                            hint="Select number of adults traveling"
                        />
                    </Label>
                    <div className="relative">
                        <Select
                            value={String(data.no_of_adults ?? '')}
                            onValueChange={(v) =>
                                setData('no_of_adults', parseInt(v) || 0)
                            }
                            disabled={isView || processing}
                        >
                            <SelectTrigger id="no_of_adults">
                                <SelectValue placeholder="Select..." />
                            </SelectTrigger>
                            <SelectContent>
                                {Array.from({ length: 16 }, (_, i) => i).map(
                                    (n) => (
                                        <SelectItem key={n} value={String(n)}>
                                            {n === 0 ? '0' : n}
                                        </SelectItem>
                                    ),
                                )}
                            </SelectContent>
                        </Select>
                        {renderError('no_of_adults')}
                    </div>
                </div>

                {/* Number of Children */}
                <div className="grid w-full items-center gap-3">
                    <Label htmlFor="no_of_children">
                        Number of Children
                        <FieldRequirements hint="Select number of children" />
                    </Label>
                    <div className="relative">
                        <Select
                            value={String(data.no_of_children ?? '')}
                            onValueChange={(v) =>
                                setData('no_of_children', parseInt(v) || 0)
                            }
                            disabled={isView || processing}
                        >
                            <SelectTrigger id="no_of_children">
                                <SelectValue placeholder="Select..." />
                            </SelectTrigger>
                            <SelectContent>
                                {Array.from({ length: 16 }, (_, i) => i).map(
                                    (n) => (
                                        <SelectItem key={n} value={String(n)}>
                                            {n === 0 ? '0' : n}
                                        </SelectItem>
                                    ),
                                )}
                            </SelectContent>
                        </Select>
                        {renderError('no_of_children')}
                    </div>
                </div>
            </div>

            {/* Preferred Travelling Date */}
            <div className="grid w-full items-center gap-3">
                <Label htmlFor="preferred_travelling_date">
                    Preferred Travelling Date
                    <FieldRequirements
                        required
                        hint="Select your preferred travel date"
                    />
                </Label>
                <div className="relative">
                    <DatePickerField
                        id="preferred_travelling_date"
                        value={data.preferred_travelling_date}
                        disabled={isView || processing}
                        disabledDates={isBeforeToday}
                        onChange={(v) =>
                            setData('preferred_travelling_date', v)
                        }
                    />
                    {renderError('preferred_travelling_date')}
                </div>
            </div>

            {/* Preferred Destinations */}
            <div className="grid w-full items-center gap-3">
                <Label htmlFor="preferred_destinations">
                    Preferred Destinations
                    <FieldRequirements
                        required
                        hint="Enter your preferred travel destinations"
                    />
                </Label>
                <div className="relative">
                    <ProperInput
                        id="preferred_destinations"
                        value={data.preferred_destinations ?? ''}
                        disabled={isView || processing}
                        textarea
                        onCommit={(v) => setData('preferred_destinations', v)}
                        placeholder="e.g., Makkah, Madinah, Taif"
                    />
                    {renderError('preferred_destinations')}
                </div>
            </div>

            {/* Mobility Assistance */}
            <div className="grid w-full items-center gap-3 md:col-span-2">
                <Label htmlFor="requires_mobility_assistance">
                    Mobility Assistance Requirements
                    <FieldRequirements hint="Let us know if you have any special mobility needs (optional)" />
                </Label>
                <div className="relative">
                    <ProperInput
                        id="requires_mobility_assistance"
                        value={data.requires_mobility_assistance || ''}
                        disabled={isView || processing}
                        textarea
                        onCommit={(v) =>
                            setData('requires_mobility_assistance', v || null)
                        }
                        placeholder="Tell us about your requirements (optional)"
                    />
                    {renderError('requires_mobility_assistance')}
                </div>
            </div>
        </div>
    );
}
