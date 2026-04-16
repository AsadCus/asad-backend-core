import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useForm } from '@inertiajs/react';
import { useEffect, useMemo } from 'react';
import { financialYearFormSchema, FinancialYearFormSchema } from './schema';

interface FinancialYearFormProps {
    mode: 'create' | 'edit' | 'view';
    initialData?: FinancialYearFormSchema;
    onCancel?: () => void;
}

const dayOptions = Array.from({ length: 31 }, (_, index) => ({
    value: String(index + 1),
    label: String(index + 1),
}));

const monthOptions = [
    { value: '1', label: 'January' },
    { value: '2', label: 'February' },
    { value: '3', label: 'March' },
    { value: '4', label: 'April' },
    { value: '5', label: 'May' },
    { value: '6', label: 'June' },
    { value: '7', label: 'July' },
    { value: '8', label: 'August' },
    { value: '9', label: 'September' },
    { value: '10', label: 'October' },
    { value: '11', label: 'November' },
    { value: '12', label: 'December' },
];

export function FinancialYearForm({
    mode,
    initialData,
    onCancel,
}: FinancialYearFormProps) {
    const isView = mode === 'view';
    const isEdit = mode === 'edit';
    const isCreate = mode === 'create';

    const initialFormState: FinancialYearFormSchema = {
        id: 0,
        year: String(new Date().getFullYear()),
        start_day: '1',
        start_month: '1',
        end_day: '31',
        end_month: '12',
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
    } = useForm<FinancialYearFormSchema>(defaultData);

    const baseYear = useMemo(() => {
        const rawInitialYear = Number(initialData?.year ?? 0);

        if (Number.isFinite(rawInitialYear) && rawInitialYear > 0) {
            return rawInitialYear;
        }

        if (initialData?.start_date) {
            const startDate = new Date(initialData.start_date);

            if (!Number.isNaN(startDate.getTime())) {
                return startDate.getFullYear();
            }
        }

        return new Date().getFullYear();
    }, [initialData?.start_date, initialData?.year]);

    useEffect(() => {
        const startMonth = Number(data.start_month);
        const startDay = Number(data.start_day);
        const endMonth = Number(data.end_month);
        const endDay = Number(data.end_day);

        if (
            !Number.isFinite(startMonth) ||
            !Number.isFinite(startDay) ||
            !Number.isFinite(endMonth) ||
            !Number.isFinite(endDay)
        ) {
            setData('year', '');

            return;
        }

        const resolvedEndYear =
            endMonth < startMonth ||
            (endMonth === startMonth && endDay < startDay)
                ? baseYear + 1
                : baseYear;

        const monthsInStartYear = 12 - startMonth + 1;
        const monthsInEndYear = endMonth;
        const dominantYear =
            baseYear === resolvedEndYear
                ? baseYear
                : monthsInStartYear >= monthsInEndYear
                  ? baseYear
                  : resolvedEndYear;

        setData('year', String(dominantYear));
    }, [
        baseYear,
        data.end_day,
        data.end_month,
        data.start_day,
        data.start_month,
        setData,
    ]);

    function validateClientSide() {
        const result = financialYearFormSchema.safeParse(data);

        clearErrors();

        if (!result.success) {
            result.error.issues.forEach((issue) => {
                const key = String(
                    issue.path[0],
                ) as keyof FinancialYearFormSchema;
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

    const renderError = (field: keyof FinancialYearFormSchema) =>
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
                    <div className="grid w-full items-center gap-3">
                        <Label htmlFor="start_day">
                            Start Day <span className="text-red-500">*</span>
                        </Label>
                        <div className="relative">
                            <Select
                                value={data.start_day}
                                disabled={isView}
                                onValueChange={(value) =>
                                    setData('start_day', value)
                                }
                            >
                                <SelectTrigger id="start_day">
                                    <SelectValue placeholder="Select day" />
                                </SelectTrigger>
                                <SelectContent>
                                    {dayOptions.map((option) => (
                                        <SelectItem
                                            key={`start-day-${option.value}`}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {renderError('start_day')}
                        </div>
                    </div>

                    <div className="grid w-full items-center gap-3">
                        <Label htmlFor="start_month">
                            Start Month <span className="text-red-500">*</span>
                        </Label>
                        <div className="relative">
                            <Select
                                value={data.start_month}
                                disabled={isView}
                                onValueChange={(value) =>
                                    setData('start_month', value)
                                }
                            >
                                <SelectTrigger id="start_month">
                                    <SelectValue placeholder="Select month" />
                                </SelectTrigger>
                                <SelectContent>
                                    {monthOptions.map((option) => (
                                        <SelectItem
                                            key={`start-month-${option.value}`}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {renderError('start_month')}
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-2">
                    <div className="grid w-full items-center gap-3">
                        <Label htmlFor="end_day">
                            End Day <span className="text-red-500">*</span>
                        </Label>
                        <div className="relative">
                            <Select
                                value={data.end_day}
                                disabled={isView}
                                onValueChange={(value) =>
                                    setData('end_day', value)
                                }
                            >
                                <SelectTrigger id="end_day">
                                    <SelectValue placeholder="Select day" />
                                </SelectTrigger>
                                <SelectContent>
                                    {dayOptions.map((option) => (
                                        <SelectItem
                                            key={`end-day-${option.value}`}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {renderError('end_day')}
                        </div>
                    </div>

                    <div className="grid w-full items-center gap-3">
                        <Label htmlFor="end_month">
                            End Month <span className="text-red-500">*</span>
                        </Label>
                        <div className="relative">
                            <Select
                                value={data.end_month}
                                disabled={isView}
                                onValueChange={(value) =>
                                    setData('end_month', value)
                                }
                            >
                                <SelectTrigger id="end_month">
                                    <SelectValue placeholder="Select month" />
                                </SelectTrigger>
                                <SelectContent>
                                    {monthOptions.map((option) => (
                                        <SelectItem
                                            key={`end-month-${option.value}`}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {renderError('end_month')}
                        </div>
                    </div>
                </div>

                <div className="grid w-full items-center gap-3">
                    <Label htmlFor="year">Fiscal Year Label (Auto)</Label>
                    <Input id="year" value={data.year ?? ''} disabled />
                </div>

                <div className="rounded-md bg-blue-50 p-4 dark:bg-blue-950">
                    <p className="text-base text-blue-800 dark:text-blue-200">
                        <strong>Note:</strong> Fiscal year label is generated
                        automatically. Existing financial transactions keep
                        their original fiscal year assignment after edits.
                    </p>
                </div>

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
