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
import { isBeforeToday } from '@/lib/utils';
import { useForm } from '@inertiajs/react';
import { AlertCircle, CheckCircle } from 'lucide-react';
import { useState } from 'react';
import { GeneralEnquirySchema } from '../schema';
import { generalEnquiryValidationSchema } from '../validation';

interface GeneralEnquiryFormProps {
    mode: 'create' | 'edit' | 'view';
    initialData?: GeneralEnquirySchema;
    onCancel?: () => void;
}

export default function GeneralEnquiryForm({
    mode,
    initialData,
    onCancel,
}: GeneralEnquiryFormProps) {
    const isView = mode === 'view';
    const isEdit = mode === 'edit';
    // const isCreate = mode === 'create';
    const [isSubmitted, setIsSubmitted] = useState(false);

    const defaultData: GeneralEnquirySchema = initialData || {
        full_name: '',
        mobile: '',
        email: '',
        preferred_destinations: '',
        preferred_travelling_date: '',
        no_of_adults: 0,
        no_of_children: 0,
        requires_mobility_assistance: null,
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
    } = useForm<GeneralEnquirySchema>(defaultData);

    function validateClientSide(): boolean {
        clearErrors();
        let valid = true;

        const result = generalEnquiryValidationSchema.safeParse(data);
        if (!result.success) {
            result.error.issues.forEach((issue) => {
                const key = issue.path.join('.') as keyof GeneralEnquirySchema;
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

        const url = '/general-enquiries/public/store';

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
                        Enquiry Form
                    </CardTitle>
                    <CardDescription className="mt-2 text-base">
                        Thank you for your interest. Kindly fill in the details
                        below so we can assist you with your enquiry.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <form onSubmit={submit} className="space-y-6">
                        {/* Success Alert */}
                        {isSubmitted && (
                            <Alert className="border-green-600 bg-green-50 shadow-sm">
                                <CheckCircle className="h-5 w-5 text-green-600" />
                                <AlertDescription className="font-medium text-green-900">
                                    Success! Your enquiry has been submitted. We
                                    will contact you soon.
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
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="full_name">
                                    Full Name
                                    <FieldRequirements
                                        required
                                        hint="Enter your full name"
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
                                        placeholder="John Doe"
                                    />
                                    {renderError('full_name')}
                                </div>
                            </div>

                            {/* Mobile */}
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="mobile">
                                    Mobile Number
                                    <FieldRequirements
                                        required
                                        hint="Enter mobile number with country code"
                                    />
                                </Label>
                                <div className="relative">
                                    <ProperInput
                                        id="mobile"
                                        value={data.mobile ?? ''}
                                        disabled={isView || processing}
                                        onCommit={(v) => setData('mobile', v)}
                                        placeholder="+1 234 567 8900"
                                    />
                                    {renderError('mobile')}
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
                                        onCommit={(v) => setData('email', v)}
                                        placeholder="john@example.com"
                                    />
                                    {renderError('email')}
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
                                        value={
                                            data.preferred_destinations ?? ''
                                        }
                                        disabled={isView || processing}
                                        textarea
                                        onCommit={(v) =>
                                            setData('preferred_destinations', v)
                                        }
                                        placeholder="e.g., Paris, London, Amsterdam"
                                    />
                                    {renderError('preferred_destinations')}
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
                                            setData(
                                                'preferred_travelling_date',
                                                v,
                                            )
                                        }
                                    />
                                    {renderError('preferred_travelling_date')}
                                </div>
                            </div>

                            {/* Number of Adults */}
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="no_of_adults">
                                    Number of Adults
                                    <FieldRequirements hint="Enter number of adults traveling" />
                                </Label>
                                <div className="relative">
                                    <ProperInput
                                        id="no_of_adults"
                                        type="number"
                                        value={data.no_of_adults ?? 0}
                                        disabled={isView || processing}
                                        onCommit={(v) =>
                                            setData(
                                                'no_of_adults',
                                                parseInt(v) || 0,
                                            )
                                        }
                                        placeholder="0"
                                        inputProps={{ min: '0' }}
                                    />
                                    {renderError('no_of_adults')}
                                </div>
                            </div>

                            {/* Number of Children */}
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="no_of_children">
                                    Number of Children
                                    <FieldRequirements hint="Enter number of children traveling" />
                                </Label>
                                <div className="relative">
                                    <ProperInput
                                        id="no_of_children"
                                        type="number"
                                        value={data.no_of_children ?? 0}
                                        disabled={isView || processing}
                                        onCommit={(v) =>
                                            setData(
                                                'no_of_children',
                                                parseInt(v) || 0,
                                            )
                                        }
                                        placeholder="0"
                                        inputProps={{ min: '0' }}
                                    />
                                    {renderError('no_of_children')}
                                </div>
                            </div>

                            {/* Mobility Assistance */}
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="requires_mobility_assistance">
                                    Mobility Assistance Requirements
                                    <FieldRequirements hint="Let us know if you have any special mobility needs (optional)" />
                                </Label>
                                <div className="relative">
                                    <ProperInput
                                        id="requires_mobility_assistance"
                                        value={
                                            data.requires_mobility_assistance ||
                                            ''
                                        }
                                        disabled={isView || processing}
                                        textarea
                                        onCommit={(v) =>
                                            setData(
                                                'requires_mobility_assistance',
                                                v || null,
                                            )
                                        }
                                        placeholder="Tell us about your requirements (optional)"
                                    />
                                    {renderError(
                                        'requires_mobility_assistance',
                                    )}
                                </div>
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
