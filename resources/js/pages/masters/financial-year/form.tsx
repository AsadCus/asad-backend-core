import { DatePickerField } from '@/components/date-picker';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { financialYearFormSchema, FinancialYearFormSchema } from './schema';

interface FinancialYearFormProps {
    mode: 'create' | 'edit' | 'view';
    initialData?: FinancialYearFormSchema;
    useFinancialTransactionsForFytdTotalSales?: boolean;
    onCancel?: () => void;
}

export function FinancialYearForm({
    mode,
    initialData,
    useFinancialTransactionsForFytdTotalSales = false,
    onCancel,
}: FinancialYearFormProps) {
    const isView = mode === 'view';
    const isEdit = mode === 'edit';
    const isCreate = mode === 'create';

    const initialFormState: FinancialYearFormSchema = {
        id: 0,
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
    } = useForm<FinancialYearFormSchema>(defaultData);

    useEffect(() => {
        const startDate = data.start_date ? new Date(data.start_date) : null;
        const endDate = data.end_date ? new Date(data.end_date) : null;

        if (
            !startDate ||
            !endDate ||
            Number.isNaN(startDate.getTime()) ||
            Number.isNaN(endDate.getTime())
        ) {
            setData('year', '');

            return;
        }

        const startYear = startDate.getFullYear();
        const endYear = endDate.getFullYear();

        if (startYear === endYear) {
            setData('year', String(startYear));

            return;
        }

        const monthsInStartYear = 12 - startDate.getMonth();
        const monthsInEndYear = endDate.getMonth() + 1;
        const dominantYear =
            monthsInStartYear >= monthsInEndYear ? startYear : endYear;

        setData('year', String(dominantYear));
    }, [data.end_date, data.start_date, setData]);

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
                        <Label htmlFor="start_date">
                            Start Date <span className="text-red-500">*</span>
                        </Label>
                        <div className="relative">
                            <DatePickerField
                                id="start_date"
                                value={data.start_date ?? ''}
                                fromYear={new Date().getFullYear()}
                                toYear={new Date().getFullYear()}
                                displayFormat="day-month"
                                valueFormat="iso"
                                disabled={isView}
                                disabledDates={(date) => {
                                    if (!data.end_date) {
                                        return false;
                                    }

                                    const endDate = new Date(data.end_date);

                                    if (Number.isNaN(endDate.getTime())) {
                                        return false;
                                    }

                                    return date >= endDate;
                                }}
                                onChange={(value) =>
                                    setData('start_date', value)
                                }
                            />
                            {renderError('start_date')}
                        </div>
                    </div>

                    <div className="grid w-full items-center gap-3">
                        <Label htmlFor="end_date">
                            End Date <span className="text-red-500">*</span>
                        </Label>
                        <div className="relative">
                            <DatePickerField
                                id="end_date"
                                value={data.end_date ?? ''}
                                fromYear={new Date().getFullYear()}
                                toYear={new Date().getFullYear()}
                                displayFormat="day-month"
                                valueFormat="iso"
                                disabled={isView}
                                disabledDates={(date) => {
                                    if (!data.start_date) {
                                        return false;
                                    }

                                    const startDate = new Date(data.start_date);

                                    if (Number.isNaN(startDate.getTime())) {
                                        return false;
                                    }

                                    return date <= startDate;
                                }}
                                onChange={(value) => setData('end_date', value)}
                            />
                            {renderError('end_date')}
                        </div>
                    </div>
                </div>

                <div className="rounded-md bg-blue-50 p-4 dark:bg-blue-950">
                    <p className="text-base text-blue-800 dark:text-blue-200">
                        <strong>Note:</strong>
                        {/* {' '}Dashboard FYTD source toggle{' '}<strong>DASHBOARD_USE_FINANCIAL_TRANSACTIONS_FOR_FYTD_TOTAL_SALES</strong>{' '}is currently set to{' '}<strong>{useFinancialTransactionsForFytdTotalSales ? 'true' : 'false'}</strong>. */}{' '}
                        {useFinancialTransactionsForFytdTotalSales
                            ? 'Fiscal Year Total Sales is currently using Financial Transactions (receipt-based ledger entries).'
                            : 'Fiscal Year Total Sales is currently using converted quotation invoices filtered by receipt date.'}{' '}
                        Existing financial transactions keep their original
                        fiscal year assignment after edits.
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
                            className="min-w-35"
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
