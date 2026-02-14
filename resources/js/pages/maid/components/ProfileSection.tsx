import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ZoomableImage } from '@/components/zoomable-image';
import { formatDateForDisplay, parseDisplayDate } from '@/lib/utils';
import { ValueNumberOptionType } from '@/types';
import { CalendarIcon, X } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { FieldRequirements } from '../../../components/field-requirements';
import { ProperInput } from '../../../components/proper-input';
import { maritalStatus } from '../schema';
import { MaidFormData, SetDataFn } from '../types';
import { FieldError } from './FieldError';

type ProfileSectionProps = {
    data: MaidFormData;
    setData: SetDataFn;
    isView: boolean;
    errors: Partial<Record<keyof MaidFormData, string>>;
    nationalities: ValueNumberOptionType[];
    religions: ValueNumberOptionType[];
    educationLevels: ValueNumberOptionType[];
    validateField?: (fieldName: keyof MaidFormData) => boolean;
    clearErrors?: (...fields: (keyof MaidFormData)[]) => void;
};

export function ProfileSection({
    data,
    setData,
    isView,
    errors,
    nationalities,
    religions,
    educationLevels,
    // validateField,
    clearErrors,
}: ProfileSectionProps) {
    const todayPlaceholder = new Date().toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
    const [calendarOpen, setCalendarOpen] = useState(false);
    const [selectedDate, setSelectedDate] = useState<Date | undefined>(() =>
        parseDisplayDate(data.date_of_birth),
    );
    const [month, setMonth] = useState<Date | undefined>(selectedDate);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);

    // Function to calculate age from date of birth
    const calculateAge = useCallback(
        (dateOfBirth: Date | undefined): string => {
            if (!dateOfBirth) return '';

            const today = new Date();
            const birthDate = new Date(dateOfBirth);

            let years = today.getFullYear() - birthDate.getFullYear();
            let months = today.getMonth() - birthDate.getMonth();
            let days = today.getDate() - birthDate.getDate();

            // Adjust if days are negative
            if (days < 0) {
                months--;
                const lastMonth = new Date(
                    today.getFullYear(),
                    today.getMonth(),
                    0,
                );
                days += lastMonth.getDate();
            }

            // Adjust if months are negative
            if (months < 0) {
                years--;
                months += 12;
            }

            // Return age in format: "25 years 3 months" or just "25 years" if months is 0
            if (months > 0) {
                return `${years} years ${months} months`;
            }
            return `${years} years`;
        },
        [],
    );

    // Handler for blur validation
    // const handleBlur = (fieldName: keyof MaidFormData) => {
    //     if (validateField && !isView) {
    //         validateField(fieldName);
    //     }
    // };

    useEffect(() => {
        const parsed = parseDisplayDate(data.date_of_birth);
        setSelectedDate(parsed);
        setMonth(parsed);

        if (parsed) {
            const calculatedAge = calculateAge(parsed);
            if (calculatedAge !== data.age) {
                setData('age', calculatedAge);
            }
        }
    }, [data.date_of_birth, calculateAge, setData, isView, data.age]);

    useEffect(() => {
        if (typeof data.photo_url === 'string' && data.photo_url) {
            setPreviewUrl(data.photo_url);
        } else if (!data.photo_url) {
            setPreviewUrl(null);
        }
    }, [data.photo_url]);

    useEffect(
        () => () => {
            if (previewUrl && previewUrl.startsWith('blob:')) {
                URL.revokeObjectURL(previewUrl);
            }
        },
        [previewUrl],
    );

    const fields = useMemo(
        () => ({
            name: errors.name,
            date_of_birth: errors.date_of_birth,
            place_of_birth: errors.place_of_birth,
            height: errors.height,
            weight: errors.weight,
            country_id: errors.country_id,
            address: errors.address,
            repatriation_port_airport: errors.repatriation_port_airport,
            contact_number_home_country: errors.contact_number_home_country,
            religion_id: errors.religion_id,
            education_level_id: errors.education_level_id,
            marital_status: errors.marital_status,
            photo_url: errors.photo_url,
        }),
        [errors],
    );

    return (
        <section className="space-y-4">
            <h3 className="text-lg font-semibold">A1. Personal Information</h3>
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_auto]">
                {/* Photo Upload - Top on Mobile, Right on Desktop */}
                <div className="order-1 flex flex-col gap-3 lg:order-2 lg:w-64">
                    <Label
                        htmlFor="photo_url"
                        className="flex items-center gap-1"
                    >
                        Photo Profile
                        <FieldRequirements
                            hint="Upload maid's profile photo (JPG, PNG, or WEBP, max 2MB)"
                            format="JPG, PNG, WEBP"
                        />
                    </Label>
                    <div className="relative flex flex-col items-center gap-3 rounded-lg border-2 border-dashed p-4">
                        {data.photo_url || previewUrl ? (
                            <div className="relative">
                                <ZoomableImage
                                    src={
                                        previewUrl
                                            ? previewUrl
                                            : typeof data.photo_url === 'string'
                                              ? data.photo_url
                                              : ''
                                    }
                                    alt={data.name || 'Maid profile photo'}
                                    thumbnailSize={200}
                                />
                                {!isView && (
                                    <button
                                        type="button"
                                        className="absolute -top-2 -right-2 rounded-full bg-red-500 p-1 text-white hover:bg-red-600"
                                        onClick={() => {
                                            setData('photo_url', '');
                                            setPreviewUrl(null);
                                        }}
                                        aria-label="Remove photo"
                                        title="Remove photo"
                                    >
                                        <X
                                            className="h-4 w-4"
                                            aria-hidden="true"
                                        />
                                    </button>
                                )}
                            </div>
                        ) : (
                            <div className="flex h-48 w-full items-center justify-center text-muted-foreground">
                                No photo uploaded
                            </div>
                        )}
                        {!isView && (
                            <Input
                                id="photo_url"
                                type="file"
                                accept="image/*"
                                name="photo_url"
                                key={previewUrl || 'photo-input'}
                                onChange={(event) => {
                                    const file = event.target.files?.[0];
                                    if (file) {
                                        setData('photo_url', file);
                                        const objectUrl =
                                            URL.createObjectURL(file);
                                        setPreviewUrl(objectUrl);
                                    }
                                }}
                                className="cursor-pointer"
                            />
                        )}
                        <FieldError message={fields.photo_url} />
                    </div>
                </div>

                {/* Form Fields - Bottom on Mobile, Left on Desktop */}
                <div className="order-2 grid grid-cols-1 items-start gap-4 md:grid-cols-3 lg:order-1">
                    <div className="grid w-full items-center gap-3">
                        <Label
                            htmlFor="name"
                            className="flex items-center gap-1"
                        >
                            1. Name
                            <FieldRequirements
                                required
                                hint="Enter full legal name as shown in passport"
                                example="Maria Santos Cruz"
                            />
                        </Label>
                        <div className="relative">
                            <ProperInput
                                id="name"
                                value={data.name}
                                onCommit={(value) => setData('name', value)}
                                placeholder="Name"
                                disabled={isView}
                                type="text"
                            />
                            <FieldError message={fields.name} />
                        </div>
                    </div>

                    <div className="grid w-full items-center gap-3">
                        <Label
                            htmlFor="date_of_birth"
                            className="flex items-center gap-1"
                        >
                            2. Date of Birth
                            <FieldRequirements
                                required
                                hint="Must be at least 21 years old for employment"
                                format="DD/MM/YYYY"
                            />
                        </Label>
                        <div className="relative flex gap-2">
                            <Input
                                id="date_of_birth"
                                value={data.date_of_birth}
                                placeholder={todayPlaceholder}
                                className="pr-10"
                                onChange={(event) => {
                                    const date = parseDisplayDate(
                                        event.target.value,
                                    );
                                    setData(
                                        'date_of_birth',
                                        event.target.value,
                                    );
                                    setSelectedDate(date);
                                    setMonth(date);
                                }}
                                onKeyDown={(event) => {
                                    if (event.key === 'ArrowDown') {
                                        event.preventDefault();
                                        setCalendarOpen(true);
                                    }
                                }}
                                disabled={isView}
                                required
                            />
                            {!isView && (
                                <Popover
                                    open={calendarOpen}
                                    onOpenChange={setCalendarOpen}
                                >
                                    <PopoverTrigger asChild>
                                        <Button
                                            id="date-picker"
                                            variant="ghost"
                                            className="absolute top-1/2 right-2 size-6 -translate-y-1/2"
                                        >
                                            <CalendarIcon className="size-3.5" />
                                            <span className="sr-only">
                                                Select date of birth
                                            </span>
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent
                                        className="w-auto overflow-hidden p-0"
                                        align="end"
                                        alignOffset={-8}
                                        sideOffset={10}
                                    >
                                        <Calendar
                                            mode="single"
                                            selected={selectedDate}
                                            captionLayout="dropdown"
                                            month={month}
                                            onMonthChange={setMonth}
                                            onSelect={(date) => {
                                                setSelectedDate(
                                                    date ?? undefined,
                                                );
                                                setData(
                                                    'date_of_birth',
                                                    formatDateForDisplay(
                                                        date ?? undefined,
                                                    ),
                                                );
                                                setCalendarOpen(false);
                                            }}
                                        />
                                    </PopoverContent>
                                </Popover>
                            )}
                            <FieldError message={fields.date_of_birth} />
                        </div>
                    </div>

                    <div className="grid w-full items-center gap-3">
                        <Label
                            htmlFor="age"
                            className="flex items-center gap-1"
                        >
                            Age
                            <FieldRequirements
                                hint="Automatically calculated from date of birth"
                                format="XX years XX months"
                            />
                        </Label>
                        <div className="relative">
                            <Input
                                type="text"
                                id="age"
                                value={data.age ?? ''}
                                placeholder="Will be calculated from date of birth"
                                disabled
                                className="cursor-not-allowed bg-muted"
                            />
                        </div>
                    </div>

                    <div className="grid w-full items-center gap-3">
                        <Label
                            htmlFor="place_of_birth"
                            className="flex items-center gap-1"
                        >
                            3. Place of Birth
                            <FieldRequirements
                                required
                                hint="City and country of birth"
                                example="Manila, Philippines"
                            />
                        </Label>
                        <div className="relative">
                            <ProperInput
                                id="place_of_birth"
                                value={data.place_of_birth}
                                onCommit={(value) =>
                                    setData('place_of_birth', value)
                                }
                                placeholder="Place of Birth"
                                disabled={isView}
                                type="text"
                            />
                            <FieldError message={fields.place_of_birth} />
                        </div>
                    </div>

                    <div className="grid w-full items-center gap-3">
                        <Label
                            htmlFor="height"
                            className="flex items-center gap-1"
                        >
                            4. Height
                            <FieldRequirements
                                required
                                hint="Height in centimeters"
                                format="XXX"
                                example="165"
                            />
                        </Label>
                        <div className="relative">
                            <ProperInput
                                id="height"
                                value={data.height}
                                onCommit={(value) => setData('height', value)}
                                placeholder="Height (cm)"
                                disabled={isView}
                                type="number"
                            />
                            <span className="absolute top-1/2 right-10 -translate-y-1/2 text-base text-gray-500">
                                cm
                            </span>
                            <FieldError message={fields.height} />
                        </div>
                    </div>

                    <div className="grid w-full items-center gap-3">
                        <Label
                            htmlFor="weight"
                            className="flex items-center gap-1"
                        >
                            Weight
                            <FieldRequirements
                                required
                                hint="Weight in kilograms"
                                format="XX"
                                example="55"
                            />
                        </Label>
                        <div className="relative">
                            <ProperInput
                                id="weight"
                                value={data.weight}
                                onCommit={(value) => setData('weight', value)}
                                placeholder="Weight (kg)"
                                disabled={isView}
                                type="number"
                            />
                            <span className="absolute top-1/2 right-10 -translate-y-1/2 text-base text-gray-500">
                                kg
                            </span>
                            <FieldError message={fields.weight} />
                        </div>
                    </div>

                    <div className="grid w-full items-center gap-3">
                        <Label className="flex items-center gap-1">
                            5. Nationality
                            <FieldRequirements
                                required
                                hint="Select the country of citizenship"
                            />
                        </Label>
                        <div className="relative">
                            <Select
                                disabled={isView}
                                value={String(data.country_id || '')}
                                onValueChange={(value) => {
                                    setData('country_id', value);
                                    if (clearErrors) clearErrors('country_id');
                                }}
                                required
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select Nationality" />
                                </SelectTrigger>
                                <SelectContent>
                                    {nationalities.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={String(option.value)}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <FieldError message={fields.country_id} />
                        </div>
                    </div>

                    <div className="grid w-full items-center gap-3">
                        <Label
                            htmlFor="passport_number"
                            className="flex items-center gap-1"
                        >
                            6. Passport Number
                            <FieldRequirements
                                hint="Official passport identification number"
                                example="AB1234567"
                            />
                        </Label>
                        <div className="relative">
                            <ProperInput
                                id="passport_number"
                                value={data.passport_number ?? ''}
                                onCommit={(value) =>
                                    setData('passport_number', value)
                                }
                                placeholder="Passport Number"
                                disabled={isView}
                                type="text"
                            />
                        </div>
                    </div>

                    <div className="grid w-full items-center gap-3">
                        <Label
                            htmlFor="address"
                            className="flex items-center gap-1"
                        >
                            7. Address
                            <FieldRequirements
                                required
                                hint="Complete home address in home country"
                                example="123 Main St, Barangay Centro, Quezon City"
                            />
                        </Label>
                        <div className="relative">
                            <ProperInput
                                id="address"
                                value={data.address ?? ''}
                                onCommit={(value) => setData('address', value)}
                                placeholder="Enter address"
                                disabled={isView}
                                textarea={true}
                            />
                            <p className="text-right text-sm text-muted-foreground">
                                {(data.address ?? '').length}/200 characters
                            </p>
                            <FieldError message={fields.address} />
                        </div>
                    </div>

                    <div className="grid w-full items-center gap-3">
                        <Label
                            htmlFor="repatriation_port_airport"
                            className="flex items-center gap-1"
                        >
                            8. Name of Port / Airport to be repatriated to
                            <FieldRequirements
                                required
                                hint="Nearest international airport or seaport"
                                example="Ninoy Aquino International Airport (MNL)"
                            />
                        </Label>
                        <div className="relative">
                            <ProperInput
                                id="repatriation_port_airport"
                                value={data.repatriation_port_airport ?? ''}
                                onCommit={(value) =>
                                    setData('repatriation_port_airport', value)
                                }
                                placeholder="Repatriation Port/Airport"
                                disabled={isView}
                                type="text"
                            />
                            <FieldError
                                message={fields.repatriation_port_airport}
                            />
                        </div>
                    </div>

                    <div className="grid w-full items-center gap-3">
                        <Label
                            htmlFor="contact_number_home_country"
                            className="flex items-center gap-1"
                        >
                            9. Contact Number in home country
                            <FieldRequirements
                                hint="Include country code"
                                format="+XX-XXXXXXXXXX"
                                example="+63-9123456789"
                            />
                        </Label>
                        <div className="relative">
                            <ProperInput
                                id="contact_number_home_country"
                                value={data.contact_number_home_country ?? ''}
                                onCommit={(value) =>
                                    setData(
                                        'contact_number_home_country',
                                        value,
                                    )
                                }
                                placeholder="Contact Number"
                                disabled={isView}
                                type="text"
                            />
                            <FieldError
                                message={fields.contact_number_home_country}
                            />
                        </div>
                    </div>

                    <div className="grid w-full items-center gap-3">
                        <Label className="flex items-center gap-1">
                            10. Religion
                            <FieldRequirements
                                required
                                hint="Select religious affiliation"
                            />
                        </Label>
                        <div className="relative">
                            <Select
                                disabled={isView}
                                value={String(data.religion_id || '')}
                                onValueChange={(value) => {
                                    setData('religion_id', value);
                                    if (clearErrors) clearErrors('religion_id');
                                }}
                                required
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select Religion" />
                                </SelectTrigger>
                                <SelectContent>
                                    {religions.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={String(option.value)}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <FieldError message={fields.religion_id} />
                        </div>
                    </div>

                    <div className="grid w-full items-center gap-3">
                        <Label className="flex items-center gap-1">
                            11. Education Level
                            <FieldRequirements
                                required
                                hint="Highest level of education completed"
                            />
                        </Label>
                        <div className="relative">
                            <Select
                                disabled={isView}
                                value={String(data.education_level_id || '')}
                                onValueChange={(value) => {
                                    setData('education_level_id', value);
                                    if (clearErrors)
                                        clearErrors('education_level_id');
                                }}
                                required
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select Education Level" />
                                </SelectTrigger>
                                <SelectContent>
                                    {educationLevels.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={String(option.value)}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <FieldError message={fields.education_level_id} />
                        </div>
                    </div>

                    <div className="grid w-full items-center gap-3">
                        <Label
                            htmlFor="number_of_siblings"
                            className="flex items-center gap-1"
                        >
                            12. Number of Siblings
                        </Label>
                        <div className="relative">
                            <ProperInput
                                id="number_of_siblings"
                                value={data.number_of_siblings ?? ''}
                                onCommit={(value) =>
                                    setData('number_of_siblings', value)
                                }
                                placeholder="Number of Siblings"
                                disabled={isView}
                                type="number"
                            />
                        </div>
                    </div>

                    <div className="grid w-full items-center gap-3">
                        <Label className="flex items-center gap-1">
                            13. Marital Status
                            <FieldRequirements
                                required
                                hint="Current marital status"
                            />
                        </Label>
                        <div className="relative">
                            <Select
                                disabled={isView}
                                value={String(data.marital_status || '')}
                                onValueChange={(value) => {
                                    setData('marital_status', value);
                                    if (clearErrors)
                                        clearErrors('marital_status');
                                }}
                                required
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select Marital Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    {maritalStatus.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <FieldError message={fields.marital_status} />
                        </div>
                    </div>

                    <div className="grid w-full items-center gap-3">
                        <Label
                            htmlFor="number_of_children"
                            className="flex items-center gap-1"
                        >
                            14. Number of Children
                        </Label>
                        <div className="relative">
                            <ProperInput
                                id="number_of_children"
                                value={data.number_of_children ?? ''}
                                onCommit={(value) => {
                                    const numValue = Number(value) || 0;
                                    setData('number_of_children', value);

                                    const ages = (data.children_ages ?? '')
                                        .split(',')
                                        .map((age) => age.trim())
                                        .filter(Boolean);
                                    const newAges = Array.from(
                                        { length: numValue },
                                        (_, index) => ages[index] ?? '',
                                    );
                                    setData(
                                        'children_ages',
                                        newAges.join(', '),
                                    );
                                }}
                                placeholder="Number of Children"
                                disabled={isView}
                                type="number"
                            />
                        </div>
                    </div>

                    {/* Children Age Inputs */}
                    {Number(data.number_of_children ?? 0) > 0 && (
                        <div className="col-span-full">
                            <div className="mt-4 grid grid-cols-1 items-start gap-4 md:grid-cols-3">
                                {Array.from({
                                    length: Number(
                                        data.number_of_children ?? 0,
                                    ),
                                }).map((_, index) => {
                                    const ages = (data.children_ages ?? '')
                                        .split(',')
                                        .map((age) => age.trim());

                                    return (
                                        <div
                                            key={index}
                                            className="grid w-full items-center gap-3"
                                        >
                                            <Label
                                                htmlFor={`child_age_${index}`}
                                            >
                                                Child {index + 1} Age
                                            </Label>
                                            <div className="relative">
                                                <ProperInput
                                                    id={`child_age_${index}`}
                                                    value={ages[index] ?? ''}
                                                    onCommit={(value) => {
                                                        const totalChildren =
                                                            Number(
                                                                data.number_of_children ??
                                                                    0,
                                                            );
                                                        const currentAges = (
                                                            data.children_ages ??
                                                            ''
                                                        )
                                                            .split(',')
                                                            .map((age) =>
                                                                age.trim(),
                                                            );

                                                        // Ensure array has correct length
                                                        while (
                                                            currentAges.length <
                                                            totalChildren
                                                        ) {
                                                            currentAges.push(
                                                                '',
                                                            );
                                                        }

                                                        currentAges[index] =
                                                            value;
                                                        setData(
                                                            'children_ages',
                                                            currentAges
                                                                .slice(
                                                                    0,
                                                                    totalChildren,
                                                                )
                                                                .join(', '),
                                                        );
                                                    }}
                                                    placeholder="Age"
                                                    disabled={isView}
                                                    type="number"
                                                />
                                                <span className="absolute top-1/2 right-4 -translate-y-1/2 text-base text-gray-500">
                                                    years old
                                                </span>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </section>
    );
}
