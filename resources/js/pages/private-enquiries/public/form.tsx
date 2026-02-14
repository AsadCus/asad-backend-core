import { DatePickerField } from '@/components/date-picker';
import { FieldRequirements } from '@/components/field-requirements';
import { ProperInput } from '@/components/proper-input';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { isBeforeToday } from '@/lib/utils';
import { useForm } from '@inertiajs/react';
import { AlertCircle, CheckCircle } from 'lucide-react';
import { useState } from 'react';
import { PrivateEnquirySchema } from '../schema';
import { privateEnquiryValidationSchema } from '../validation';

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
    // const isCreate = mode === 'create';
    const [isSubmitted, setIsSubmitted] = useState(false);

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
        // put,
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

        const url = '/private-enquiries/public/store';

        post(url, {
            preserveScroll: true,
            onSuccess: () => {
                setIsSubmitted(true);
                reset();
                window.scrollTo({ top: 0, behavior: 'smooth' });
                setTimeout(() => setIsSubmitted(false), 5000);
            },
            onError: (errors) => {
                setError(errors);
                window.scrollTo({ top: 0, behavior: 'smooth' });
            },
        });
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
        <div className="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-8">
            <Card className="w-full max-w-4xl border-0 shadow-md">
                <CardHeader className="pb-6">
                    <CardTitle className="text-4xl font-light">
                        Private Umrah Enquiry Form
                    </CardTitle>
                    <CardDescription className="mt-2 text-base">
                        Thank you for your interest in our private Umrah
                        packages. Please fill in the details below so we can
                        prepare a personalized quotation for you.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <form onSubmit={submit} className="space-y-6">
                        {/* Success Alert */}
                        {isSubmitted && (
                            <Alert className="border-green-600 bg-green-50 shadow-sm">
                                <CheckCircle className="h-5 w-5 text-green-600" />
                                <AlertDescription className="font-medium text-green-900">
                                    Success! Your private enquiry has been
                                    submitted. We will contact you soon with a
                                    detailed quotation.
                                </AlertDescription>
                            </Alert>
                        )}

                        {/* Error Alert */}
                        {Object.keys(errors).length > 0 && !isView && (
                            <Alert variant="destructive">
                                <AlertCircle className="h-4 w-4" />
                                <AlertDescription>
                                    Please fix the errors below and try again
                                </AlertDescription>
                            </Alert>
                        )}

                        {/* Form Fields */}
                        <div className="space-y-8">
                            {/* Personal Information Section */}
                            <div className="space-y-5">
                                <h3 className="text-lg font-semibold text-gray-900">
                                    Personal Information
                                </h3>

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
                                            onCommit={(v) =>
                                                setData('full_name', v)
                                            }
                                            placeholder="Enter full name as per passport"
                                        />
                                        {renderError('full_name')}
                                    </div>
                                </div>

                                {/* Contact Number */}
                                <div className="grid w-full items-center gap-3">
                                    <Label htmlFor="contact_number">
                                        Contact Number
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

                                {/* Email */}
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
                                            onCommit={(v) =>
                                                setData('email', v)
                                            }
                                            placeholder="Enter email address"
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
                                            disabled={isView || processing}
                                            disabledDates={isBeforeToday}
                                            onChange={(v) =>
                                                setData(
                                                    'passport_expiry_date',
                                                    v,
                                                )
                                            }
                                        />
                                        {renderError('passport_expiry_date')}
                                    </div>
                                </div>
                            </div>

                            {/* Travel Details Section */}
                            <div className="space-y-5">
                                <h3 className="text-lg font-semibold text-gray-900">
                                    Travel Details
                                </h3>

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
                                            disabled={isView || processing}
                                            disabledDates={isBeforeToday}
                                            onChange={(v) =>
                                                setData('departure_date', v)
                                            }
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
                                            disabled={isView || processing}
                                            disabledDates={isBeforeToday}
                                            onChange={(v) =>
                                                setData('return_date', v)
                                            }
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
                                            hint="Enter number of passengers"
                                        />
                                    </Label>
                                    <div className="relative">
                                        <ProperInput
                                            id="no_of_pax"
                                            type="number"
                                            inputProps={{ min: '1' }}
                                            value={data.no_of_pax ?? 0}
                                            disabled={isView || processing}
                                            onCommit={(v) =>
                                                setData(
                                                    'no_of_pax',
                                                    parseInt(v) || 1,
                                                )
                                            }
                                        />
                                        {renderError('no_of_pax')}
                                    </div>
                                </div>

                                {/* Number of Children */}
                                <div className="grid w-full items-center gap-3">
                                    <Label htmlFor="no_of_children">
                                        Number of Children
                                        <FieldRequirements hint="Enter number of children" />
                                    </Label>
                                    <div className="relative">
                                        <ProperInput
                                            id="no_of_children"
                                            type="number"
                                            inputProps={{ min: '0' }}
                                            value={data.no_of_children ?? 0}
                                            disabled={isView || processing}
                                            onCommit={(v) =>
                                                setData(
                                                    'no_of_children',
                                                    parseInt(v) || 0,
                                                )
                                            }
                                        />
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
                                            disabled={isView || processing}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select airline" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="Malaysia Airlines">
                                                    Malaysia Airlines
                                                </SelectItem>
                                                <SelectItem value="Saudi Airlines">
                                                    Saudi Airlines
                                                </SelectItem>
                                                <SelectItem value="Fly Dubai">
                                                    Fly Dubai
                                                </SelectItem>
                                                <SelectItem value="other">
                                                    Other
                                                </SelectItem>
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
                                            disabled={isView || processing}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select class" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="Economy">
                                                    Economy
                                                </SelectItem>
                                                <SelectItem value="Business">
                                                    Business
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {renderError('class')}
                                    </div>
                                </div>
                            </div>

                            {/* Service Requirements Section */}
                            <div className="space-y-5">
                                <h3 className="text-lg font-semibold text-gray-900">
                                    Service Requirements
                                </h3>

                                <div className="space-y-3">
                                    <div className="grid w-full items-center gap-3">
                                        <Label htmlFor="require_mutawif">
                                            Require Mutawif (Guide)
                                            <FieldRequirements hint="Select if you need a guide" />
                                        </Label>
                                        <div className="relative">
                                            <Select
                                                value={
                                                    data.require_mutawif
                                                        ? 'yes'
                                                        : 'no'
                                                }
                                                onValueChange={(value) =>
                                                    setData(
                                                        'require_mutawif',
                                                        value === 'yes',
                                                    )
                                                }
                                                disabled={isView || processing}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Select option" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="no">
                                                        No
                                                    </SelectItem>
                                                    <SelectItem value="yes">
                                                        Yes
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>
                                    <div className="grid w-full items-center gap-3">
                                        <Label htmlFor="require_umrah_course">
                                            Require Umrah Course
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
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Select option" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="no">
                                                        No
                                                    </SelectItem>
                                                    <SelectItem value="yes">
                                                        Yes
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>
                                    <div className="grid w-full items-center gap-3">
                                        <Label htmlFor="require_umrah_official">
                                            Require Umrah Official
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
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Select option" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="no">
                                                        No
                                                    </SelectItem>
                                                    <SelectItem value="yes">
                                                        Yes
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Accommodation Section */}
                            <div className="space-y-5">
                                <h3 className="text-lg font-semibold text-gray-900">
                                    Accommodation Details
                                </h3>

                                {/* Makkah or Madinah First */}
                                <div className="space-y-2">
                                    <Label htmlFor="makkah_or_madinah_first">
                                        Makkah or Madinah First?{' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
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
                                        <SelectTrigger>
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

                                {/* Makkah Accommodation */}
                                <div className="space-y-3 rounded-lg bg-gray-50 p-4">
                                    <h4 className="font-medium text-gray-900">
                                        Makkah
                                    </h4>
                                    <div className="space-y-2">
                                        <Label htmlFor="no_of_nights_makkah">
                                            Number of Nights{' '}
                                            <span className="text-red-500">
                                                *
                                            </span>
                                        </Label>
                                        <ProperInput
                                            id="no_of_nights_makkah"
                                            value={
                                                data.no_of_nights_makkah ?? ''
                                            }
                                            disabled={isView || processing}
                                            onCommit={(v) =>
                                                setData(
                                                    'no_of_nights_makkah',
                                                    v,
                                                )
                                            }
                                            placeholder="e.g. 5"
                                        />
                                        {renderError('no_of_nights_makkah')}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="hotel_makkah">
                                            Hotel Preference{' '}
                                            <span className="text-red-500">
                                                *
                                            </span>
                                        </Label>
                                        <Select
                                            value={data.hotel_makkah}
                                            onValueChange={(value) =>
                                                setData('hotel_makkah', value)
                                            }
                                            disabled={isView || processing}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select hotel" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="5 Star">
                                                    5 Star
                                                </SelectItem>
                                                <SelectItem value="4 Star">
                                                    4 Star
                                                </SelectItem>
                                                <SelectItem value="3 Star">
                                                    3 Star
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {renderError('hotel_makkah')}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="meals_makkah">
                                            Meal Plan{' '}
                                            <span className="text-red-500">
                                                *
                                            </span>
                                        </Label>
                                        <Select
                                            value={data.meals_makkah}
                                            onValueChange={(value) =>
                                                setData('meals_makkah', value)
                                            }
                                            disabled={isView || processing}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select meal plan" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="Breakfast Only">
                                                    Breakfast Only
                                                </SelectItem>
                                                <SelectItem value="Half Board">
                                                    Half Board (Breakfast &
                                                    Dinner)
                                                </SelectItem>
                                                <SelectItem value="Full Board">
                                                    Full Board (3 Meals)
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {renderError('meals_makkah')}
                                    </div>
                                </div>

                                {/* Madinah Accommodation */}
                                <div className="space-y-3 rounded-lg bg-gray-50 p-4">
                                    <h4 className="font-medium text-gray-900">
                                        Madinah
                                    </h4>
                                    <div className="space-y-2">
                                        <Label htmlFor="no_of_nights_madinah">
                                            Number of Nights{' '}
                                            <span className="text-red-500">
                                                *
                                            </span>
                                        </Label>
                                        <ProperInput
                                            id="no_of_nights_madinah"
                                            value={
                                                data.no_of_nights_madinah ?? ''
                                            }
                                            disabled={isView || processing}
                                            onCommit={(v) =>
                                                setData(
                                                    'no_of_nights_madinah',
                                                    v,
                                                )
                                            }
                                            placeholder="e.g. 5"
                                        />
                                        {renderError('no_of_nights_madinah')}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="hotel_madinah">
                                            Hotel Preference{' '}
                                            <span className="text-red-500">
                                                *
                                            </span>
                                        </Label>
                                        <Select
                                            value={data.hotel_madinah}
                                            onValueChange={(value) =>
                                                setData('hotel_madinah', value)
                                            }
                                            disabled={isView || processing}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select hotel" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="5 Star">
                                                    5 Star
                                                </SelectItem>
                                                <SelectItem value="4 Star">
                                                    4 Star
                                                </SelectItem>
                                                <SelectItem value="3 Star">
                                                    3 Star
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {renderError('hotel_madinah')}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="meals_madinah">
                                            Meal Plan{' '}
                                            <span className="text-red-500">
                                                *
                                            </span>
                                        </Label>
                                        <Select
                                            value={data.meals_madinah}
                                            onValueChange={(value) =>
                                                setData('meals_madinah', value)
                                            }
                                            disabled={isView || processing}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select meal plan" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="Breakfast Only">
                                                    Breakfast Only
                                                </SelectItem>
                                                <SelectItem value="Half Board">
                                                    Half Board (Breakfast &
                                                    Dinner)
                                                </SelectItem>
                                                <SelectItem value="Full Board">
                                                    Full Board (3 Meals)
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {renderError('meals_madinah')}
                                    </div>
                                </div>
                            </div>

                            {/* Transportation Section */}
                            <div className="space-y-5">
                                <h3 className="text-lg font-semibold text-gray-900">
                                    Transportation
                                </h3>

                                <div className="space-y-2">
                                    <Label htmlFor="land_transfer">
                                        Land Transfer{' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
                                    <Select
                                        value={data.land_transfer}
                                        onValueChange={(value) =>
                                            setData('land_transfer', value)
                                        }
                                        disabled={isView || processing}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select transfer type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="Private">
                                                Private Transfer
                                            </SelectItem>
                                            <SelectItem value="Sharing">
                                                Sharing Transfer
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {renderError('land_transfer')}
                                </div>

                                <div className="space-y-3">
                                    <div className="space-y-2">
                                        <Label htmlFor="add_on_speed_train">
                                            Add-on: High Speed Train
                                            (Makkah-Madinah)
                                        </Label>
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
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select option" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="no">
                                                    No
                                                </SelectItem>
                                                <SelectItem value="yes">
                                                    Yes
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="require_meet_greet">
                                            Require Meet & Greet Service
                                        </Label>
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
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select option" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="no">
                                                    No
                                                </SelectItem>
                                                <SelectItem value="yes">
                                                    Yes
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                            </div>

                            {/* Additional Services Section */}
                            <div className="space-y-5">
                                <h3 className="text-lg font-semibold text-gray-900">
                                    Additional Services
                                </h3>

                                <div className="space-y-3">
                                    <div className="space-y-2">
                                        <Label htmlFor="require_mutawiffah_ustazah_rawdah">
                                            Require Mutawiffah / Ustazah for
                                            Rawdah Visit
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
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select option" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="no">
                                                    No
                                                </SelectItem>
                                                <SelectItem value="yes">
                                                    Yes
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="madinah_tour_with_mutawif">
                                            Madinah Tour with Mutawif
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
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select option" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="no">
                                                    No
                                                </SelectItem>
                                                <SelectItem value="yes">
                                                    Yes
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="makkah_tour_with_mutawif">
                                            Makkah Tour with Mutawif
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
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select option" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="no">
                                                    No
                                                </SelectItem>
                                                <SelectItem value="yes">
                                                    Yes
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                            </div>

                            {/* Health Information Section */}
                            <div className="space-y-5">
                                <h3 className="text-lg font-semibold text-gray-900">
                                    Health Information
                                </h3>

                                <div className="space-y-2">
                                    <Label htmlFor="has_chronic_disease">
                                        Any applicant suffer from chronic
                                        diseases or sickness?
                                    </Label>
                                    <Select
                                        value={
                                            data.has_chronic_disease
                                                ? 'yes'
                                                : 'no'
                                        }
                                        onValueChange={(value) =>
                                            setData(
                                                'has_chronic_disease',
                                                value === 'yes',
                                            )
                                        }
                                        disabled={isView || processing}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select option" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="no">
                                                No
                                            </SelectItem>
                                            <SelectItem value="yes">
                                                Yes
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {renderError('has_chronic_disease')}
                                </div>

                                {data.has_chronic_disease && (
                                    <div className="space-y-2">
                                        <Label htmlFor="chronic_disease_details">
                                            Please specify details
                                        </Label>
                                        <ProperInput
                                            textarea
                                            id="chronic_disease_details"
                                            value={
                                                data.chronic_disease_details ||
                                                ''
                                            }
                                            disabled={isView || processing}
                                            onCommit={(v) =>
                                                setData(
                                                    'chronic_disease_details',
                                                    v,
                                                )
                                            }
                                            placeholder="Please provide details about the chronic disease or sickness"
                                        />
                                        {renderError('chronic_disease_details')}
                                    </div>
                                )}

                                <div className="space-y-2">
                                    <Label htmlFor="need_wheelchair">
                                        Do you need a wheelchair?{' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
                                    <Select
                                        value={data.need_wheelchair}
                                        onValueChange={(value) =>
                                            setData('need_wheelchair', value)
                                        }
                                        disabled={isView || processing}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select option" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="No">
                                                No
                                            </SelectItem>
                                            <SelectItem value="Yes">
                                                Yes
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {renderError('need_wheelchair')}
                                </div>
                            </div>

                            {/* Other Remarks Section */}
                            <div className="space-y-2">
                                <Label htmlFor="other_remarks">
                                    Other Remarks
                                </Label>
                                <ProperInput
                                    textarea
                                    id="other_remarks"
                                    value={data.other_remarks || ''}
                                    disabled={isView || processing}
                                    onCommit={(v) =>
                                        setData('other_remarks', v)
                                    }
                                    placeholder="Any other special requests or information we should know? (optional)"
                                />
                                {renderError('other_remarks')}
                            </div>
                        </div>

                        {/* Action Buttons */}
                        <div className="flex items-center justify-between gap-4 border-t border-gray-200 pt-6">
                            <div className="flex gap-3">
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
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={handleReset}
                                        disabled={processing}
                                    >
                                        Clear
                                    </Button>
                                )}
                            </div>
                            {!isView && (
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="min-w-[120px]"
                                >
                                    {processing
                                        ? 'Submitting...'
                                        : isEdit
                                          ? 'Update'
                                          : 'Submit Enquiry'}
                                </Button>
                            )}
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}
