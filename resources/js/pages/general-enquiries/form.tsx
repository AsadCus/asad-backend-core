import { FormField } from '@/components/form-field';
import { ProperInputSelect } from '@/components/proper-input-select';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { OptionType } from '@/types';
import { useForm } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';
import GeneralEnquiryFormFields from './form-fields';
import { GeneralEnquirySchema } from './schema';
import { generalEnquiryValidationSchema } from './validation';

export type GeneralEnquiryFormSchema = GeneralEnquirySchema;

interface GeneralEnquiryFormProps {
    mode: 'create' | 'edit' | 'view';
    initialData?: GeneralEnquirySchema;
    packageOptions?: OptionType[];
    onCancel?: () => void;
}

export default function GeneralEnquiryForm({
    mode,
    initialData,
    packageOptions = [],
    onCancel,
}: GeneralEnquiryFormProps) {
    const isView = mode === 'view';
    const isEdit = mode === 'edit';
    const isCreate = mode === 'create';

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
        put,
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

        const url = '/general-enquiries';

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
                    <CardHeader>
                        <CardTitle className="text-xl">
                            {isView
                                ? 'View General Enquiry'
                                : isEdit
                                  ? 'Edit General Enquiry'
                                  : 'Create General Enquiry'}
                        </CardTitle>
                        <CardDescription>
                            {isView
                                ? 'Details of the general enquiry.'
                                : isEdit
                                  ? 'Modify the details of the general enquiry and submit to save changes.'
                                  : 'Fill in the details of the general enquiry and submit.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {/* Package Select (internal only, not for public forms) */}
                        {packageOptions.length > 0 && (
                            <div className="mb-6">
                                <FormField
                                    label="Package"
                                    fieldRequirementsProps={{
                                        hint: 'Link a travel package to this enquiry (optional)',
                                    }}
                                    htmlFor="package_id"
                                    error={
                                        renderError('package_id')?.props
                                            ?.children
                                    }
                                >
                                    <ProperInputSelect
                                        options={packageOptions}
                                        value={
                                            data.package_id
                                                ? String(data.package_id)
                                                : ''
                                        }
                                        onValueChange={(v) =>
                                            setData(
                                                'package_id',
                                                v ? Number(v) : null,
                                            )
                                        }
                                        placeholder="Select package..."
                                        disabled={isView || processing}
                                        truncate={30}
                                    />
                                </FormField>
                            </div>
                        )}

                        <GeneralEnquiryFormFields
                            data={data}
                            setData={setData}
                            renderError={renderError}
                            isView={isView}
                            processing={processing}
                        />
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
