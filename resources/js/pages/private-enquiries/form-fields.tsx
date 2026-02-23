import { BooleanSelect } from '@/components/boolean-select';
import { DatePickerField } from '@/components/date-picker';
import { FieldRequirements } from '@/components/field-requirements';
import { FormField } from '@/components/form-field';
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
import { OptionType } from '@/types';
import { PrivateEnquirySchema } from './schema';

export interface PrivateEnquiryFieldOptions {
    airlines: OptionType[];
    flightClasses: OptionType[];
    hotelsMakkah: OptionType[];
    hotelsMadinah: OptionType[];
    mealOptions: OptionType[];
    nightsMakkah: OptionType[];
    nightsMadinah: OptionType[];
    landTransfer: OptionType[];
    wheelchair: OptionType[];
}

interface PrivateEnquiryFormFieldsProps {
    data: PrivateEnquirySchema;
    setData: <K extends keyof PrivateEnquirySchema>(
        key: K,
        value: PrivateEnquirySchema[K],
    ) => void;
    renderError: (path: string) => React.ReactNode;
    isView: boolean;
    processing: boolean;
    options?: PrivateEnquiryFieldOptions;
}

export const internalFieldOptions: PrivateEnquiryFieldOptions = {
    airlines: [
        { value: 'Saudia Airlines', label: 'Saudia Airlines' },
        { value: 'Emirates', label: 'Emirates' },
        { value: 'Qatar Airways', label: 'Qatar Airways' },
    ],
    flightClasses: [
        { value: 'Business', label: 'Business' },
        { value: 'Economy', label: 'Economy' },
    ],
    hotelsMakkah: [
        { value: 'Makkah Hotel & Towers', label: 'Makkah Hotel & Towers' },
        { value: 'Swissotel Makkah', label: 'Swissotel Makkah' },
        { value: 'Hilton Suites Makkah', label: 'Hilton Suites Makkah' },
        {
            value: 'Jumeirah Jabal Omar Makkah',
            label: 'Jumeirah Jabal Omar Makkah',
        },
        {
            value: 'Fairmont Makkah Clock Royal Tower Hotel',
            label: 'Fairmont Makkah Clock Royal Tower Hotel',
        },
        {
            value: 'Address Jabal Omar Makkah',
            label: 'Address Jabal Omar Makkah',
        },
        { value: 'Swissotel Maqam', label: 'Swissotel Maqam' },
        { value: 'Hyatt Jabal Omar', label: 'Hyatt Jabal Omar' },
        {
            value: 'InterContinental Dar Al Tawhid Makkah',
            label: 'InterContinental Dar Al Tawhid Makkah',
        },
        {
            value: 'Hilton Convention Makkah',
            label: 'Hilton Convention Makkah',
        },
        { value: 'Conrad Jabal Omar', label: 'Conrad Jabal Omar' },
        { value: 'Al Ghufran Safwa Hotel', label: 'Al Ghufran Safwa Hotel' },
    ],
    hotelsMadinah: [
        { value: 'The Oberoi', label: 'The Oberoi' },
        {
            value: 'Intercontinental Dar Al Iman',
            label: 'Intercontinental Dar Al Iman',
        },
        {
            value: 'Sofitel Shahd Al Madinah',
            label: 'Sofitel Shahd Al Madinah',
        },
        { value: 'Madinah Hilton Hotel', label: 'Madinah Hilton Hotel' },
        {
            value: 'Dar Al Eiman Al Haram Madinah',
            label: 'Dar Al Eiman Al Haram Madinah',
        },
        {
            value: 'Dar Al Taqwa Madinah',
            label: 'Dar Al Taqwa Madinah',
        },
    ],
    mealOptions: [
        { value: 'Breakfast Only', label: 'Breakfast Only' },
        { value: 'Half Board', label: 'Half Board (Breakfast & Dinner)' },
        { value: 'Full Board', label: 'Full Board (3 Meals)' },
    ],
    nightsMakkah: [
        { value: '4', label: '4' },
        { value: '5', label: '5' },
    ],
    nightsMadinah: [
        { value: '3', label: '3' },
        { value: '4', label: '4' },
        { value: '5', label: '5' },
    ],
    landTransfer: [
        { value: 'Sedan (2 Pax)', label: 'Sedan (2 Pax)' },
        { value: 'Starex (4 Pax)', label: 'Starex (4 Pax)' },
        { value: 'GMC (4 Pax)', label: 'GMC (4 Pax)' },
        { value: 'Hi-Ace (8 Pax)', label: 'Hi-Ace (8 Pax)' },
        { value: 'Coaster (12 Pax)', label: 'Coaster (12 Pax)' },
    ],
    wheelchair: [
        { value: 'No', label: 'No' },
        { value: 'Yes', label: 'Yes' },
    ],
};

export default function PrivateEnquiryFormFields({
    data,
    setData,
    renderError,
    isView,
    processing,
    options = internalFieldOptions,
}: PrivateEnquiryFormFieldsProps) {
    const airlines = options.airlines;
    const flightClasses = options.flightClasses;
    const hotelsMakkah = options.hotelsMakkah;
    const hotelsMadinah = options.hotelsMadinah;
    const mealOptions = options.mealOptions;
    const nightsMakkah = options.nightsMakkah;
    const nightsMadinah = options.nightsMadinah;
    const landTransferOpts = options.landTransfer;
    const wheelchairOpts = options.wheelchair;
    const disabled = isView || processing;

    return (
        <div className="space-y-6">
            {/* Personal Information Section */}
            <div className="space-y-4">
                <h3 className="text-xl font-semibold">Personal Information</h3>

                <div className="grid grid-cols-1 items-start gap-6 md:grid-cols-2">
                    {/* Full Name */}
                    <div className="grid w-full items-center gap-3">
                        <Label htmlFor="name">
                            Full Name (As Per Passport)
                            <FieldRequirements
                                required
                                hint="Enter full name as per passport"
                            />
                        </Label>
                        <div className="relative">
                            <ProperInput
                                id="name"
                                value={data.name ?? ''}
                                disabled={disabled}
                                onCommit={(v) => setData('name', v)}
                                placeholder="Enter full name as per passport"
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
                                disabled={disabled}
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
                                disabled={disabled}
                                onCommit={(v) => setData('email', v)}
                                placeholder="email@example.com"
                            />
                            {renderError('email')}
                        </div>
                    </div>

                    {/* Passport Expiry Date */}
                    <div className="grid w-full items-center gap-3">
                        <Label htmlFor="passport_expiry_date">
                            Passport Expiry Date
                            <FieldRequirements
                                required
                                hint="Select passport expiry date"
                            />
                        </Label>
                        <div className="relative">
                            <DatePickerField
                                id="passport_expiry_date"
                                value={data.passport_expiry_date}
                                disabled={disabled}
                                disabledDates={isBeforeToday}
                                onChange={(v) =>
                                    setData('passport_expiry_date', v)
                                }
                            />
                            {renderError('passport_expiry_date')}
                        </div>
                    </div>
                </div>
            </div>

            {/* Travel Details Section */}
            <div className="space-y-4">
                <h3 className="text-xl font-semibold">Travel Details</h3>

                <div className="grid grid-cols-1 items-start gap-6 md:grid-cols-2">
                    {/* Departure Date */}
                    <div className="grid w-full items-center gap-3">
                        <Label htmlFor="departure_date">
                            Departure Date
                            <FieldRequirements
                                required
                                hint="Select departure date"
                            />
                        </Label>
                        <div className="relative">
                            <DatePickerField
                                id="departure_date"
                                value={data.departure_date}
                                disabled={disabled}
                                disabledDates={isBeforeToday}
                                onChange={(v) => setData('departure_date', v)}
                            />
                            {renderError('departure_date')}
                        </div>
                    </div>

                    {/* Return Date */}
                    <div className="grid w-full items-center gap-3">
                        <Label htmlFor="return_date">
                            Return Date
                            <FieldRequirements
                                required
                                hint="Select return date"
                            />
                        </Label>
                        <div className="relative">
                            <DatePickerField
                                id="return_date"
                                value={data.return_date}
                                disabled={disabled}
                                disabledDates={isBeforeToday}
                                onChange={(v) => setData('return_date', v)}
                            />
                            {renderError('return_date')}
                        </div>
                    </div>

                    {/* Number of Pax */}
                    <div className="grid w-full items-center gap-3">
                        <Label htmlFor="no_of_pax">
                            Number of Pax
                            <FieldRequirements
                                required
                                hint="Select number of passengers"
                            />
                        </Label>
                        <div className="relative">
                            <Select
                                value={String(data.no_of_pax ?? '')}
                                onValueChange={(v) =>
                                    setData('no_of_pax', parseInt(v) || 0)
                                }
                                disabled={disabled}
                            >
                                <SelectTrigger id="no_of_pax">
                                    <SelectValue placeholder="Select..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {Array.from(
                                        { length: 16 },
                                        (_, i) => i,
                                    ).map((n) => (
                                        <SelectItem key={n} value={String(n)}>
                                            {n === 0 ? '0' : n}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {renderError('no_of_pax')}
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
                                disabled={disabled}
                            >
                                <SelectTrigger id="no_of_children">
                                    <SelectValue placeholder="Select..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {Array.from(
                                        { length: 16 },
                                        (_, i) => i,
                                    ).map((n) => (
                                        <SelectItem key={n} value={String(n)}>
                                            {n === 0 ? '0' : n}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {renderError('no_of_children')}
                        </div>
                    </div>

                    {/* Preferred Airline */}
                    <div className="grid w-full items-center gap-3">
                        <Label htmlFor="airline">
                            Preferred Airline
                            <FieldRequirements
                                required
                                hint="Choose your preferred airline"
                            />
                        </Label>
                        <div className="relative">
                            <Select
                                value={data.airline}
                                onValueChange={(value) =>
                                    setData('airline', value)
                                }
                                disabled={disabled}
                            >
                                <SelectTrigger id="airline">
                                    <SelectValue placeholder="Select airline" />
                                </SelectTrigger>
                                <SelectContent>
                                    {airlines.map((opt) => (
                                        <SelectItem
                                            key={opt.value}
                                            value={opt.value}
                                        >
                                            {opt.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {renderError('airline')}
                        </div>
                    </div>

                    {/* Flight Class */}
                    <div className="grid w-full items-center gap-3">
                        <Label htmlFor="class">
                            Flight Class
                            <FieldRequirements
                                required
                                hint="Choose flight class"
                            />
                        </Label>
                        <div className="relative">
                            <Select
                                value={data.class}
                                onValueChange={(value) =>
                                    setData('class', value)
                                }
                                disabled={disabled}
                            >
                                <SelectTrigger id="class">
                                    <SelectValue placeholder="Select class" />
                                </SelectTrigger>
                                <SelectContent>
                                    {flightClasses.map((opt) => (
                                        <SelectItem
                                            key={opt.value}
                                            value={opt.value}
                                        >
                                            {opt.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {renderError('class')}
                        </div>
                    </div>
                </div>
            </div>

            {/* Service Requirements Section */}
            <div className="space-y-4">
                <h3 className="text-xl font-semibold">Service Requirements</h3>

                <div className="grid grid-cols-1 items-start gap-6 md:grid-cols-3">
                    <FormField
                        label="Require Mutawif (Guide)"
                        fieldRequirementsProps={{
                            hint: 'Select if you need a guide',
                        }}
                        htmlFor={`require_mutawif`}
                    >
                        <BooleanSelect
                            id="require_mutawif"
                            value={!!data.require_mutawif}
                            onChange={(v) => setData('require_mutawif', v)}
                            disabled={disabled}
                        />
                    </FormField>

                    <FormField
                        label="Require Umrah Course"
                        fieldRequirementsProps={{
                            hint: 'Select if you need an Umrah course',
                        }}
                        htmlFor={`require_umrah_course`}
                    >
                        <BooleanSelect
                            id="require_umrah_course"
                            value={!!data.require_umrah_course}
                            onChange={(v) => setData('require_umrah_course', v)}
                            disabled={disabled}
                        />
                    </FormField>

                    <FormField
                        label="Require Umrah Official"
                        fieldRequirementsProps={{
                            hint: 'Select if you need an Umrah official',
                        }}
                        htmlFor={`require_umrah_official`}
                    >
                        <BooleanSelect
                            id="require_umrah_official"
                            value={!!data.require_umrah_official}
                            onChange={(v) =>
                                setData('require_umrah_official', v)
                            }
                            disabled={disabled}
                        />
                    </FormField>
                </div>
            </div>

            {/* Accommodation Section */}
            <div className="space-y-4">
                <h3 className="text-xl font-semibold">Accommodation Details</h3>

                {/* Makkah or Madinah First */}
                <div className="grid w-full items-center gap-3">
                    <Label htmlFor="makkah_or_madinah_first">
                        Makkah or Madinah First?{' '}
                        <FieldRequirements
                            required
                            hint="Choose which city to visit first"
                        />
                    </Label>
                    <div className="relative">
                        <Select
                            value={data.makkah_or_madinah_first}
                            onValueChange={(value) =>
                                setData('makkah_or_madinah_first', value)
                            }
                            disabled={disabled}
                        >
                            <SelectTrigger id="makkah_or_madinah_first">
                                <SelectValue placeholder="Choose destination order" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="Makkah">
                                    Makkah First
                                </SelectItem>
                                <SelectItem value="Madinah">
                                    Madinah First
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        {renderError('makkah_or_madinah_first')}
                    </div>
                </div>

                <div className="grid grid-cols-1 items-start gap-6 md:grid-cols-2">
                    {/* Makkah Accommodation */}
                    <div className="space-y-3 rounded-lg bg-gray-50 p-4 dark:bg-gray-700">
                        <h4 className="font-medium">Makkah</h4>
                        <div className="grid w-full items-center gap-3">
                            <Label htmlFor="no_of_nights_makkah">
                                Number of Nights{' '}
                                <FieldRequirements
                                    required
                                    hint="Enter number of nights in Makkah"
                                />
                            </Label>
                            <div className="relative">
                                <Select
                                    value={data.no_of_nights_makkah}
                                    onValueChange={(value) =>
                                        setData('no_of_nights_makkah', value)
                                    }
                                    disabled={disabled}
                                >
                                    <SelectTrigger id="no_of_nights_makkah">
                                        <SelectValue placeholder="Select nights" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {nightsMakkah.map((opt) => (
                                            <SelectItem
                                                key={opt.value}
                                                value={opt.value}
                                            >
                                                {opt.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {renderError('no_of_nights_makkah')}
                            </div>
                        </div>
                        <div className="grid w-full items-center gap-3">
                            <Label htmlFor="hotel_makkah">
                                Hotel Preference{' '}
                                <FieldRequirements
                                    required
                                    hint="Select your preferred hotel in Makkah"
                                />
                            </Label>
                            <div className="relative">
                                <Select
                                    value={data.hotel_makkah}
                                    onValueChange={(value) =>
                                        setData('hotel_makkah', value)
                                    }
                                    disabled={disabled}
                                >
                                    <SelectTrigger id="hotel_makkah">
                                        <SelectValue placeholder="Select hotel" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {hotelsMakkah.map((opt) => (
                                            <SelectItem
                                                key={opt.value}
                                                value={opt.value}
                                            >
                                                {opt.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {renderError('hotel_makkah')}
                            </div>
                        </div>
                        <div className="grid w-full items-center gap-3">
                            <Label htmlFor="meals_makkah">
                                Meal Plan{' '}
                                <FieldRequirements
                                    required
                                    hint="Choose your meal preference in Makkah"
                                />
                            </Label>
                            <div className="relative">
                                <Select
                                    value={data.meals_makkah}
                                    onValueChange={(value) =>
                                        setData('meals_makkah', value)
                                    }
                                    disabled={disabled}
                                >
                                    <SelectTrigger id="meals_makkah">
                                        <SelectValue placeholder="Select meal plan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {mealOptions.map((opt) => (
                                            <SelectItem
                                                key={opt.value}
                                                value={opt.value}
                                            >
                                                {opt.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {renderError('meals_makkah')}
                            </div>
                        </div>
                    </div>

                    {/* Madinah Accommodation */}
                    <div className="space-y-3 rounded-lg bg-gray-50 p-4 dark:bg-gray-700">
                        <h4 className="font-medium">Madinah</h4>
                        <div className="grid w-full items-center gap-3">
                            <Label htmlFor="no_of_nights_madinah">
                                Number of Nights{' '}
                                <FieldRequirements
                                    required
                                    hint="Enter number of nights in Madinah"
                                />
                            </Label>
                            <div className="relative">
                                <Select
                                    value={data.no_of_nights_madinah}
                                    onValueChange={(value) =>
                                        setData('no_of_nights_madinah', value)
                                    }
                                    disabled={disabled}
                                >
                                    <SelectTrigger id="no_of_nights_madinah">
                                        <SelectValue placeholder="Select nights" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {nightsMadinah.map((opt) => (
                                            <SelectItem
                                                key={opt.value}
                                                value={opt.value}
                                            >
                                                {opt.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {renderError('no_of_nights_madinah')}
                            </div>
                        </div>
                        <div className="grid w-full items-center gap-3">
                            <Label htmlFor="hotel_madinah">
                                Hotel Preference{' '}
                                <FieldRequirements
                                    required
                                    hint="Select your preferred hotel in Madinah"
                                />
                            </Label>
                            <div className="relative">
                                <Select
                                    value={data.hotel_madinah}
                                    onValueChange={(value) =>
                                        setData('hotel_madinah', value)
                                    }
                                    disabled={disabled}
                                >
                                    <SelectTrigger id="hotel_madinah">
                                        <SelectValue placeholder="Select hotel" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {hotelsMadinah.map((opt) => (
                                            <SelectItem
                                                key={opt.value}
                                                value={opt.value}
                                            >
                                                {opt.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {renderError('hotel_madinah')}
                            </div>
                        </div>
                        <div className="grid w-full items-center gap-3">
                            <Label htmlFor="meals_madinah">
                                Meal Plan{' '}
                                <FieldRequirements
                                    required
                                    hint="Choose your meal preference in Madinah"
                                />
                            </Label>
                            <div className="relative">
                                <Select
                                    value={data.meals_madinah}
                                    onValueChange={(value) =>
                                        setData('meals_madinah', value)
                                    }
                                    disabled={disabled}
                                >
                                    <SelectTrigger id="meals_madinah">
                                        <SelectValue placeholder="Select meal plan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {mealOptions.map((opt) => (
                                            <SelectItem
                                                key={opt.value}
                                                value={opt.value}
                                            >
                                                {opt.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {renderError('meals_madinah')}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Transportation Section */}
            <div className="space-y-4">
                <h3 className="text-xl font-semibold">Transportation</h3>

                <div className="grid grid-cols-1 items-start gap-6 md:grid-cols-3">
                    {/* Land Transfer */}
                    <div className="grid w-full items-center gap-3">
                        <Label htmlFor="land_transfer">
                            Land Transfer{' '}
                            <FieldRequirements
                                required
                                hint="Select your preferred transfer type"
                            />
                        </Label>
                        <div className="relative">
                            <Select
                                value={data.land_transfer}
                                onValueChange={(value) =>
                                    setData('land_transfer', value)
                                }
                                disabled={disabled}
                            >
                                <SelectTrigger id="land_transfer">
                                    <SelectValue placeholder="Select transfer type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {landTransferOpts.map((opt) => (
                                        <SelectItem
                                            key={opt.value}
                                            value={opt.value}
                                        >
                                            {opt.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {renderError('land_transfer')}
                        </div>
                    </div>

                    <FormField
                        label="Add-on: High Speed Train (Makkah-Madinah)"
                        fieldRequirementsProps={{
                            hint: 'Add high speed train service between Makkah and Madinah',
                        }}
                        htmlFor={`add_on_speed_train`}
                    >
                        <BooleanSelect
                            id="add_on_speed_train"
                            value={!!data.add_on_speed_train}
                            onChange={(v) => setData('add_on_speed_train', v)}
                            disabled={disabled}
                        />
                    </FormField>

                    <FormField
                        label="Require Meet & Greet Service"
                        fieldRequirementsProps={{
                            hint: 'Get airport meet and greet service',
                        }}
                        htmlFor={`require_meet_greet`}
                    >
                        <BooleanSelect
                            id="require_meet_greet"
                            value={!!data.require_meet_greet}
                            onChange={(v) => setData('require_meet_greet', v)}
                            disabled={disabled}
                        />
                    </FormField>
                </div>
            </div>

            {/* Additional Services Section */}
            <div className="space-y-4">
                <h3 className="text-xl font-semibold">Additional Services</h3>

                <div className="grid grid-cols-1 items-start gap-6 md:grid-cols-3">
                    <FormField
                        label="Require Mutawiffah / Ustazah for Rawdah Visit"
                        fieldRequirementsProps={{
                            hint: 'Request female guide for Rawdah visit in Madinah',
                        }}
                        htmlFor={`require_mutawiffah_ustazah_rawdah`}
                    >
                        <BooleanSelect
                            id="require_mutawiffah_ustazah_rawdah"
                            value={!!data.require_mutawiffah_ustazah_rawdah}
                            onChange={(v) =>
                                setData('require_mutawiffah_ustazah_rawdah', v)
                            }
                            disabled={disabled}
                        />
                    </FormField>

                    <FormField
                        label="Madinah Tour with Mutawif"
                        fieldRequirementsProps={{
                            hint: 'Include guided tour of Madinah',
                        }}
                        htmlFor={`madinah_tour_with_mutawif`}
                    >
                        <BooleanSelect
                            id="madinah_tour_with_mutawif"
                            value={!!data.madinah_tour_with_mutawif}
                            onChange={(v) =>
                                setData('madinah_tour_with_mutawif', v)
                            }
                            disabled={disabled}
                        />
                    </FormField>

                    <FormField
                        label="Makkah Tour with Mutawif"
                        fieldRequirementsProps={{
                            hint: 'Include guided tour of Makkah',
                        }}
                        htmlFor={`makkah_tour_with_mutawif`}
                    >
                        <BooleanSelect
                            id="makkah_tour_with_mutawif"
                            value={!!data.makkah_tour_with_mutawif}
                            onChange={(v) =>
                                setData('makkah_tour_with_mutawif', v)
                            }
                            disabled={disabled}
                        />
                    </FormField>
                </div>
            </div>

            {/* Health Information Section */}
            <div className="space-y-4">
                <h3 className="text-xl font-semibold">Health Information</h3>

                <div className="grid grid-cols-1 items-start gap-6 md:grid-cols-2">
                    {/* Chronic Disease */}
                    <div className="grid w-full items-center gap-3">
                        <Label htmlFor="has_chronic_disease">
                            Any applicant suffer from chronic diseases or
                            sickness?
                            <FieldRequirements hint="Inform us of any chronic diseases or health conditions" />
                        </Label>
                        <div className="relative">
                            <Select
                                value={data.has_chronic_disease ? 'yes' : 'no'}
                                onValueChange={(value) =>
                                    setData(
                                        'has_chronic_disease',
                                        value === 'yes',
                                    )
                                }
                                disabled={disabled}
                            >
                                <SelectTrigger id="has_chronic_disease">
                                    <SelectValue placeholder="Select option" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="no">No</SelectItem>
                                    <SelectItem value="yes">Yes</SelectItem>
                                </SelectContent>
                            </Select>
                            {renderError('has_chronic_disease')}
                        </div>
                    </div>

                    {/* Need Wheelchair */}
                    <div className="grid w-full items-center gap-3">
                        <Label htmlFor="need_wheelchair">
                            Do you need a wheelchair?{' '}
                            <FieldRequirements
                                required
                                hint="Let us know if wheelchair is required"
                            />
                        </Label>
                        <div className="relative">
                            <Select
                                value={data.need_wheelchair}
                                onValueChange={(value) =>
                                    setData('need_wheelchair', value)
                                }
                                disabled={disabled}
                            >
                                <SelectTrigger id="need_wheelchair">
                                    <SelectValue placeholder="Select option" />
                                </SelectTrigger>
                                <SelectContent>
                                    {wheelchairOpts.map((opt) => (
                                        <SelectItem
                                            key={opt.value}
                                            value={opt.value}
                                        >
                                            {opt.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {renderError('need_wheelchair')}
                        </div>
                    </div>
                </div>

                {data.has_chronic_disease && (
                    <div className="space-y-2">
                        <Label htmlFor="chronic_disease_details">
                            Please specify details
                            <FieldRequirements hint="Provide details about the health condition" />
                        </Label>
                        <ProperInput
                            textarea
                            id="chronic_disease_details"
                            value={data.chronic_disease_details || ''}
                            disabled={disabled}
                            onCommit={(v) =>
                                setData('chronic_disease_details', v)
                            }
                            placeholder="Please provide details about the chronic disease or sickness"
                        />
                        {renderError('chronic_disease_details')}
                    </div>
                )}
            </div>

            {/* Other Remarks Section */}
            <div className="grid w-full items-center gap-3">
                <Label htmlFor="other_remarks">
                    Other Remarks
                    <FieldRequirements hint="Add any special requests or additional information" />
                </Label>
                <div className="relative">
                    <ProperInput
                        textarea
                        id="other_remarks"
                        value={data.other_remarks || ''}
                        disabled={disabled}
                        onCommit={(v) => setData('other_remarks', v)}
                        placeholder="Any other special requests or information we should know? (optional)"
                    />
                    {renderError('other_remarks')}
                </div>
            </div>
        </div>
    );
}
