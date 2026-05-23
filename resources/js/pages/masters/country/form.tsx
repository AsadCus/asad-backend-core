import { FormField } from '@/components/form-field';
import { ProperInput } from '@/components/proper-input';
import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/react';
import { countrySchema, CountrySchema } from './schema';

interface CountryFormProps {
    mode: 'create' | 'edit' | 'view';
    initialData?: CountrySchema;
    onCancel?: () => void;
}

export function CountryForm({ mode, initialData, onCancel }: CountryFormProps) {
    const isView = mode === 'view';
    const isEdit = mode === 'edit';
    const isCreate = mode === 'create';

    const initialFormState: CountrySchema = {
        name: '',
        adjective: '',
        currency_symbol: '',
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
    } = useForm<CountrySchema>(defaultData);

    function validateClientSide() {
        const result = countrySchema.safeParse(data);

        clearErrors();

        if (!result.success) {
            result.error.issues.forEach((issue) => {
                const key = String(issue.path[0]) as keyof CountrySchema;
                setError(key, issue.message);
            });
            return false;
        }

        return true;
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();

        if (!validateClientSide()) {
            return;
        }

        const url = '/master/country';

        if (isCreate) {
            post(url, {
                onError: (validationErrors) => {
                    setError(validationErrors);
                },
            });
        } else if (isEdit) {
            put(`${url}/${data.id}`, {
                onError: (validationErrors) => {
                    setError(validationErrors);
                },
            });
        }
    }

    return (
        <div className="mx-auto max-h-[90vh] w-full overflow-y-auto p-2">
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-3">
                    <FormField
                        label="Name"
                        fieldRequirementsProps={{ required: true }}
                        htmlFor="name"
                        error={errors.name}
                    >
                        <ProperInput
                            id="name"
                            value={data.name}
                            onCommit={(value) => setData('name', value)}
                            placeholder="Country name"
                            disabled={isView}
                        />
                    </FormField>

                    <FormField
                        label="Adjective"
                        htmlFor="adjective"
                        error={errors.adjective}
                    >
                        <ProperInput
                            id="adjective"
                            value={data.adjective ?? null}
                            onCommit={(value) => setData('adjective', value)}
                            placeholder="Country adjective"
                            disabled={isView}
                        />
                    </FormField>

                    <FormField
                        label="Currency Symbol"
                        htmlFor="currency_symbol"
                        error={errors.currency_symbol}
                    >
                        <ProperInput
                            id="currency_symbol"
                            value={data.currency_symbol ?? null}
                            onCommit={(value) =>
                                setData('currency_symbol', value)
                            }
                            placeholder="e.g. S$"
                            disabled={isView}
                        />
                    </FormField>
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
