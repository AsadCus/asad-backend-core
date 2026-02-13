import { DatePickerField } from '@/components/date-picker';
import { FieldRequirements } from '@/components/field-requirements';
import { ProperInput } from '@/components/proper-input';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { isBeforeToday } from '@/lib/utils';
import { useForm } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';
import { GeneralEnquirySchema } from './schema';
import { generalEnquiryValidationSchema } from './validation';

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
    const isCreate = mode === 'create';

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
        return <p className="mt-1 text-xs text-red-500">{message}</p>;
    };

    const handleReset = () => {
        reset();
    };

    return (
        <div className="mx-auto w-full">
            <form onSubmit={submit} className="space-y-4">
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
                                    onCommit={(v) => setData('full_name', v)}
                                    placeholder="Enter full name"
                                />
                                {renderError('full_name')}
                            </div>
                        </div>

                        {/* Mobile & Email Row */}
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            {/* Mobile */}
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="mobile">
                                    Mobile
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
                                        placeholder="Enter mobile number"
                                    />
                                    {renderError('mobile')}
                                </div>
                            </div>

                            {/* Email */}
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="email">
                                    Email
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
                                    value={data.preferred_destinations ?? ''}
                                    disabled={isView || processing}
                                    textarea
                                    onCommit={(v) =>
                                        setData('preferred_destinations', v)
                                    }
                                    placeholder="Enter preferred destinations (e.g., Paris, London, Amsterdam)"
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
                                        setData('preferred_travelling_date', v)
                                    }
                                />
                                {renderError('preferred_travelling_date')}
                            </div>
                        </div>

                        {/* Adults & Children Row */}
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            {/* No of Adults */}
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="no_of_adults">
                                    Number of Adults
                                    <FieldRequirements hint="Enter number of adults traveling" />
                                </Label>
                                <div className="relative">
                                    <ProperInput
                                        id="no_of_adults"
                                        value={data.no_of_adults ?? 0}
                                        type="number"
                                        disabled={isView || processing}
                                        onCommit={(v) =>
                                            setData(
                                                'no_of_adults',
                                                parseInt(v) || 0,
                                            )
                                        }
                                        inputProps={{ min: '0' }}
                                    />
                                    {renderError('no_of_adults')}
                                </div>
                            </div>

                            {/* No of Children */}
                            <div className="grid w-full items-center gap-3">
                                <Label htmlFor="no_of_children">
                                    Number of Children
                                    <FieldRequirements hint="Enter number of children traveling" />
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

                        {/* Mobility Assistance */}
                        <div className="grid w-full items-center gap-3">
                            <Label htmlFor="requires_mobility_assistance">
                                Mobility Assistance Requirements
                                <FieldRequirements hint="Enter any special mobility needs (optional)" />
                            </Label>
                            <div className="relative">
                                <ProperInput
                                    id="requires_mobility_assistance"
                                    value={
                                        data.requires_mobility_assistance ?? ''
                                    }
                                    disabled={isView || processing}
                                    textarea
                                    onCommit={(v) =>
                                        setData(
                                            'requires_mobility_assistance',
                                            v || null,
                                        )
                                    }
                                    placeholder="Enter any mobility assistance requirements (optional)"
                                />
                                {renderError('requires_mobility_assistance')}
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
