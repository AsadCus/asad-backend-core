import { DatePickerField } from '@/components/date-picker';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useForm } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';
import { privateEnquiryValidationSchema } from './schema';



export interface PrivateEnquiryFormSchema {
    id?: number;
    full_name: string;
    contact_number: string;
    email: string;
    passport_expiry_date: string;
    departure_date: string;
    return_date: string;
    no_of_pax: number;
    no_of_children: number;
    airline: string;
    class: string;
    require_mutawif: boolean;
    require_umrah_course: boolean;
    require_umrah_official: boolean;
    makkah_or_madinah_first: string;
    no_of_nights_makkah: string;
    hotel_makkah: string;
    meals_makkah: string;
    no_of_nights_madinah: string;
    hotel_madinah: string;
    meals_madinah: string;
    land_transfer: string;
    add_on_speed_train: boolean;
    require_meet_greet: boolean;
    require_mutawiffah_ustazah_rawdah: boolean;
    madinah_tour_with_mutawif: boolean;
    makkah_tour_with_mutawif: boolean;
    has_chronic_disease: boolean;
    chronic_disease_details?: string | null;
    need_wheelchair: string;
    other_remarks?: string | null;
}


interface PrivateEnquiryFormProps {
    mode: 'create' | 'edit' | 'view';
    initialData?: PrivateEnquiryFormSchema;
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

    const defaultData: PrivateEnquiryFormSchema = initialData || {
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

    const { data, setData, post, put, processing, errors, reset } =
        useForm<PrivateEnquiryFormSchema>(defaultData);

    function validateClientSide(): boolean {
        const result = privateEnquiryValidationSchema.safeParse(data);
        if (!result.success) {
            const validationErrors = result.error.flatten().fieldErrors;
            // Clear previous errors
            Object.keys(errors).forEach((key) => {
                errors[key as keyof PrivateEnquiryFormSchema] = undefined;
            });
            // Set new errors
            Object.entries(validationErrors).forEach(([key, messages]) => {
                if (Array.isArray(messages) && messages.length > 0) {
                    errors[key as keyof PrivateEnquiryFormSchema] = messages[0];
                }
            });
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return false;
        }
        return true;
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();

        if (!validateClientSide()) return;

        const url = '/private-enquiries';

        if (isCreate) {
            post(url, {
                preserveState: true,
                preserveScroll: true,
                onError: () => {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },
            });
        } else if (isEdit) {
            put(`${url}/${data.id}`, {
                preserveState: true,
                preserveScroll: true,
                onError: () => {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },
            });
        }
    }

    const renderError = (fieldName: keyof PrivateEnquiryFormSchema) => {
        const message = errors[fieldName];
        if (!message) return null;
        return (
            <p className="mt-1 text-xs text-red-500">{message}</p>
        );
    };

    const handleReset = () => {
        reset();
    };

    return (
        <div className="mx-auto w-full">
            <form onSubmit={submit} className="space-y-6">
                {/* Error Summary Banner */}
                {Object.keys(errors).length > 0 && (
                    <div className="rounded-lg border border-red-200 bg-red-50 p-4">
                        <div className="flex items-start gap-3">
                            <AlertCircle className="mt-0.5 h-5 w-5 flex-shrink-0 text-red-600" />
                            <div className="flex-1">
                                Please check the form for errors and try again.
                            </div>
                        </div>
                    </div>
                )}

                <Card>
                    <CardContent className="space-y-6 px-6 py-6">
                        {/* Full Name */}
                        <div className="grid gap-2">
                            <Label htmlFor="full_name">
                                Full Name (As Per Passport) *
                            </Label>
                            <Input
                                id="full_name"
                                type="text"
                                value={data.full_name}
                                disabled={isView || processing}
                                onChange={(e) =>
                                    setData('full_name', e.target.value)
                                }
                                placeholder="Enter full name as per passport"
                            />
                            {renderError('full_name')}
                        </div>

                        {/* Contact Number & Email */}
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="contact_number">
                                    Contact Number (Include Country Code) *
                                </Label>
                                <Input
                                    id="contact_number"
                                    type="text"
                                    value={data.contact_number}
                                    disabled={isView || processing}
                                    onChange={(e) =>
                                        setData(
                                            'contact_number',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g. +60123456789"
                                />
                                {renderError('contact_number')}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email Address *</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    disabled={isView || processing}
                                    onChange={(e) =>
                                        setData('email', e.target.value)
                                    }
                                    placeholder="Enter email address"
                                />
                                {renderError('email')}
                            </div>
                        </div>

                        {/* Passport Expiry Date, Departure, Return */}
                        <div className="grid grid-cols-1 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="passport_expiry_date">
                                    Passport - Expiry Date *
                                </Label>
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
                            <div className="grid gap-2">
                                <Label htmlFor="departure_date">
                                    Departure Date *
                                </Label>
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
                            <div className="grid gap-2">
                                <Label htmlFor="return_date">
                                    Return Date *
                                </Label>
                                <DatePickerField
                                    id="return_date"
                                    value={data.return_date}
                                    disabled={isView || processing}
                                    onChange={(v) => setData('return_date', v)}
                                />
                                {renderError('return_date')}
                            </div>
                        </div>

                        {/* Pax & Children */}
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="no_of_pax">
                                    No. of Pax Travelling *
                                </Label>
                                <Input
                                    id="no_of_pax"
                                    type="number"
                                    min="1"
                                    value={data.no_of_pax}
                                    disabled={isView || processing}
                                    onChange={(e) =>
                                        setData(
                                            'no_of_pax',
                                            parseInt(e.target.value) || 1,
                                        )
                                    }
                                />
                                {renderError('no_of_pax')}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="no_of_children">
                                    No. of Children Travelling
                                </Label>
                                <Input
                                    id="no_of_children"
                                    type="number"
                                    min="0"
                                    value={data.no_of_children}
                                    disabled={isView || processing}
                                    onChange={(e) =>
                                        setData(
                                            'no_of_children',
                                            parseInt(e.target.value) || 0,
                                        )
                                    }
                                />
                                {renderError('no_of_children')}
                            </div>
                        </div>

                        {/* Airlines & Class */}
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="airline">
                                    Select Airlines *
                                </Label>
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
                            <div className="grid gap-2">
                                <Label htmlFor="class">Select Class *</Label>
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

                        {/* Boolean Switches */}
                        <div className="grid grid-cols-1 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="require_mutawif">
                                    Require a Mutawif?
                                </Label>
                                <Select
                                    value={data.require_mutawif ? 'yes' : 'no'}
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
                                        <SelectItem value="yes">Yes</SelectItem>
                                        <SelectItem value="no">No</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="require_umrah_course">
                                    Need Umrah Course?
                                </Label>
                                <Select
                                    value={
                                        data.require_umrah_course ? 'yes' : 'no'
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
                                        <SelectItem value="yes">Yes</SelectItem>
                                        <SelectItem value="no">No</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="require_umrah_official">
                                    Need Umrah Official (15+ Pax)?
                                </Label>
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
                                        <SelectItem value="yes">Yes</SelectItem>
                                        <SelectItem value="no">No</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        {/* Makkah or Madinah First */}
                        <div className="grid gap-2">
                            <Label htmlFor="makkah_or_madinah_first">
                                Makkah or Madinah First? *
                            </Label>
                            <Select
                                value={data.makkah_or_madinah_first}
                                onValueChange={(value) =>
                                    setData('makkah_or_madinah_first', value)
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

                        {/* No. of Nights in Makkah & Hotel Makkah */}
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="no_of_nights_makkah">
                                    No. of Nights in Makkah *
                                </Label>
                                <Select
                                    value={data.no_of_nights_makkah}
                                    onValueChange={(value) =>
                                        setData('no_of_nights_makkah', value)
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
                            <div className="grid gap-2">
                                <Label htmlFor="hotel_makkah">
                                    Select Hotel in Makkah *
                                </Label>
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
                                            Fairmont Makkah Clock Royal Tower
                                            Hotel
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

                        {/* Meals in Makkah */}
                        <div className="grid gap-2">
                            <Label htmlFor="meals_makkah">
                                Hotels Meals in Makkah *
                            </Label>
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

                        {/* No. of Nights in Madinah & Hotel Madinah */}
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="no_of_nights_madinah">
                                    No. of Nights in Madinah *
                                </Label>
                                <Select
                                    value={data.no_of_nights_madinah}
                                    onValueChange={(value) =>
                                        setData('no_of_nights_madinah', value)
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
                            <div className="grid gap-2">
                                <Label htmlFor="hotel_madinah">
                                    Select Hotel in Madinah *
                                </Label>
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

                        {/* Meals in Madinah */}
                        <div className="grid gap-2">
                            <Label htmlFor="meals_madinah">
                                Hotels Meals in Madinah *
                            </Label>
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

                        {/* Land Transfer */}
                        <div className="grid gap-2">
                            <Label htmlFor="land_transfer">
                                Select Land Transfer *
                            </Label>
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

                        {/* Boolean Add-ons */}
                        <div className="grid grid-cols-1 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="add_on_speed_train">
                                    Add on Speed Train?
                                </Label>
                                <Select
                                    value={
                                        data.add_on_speed_train ? 'yes' : 'no'
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
                                        <SelectItem value="yes">Yes</SelectItem>
                                        <SelectItem value="no">No</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="require_meet_greet">
                                    Require Meet & Greet at Airport?
                                </Label>
                                <Select
                                    value={
                                        data.require_meet_greet ? 'yes' : 'no'
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
                                        <SelectItem value="yes">Yes</SelectItem>
                                        <SelectItem value="no">No</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="require_mutawiffah_ustazah_rawdah">
                                    Require Mutawiffah/Ustazah for Rawdah?
                                </Label>
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
                                        <SelectItem value="yes">Yes</SelectItem>
                                        <SelectItem value="no">No</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        {/* Tours */}
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="madinah_tour_with_mutawif">
                                    Madinah Tour with Mutawif?
                                </Label>
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
                                        <SelectItem value="yes">Yes</SelectItem>
                                        <SelectItem value="no">No</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="makkah_tour_with_mutawif">
                                    Makkah Tour with Mutawif?
                                </Label>
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
                                        <SelectItem value="yes">Yes</SelectItem>
                                        <SelectItem value="no">No</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        {/* Chronic Disease */}
                        <div className="grid gap-2">
                            <Label htmlFor="has_chronic_disease">
                                Any applicant suffer from chronic diseases or
                                sickness?
                            </Label>
                            <Select
                                value={data.has_chronic_disease ? 'yes' : 'no'}
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
                        {data.has_chronic_disease && (
                            <div className="grid gap-2">
                                <Label htmlFor="chronic_disease_details">
                                    Chronic Disease Details
                                </Label>
                                <Textarea
                                    id="chronic_disease_details"
                                    value={data.chronic_disease_details || ''}
                                    disabled={isView || processing}
                                    onChange={(e) =>
                                        setData(
                                            'chronic_disease_details',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Please specify details"
                                    rows={2}
                                />
                                {renderError('chronic_disease_details')}
                            </div>
                        )}

                        {/* Wheelchair */}
                        <div className="grid gap-2">
                            <Label htmlFor="need_wheelchair">
                                Do you need a wheelchair?
                            </Label>
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
                                    <SelectItem value="Other">Other</SelectItem>
                                </SelectContent>
                            </Select>
                            {renderError('need_wheelchair')}
                        </div>

                        {/* Other Remarks */}
                        <div className="grid gap-2">
                            <Label htmlFor="other_remarks">Other Remarks</Label>
                            <Textarea
                                id="other_remarks"
                                value={data.other_remarks || ''}
                                disabled={isView || processing}
                                onChange={(e) =>
                                    setData('other_remarks', e.target.value)
                                }
                                placeholder="Enter any other remarks (optional)"
                                rows={2}
                            />
                            {renderError('other_remarks')}
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
// ...existing code...
