import { DatePickerField } from '@/components/date-picker';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { isBeforeToday } from '@/lib/utils';
import { useForm } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';
import { generalEnquiryValidationSchema } from './schema';


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

        const url = '/general-enquiries';

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

    const renderError = (fieldName: keyof GeneralEnquiryFormSchema) => {
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
                                <h3 className="font-semibold text-red-900">
                                    Please fix the following errors:
                                </h3>
                                <ul className="mt-2 space-y-1 text-sm text-red-800">
                                    {Object.entries(errors).map(
                                        ([key, message]) => (
                                            <li key={key}>
                                                • {message}
                                            </li>
                                        ),
                                    )}
                                </ul>
                            </div>
                        </div>
                    </div>
                )}

                <Card>
                    <CardContent className="space-y-6 px-6 py-6">
                        {/* Full Name */}
                        <div className="grid gap-2">
                            <Label htmlFor="full_name">Full Name *</Label>
                            <Input
                                id="full_name"
                                type="text"
                                value={data.full_name}
                                disabled={isView || processing}
                                onChange={(e) =>
                                    setData('full_name', e.target.value)
                                }
                                placeholder="Enter full name"
                            />
                            {renderError('full_name')}
                        </div>

                        {/* Mobile & Email Row */}
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            {/* Mobile */}
                            <div className="grid gap-2">
                                <Label htmlFor="mobile">Mobile *</Label>
                                <Input
                                    id="mobile"
                                    type="tel"
                                    value={data.mobile}
                                    disabled={isView || processing}
                                    onChange={(e) =>
                                        setData('mobile', e.target.value)
                                    }
                                    placeholder="Enter mobile number"
                                />
                                {renderError('mobile')}
                            </div>

                            {/* Email */}
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email *</Label>
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

                        {/* Preferred Destinations */}
                        <div className="grid gap-2">
                            <Label htmlFor="preferred_destinations">
                                Preferred Destinations *
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
                                placeholder="Enter preferred destinations (e.g., Paris, London, Amsterdam)"
                                rows={3}
                            />
                            {renderError('preferred_destinations')}
                        </div>

                        {/* Preferred Travelling Date */}
                        <div className="grid gap-2">
                            <Label htmlFor="preferred_travelling_date">
                                Preferred Travelling Date *
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
                            {renderError('preferred_travelling_date')}
                        </div>

                        {/* Adults & Children Row */}
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            {/* No of Adults */}
                            <div className="grid gap-2">
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
                                />
                                {renderError('no_of_adults')}
                            </div>

                            {/* No of Children */}
                            <div className="grid gap-2">
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
                                />
                                {renderError('no_of_children')}
                            </div>
                        </div>

                        {/* Mobility Assistance */}
                        <div className="grid gap-2">
                            <Label htmlFor="requires_mobility_assistance">
                                Mobility Assistance Requirements
                            </Label>
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
                                placeholder="Enter any mobility assistance requirements (optional)"
                                rows={2}
                            />
                            {renderError('requires_mobility_assistance')}
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
