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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { OptionType } from '@/types';
import { useForm } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import PackageForm from '../packages/form';
import PackageInformationSection from '../packages/package-information-section';
import { type PackageSchema } from '../packages/schema';
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
        enquiry_number: '',
        number_format_id: null,
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

    const [linkedPackageInfo, setLinkedPackageInfo] = useState<{
        id: number;
        name: string;
        status?: string;
        departure_date?: string | null;
        return_date?: string | null;
    } | null>(null);
    const [linkedPackageData, setLinkedPackageData] =
        useState<PackageSchema | null>(null);
    const [packageDialogOpen, setPackageDialogOpen] = useState(false);
    const [isLoadingLinkedPackage, setIsLoadingLinkedPackage] = useState(false);

    const loadPackageInfo = useCallback(async (packageId: number) => {
        setIsLoadingLinkedPackage(true);

        try {
            const response = await fetch(`/packages-get-for-show/${packageId}`);

            if (!response.ok) {
                return;
            }

            const pkg = await response.json();

            setLinkedPackageInfo({
                id: pkg.id,
                name: pkg.name,
                status: pkg.status,
                departure_date: pkg.departure_date,
                return_date: pkg.return_date,
            });
            setLinkedPackageData(pkg);
        } finally {
            setIsLoadingLinkedPackage(false);
        }
    }, []);

    useEffect(() => {
        const packageId = data.package_id;

        if (!packageId) {
            setLinkedPackageInfo(null);
            setLinkedPackageData(null);

            return;
        }

        const selected = packageOptions.find(
            (option) => Number(option.value) === Number(packageId),
        );

        setLinkedPackageInfo((current) => ({
            id: Number(packageId),
            name: selected?.label ?? current?.name ?? '-',
            status: current?.status,
            departure_date: current?.departure_date,
            return_date: current?.return_date,
        }));

        loadPackageInfo(Number(packageId));
    }, [data.package_id, packageOptions, loadPackageInfo]);

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

    const handleOpenPackageDialog = async () => {
        if (!data.package_id) {
            return;
        }

        if (!linkedPackageData || linkedPackageData.id !== data.package_id) {
            await loadPackageInfo(Number(data.package_id));
        }

        setPackageDialogOpen(true);
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

                {isView && data.enquiry_number && (
                    <div className="rounded-lg border border-primary/20 bg-primary/5 p-4">
                        <p className="text-base text-muted-foreground">
                            Enquiry Number
                        </p>
                        <p className="text-2xl font-bold text-primary">
                            {data.enquiry_number}
                        </p>
                    </div>
                )}

                {packageOptions.length > 0 && (
                    <PackageInformationSection
                        description="Select a package for this enquiry and review key package details."
                        packageInfo={linkedPackageInfo}
                        isLoading={isLoadingLinkedPackage}
                        onViewDetails={
                            linkedPackageInfo
                                ? handleOpenPackageDialog
                                : undefined
                        }
                        renderPackageSelector={
                            <FormField
                                label="Package"
                                fieldRequirementsProps={{
                                    hint: 'Link a travel package to this enquiry (optional)',
                                }}
                                htmlFor="package_id"
                                error={
                                    renderError('package_id')?.props?.children
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
                        }
                    />
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

                <Dialog
                    open={packageDialogOpen}
                    onOpenChange={setPackageDialogOpen}
                >
                    <DialogContent className="flex max-h-[95%] max-w-[95%] min-w-[95%] flex-col">
                        <DialogHeader>
                            <DialogTitle>Package Details</DialogTitle>
                            <DialogDescription className="sr-only">
                                View package details
                            </DialogDescription>
                        </DialogHeader>

                        <div className="h-full w-full flex-1 overflow-y-auto pb-2">
                            {linkedPackageData ? (
                                <PackageForm
                                    mode="view"
                                    initialData={linkedPackageData}
                                    onCancel={() => setPackageDialogOpen(false)}
                                />
                            ) : (
                                <div className="text-sm text-muted-foreground">
                                    Loading package details...
                                </div>
                            )}
                        </div>
                    </DialogContent>
                </Dialog>
            </form>
        </div>
    );
}
