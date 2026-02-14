import { DatePickerField } from '@/components/date-picker';
import { FieldRequirements } from '@/components/field-requirements';
import { ProperInput } from '@/components/proper-input';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useForm } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';
import { PrivateEnquirySchema } from './schema';
import { privateEnquiryValidationSchema } from './validation';

interface PrivateEnquiryFormProps {
    mode: 'create' | 'edit' | 'view';
    initialData?: PrivateEnquirySchema;
    onCancel?: () => void;
}

export default function PrivateEnquiryForm({
    mode,
    initialData,
    onCancel,
}: PrivateEnquiryFormProps) {
    const isView = mode === 'view';
    const isEdit = mode === 'edit';
    const isCreate = mode === 'create';

    const defaultData: PrivateEnquirySchema = initialData || {
        full_name: '',
        contact_number: '',
        email: '',
        passport_expiry_date: '',
        departure_date: '',
        return_date: '',
        no_of_pax: 1,
        no_of_children: 0,
        airline: '',
        class: '',
        require_mutawif: false,
        require_umrah_course: false,
        require_umrah_official: false,
        makkah_or_madinah_first: '',
        no_of_nights_makkah: '',
        hotel_makkah: '',
        meals_makkah: '',
        no_of_nights_madinah: '',
        hotel_madinah: '',
        meals_madinah: '',
        land_transfer: '',
        add_on_speed_train: false,
        require_meet_greet: false,
        require_mutawiffah_ustazah_rawdah: false,
        madinah_tour_with_mutawif: false,
        makkah_tour_with_mutawif: false,
        has_chronic_disease: false,
        chronic_disease_details: '',
        need_wheelchair: '',
        other_remarks: '',
    };

    const {
        data,
        setData,
        post,
        put,
        processing,
        errors,
        reset,
        setError,
        clearErrors,
    } = useForm<PrivateEnquirySchema>(defaultData);

    function validateClientSide(): boolean {
        clearErrors();
        let valid = true;

        const result = privateEnquiryValidationSchema.safeParse(data);
        if (!result.success) {
            result.error.issues.forEach((issue) => {
                const key = issue.path.join('.') as keyof PrivateEnquirySchema;
                if (typeof key === 'string') {
                    setError(key, issue.message);
                }
            });
            valid = false;
        }

        return valid;
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();

        if (!validateClientSide()) return;

        const url = '/private-enquiries';

        if (isCreate) {
            post(url, {
                onError: (errors) => setError(errors),
            });
        } else if (isEdit) {
            put(`${url}/${data.id}`, {
                onError: (errors) => setError(errors),
            });
        }
    }

    const renderError = (path: string) => {
        const errorMap = errors as Record<string, string | undefined>;
        const message = errorMap[path];
        if (!message) return null;
        return <p className="mt-1 text-sm text-red-500">{message}</p>;
    };

    const handleReset = () => {
        reset();
    };

    return (
        <div className="mx-auto w-full">
            <form onSubmit={submit} className="space-y-4 py-2">
                {/* Error Summary Banner */}
                {/* Error Alert */}
                {Object.keys(errors).length > 0 && !isView && (
                    <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>
                            Please fix the errors below and try again
                        </AlertDescription>
                    </Alert>
                )}

                <Card>
                    <CardContent className="space-y-6 px-4 py-4">
                        {/* Full Name */}
                        <div className="grid w-full items-center gap-3">
                            <Label htmlFor="full_name">
                                Full Name (As Per Passport)
                                <FieldRequirements
                                    required
                                    hint="Enter full name as per passport"
                                />
                            </Label>
                            <div className="relative">
                                <ProperInput
                                    id="full_name"
                                    value={data.full_name ?? ''}
                                    disabled={isView || processing}
                                    onCommit={(v) => setData('full_name', v)}
                                    placeholder="Enter full name as per passport"
                                />
                                {renderError('full_name')}
                            </div>
                        </div>

                        {/* Contact Number & Email */}
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="contact_number">
                                    Contact Number (Include Country Code)
                                    <FieldRequirements
                                        required
                                        hint="Enter contact number with country code"
                                    />
                                </Label>
                                <div className="relative">
                                    <ProperInput
                                        id="contact_number"
                                        value={data.contact_number ?? ''}
                                        disabled={isView || processing}
                                        onCommit={(v) =>
                                            setData('contact_number', v)
                                        }
                                        placeholder="e.g. +60123456789"
                                    />
                                    {renderError('contact_number')}
                                </div>
                            </div>
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="email">
                                    Email Address
                                    <FieldRequirements
                                        required
                                        hint="Enter email address"
                                        format="test@example.com"
                                    />
                                </Label>
                                <div className="relative">
                                    <ProperInput
                                        id="email"
                                        value={data.email ?? ''}
                                        disabled={isView || processing}
                                        onCommit={(v) => setData('email', v)}
                                        placeholder="Enter email address"
                                    />
                                    {renderError('email')}
                                </div>
                            </div>
                        </div>

                        {/* Passport Expiry Date, Departure, Return */}
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="passport_expiry_date">
                                    Passport - Expiry Date
                                    <FieldRequirements
                                        required
                                        hint="Select passport expiry date"
                                    />
                                </Label>
                                <div className="relative">
                                    <DatePickerField
                                        id="passport_expiry_date"
                                        value={data.passport_expiry_date}
                                        disabled={isView || processing}
                                        onChange={(v) =>
                                            setData('passport_expiry_date', v)
                                        }
                                    />
                                    {renderError('passport_expiry_date')}
                                </div>
                            </div>
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
                                        disabled={isView || processing}
                                        onChange={(v) =>
                                            setData('departure_date', v)
                                        }
                                    />
                                    {renderError('departure_date')}
                                </div>
                            </div>
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
                                        disabled={isView || processing}
                                        onChange={(v) =>
                                            setData('return_date', v)
                                        }
                                    />
                                    {renderError('return_date')}
                                </div>
                            </div>
                        </div>

                        {/* Pax & Children */}
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="no_of_pax">
                                    No. of Pax Travelling
                                    <FieldRequirements
                                        required
                                        hint="Enter number of passengers"
                                    />
                                </Label>
                                <div className="relative">
                                    <ProperInput
                                        id="no_of_pax"
                                        value={data.no_of_pax ?? 0}
                                        type="number"
                                        disabled={isView || processing}
                                        onCommit={(v) =>
                                            setData(
                                                'no_of_pax',
                                                parseInt(v) || 1,
                                            )
                                        }
                                        inputProps={{ min: '1' }}
                                    />
                                    {renderError('no_of_pax')}
                                </div>
                            </div>
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="no_of_children">
                                    No. of Children Travelling
                                    <FieldRequirements hint="Enter number of children" />
                                </Label>
                                <div className="relative">
                                    <ProperInput
                                        id="no_of_children"
                                        value={data.no_of_children ?? 0}
                                        type="number"
                                        disabled={isView || processing}
                                        onCommit={(v) =>
                                            setData(
                                                'no_of_children',
                                                parseInt(v) || 0,
                                            )
                                        }
                                        inputProps={{ min: '0' }}
                                    />
                                    {renderError('no_of_children')}
                                </div>
                            </div>
                        </div>

                        {/* Airlines & Class */}
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="airline">
                                    Select Airlines
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
                                        disabled={isView || processing}
                                    >
                                        <SelectTrigger id="airline">
                                            <SelectValue placeholder="Select" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="Saudia Airlines">
                                                Saudia Airlines
                                            </SelectItem>
                                            <SelectItem value="Emirates">
                                                Emirates
                                            </SelectItem>
                                            <SelectItem value="Qatar Airways">
                                                Qatar Airways
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {renderError('airline')}
                                </div>
                            </div>
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="class">
                                    Select Class
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
                                        disabled={isView || processing}
                                    >
                                        <SelectTrigger id="class">
                                            <SelectValue placeholder="Select" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="Business">
                                                Business
                                            </SelectItem>
                                            <SelectItem value="Economy">
                                                Economy
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {renderError('class')}
                                </div>
                            </div>
                        </div>

                        {/* Boolean Switches */}
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="require_mutawif">
                                    Require a Mutawif?
                                    <FieldRequirements hint="Select if you need a guide" />
                                </Label>
                                <div className="relative">
                                    <Select
                                        value={
                                            data.require_mutawif ? 'yes' : 'no'
                                        }
                                        onValueChange={(value) =>
                                            setData(
                                                'require_mutawif',
                                                value === 'yes',
                                            )
                                        }
                                        disabled={isView || processing}
                                    >
                                        <SelectTrigger id="require_mutawif">
                                            <SelectValue placeholder="Select" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="yes">
                                                Yes
                                            </SelectItem>
                                            <SelectItem value="no">
                                                No
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="require_umrah_course">
                                    Need Umrah Course?
                                    <FieldRequirements hint="Select if you need an Umrah course" />
                                </Label>
                                <div className="relative">
                                    <Select
                                        value={
                                            data.require_umrah_course
                                                ? 'yes'
                                                : 'no'
                                        }
                                        onValueChange={(value) =>
                                            setData(
                                                'require_umrah_course',
                                                value === 'yes',
                                            )
                                        }
                                        disabled={isView || processing}
                                    >
                                        <SelectTrigger id="require_umrah_course">
                                            <SelectValue placeholder="Select" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="yes">
                                                Yes
                                            </SelectItem>
                                            <SelectItem value="no">
                                                No
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="require_umrah_official">
                                    Need Umrah Official (15+ Pax)?
                                    <FieldRequirements hint="Select if you need an Umrah official" />
                                </Label>
                                <div className="relative">
                                    <Select
                                        value={
                                            data.require_umrah_official
                                                ? 'yes'
                                                : 'no'
                                        }
                                        onValueChange={(value) =>
                                            setData(
                                                'require_umrah_official',
                                                value === 'yes',
                                            )
                                        }
                                        disabled={isView || processing}
                                    >
                                        <SelectTrigger id="require_umrah_official">
                                            <SelectValue placeholder="Select" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="yes">
                                                Yes
                                            </SelectItem>
                                            <SelectItem value="no">
                                                No
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </div>

                        {/* Makkah or Madinah First */}
                        <div className="grid w-full items-center gap-3">
                            <Label htmlFor="makkah_or_madinah_first">
                                Makkah or Madinah First?
                                <FieldRequirements
                                    required
                                    hint="Choose which city to visit first"
                                />
                            </Label>
                            <div className="relative">
                                <Select
                                    value={data.makkah_or_madinah_first}
                                    onValueChange={(value) =>
                                        setData(
                                            'makkah_or_madinah_first',
                                            value,
                                        )
                                    }
                                    disabled={isView || processing}
                                >
                                    <SelectTrigger id="makkah_or_madinah_first">
                                        <SelectValue placeholder="Select" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="Makkah">
                                            Makkah
                                        </SelectItem>
                                        <SelectItem value="Madinah">
                                            Madinah
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                {renderError('makkah_or_madinah_first')}
                            </div>
                        </div>

                        {/* Makkah: Nights & Hotel */}
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="no_of_nights_makkah">
                                    No. of Nights in Makkah
                                    <FieldRequirements
                                        required
                                        hint="Select number of nights"
                                    />
                                </Label>
                                <div className="relative">
                                    <Select
                                        value={data.no_of_nights_makkah}
                                        onValueChange={(value) =>
                                            setData(
                                                'no_of_nights_makkah',
                                                value,
                                            )
                                        }
                                        disabled={isView || processing}
                                    >
                                        <SelectTrigger id="no_of_nights_makkah">
                                            <SelectValue placeholder="Select" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="4">4</SelectItem>
                                            <SelectItem value="5">5</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {renderError('no_of_nights_makkah')}
                                </div>
                            </div>
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="hotel_makkah">
                                    Select Hotel in Makkah
                                    <FieldRequirements
                                        required
                                        hint="Choose your preferred hotel"
                                    />
                                </Label>
                                <div className="relative">
                                    <Select
                                        value={data.hotel_makkah}
                                        onValueChange={(value) =>
                                            setData('hotel_makkah', value)
                                        }
                                        disabled={isView || processing}
                                    >
                                        <SelectTrigger id="hotel_makkah">
                                            <SelectValue placeholder="Select" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="Makkah Hotel & Towers">
                                                Makkah Hotel & Towers
                                            </SelectItem>
                                            <SelectItem value="Swissotel Makkah">
                                                Swissotel Makkah
                                            </SelectItem>
                                            <SelectItem value="Hilton Suites Makkah">
                                                Hilton Suites Makkah
                                            </SelectItem>
                                            <SelectItem value="Jumeirah Jabal Omar Makkah">
                                                Jumeirah Jabal Omar Makkah
                                            </SelectItem>
                                            <SelectItem value="Fairmont Makkah Clock Royal Tower Hotel">
                                                Fairmont Makkah Clock Royal
                                                Tower Hotel
                                            </SelectItem>
                                            <SelectItem value="Address Jabal Omar Makkah">
                                                Address Jabal Omar Makkah
                                            </SelectItem>
                                            <SelectItem value="Swissotel Maqam">
                                                Swissotel Maqam
                                            </SelectItem>
                                            <SelectItem value="Hyatt Jabal Omar">
                                                Hyatt Jabal Omar
                                            </SelectItem>
                                            <SelectItem value="InterContinental Dar Al Tawhid Makkah">
                                                InterContinental Dar Al Tawhid
                                                Makkah
                                            </SelectItem>
                                            <SelectItem value="Hilton Convention Makkah">
                                                Hilton Convention Makkah
                                            </SelectItem>
                                            <SelectItem value="Conrad Jabal Omar">
                                                Conrad Jabal Omar
                                            </SelectItem>
                                            <SelectItem value="Al Ghufran Safwa Hotel">
                                                Al Ghufran Safwa Hotel
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {renderError('hotel_makkah')}
                                </div>
                            </div>
                        </div>

                        {/* Meals in Makkah */}
                        <div className="grid w-full items-center gap-3">
                            <Label htmlFor="meals_makkah">
                                Hotels Meals in Makkah
                                <FieldRequirements
                                    required
                                    hint="Select meal plan preference"
                                />
                            </Label>
                            <div className="relative">
                                <Select
                                    value={data.meals_makkah}
                                    onValueChange={(value) =>
                                        setData('meals_makkah', value)
                                    }
                                    disabled={isView || processing}
                                >
                                    <SelectTrigger id="meals_makkah">
                                        <SelectValue placeholder="Select" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="Breakfast only">
                                            Breakfast only
                                        </SelectItem>
                                        <SelectItem value="Breakfast & Dinner">
                                            Breakfast & Dinner
                                        </SelectItem>
                                        <SelectItem value="Breakfast, Lunch & Dinner">
                                            Breakfast, Lunch & Dinner
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                {renderError('meals_makkah')}
                            </div>
                        </div>

                        {/* Madinah: Nights & Hotel */}
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="no_of_nights_madinah">
                                    No. of Nights in Madinah
                                    <FieldRequirements
                                        required
                                        hint="Select number of nights"
                                    />
                                </Label>
                                <div className="relative">
                                    <Select
                                        value={data.no_of_nights_madinah}
                                        onValueChange={(value) =>
                                            setData(
                                                'no_of_nights_madinah',
                                                value,
                                            )
                                        }
                                        disabled={isView || processing}
                                    >
                                        <SelectTrigger id="no_of_nights_madinah">
                                            <SelectValue placeholder="Select" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="3">3</SelectItem>
                                            <SelectItem value="4">4</SelectItem>
                                            <SelectItem value="5">5</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {renderError('no_of_nights_madinah')}
                                </div>
                            </div>
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="hotel_madinah">
                                    Select Hotel in Madinah
                                    <FieldRequirements
                                        required
                                        hint="Choose your preferred hotel"
                                    />
                                </Label>
                                <div className="relative">
                                    <Select
                                        value={data.hotel_madinah}
                                        onValueChange={(value) =>
                                            setData('hotel_madinah', value)
                                        }
                                        disabled={isView || processing}
                                    >
                                        <SelectTrigger id="hotel_madinah">
                                            <SelectValue placeholder="Select" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="The Oberoi">
                                                The Oberoi
                                            </SelectItem>
                                            <SelectItem value="Intercontinental Dar Al Iman">
                                                Intercontinental Dar Al Iman
                                            </SelectItem>
                                            <SelectItem value="Sofitel Shahd Al Madinah">
                                                Sofitel Shahd Al Madinah
                                            </SelectItem>
                                            <SelectItem value="Madinah Hilton Hotel">
                                                Madinah Hilton Hotel
                                            </SelectItem>
                                            <SelectItem value="Dar Al Eiman Al Haram Madinah">
                                                Dar Al Eiman Al Haram Madinah
                                            </SelectItem>
                                            <SelectItem value="Dar Al Taqwa Madinah">
                                                Dar Al Taqwa Madinah
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {renderError('hotel_madinah')}
                                </div>
                            </div>
                        </div>

                        {/* Meals in Madinah */}
                        <div className="grid w-full items-center gap-3">
                            <Label htmlFor="meals_madinah">
                                Hotels Meals in Madinah
                                <FieldRequirements
                                    required
                                    hint="Select meal plan preference"
                                />
                            </Label>
                            <div className="relative">
                                <Select
                                    value={data.meals_madinah}
                                    onValueChange={(value) =>
                                        setData('meals_madinah', value)
                                    }
                                    disabled={isView || processing}
                                >
                                    <SelectTrigger id="meals_madinah">
                                        <SelectValue placeholder="Select" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="Breakfast only">
                                            Breakfast only
                                        </SelectItem>
                                        <SelectItem value="Breakfast & Dinner">
                                            Breakfast & Dinner
                                        </SelectItem>
                                        <SelectItem value="Breakfast, Lunch & Dinner">
                                            Breakfast, Lunch & Dinner
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                {renderError('meals_madinah')}
                            </div>
                        </div>

                        {/* Land Transfer */}
                        <div className="grid w-full items-center gap-3">
                            <Label htmlFor="land_transfer">
                                Select Land Transfer
                                <FieldRequirements
                                    required
                                    hint="Choose land transfer option"
                                />
                            </Label>
                            <div className="relative">
                                <Select
                                    value={data.land_transfer}
                                    onValueChange={(value) =>
                                        setData('land_transfer', value)
                                    }
                                    disabled={isView || processing}
                                >
                                    <SelectTrigger id="land_transfer">
                                        <SelectValue placeholder="Select" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="Sedan (2 Pax)">
                                            Sedan (2 Pax)
                                        </SelectItem>
                                        <SelectItem value="Stare (4 Pax)">
                                            Stare (4 Pax)
                                        </SelectItem>
                                        <SelectItem value="GMC (4 Pax)">
                                            GMC (4 Pax)
                                        </SelectItem>
                                        <SelectItem value="Hi-Ace (8 Pax)">
                                            Hi-Ace (8 Pax)
                                        </SelectItem>
                                        <SelectItem value="Coaster (12 Pax)">
                                            Coaster (12 Pax)
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                {renderError('land_transfer')}
                            </div>
                        </div>

                        {/* Boolean Add-ons */}
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="add_on_speed_train">
                                    Add on Speed Train?
                                    <FieldRequirements hint="Select if you want to add high-speed train" />
                                </Label>
                                <div className="relative">
                                    <Select
                                        value={
                                            data.add_on_speed_train
                                                ? 'yes'
                                                : 'no'
                                        }
                                        onValueChange={(value) =>
                                            setData(
                                                'add_on_speed_train',
                                                value === 'yes',
                                            )
                                        }
                                        disabled={isView || processing}
                                    >
                                        <SelectTrigger id="add_on_speed_train">
                                            <SelectValue placeholder="Select" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="yes">
                                                Yes
                                            </SelectItem>
                                            <SelectItem value="no">
                                                No
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="require_meet_greet">
                                    Require Meet & Greet at Airport?
                                    <FieldRequirements hint="Select if you need meet and greet service" />
                                </Label>
                                <div className="relative">
                                    <Select
                                        value={
                                            data.require_meet_greet
                                                ? 'yes'
                                                : 'no'
                                        }
                                        onValueChange={(value) =>
                                            setData(
                                                'require_meet_greet',
                                                value === 'yes',
                                            )
                                        }
                                        disabled={isView || processing}
                                    >
                                        <SelectTrigger id="require_meet_greet">
                                            <SelectValue placeholder="Select" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="yes">
                                                Yes
                                            </SelectItem>
                                            <SelectItem value="no">
                                                No
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="require_mutawiffah_ustazah_rawdah">
                                    Require Mutawiffah/Ustazah for Rawdah?
                                    <FieldRequirements hint="Select if you need a female guide for Rawdah" />
                                </Label>
                                <div className="relative">
                                    <Select
                                        value={
                                            data.require_mutawiffah_ustazah_rawdah
                                                ? 'yes'
                                                : 'no'
                                        }
                                        onValueChange={(value) =>
                                            setData(
                                                'require_mutawiffah_ustazah_rawdah',
                                                value === 'yes',
                                            )
                                        }
                                        disabled={isView || processing}
                                    >
                                        <SelectTrigger id="require_mutawiffah_ustazah_rawdah">
                                            <SelectValue placeholder="Select" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="yes">
                                                Yes
                                            </SelectItem>
                                            <SelectItem value="no">
                                                No
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </div>

                        {/* Tours */}
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="madinah_tour_with_mutawif">
                                    Madinah Tour with Mutawif?
                                    <FieldRequirements hint="Select if you want a guided tour in Madinah" />
                                </Label>
                                <div className="relative">
                                    <Select
                                        value={
                                            data.madinah_tour_with_mutawif
                                                ? 'yes'
                                                : 'no'
                                        }
                                        onValueChange={(value) =>
                                            setData(
                                                'madinah_tour_with_mutawif',
                                                value === 'yes',
                                            )
                                        }
                                        disabled={isView || processing}
                                    >
                                        <SelectTrigger id="madinah_tour_with_mutawif">
                                            <SelectValue placeholder="Select" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="yes">
                                                Yes
                                            </SelectItem>
                                            <SelectItem value="no">
                                                No
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="makkah_tour_with_mutawif">
                                    Makkah Tour with Mutawif?
                                    <FieldRequirements hint="Select if you want a guided tour in Makkah" />
                                </Label>
                                <div className="relative">
                                    <Select
                                        value={
                                            data.makkah_tour_with_mutawif
                                                ? 'yes'
                                                : 'no'
                                        }
                                        onValueChange={(value) =>
                                            setData(
                                                'makkah_tour_with_mutawif',
                                                value === 'yes',
                                            )
                                        }
                                        disabled={isView || processing}
                                    >
                                        <SelectTrigger id="makkah_tour_with_mutawif">
                                            <SelectValue placeholder="Select" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="yes">
                                                Yes
                                            </SelectItem>
                                            <SelectItem value="no">
                                                No
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </div>

                        {/* Chronic Disease */}
                        <div className="grid w-full items-center gap-3">
                            <Label htmlFor="has_chronic_disease">
                                Any applicant suffer from chronic diseases or
                                sickness?
                                <FieldRequirements hint="Select if any traveler has a chronic disease" />
                            </Label>
                            <div className="relative">
                                <Select
                                    value={
                                        data.has_chronic_disease ? 'yes' : 'no'
                                    }
                                    onValueChange={(value) =>
                                        setData(
                                            'has_chronic_disease',
                                            value === 'yes',
                                        )
                                    }
                                    disabled={isView || processing}
                                >
                                    <SelectTrigger id="has_chronic_disease">
                                        <SelectValue placeholder="Select" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="no">No</SelectItem>
                                        <SelectItem value="yes">
                                            Yes (Please indicate below)
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                {renderError('has_chronic_disease')}
                            </div>
                        </div>
                        {data.has_chronic_disease && (
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="chronic_disease_details">
                                    Chronic Disease Details
                                    <FieldRequirements hint="Provide details about the chronic disease" />
                                </Label>
                                <div className="relative">
                                    <ProperInput
                                        id="chronic_disease_details"
                                        value={
                                            data.chronic_disease_details ?? ''
                                        }
                                        disabled={isView || processing}
                                        textarea
                                        onCommit={(v) =>
                                            setData(
                                                'chronic_disease_details',
                                                v,
                                            )
                                        }
                                        placeholder="Please specify details"
                                    />
                                    {renderError('chronic_disease_details')}
                                </div>
                            </div>
                        )}

                        {/* Wheelchair */}
                        <div className="grid w-full items-center gap-3">
                            <Label htmlFor="need_wheelchair">
                                Do you need a wheelchair?
                                <FieldRequirements
                                    required
                                    hint="Select wheelchair requirement"
                                />
                            </Label>
                            <div className="relative">
                                <Select
                                    value={data.need_wheelchair}
                                    onValueChange={(value) =>
                                        setData('need_wheelchair', value)
                                    }
                                    disabled={isView || processing}
                                >
                                    <SelectTrigger id="need_wheelchair">
                                        <SelectValue placeholder="Select" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="No">No</SelectItem>
                                        <SelectItem value="Yes">Yes</SelectItem>
                                        <SelectItem value="Other">
                                            Other
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                {renderError('need_wheelchair')}
                            </div>
                        </div>

                        {/* Other Remarks */}
                        <div className="grid w-full items-center gap-3">
                            <Label htmlFor="other_remarks">
                                Other Remarks
                                <FieldRequirements hint="Enter any additional remarks (optional)" />
                            </Label>
                            <div className="relative">
                                <ProperInput
                                    id="other_remarks"
                                    value={data.other_remarks ?? ''}
                                    disabled={isView || processing}
                                    textarea
                                    onCommit={(v) =>
                                        setData('other_remarks', v)
                                    }
                                    placeholder="Enter any other remarks (optional)"
                                />
                                {renderError('other_remarks')}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Action Buttons */}
                <div className="flex justify-end gap-4">
                    {onCancel && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onCancel}
                            disabled={processing}
                        >
                            Back
                        </Button>
                    )}
                    {!isView && (
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={handleReset}
                                disabled={processing}
                            >
                                Reset
                            </Button>
                            <Button
                                type="submit"
                                className="min-w-[140px]"
                                disabled={processing}
                            >
                                {isEdit ? 'Update' : 'Create'}
                            </Button>
                        </>
                    )}
                </div>
            </form>
        </div>
    );
}
