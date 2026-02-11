import { DatePickerField } from '@/components/date-picker';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { isBeforeToday } from '@/lib/utils';
import { useForm } from '@inertiajs/react';
import { AlertCircle, CheckCircle } from 'lucide-react';
import { useState } from 'react';
import { generalEnquiryValidationSchema } from '../schema';


export interface GeneralEnquiryFormSchema {
    id?: number;
    full_name: string;
    mobile: string;
    email: string;
    preferred_destinations: string;
    preferred_travelling_date: string;
    no_of_adults: number;
    no_of_children: number;
    requires_mobility_assistance?: string | null;
}

interface GeneralEnquiryFormProps {
    mode: 'create' | 'edit' | 'view';
    initialData?: GeneralEnquiryFormSchema;
    onCancel?: () => void;
}

export default function GeneralEnquiryForm({
    mode,
    initialData,
    onCancel,
}: GeneralEnquiryFormProps) {
    const isView = mode === 'view';
    const isEdit = mode === 'edit';
    const isCreate = mode === 'create';
    const [isSubmitted, setIsSubmitted] = useState(false);

    const defaultData: GeneralEnquiryFormSchema = initialData || {
        full_name: '',
        mobile: '',
        email: '',
        preferred_destinations: '',
        preferred_travelling_date: '',
        no_of_adults: 0,
        no_of_children: 0,
        requires_mobility_assistance: null,
    };

    const { data, setData, post, put, processing, errors, reset } =
        useForm<GeneralEnquiryFormSchema>(defaultData);

    function validateClientSide(): boolean {
        const result = generalEnquiryValidationSchema.safeParse(data);

        if (!result.success) {
            const validationErrors = result.error.flatten().fieldErrors;
            Object.entries(validationErrors).forEach(([key, messages]) => {
                if (messages && messages.length > 0) {
                    errors[key as keyof GeneralEnquiryFormSchema] = messages[0];
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

        const url = '/general-enquiries/public/store';

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
            <Card className="w-full max-w-2xl border-0 shadow-md">
                <CardHeader className="pb-6">
                    <CardTitle className="text-4xl font-light">
                        Enquiry Form
                    </CardTitle>
                    <CardDescription className="mt-2 text-base">
                        Thank you for your interest. Kindly fill
                        in the details below so we can assist you with your
                        enquiry.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <form onSubmit={submit} className="space-y-6">
                        {/* Success Alert */}
                        {isSubmitted && (
                            <Alert className="border-green-600 bg-green-50 shadow-sm">
                                <CheckCircle className="h-5 w-5 text-green-600" />
                                <AlertDescription className="text-green-900 font-medium">
                                    Success! Your enquiry has been submitted. We will contact you soon.
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
                        <div className="space-y-6">
                            {/* Full Name */}
                            <div className="space-y-1">
                                <Label htmlFor="full_name">
                                    Full Name{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="full_name"
                                    type="text"
                                    value={data.full_name}
                                    disabled={isView || processing}
                                    onChange={(e) =>
                                        setData('full_name', e.target.value)
                                    }
                                    placeholder="John Doe"
                                    className={` ${
                                        errors.full_name
                                            ? 'border-red-500'
                                            : 'border-gray-300'
                                    }`}
                                />
                                {errors.full_name && (
                                    <p className="text-sm text-red-500">
                                        {errors.full_name}
                                    </p>
                                )}
                            </div>

                            {/* Mobile */}
                            <div className="space-y-1">
                                <Label htmlFor="mobile">
                                    Mobile Number{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="mobile"
                                    type="tel"
                                    value={data.mobile}
                                    disabled={isView || processing}
                                    onChange={(e) =>
                                        setData('mobile', e.target.value)
                                    }
                                    placeholder="+1 234 567 8900"
                                    className={` ${
                                        errors.mobile
                                            ? 'border-red-500'
                                            : 'border-gray-300'
                                    }`}
                                />
                                {errors.mobile && (
                                    <p className="text-sm text-red-500">
                                        {errors.mobile}
                                    </p>
                                )}
                            </div>

                            {/* Email */}
                            <div className="space-y-1">
                                <Label htmlFor="email">
                                    Email Address{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    disabled={isView || processing}
                                    onChange={(e) =>
                                        setData('email', e.target.value)
                                    }
                                    placeholder="john@example.com"
                                    className={` ${
                                        errors.email
                                            ? 'border-red-500'
                                            : 'border-gray-300'
                                    }`}
                                />
                                {errors.email && (
                                    <p className="text-sm text-red-500">
                                        {errors.email}
                                    </p>
                                )}
                            </div>

                            {/* Preferred Destinations */}
                            <div className="space-y-1">
                                <Label htmlFor="preferred_destinations">
                                    Preferred Destinations{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <Textarea
                                    id="preferred_destinations"
                                    value={data.preferred_destinations}
                                    disabled={isView || processing}
                                    onChange={(e) =>
                                        setData(
                                            'preferred_destinations',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g., Paris, London, Amsterdam"
                                    rows={2}
                                    className={`resize-none ${
                                        errors.preferred_destinations
                                            ? 'border-red-500'
                                            : 'border-gray-300'
                                    }`}
                                />
                                {errors.preferred_destinations && (
                                    <p className="text-sm text-red-500">
                                        {errors.preferred_destinations}
                                    </p>
                                )}
                            </div>

                            {/* Preferred Travelling Date */}
                            <div className="space-y-1">
                                <Label htmlFor="preferred_travelling_date">
                                    Preferred Travelling Date{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <DatePickerField
                                    id="preferred_travelling_date"
                                    value={data.preferred_travelling_date}
                                    disabled={isView || processing}
                                    disabledDates={isBeforeToday}
                                    onChange={(v) =>
                                        setData('preferred_travelling_date', v)
                                    }
                                />
                                {errors.preferred_travelling_date && (
                                    <p className="text-sm text-red-500">
                                        {errors.preferred_travelling_date}
                                    </p>
                                )}
                            </div>

                            {/* Number of Adults */}
                            <div className="space-y-1">
                                <Label htmlFor="no_of_adults">
                                    Number of Adults
                                </Label>
                                <Input
                                    id="no_of_adults"
                                    type="number"
                                    min="0"
                                    value={data.no_of_adults}
                                    disabled={isView || processing}
                                    onChange={(e) =>
                                        setData(
                                            'no_of_adults',
                                            parseInt(e.target.value) || 0,
                                        )
                                    }
                                    placeholder="0"
                                    className=""
                                />
                                {errors.no_of_adults && (
                                    <p className="text-sm text-red-500">
                                        {errors.no_of_adults}
                                    </p>
                                )}
                            </div>

                            {/* Number of Children */}
                            <div className="space-y-1">
                                <Label htmlFor="no_of_children">
                                    Number of Children
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
                                    placeholder="0"
                                    className=""
                                />
                                {errors.no_of_children && (
                                    <p className="text-sm text-red-500">
                                        {errors.no_of_children}
                                    </p>
                                )}
                            </div>

                            {/* Mobility Assistance */}
                            <div className="space-y-1">
                                <Label htmlFor="requires_mobility_assistance">
                                    Mobility Assistance Requirements
                                </Label>
                                <p className="text-sm text-gray-500">
                                    Let us know if you have any special mobility
                                    requirements
                                </p>
                                <Textarea
                                    id="requires_mobility_assistance"
                                    value={
                                        data.requires_mobility_assistance || ''
                                    }
                                    disabled={isView || processing}
                                    onChange={(e) =>
                                        setData(
                                            'requires_mobility_assistance',
                                            e.target.value || null,
                                        )
                                    }
                                    placeholder="Tell us about your requirements (optional)"
                                    rows={2}
                                    className="resize-none"
                                />
                                {errors.requires_mobility_assistance && (
                                    <p className="text-sm text-red-500">
                                        {errors.requires_mobility_assistance}
                                    </p>
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
                                    {processing
                                        ? 'Submitting...'
                                        : isEdit
                                          ? 'Update'
                                          : 'Submit'}
                                </Button>
                            )}
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}
