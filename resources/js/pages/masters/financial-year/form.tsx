import { DatePickerField } from '@/components/date-picker';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { financialYearSchema, FinancialYearSchema } from './schema';

interface FinancialYearFormProps {
    mode: 'create' | 'edit' | 'view';
    initialData?: FinancialYearSchema;
    onSubmit?: (values: FinancialYearSchema) => void;
    onCancel?: () => void;
}

export function FinancialYearForm({
    mode,
    initialData,
    onCancel,
}: FinancialYearFormProps) {
    const isView = mode === 'view';
    const isEdit = mode === 'edit';
    const isCreate = mode === 'create';

    const initialFormState: FinancialYearSchema = {
        id: 0,
        year: '',
        start_date: '',
        end_date: '',
        default: true,
    };

    const defaultData = initialData
        ? {
              ...initialData,
          }
        : initialFormState;

    const {
        data,
        setData,
        post,
        put,
        processing,
        errors,
        setError,
        clearErrors,
    } = useForm<FinancialYearSchema>(defaultData);

    useEffect(() => {
        if (data.start_date && data.end_date) {
            const startDate = new Date(data.start_date);
            const endDate = new Date(data.end_date);

            // Calculate which year has the most months
            const startYear = startDate.getFullYear();
            const endYear = endDate.getFullYear();

            if (startYear === endYear) {
                setData('year', String(startYear));
            } else {
                // Count months in each year
                const monthsInStartYear = 12 - startDate.getMonth(); // months remaining in start year
                const monthsInEndYear = endDate.getMonth() + 1; // months in end year (0-indexed, so +1)

                // Determine dominant year
                const dominantYear =
                    monthsInStartYear >= monthsInEndYear ? startYear : endYear;
                setData('year', String(dominantYear));
            }
        }
    }, [setData, data.start_date, data.end_date]);

    function validateClientSide() {
        const result = financialYearSchema.safeParse(data);

        clearErrors();

        if (!result.success) {
            result.error.issues.forEach((issue) => {
                const key = String(issue.path[0]) as keyof FinancialYearSchema;
                setError(key, issue.message);
            });
            return false;
        }

        return true;
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();

        if (!validateClientSide()) return;

        const url = '/master/financial-year';

        if (isCreate) {
            post(url, {
                onError: (errors) => {
                    setError(errors);
                    console.error(errors);
                },
            });
        } else if (isEdit) {
            put(`${url}/${data.id}`, {
                onError: (errors) => {
                    setError(errors);
                    console.error(errors);
                },
            });
        }
    }

    const renderError = (field: keyof FinancialYearSchema) =>
        errors[field] && (
            <p className="absolute -bottom-4 left-0 text-sm text-red-500">
                {errors[field]}
            </p>
        );

    return (
        <div
            className="mx-auto max-h-[90vh] w-full overflow-y-auto p-2"
            style={{
                scrollbarWidth: 'none',
                msOverflowStyle: 'none',
            }}
        >
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-2">
                    {/* Start Date */}
                    <div className="grid w-full items-center gap-3">
                        <Label htmlFor="start_date">
                            Start Date <span className="text-red-500">*</span>
                        </Label>
                        <div className="relative">
                            <DatePickerField
                                id="start_date"
                                value={data.start_date}
                                disabled={isView}
                                onChange={(value) =>
                                    setData('start_date', value)
                                }
                            />
                            {renderError('start_date')}
                        </div>
                    </div>

                    {/* End Date */}
                    <div className="grid w-full items-center gap-3">
                        <Label htmlFor="end_date">
                            End Date
                            <span className="text-red-500">*</span>
                        </Label>
                        <div className="relative">
                            <DatePickerField
                                id="end_date"
                                value={data.end_date}
                                disabled={isView}
                                disabledDates={(date) => {
                                    if (!data.start_date) return false;
                                    return date <= new Date(data.start_date);
                                }}
                                onChange={(value) => setData('end_date', value)}
                            />
                            {renderError('end_date')}
                        </div>
                    </div>
                </div>

                {/* Year Display */}
                <div className="grid w-full items-center gap-3">
                    <Label htmlFor="year">
                        Fiscal Year Label{' '}
                        <span className="text-red-500">*</span>
                    </Label>
                    <div className="relative">
                        <Input
                            id="year"
                            value={data.year}
                            disabled={isView}
                            onChange={(e) => setData('year', e.target.value)}
                            placeholder="Select start and end dates to auto-generate"
                        />
                        {renderError('year')}
                    </div>
                </div>

                {/* Default Checkbox */}
                <div className="flex items-center space-x-2">
                    <Checkbox
                        id="default"
                        checked={data.default ?? false}
                        onCheckedChange={(checked) =>
                            setData('default', Boolean(checked))
                        }
                        disabled={isView}
                    />
                    <Label htmlFor="default" className="cursor-pointer">
                        Set as default fiscal year
                    </Label>
                </div>

                {/* Info message */}
                <div className="rounded-md bg-blue-50 p-4 dark:bg-blue-950">
                    <p className="text-base text-blue-800 dark:text-blue-200">
                        <strong>Note:</strong> You can customize the dates as
                        needed. Only one fiscal year is allowed at a time.
                    </p>
                </div>

                {/* Buttons */}
                <div className="flex justify-end gap-4">
                    {onCancel && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onCancel}
                        >
                            Back
                        </Button>
                    )}
                    {!isView && (
                        <Button
                            type="submit"
                            className="min-w-[140px]"
                            disabled={processing}
                        >
                            {isEdit ? 'Update' : 'Create'}
                        </Button>
                    )}
                </div>
            </form>
        </div>
    );
}
