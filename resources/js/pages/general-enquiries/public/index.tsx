import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Head, useForm } from '@inertiajs/react';
import { AlertCircle, CheckCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import GeneralEnquiryFormFields from '../form-fields';
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

    const title = 'General Enquiry Form';

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

    const defaultData: GeneralEnquirySchema = initialData || {
        name: '',
        contact_number: '',
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
        <div className="flex min-h-screen items-center justify-center bg-orange-50 p-4 dark:bg-gray-600">
            <Head title={title} />

            <Card className="w-full gap-0 border-0 shadow-md md:max-w-[90%]">
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
                    <Dialog
                        open={isSubmitted}
                        onOpenChange={(open) => setIsSubmitted(open)}
                    >
                        <DialogContent className="sm:max-w-md">
                            <DialogHeader>
                                <DialogTitle className="flex items-center gap-2 text-green-700">
                                    <CheckCircle className="h-5 w-5" />
                                    Submission Successful
                                </DialogTitle>
                                <DialogDescription className="text-base text-green-700">
                                    Your enquiry has been submitted. We will
                                    contact you soon.
                                </DialogDescription>
                            </DialogHeader>
                            <div className="flex justify-end">
                                <Button
                                    type="button"
                                    onClick={() => setIsSubmitted(false)}
                                >
                                    Close
                                </Button>
                            </div>
                        </DialogContent>
                    </Dialog>

                    <form onSubmit={submit} className="space-y-6">
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
                            <GeneralEnquiryFormFields
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
