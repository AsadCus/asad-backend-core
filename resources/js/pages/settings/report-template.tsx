import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { update as updateReportTemplate } from '@/routes/report-template';
import { destroy as destroyModuleRoute } from '@/routes/report-template/modules';
import { Transition } from '@headlessui/react';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect, useRef, useState } from 'react';
import { AddModuleDialog, FileUploadField } from './report-template/components';
import { GlobalBrandingSection } from './report-template/global-branding-section';
import { ModuleTemplateSection } from './report-template/module-template-section';
import { PdfPreview } from './report-template/pdf-preview';
import type { ModuleTemplate, RegisteredModule } from './report-template/types';

interface ReportTemplateSettings {
    company_name: string;
    company_address: string;
    company_phone: string;
    company_email: string;
    brand_color: string;
    logo_path: string | null;
    stamp_path: string | null;
    signature_path: string | null;
    module_templates: Record<string, ModuleTemplate>;
    registered_modules: RegisteredModule[];
}

interface ReportTemplateFormData {
    company_name: string;
    company_address: string;
    company_phone: string;
    company_email: string;
    brand_color: string;
    _method: 'put';
    logo_file: File | null;
    stamp_file: File | null;
    signature_file: File | null;
    logo_path: string | null;
    stamp_path: string | null;
    signature_path: string | null;
    module_templates: Record<string, ModuleTemplate>;
}

const BUILTIN_MODULES: RegisteredModule[] = [
    { key: 'quotation', label: 'Quotation', document_type: 'QUOTATION' },
    { key: 'invoice', label: 'Invoice', document_type: 'INVOICE' },
    { key: 'receipt', label: 'Official Receipt', document_type: 'OFFICIAL RECEIPT' },
    { key: 'agreement', label: 'Agreement', document_type: 'AGREEMENT' },
    { key: 'sales', label: 'Sales Profile', document_type: 'SALES PROFILE' },
];

const DEFAULT_LOGO_PREVIEW = '/logo-primary.png';
const DEFAULT_LOGO_FILE_NAME = 'logo-primary.png';

export default function ReportTemplate({ settings }: { settings: ReportTemplateSettings }) {
    const extractFileName = (path: string | null): string | null => {
        if (!path) {
            return null;
        }

        return decodeURIComponent(path.split('/').pop() || '');
    };

    const allModules: RegisteredModule[] = [
        ...BUILTIN_MODULES,
        ...(settings.registered_modules ?? []),
    ];

    const [selectedModule, setSelectedModule] = useState<string>(
        allModules[0]?.key ?? 'quotation',
    );

    const buildInitialModuleTemplates = (): Record<string, ModuleTemplate> => {
        const defaults: Record<string, ModuleTemplate> = {};
        allModules.forEach(({ key }) => {
            const serverData = settings.module_templates?.[key];
            defaults[key] = {
                footer_text: serverData?.footer_text ?? '',
                show_stamp: Boolean(serverData?.show_stamp ?? false),
                show_signature: Boolean(serverData?.show_signature ?? false),
            };
        });
        return defaults;
    };

    const { data, setData, post, errors, processing, recentlySuccessful } =
        useForm<ReportTemplateFormData>({
            company_name: settings.company_name,
            company_address: settings.company_address || '',
            company_phone: settings.company_phone || '',
            company_email: settings.company_email || '',
            brand_color: settings.brand_color || '#c05427',
            _method: 'put',
            logo_file: null,
            stamp_file: null,
            signature_file: null,
            logo_path: null,
            stamp_path: null,
            signature_path: null,
            module_templates: buildInitialModuleTemplates(),
        });

    const [logoPreview, setLogoPreview] = useState<string | null>(
        settings.logo_path ? `/storage/${settings.logo_path}` : DEFAULT_LOGO_PREVIEW,
    );
    const [stampPreview, setStampPreview] = useState<string | null>(
        settings.stamp_path ? `/storage/${settings.stamp_path}` : null,
    );
    const [signaturePreview, setSignaturePreview] = useState<string | null>(
        settings.signature_path ? `/storage/${settings.signature_path}` : null,
    );
    const [logoPreviewFileName, setLogoPreviewFileName] = useState<string | null>(
        extractFileName(settings.logo_path) ?? DEFAULT_LOGO_FILE_NAME,
    );
    const [stampPreviewFileName, setStampPreviewFileName] = useState<string | null>(
        extractFileName(settings.stamp_path),
    );
    const [signaturePreviewFileName, setSignaturePreviewFileName] = useState<string | null>(
        extractFileName(settings.signature_path),
    );

    // Track active FileReaders to prevent race conditions
    const activeReadersRef = useRef<Map<string, FileReader>>(new Map());

    const makeFileHandler =
        (
            field: 'logo_file' | 'stamp_file' | 'signature_file',
            setPreview: (v: string | null) => void,
            setPreviewFileName: (v: string | null) => void,
        ) =>
            (e: React.ChangeEvent<HTMLInputElement>) => {
                const file = e.target.files?.[0];
                if (!file) return;

                // Cancel any previous FileReader for this field
                const existingReader = activeReadersRef.current.get(field);
                if (existingReader) {
                    existingReader.abort();
                }

                setData(field, file);
                setPreviewFileName(file.name);
                const reader = new FileReader();
                activeReadersRef.current.set(field, reader);
                reader.onloadend = () => {
                    // Only set preview if this reader wasn't aborted
                    if (activeReadersRef.current.get(field) === reader) {
                        setPreview(reader.result as string);
                        activeReadersRef.current.delete(field);
                    }
                };
                reader.readAsDataURL(file);
            };

    const makeClearHandler =
        (
            field: 'logo_file' | 'stamp_file' | 'signature_file',
            setPreview: (v: string | null) => void,
            existingPreview: string | null,
            setPreviewFileName: (v: string | null) => void,
            existingFileName: string | null,
            pathKey: 'logo_path' | 'stamp_path' | 'signature_path',
            hasDatabaseFile: boolean,
        ) =>
            () => {
                // Cancel any active FileReader for this field
                const existingReader = activeReadersRef.current.get(field);
                if (existingReader) {
                    existingReader.abort();
                    activeReadersRef.current.delete(field);
                }

                // Clear file and preview
                setData(field, null);
                setPreview(null);
                setPreviewFileName(null);

                // If there's an existing file in database, send empty string signal for deletion
                if (hasDatabaseFile) {
                    setData(pathKey, '');
                }
            };

    const updateModule = (field: keyof ModuleTemplate, value: string | boolean) => {
        setData('module_templates', {
            ...data.module_templates,
            [selectedModule]: {
                ...(data.module_templates[selectedModule] ?? {
                    footer_text: '',
                    show_stamp: false,
                    show_signature: false,
                }),
                [field]: value,
            },
        });
    };

    const handleDeleteModule = (key: string) => {
        const label = allModules.find((m) => m.key === key)?.label ?? key;
        if (
            !confirm(
                `Delete module "${label}"? This will also remove its template settings.`,
            )
        )
            return;
        router.delete(destroyModuleRoute(key).url, {
            onSuccess: () => {
                setSelectedModule(BUILTIN_MODULES[0].key);
            },
        });
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(updateReportTemplate.url(), {
            forceFormData: true,
            onSuccess: () => {
                // Refresh page after successful submission to ensure new image paths are loaded
                router.reload();
            },
        });
    };

    const activeModule = data.module_templates[selectedModule] ?? {
        footer_text: '',
        show_stamp: false,
        show_signature: false,
    };
    const activeDefinition = allModules.find((m) => m.key === selectedModule);
    const isBuiltin = BUILTIN_MODULES.some((m) => m.key === selectedModule);
    const registeredModulesCount = settings.registered_modules?.length ?? 0;

    return (
        <AppLayout>
            <Head title="Report Template Settings" />
            <SettingsLayout wide>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Report Template Settings"
                        description="Manage branding and per-module PDF document settings"
                    />

                    <form onSubmit={submit} className="space-y-6">
                        <GlobalBrandingSection
                            data={data}
                            errors={errors}
                            logoPreview={logoPreview}
                            stampPreview={stampPreview}
                            signaturePreview={signaturePreview}
                            onDataChange={(field, value) => setData(field as keyof ReportTemplateFormData, value)}
                            makeFileHandler={makeFileHandler}
                            makeClearHandler={makeClearHandler}
                            logoPreviewFileName={logoPreviewFileName}
                            stampPreviewFileName={stampPreviewFileName}
                            signaturePreviewFileName={signaturePreviewFileName}
                            initialLogoPreview={settings.logo_path ? `/storage/${settings.logo_path}` : DEFAULT_LOGO_PREVIEW}
                            initialStampPreview={settings.stamp_path ? `/storage/${settings.stamp_path}` : null}
                            initialSignaturePreview={settings.signature_path ? `/storage/${settings.signature_path}` : null}
                            initialLogoPreviewFileName={extractFileName(settings.logo_path) ?? DEFAULT_LOGO_FILE_NAME}
                            initialStampPreviewFileName={extractFileName(settings.stamp_path)}
                            initialSignaturePreviewFileName={extractFileName(settings.signature_path)}
                            initialLogoDatabasePath={settings.logo_path}
                            initialStampDatabasePath={settings.stamp_path}
                            initialSignatureDatabasePath={settings.signature_path}
                            setLogoPreview={setLogoPreview}
                            setStampPreview={setStampPreview}
                            setSignaturePreview={setSignaturePreview}
                            setLogoPreviewFileName={setLogoPreviewFileName}
                            setStampPreviewFileName={setStampPreviewFileName}
                            setSignaturePreviewFileName={setSignaturePreviewFileName}
                            FileUploadField={FileUploadField}
                        />

                        <ModuleTemplateSection
                            selectedModule={selectedModule}
                            setSelectedModule={setSelectedModule}
                            activeModule={activeModule}
                            activeDefinition={activeDefinition}
                            isBuiltin={isBuiltin}
                            builtinModules={BUILTIN_MODULES}
                            registeredModules={settings.registered_modules ?? []}
                            updateModule={updateModule}
                            handleDeleteModule={handleDeleteModule}
                            AddModuleDialog={
                                <AddModuleDialog key={registeredModulesCount} />
                            }
                            PdfPreview={PdfPreview}
                            previewProps={{
                                titleColor: data.brand_color || '#c05427',
                                footerText: activeModule.footer_text,
                                showStamp: activeModule.show_stamp,
                                showSignature: activeModule.show_signature,
                                documentType:
                                    activeDefinition?.document_type ??
                                    selectedModule.toUpperCase(),
                                companyName: data.company_name,
                                companyPhone: data.company_phone,
                                companyEmail: data.company_email,
                                companyAddress: data.company_address,
                                logoPreview,
                                stampPreview,
                                signaturePreview,
                            }}
                        />

                        <div className="flex items-center gap-4 pt-2">
                            <Button type="submit" disabled={processing}>
                                Save Changes
                            </Button>
                            <Transition
                                show={recentlySuccessful}
                                enter="transition ease-in-out"
                                enterFrom="opacity-0"
                                leave="transition ease-in-out"
                                leaveTo="opacity-0"
                            >
                                <p className="text-base text-green-600">
                                    Saved successfully.
                                </p>
                            </Transition>
                        </div>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
