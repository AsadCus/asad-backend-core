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
import { ValueNumberOptionType } from '@/types';
import { useForm } from '@inertiajs/react';
import { branchSchema, BranchSchema } from './schema';

interface BranchFormProps {
    mode: 'create' | 'edit' | 'view';
    initialData?: BranchSchema;
    onSubmit?: (values: BranchSchema) => void;
    onCancel?: () => void;
    countries?: ValueNumberOptionType[];
}

export function BranchForm({
    mode,
    initialData,
    onCancel,
    countries = [],
}: BranchFormProps) {
    const isView = mode === 'view';
    const isEdit = mode === 'edit';
    const isCreate = mode === 'create';

    const initialFormState: BranchSchema = {
        name: '',
        country_id: '',
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
    } = useForm<BranchSchema>(defaultData);

    function validateClientSide() {
        const result = branchSchema.safeParse(data);

        clearErrors();

        if (!result.success) {
            result.error.issues.forEach((issue) => {
                const key = String(issue.path[0]) as keyof BranchSchema;
                setError(key, issue.message);
            });
            return false;
        }

        return true;
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();

        if (!validateClientSide()) return;

        const url = '/master/branch';

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

    const renderError = (field: keyof BranchSchema) =>
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
                <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-3">
                    {/* Name */}
                    <div className="grid w-full items-center gap-3">
                        <Label htmlFor="name">Name</Label>
                        <div className="relative">
                            <Input
                                type="text"
                                id="name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                placeholder="Name"
                                disabled={isView}
                            />
                            {renderError('name')}
                        </div>
                    </div>

                    {/* Country */}
                    <div className="grid w-full items-center gap-3">
                        <Label>Country</Label>
                        <div className="relative">
                            <Select
                                disabled={isView}
                                value={String(data.country_id)}
                                onValueChange={(value) =>
                                    setData('country_id', String(value))
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select country" />
                                </SelectTrigger>
                                <SelectContent>
                                    {countries.map((r) => (
                                        <SelectItem
                                            key={r.value}
                                            value={String(r.value)}
                                        >
                                            {r.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {renderError('country_id')}
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
