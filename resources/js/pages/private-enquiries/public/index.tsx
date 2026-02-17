import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Head, useForm } from '@inertiajs/react';
import { AlertCircle, CheckCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import PrivateEnquiryFormFields from '../form-fields';
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

    const title = 'Private Enquiry Form';

    const [isSubmitted, setIsSubmitted] = useState(false);

    // Force light mode for public enquiry form
    useEffect(() => {
        const htmlElement = document.documentElement;
        const wasDark = htmlElement.classList.contains('dark');

        // Remove dark class when this page is opened
        if (wasDark) {
            htmlElement.classList.remove('dark');
        }

        // Restore dark class when leaving this page
        return () => {
            if (wasDark) {
                htmlElement.classList.add('dark');
            }
        };
    }, []);

    const defaultData: PrivateEnquirySchema = initialData || {
        name: '',
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
        <div className="flex min-h-screen items-center justify-center bg-orange-50 p-4 dark:bg-gray-600">
            <Head title={title} />

            <Card className="w-full gap-0 border-0 shadow-md md:max-w-[90%]">
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
                        <div className="border-t-1 pt-6">
                            <PrivateEnquiryFormFields
                                data={data}
                                setData={setData}
                                renderError={renderError}
                                isView={isView}
                                processing={processing}
                            />
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
