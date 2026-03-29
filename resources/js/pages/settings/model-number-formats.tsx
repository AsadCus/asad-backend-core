import { FormField } from '@/components/form-field';
import HeadingSmall from '@/components/heading-small';
import ModelNumberInput from '@/components/model-number-input';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { fetchSupportedModelKeys } from '@/lib/numbering-formats';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Model number formats',
        href: '/settings/model-number-formats',
    },
];

const DEFAULT_MODEL_KEYS = [
    'customer',
    'quotation',
    'order',
    'invoice',
    'receipt',
    'package',
    'manifest',
    'customer_confirmation',
    'maid',
    'general_enquiry',
    'private_enquiry',
];

function modelLabel(modelKey: string): string {
    return modelKey
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

export default function ModelNumberFormatsSettings() {
    const [supportedModelKeys, setSupportedModelKeys] =
        useState<string[]>(DEFAULT_MODEL_KEYS);
    const [selectedModelKey, setSelectedModelKey] =
        useState<string>('quotation');
    const [isLoadingModelKeys, setIsLoadingModelKeys] = useState(true);
    const [loadError, setLoadError] = useState<string | null>(null);

    const [numberByModel, setNumberByModel] = useState<Record<string, string>>(
        {},
    );
    const [formatIdByModel, setFormatIdByModel] = useState<
        Record<string, number | null>
    >({});

    useEffect(() => {
        let isUnmounted = false;

        const load = async () => {
            setIsLoadingModelKeys(true);
            setLoadError(null);

            try {
                const modelKeys = await fetchSupportedModelKeys();

                if (isUnmounted) {
                    return;
                }

                if (modelKeys.length > 0) {
                    setSupportedModelKeys(modelKeys);
                    setSelectedModelKey((current) =>
                        modelKeys.includes(current) ? current : modelKeys[0],
                    );
                }
            } catch (exception) {
                if (isUnmounted) {
                    return;
                }

                setLoadError(
                    exception instanceof Error
                        ? exception.message
                        : 'Unable to load model keys. You can still manage common defaults below.',
                );
            } finally {
                if (!isUnmounted) {
                    setIsLoadingModelKeys(false);
                }
            }
        };

        void load();

        return () => {
            isUnmounted = true;
        };
    }, []);

    const selectedModelLabel = useMemo(() => {
        return modelLabel(selectedModelKey);
    }, [selectedModelKey]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Model Number Formats" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Model Number Formats"
                        description="Configure per-model numbering formats and preview the next generated number."
                    />

                    {loadError && (
                        <Alert variant="destructive">
                            <AlertCircle className="h-4 w-4" />
                            <AlertDescription>{loadError}</AlertDescription>
                        </Alert>
                    )}

                    <FormField
                        label="Model"
                        htmlFor="model_key"
                        fieldRequirementsProps={{
                            hint: 'Select a model to manage its numbering formats and preview generation.',
                        }}
                    >
                        <Select
                            value={selectedModelKey}
                            onValueChange={setSelectedModelKey}
                            disabled={isLoadingModelKeys}
                        >
                            <SelectTrigger id="model_key">
                                <SelectValue placeholder="Select model" />
                            </SelectTrigger>
                            <SelectContent>
                                {supportedModelKeys.map((modelKey) => (
                                    <SelectItem key={modelKey} value={modelKey}>
                                        {modelLabel(modelKey)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>

                    <ModelNumberInput
                        modelKey={selectedModelKey}
                        label={`${selectedModelLabel} Number`}
                        value={numberByModel[selectedModelKey] ?? ''}
                        formatId={formatIdByModel[selectedModelKey] ?? null}
                        onValueChange={(value) =>
                            setNumberByModel((current) => ({
                                ...current,
                                [selectedModelKey]: value,
                            }))
                        }
                        onFormatIdChange={(formatId) =>
                            setFormatIdByModel((current) => ({
                                ...current,
                                [selectedModelKey]: formatId,
                            }))
                        }
                        disabled={isLoadingModelKeys}
                        hint="Use the settings button to create or update formats for the selected model."
                    />
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
