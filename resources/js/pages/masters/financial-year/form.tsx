import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/react';
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
        default: false,
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
            <p className="absolute -bottom-4 left-0 text-xs text-red-500">
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
                <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-3">
                    {/* Year */}
                    <div className="grid w-full items-center gap-3">
                        <Label htmlFor="name">Year</Label>
                        <div className="relative">
                            <Input
                                type="text"
                                id="name"
                                value={data.year}
                                onChange={(e) =>
                                    setData('year', e.target.value)
                                }
                                placeholder="Year"
                                disabled={isView}
                            />
                            {renderError('year')}
                        </div>

                        {/* Default */}
                        <div className="flex gap-2">
                            <Label htmlFor="default">Set as default</Label>
                            <div className="relative flex items-center gap-2">
                                <Checkbox
                                    id="default"
                                    checked={data.default ?? false}
                                    onCheckedChange={(checked) =>
                                        setData('default', Boolean(checked))
                                    }
                                    disabled={isView}
                                />
                                {renderError('default')}
                            </div>
                        </div>
                    </div>
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
