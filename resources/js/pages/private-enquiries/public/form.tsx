import { DatePickerField } from '@/components/date-picker';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { isBeforeToday } from '@/lib/utils';
import { useForm } from '@inertiajs/react';
import { AlertCircle, CheckCircle } from 'lucide-react';
import { useState } from 'react';
import { privateEnquiryValidationSchema } from '../schema';


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
    const [isSubmitted, setIsSubmitted] = useState(false);

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
            Object.entries(validationErrors).forEach(([key, messages]) => {
                if (messages && messages.length > 0) {
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

        const url = '/private-enquiries/public/store';

        post(url, {
            preserveScroll: true,
            onSuccess: () => {
                // Show success message and reset form
                setIsSubmitted(true);
                reset();
                window.scrollTo({ top: 0, behavior: 'smooth' });
                // Clear success message after 5 seconds
                setTimeout(() => setIsSubmitted(false), 5000);
            },
            onError: () => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            },
        });
    }

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
                        Thank you for your interest in our private Umrah packages. 
                        Please fill in the details below so we can prepare a personalized quotation for you.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <form onSubmit={submit} className="space-y-6">
                        {/* Success Alert */}
                        {isSubmitted && (
                            <Alert className="border-green-600 bg-green-50 shadow-sm">
                                <CheckCircle className="h-5 w-5 text-green-600" />
                                <AlertDescription className="text-green-900 font-medium">
                                    Success! Your private enquiry has been submitted. We will contact you soon with a detailed quotation.
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
                                <h3 className="text-lg font-semibold text-gray-900">Personal Information</h3>
                                
                                {/* Full Name */}
                                <div className="space-y-2">
                                    <Label htmlFor="full_name">
                                        Full Name (As Per Passport) <span className="text-red-500">*</span>
                                    </Label>
                                    <Input
                                        id="full_name"
                                        type="text"
                                        value={data.full_name}
                                        disabled={isView || processing}
                                        onChange={(e) => setData('full_name', e.target.value)}
                                        placeholder="Enter full name as per passport"
                                        className={errors.full_name ? 'border-red-500' : 'border-gray-300'}
                                    />
                                    {errors.full_name && (
                                        <p className="text-sm text-red-500">{errors.full_name}</p>
                                    )}
                                </div>

                                {/* Contact Number */}
                                <div className="space-y-2">
                                    <Label htmlFor="contact_number">
                                        Contact Number <span className="text-red-500">*</span>
                                    </Label>
                                    <Input
                                        id="contact_number"
                                        type="text"
                                        value={data.contact_number}
                                        disabled={isView || processing}
                                        onChange={(e) => setData('contact_number', e.target.value)}
                                        placeholder="e.g. +60123456789"
                                        className={errors.contact_number ? 'border-red-500' : 'border-gray-300'}
                                    />
                                    {errors.contact_number && (
                                        <p className="text-sm text-red-500">{errors.contact_number}</p>
                                    )}
                                </div>

                                {/* Email */}
                                <div className="space-y-2">
                                    <Label htmlFor="email">
                                        Email Address <span className="text-red-500">*</span>
                                    </Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        disabled={isView || processing}
                                        onChange={(e) => setData('email', e.target.value)}
                                        placeholder="Enter email address"
                                        className={errors.email ? 'border-red-500' : 'border-gray-300'}
                                    />
                                    {errors.email && (
                                        <p className="text-sm text-red-500">{errors.email}</p>
                                    )}
                                </div>

                                {/* Passport Expiry Date */}
                                <div className="space-y-2">
                                    <Label htmlFor="passport_expiry_date">
                                        Passport Expiry Date <span className="text-red-500">*</span>
                                    </Label>
                                    <DatePickerField
                                        id="passport_expiry_date"
                                        value={data.passport_expiry_date}
                                        disabled={isView || processing}
                                        disabledDates={isBeforeToday}
                                        onChange={(v) => setData('passport_expiry_date', v)}
                                    />
                                    {errors.passport_expiry_date && (
                                        <p className="text-sm text-red-500">{errors.passport_expiry_date}</p>
                                    )}
                                </div>
                            </div>

                            {/* Travel Details Section */}
                            <div className="space-y-5">
                                <h3 className="text-lg font-semibold text-gray-900">Travel Details</h3>
                                
                                {/* Departure Date */}
                                <div className="space-y-2">
                                    <Label htmlFor="departure_date">
                                        Departure Date <span className="text-red-500">*</span>
                                    </Label>
                                    <DatePickerField
                                        id="departure_date"
                                        value={data.departure_date}
                                        disabled={isView || processing}
                                        disabledDates={isBeforeToday}
                                        onChange={(v) => setData('departure_date', v)}
                                    />
                                    {errors.departure_date && (
                                        <p className="text-sm text-red-500">{errors.departure_date}</p>
                                    )}
                                </div>

                                {/* Return Date */}
                                <div className="space-y-2">
                                    <Label htmlFor="return_date">
                                        Return Date <span className="text-red-500">*</span>
                                    </Label>
                                    <DatePickerField
                                        id="return_date"
                                        value={data.return_date}
                                        disabled={isView || processing}
                                        disabledDates={isBeforeToday}
                                        onChange={(v) => setData('return_date', v)}
                                    />
                                    {errors.return_date && (
                                        <p className="text-sm text-red-500">{errors.return_date}</p>
                                    )}
                                </div>

                                {/* Number of Pax */}
                                <div className="space-y-2">
                                    <Label htmlFor="no_of_pax">
                                        Number of Pax <span className="text-red-500">*</span>
                                    </Label>
                                    <Input
                                        id="no_of_pax"
                                        type="number"
                                        min="1"
                                        value={data.no_of_pax}
                                        disabled={isView || processing}
                                        onChange={(e) => setData('no_of_pax', parseInt(e.target.value) || 1)}
                                    />
                                    {errors.no_of_pax && (
                                        <p className="text-sm text-red-500">{errors.no_of_pax}</p>
                                    )}
                                </div>

                                {/* Number of Children */}
                                <div className="space-y-2">
                                    <Label htmlFor="no_of_children">
                                        Number of Children
                                    </Label>
                                    <Input
                                        id="no_of_children"
                                        type="number"
                                        min="0"
                                        value={data.no_of_children}
                                        disabled={isView || processing}
                                        onChange={(e) => setData('no_of_children', parseInt(e.target.value) || 0)}
                                    />
                                    {errors.no_of_children && (
                                        <p className="text-sm text-red-500">{errors.no_of_children}</p>
                                    )}
                                </div>

                                {/* Preferred Airline */}
                                <div className="space-y-2">
                                    <Label htmlFor="airline">
                                        Preferred Airline <span className="text-red-500">*</span>
                                    </Label>
                                    <Select
                                        value={data.airline}
                                        onValueChange={(value) => setData('airline', value)}
                                        disabled={isView || processing}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select airline" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="Malaysia Airlines">Malaysia Airlines</SelectItem>
                                            <SelectItem value="Saudi Airlines">Saudi Airlines</SelectItem>
                                            <SelectItem value="Fly Dubai">Fly Dubai</SelectItem>
                                            <SelectItem value="other">Other</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.airline && (
                                        <p className="text-sm text-red-500">{errors.airline}</p>
                                    )}
                                </div>

                                {/* Flight Class */}
                                <div className="space-y-2">
                                    <Label htmlFor="class">
                                        Flight Class <span className="text-red-500">*</span>
                                    </Label>
                                    <Select
                                        value={data.class}
                                        onValueChange={(value) => setData('class', value)}
                                        disabled={isView || processing}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select class" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="Economy">Economy</SelectItem>
                                            <SelectItem value="Business">Business</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.class && (
                                        <p className="text-sm text-red-500">{errors.class}</p>
                                    )}
                                </div>
                            </div>

                            {/* Service Requirements Section */}
                            <div className="space-y-5">
                                <h3 className="text-lg font-semibold text-gray-900">Service Requirements</h3>
                                
                                <div className="space-y-3">
                                    <div className="space-y-2">
                                        <Label htmlFor="require_mutawif">
                                            Require Mutawif (Guide)
                                        </Label>
                                        <Select
                                            value={data.require_mutawif ? 'yes' : 'no'}
                                            onValueChange={(value) => setData('require_mutawif', value === 'yes')}
                                            disabled={isView || processing}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select option" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="no">No</SelectItem>
                                                <SelectItem value="yes">Yes</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="require_umrah_course">
                                            Require Umrah Course
                                        </Label>
                                        <Select
                                            value={data.require_umrah_course ? 'yes' : 'no'}
                                            onValueChange={(value) => setData('require_umrah_course', value === 'yes')}
                                            disabled={isView || processing}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select option" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="no">No</SelectItem>
                                                <SelectItem value="yes">Yes</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="require_umrah_official">
                                            Require Umrah Official
                                        </Label>
                                        <Select
                                            value={data.require_umrah_official ? 'yes' : 'no'}
                                            onValueChange={(value) => setData('require_umrah_official', value === 'yes')}
                                            disabled={isView || processing}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select option" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="no">No</SelectItem>
                                                <SelectItem value="yes">Yes</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                            </div>

                            {/* Accommodation Section */}
                            <div className="space-y-5">
                                <h3 className="text-lg font-semibold text-gray-900">Accommodation Details</h3>
                                
                                {/* Makkah or Madinah First */}
                                <div className="space-y-2">
                                    <Label htmlFor="makkah_or_madinah_first">
                                        Makkah or Madinah First? <span className="text-red-500">*</span>
                                    </Label>
                                    <Select
                                        value={data.makkah_or_madinah_first}
                                        onValueChange={(value) => setData('makkah_or_madinah_first', value)}
                                        disabled={isView || processing}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Choose destination order" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="Makkah">Makkah First</SelectItem>
                                            <SelectItem value="Madinah">Madinah First</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.makkah_or_madinah_first && (
                                        <p className="text-sm text-red-500">{errors.makkah_or_madinah_first}</p>
                                    )}
                                </div>

                                {/* Makkah Accommodation */}
                                <div className="space-y-3 p-4 bg-gray-50 rounded-lg">
                                    <h4 className="font-medium text-gray-900">Makkah</h4>
                                    <div className="space-y-2">
                                        <Label htmlFor="no_of_nights_makkah">
                                            Number of Nights <span className="text-red-500">*</span>
                                        </Label>
                                        <Input
                                            id="no_of_nights_makkah"
                                            type="text"
                                            value={data.no_of_nights_makkah}
                                            disabled={isView || processing}
                                            onChange={(e) => setData('no_of_nights_makkah', e.target.value)}
                                            placeholder="e.g. 5"
                                        />
                                        {errors.no_of_nights_makkah && (
                                            <p className="text-sm text-red-500">{errors.no_of_nights_makkah}</p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="hotel_makkah">
                                            Hotel Preference <span className="text-red-500">*</span>
                                        </Label>
                                        <Select
                                            value={data.hotel_makkah}
                                            onValueChange={(value) => setData('hotel_makkah', value)}
                                            disabled={isView || processing}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select hotel" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="5 Star">5 Star</SelectItem>
                                                <SelectItem value="4 Star">4 Star</SelectItem>
                                                <SelectItem value="3 Star">3 Star</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {errors.hotel_makkah && (
                                            <p className="text-sm text-red-500">{errors.hotel_makkah}</p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="meals_makkah">
                                            Meal Plan <span className="text-red-500">*</span>
                                        </Label>
                                        <Select
                                            value={data.meals_makkah}
                                            onValueChange={(value) => setData('meals_makkah', value)}
                                            disabled={isView || processing}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select meal plan" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="Breakfast Only">Breakfast Only</SelectItem>
                                                <SelectItem value="Half Board">Half Board (Breakfast & Dinner)</SelectItem>
                                                <SelectItem value="Full Board">Full Board (3 Meals)</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {errors.meals_makkah && (
                                            <p className="text-sm text-red-500">{errors.meals_makkah}</p>
                                        )}
                                    </div>
                                </div>

                                {/* Madinah Accommodation */}
                                <div className="space-y-3 p-4 bg-gray-50 rounded-lg">
                                    <h4 className="font-medium text-gray-900">Madinah</h4>
                                    <div className="space-y-2">
                                        <Label htmlFor="no_of_nights_madinah">
                                            Number of Nights <span className="text-red-500">*</span>
                                        </Label>
                                        <Input
                                            id="no_of_nights_madinah"
                                            type="text"
                                            value={data.no_of_nights_madinah}
                                            disabled={isView || processing}
                                            onChange={(e) => setData('no_of_nights_madinah', e.target.value)}
                                            placeholder="e.g. 5"
                                        />
                                        {errors.no_of_nights_madinah && (
                                            <p className="text-sm text-red-500">{errors.no_of_nights_madinah}</p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="hotel_madinah">
                                            Hotel Preference <span className="text-red-500">*</span>
                                        </Label>
                                        <Select
                                            value={data.hotel_madinah}
                                            onValueChange={(value) => setData('hotel_madinah', value)}
                                            disabled={isView || processing}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select hotel" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="5 Star">5 Star</SelectItem>
                                                <SelectItem value="4 Star">4 Star</SelectItem>
                                                <SelectItem value="3 Star">3 Star</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {errors.hotel_madinah && (
                                            <p className="text-sm text-red-500">{errors.hotel_madinah}</p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="meals_madinah">
                                            Meal Plan <span className="text-red-500">*</span>
                                        </Label>
                                        <Select
                                            value={data.meals_madinah}
                                            onValueChange={(value) => setData('meals_madinah', value)}
                                            disabled={isView || processing}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select meal plan" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="Breakfast Only">Breakfast Only</SelectItem>
                                                <SelectItem value="Half Board">Half Board (Breakfast & Dinner)</SelectItem>
                                                <SelectItem value="Full Board">Full Board (3 Meals)</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {errors.meals_madinah && (
                                            <p className="text-sm text-red-500">{errors.meals_madinah}</p>
                                        )}
                                    </div>
                                </div>
                            </div>

                            {/* Transportation Section */}
                            <div className="space-y-5">
                                <h3 className="text-lg font-semibold text-gray-900">Transportation</h3>
                                
                                <div className="space-y-2">
                                    <Label htmlFor="land_transfer">
                                        Land Transfer <span className="text-red-500">*</span>
                                    </Label>
                                    <Select
                                        value={data.land_transfer}
                                        onValueChange={(value) => setData('land_transfer', value)}
                                        disabled={isView || processing}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select transfer type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="Private">Private Transfer</SelectItem>
                                            <SelectItem value="Sharing">Sharing Transfer</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.land_transfer && (
                                        <p className="text-sm text-red-500">{errors.land_transfer}</p>
                                    )}
                                </div>

                                <div className="space-y-3">
                                    <div className="space-y-2">
                                        <Label htmlFor="add_on_speed_train">
                                            Add-on: High Speed Train (Makkah-Madinah)
                                        </Label>
                                        <Select
                                            value={data.add_on_speed_train ? 'yes' : 'no'}
                                            onValueChange={(value) => setData('add_on_speed_train', value === 'yes')}
                                            disabled={isView || processing}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select option" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="no">No</SelectItem>
                                                <SelectItem value="yes">Yes</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="require_meet_greet">
                                            Require Meet & Greet Service
                                        </Label>
                                        <Select
                                            value={data.require_meet_greet ? 'yes' : 'no'}
                                            onValueChange={(value) => setData('require_meet_greet', value === 'yes')}
                                            disabled={isView || processing}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select option" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="no">No</SelectItem>
                                                <SelectItem value="yes">Yes</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                            </div>

                            {/* Additional Services Section */}
                            <div className="space-y-5">
                                <h3 className="text-lg font-semibold text-gray-900">Additional Services</h3>
                                
                                <div className="space-y-3">
                                    <div className="space-y-2">
                                        <Label htmlFor="require_mutawiffah_ustazah_rawdah">
                                            Require Mutawiffah / Ustazah for Rawdah Visit
                                        </Label>
                                        <Select
                                            value={data.require_mutawiffah_ustazah_rawdah ? 'yes' : 'no'}
                                            onValueChange={(value) => setData('require_mutawiffah_ustazah_rawdah', value === 'yes')}
                                            disabled={isView || processing}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select option" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="no">No</SelectItem>
                                                <SelectItem value="yes">Yes</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="madinah_tour_with_mutawif">
                                            Madinah Tour with Mutawif
                                        </Label>
                                        <Select
                                            value={data.madinah_tour_with_mutawif ? 'yes' : 'no'}
                                            onValueChange={(value) => setData('madinah_tour_with_mutawif', value === 'yes')}
                                            disabled={isView || processing}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select option" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="no">No</SelectItem>
                                                <SelectItem value="yes">Yes</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="makkah_tour_with_mutawif">
                                            Makkah Tour with Mutawif
                                        </Label>
                                        <Select
                                            value={data.makkah_tour_with_mutawif ? 'yes' : 'no'}
                                            onValueChange={(value) => setData('makkah_tour_with_mutawif', value === 'yes')}
                                            disabled={isView || processing}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select option" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="no">No</SelectItem>
                                                <SelectItem value="yes">Yes</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                            </div>

                            {/* Health Information Section */}
                            <div className="space-y-5">
                                <h3 className="text-lg font-semibold text-gray-900">Health Information</h3>
                                
                                <div className="space-y-2">
                                    <Label htmlFor="has_chronic_disease">
                                        Any applicant suffer from chronic diseases or sickness?
                                    </Label>
                                    <Select
                                        value={data.has_chronic_disease ? 'yes' : 'no'}
                                        onValueChange={(value) => setData('has_chronic_disease', value === 'yes')}
                                        disabled={isView || processing}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select option" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="no">No</SelectItem>
                                            <SelectItem value="yes">Yes</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.has_chronic_disease && (
                                        <p className="text-sm text-red-500">{errors.has_chronic_disease}</p>
                                    )}
                                </div>

                                {data.has_chronic_disease && (
                                    <div className="space-y-2">
                                        <Label htmlFor="chronic_disease_details">
                                            Please specify details
                                        </Label>
                                        <Textarea
                                            id="chronic_disease_details"
                                            value={data.chronic_disease_details || ''}
                                            disabled={isView || processing}
                                            onChange={(e) => setData('chronic_disease_details', e.target.value)}
                                            placeholder="Please provide details about the chronic disease or sickness"
                                            rows={3}
                                            className="resize-none"
                                        />
                                        {errors.chronic_disease_details && (
                                            <p className="text-sm text-red-500">{errors.chronic_disease_details}</p>
                                        )}
                                    </div>
                                )}

                                <div className="space-y-2">
                                    <Label htmlFor="need_wheelchair">
                                        Do you need a wheelchair? <span className="text-red-500">*</span>
                                    </Label>
                                    <Select
                                        value={data.need_wheelchair}
                                        onValueChange={(value) => setData('need_wheelchair', value)}
                                        disabled={isView || processing}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select option" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="No">No</SelectItem>
                                            <SelectItem value="Yes">Yes</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.need_wheelchair && (
                                        <p className="text-sm text-red-500">{errors.need_wheelchair}</p>
                                    )}
                                </div>
                            </div>

                            {/* Other Remarks Section */}
                            <div className="space-y-2">
                                <Label htmlFor="other_remarks">
                                    Other Remarks
                                </Label>
                                <Textarea
                                    id="other_remarks"
                                    value={data.other_remarks || ''}
                                    disabled={isView || processing}
                                    onChange={(e) => setData('other_remarks', e.target.value)}
                                    placeholder="Any other special requests or information we should know? (optional)"
                                    rows={3}
                                    className="resize-none"
                                />
                                {errors.other_remarks && (
                                    <p className="text-sm text-red-500">{errors.other_remarks}</p>
                                )}
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
                                    {processing ? 'Submitting...' : isEdit ? 'Update' : 'Submit Enquiry'}
                                </Button>
                            )}
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}
