import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { update as updateReportTemplate } from '@/routes/report-template';
import { destroy as destroyModuleRoute } from '@/routes/report-template/modules';
import { Transition } from '@headlessui/react';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useRef, useState } from 'react';
import { AddModuleDialog, FileUploadField } from './report-template/components';
import { GlobalBrandingSection } from './report-template/global-branding-section';
import { ModuleTemplateSection } from './report-template/module-template-section';
import { PdfPreview } from './report-template/pdf-preview-panel';
import type {
    ModuleTemplate,
    RegisteredModule,
    SignatureStampLayoutConfig,
} from './report-template/types';

interface ReportTemplateSettings {
    company_name: string;
    company_address: string;
    company_phone: string;
    company_email: string;
    brand_color: string;
    signature_stamp_layout: 'default' | 'custom';
    custom_signature_stamp_layout: SignatureStampLayoutConfig | null;
    logo_path: string | null;
    stamp_path: string | null;
    signature_path: string | null;
    custom_stamp_path: string | null;
    custom_signature_path: string | null;
    module_templates: Record<string, ModuleTemplate>;
    registered_modules: RegisteredModule[];
}

interface ReportTemplateFormData {
    company_name: string;
    company_address: string;
    company_phone: string;
    company_email: string;
    brand_color: string;
    signature_stamp_layout: 'default' | 'custom';
    custom_signature_stamp_layout: SignatureStampLayoutConfig;
    _method: 'put';
    logo_file: File | null;
    stamp_file: File | null;
    signature_file: File | null;
    custom_stamp_file: File | null;
    custom_signature_file: File | null;
    custom_signature_data: string | null;
    logo_path: string | null;
    stamp_path: string | null;
    signature_path: string | null;
    custom_stamp_path: string | null;
    custom_signature_path: string | null;
    module_templates: Record<string, ModuleTemplate>;
}

const BUILTIN_MODULES: RegisteredModule[] = [
    { key: 'quotation', label: 'Quotation', document_type: 'QUOTATION' },
    { key: 'invoice', label: 'Invoice', document_type: 'INVOICE' },
    {
        key: 'receipt',
        label: 'Official Receipt',
        document_type: 'OFFICIAL RECEIPT',
    },
    { key: 'sales', label: 'Sales Profile', document_type: 'SALES PROFILE' },
    { key: 'package', label: 'Package', document_type: 'PACKAGE' },
    { key: 'manifest', label: 'Manifest', document_type: 'MANIFEST' },
];

const DEFAULT_LOGO_PREVIEW = '/logo-primary.png';
const DEFAULT_LOGO_FILE_NAME = 'logo-primary.png';
const DEFAULT_CUSTOM_LAYOUT: SignatureStampLayoutConfig = {
    unit: 'percent',
    placement: 'left_side',
    labels: {
        show_name: false,
        show_date: false,
        full_name: '',
        date: '',
    },
    stamp: {
        x: 8,
        y: 10,
        width: 26,
        height: 58,
        z: 1,
    },
    signature: {
        x: 62,
        y: 18,
        width: 30,
        height: 48,
        z: 2,
    },
};

export default function ReportTemplate({
    settings,
}: {
    settings: ReportTemplateSettings;
}) {
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

    const resolveCustomLayout = (): SignatureStampLayoutConfig => {
        const incoming = settings.custom_signature_stamp_layout as
            | (SignatureStampLayoutConfig & {
                labels?: SignatureStampLayoutConfig['labels'] & {
                    stamp_name?: string;
                    signature_name?: string;
                };
                stamp?: SignatureStampLayoutConfig['stamp'] & { name?: string };
                signature?:
                    SignatureStampLayoutConfig['signature'] & {
                        name?: string;
                        date?: string;
                    };
            })
            | null;
        if (!incoming) {
            return DEFAULT_CUSTOM_LAYOUT;
        }

        return {
            unit: incoming.unit === 'px' ? 'px' : 'percent',
            placement:
                incoming.placement === 'right_side' ||
                incoming.placement === 'stack_each_other' ||
                incoming.placement === 'up_side' ||
                incoming.placement === 'down_side'
                    ? incoming.placement
                    : 'left_side',
            labels: {
                show_name: Boolean(incoming.labels?.show_name ?? false),
                show_date: Boolean(incoming.labels?.show_date ?? false),
                full_name:
                    incoming.labels?.full_name ??
                    incoming.labels?.signature_name ??
                    incoming.labels?.stamp_name ??
                    incoming.signature?.name ??
                    incoming.stamp?.name ??
                    '',
                date:
                    incoming.labels?.date ??
                    incoming.signature?.date ??
                    '',
            },
            stamp: {
                x: incoming.stamp?.x ?? DEFAULT_CUSTOM_LAYOUT.stamp.x,
                y: incoming.stamp?.y ?? DEFAULT_CUSTOM_LAYOUT.stamp.y,
                width: incoming.stamp?.width ?? DEFAULT_CUSTOM_LAYOUT.stamp.width,
                height:
                    incoming.stamp?.height ??
                    DEFAULT_CUSTOM_LAYOUT.stamp.height,
                z: incoming.stamp?.z ?? DEFAULT_CUSTOM_LAYOUT.stamp.z,
            },
            signature: {
                x: incoming.signature?.x ?? DEFAULT_CUSTOM_LAYOUT.signature.x,
                y: incoming.signature?.y ?? DEFAULT_CUSTOM_LAYOUT.signature.y,
                width:
                    incoming.signature?.width ??
                    DEFAULT_CUSTOM_LAYOUT.signature.width,
                height:
                    incoming.signature?.height ??
                    DEFAULT_CUSTOM_LAYOUT.signature.height,
                z: incoming.signature?.z ?? DEFAULT_CUSTOM_LAYOUT.signature.z,
            },
        };
    };

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
                show_signature_stamp_name: Boolean(
                    serverData?.show_signature_stamp_name ?? false,
                ),
                show_signature_stamp_date: Boolean(
                    serverData?.show_signature_stamp_date ?? false,
                ),
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
            signature_stamp_layout: settings.signature_stamp_layout || 'default',
            custom_signature_stamp_layout: resolveCustomLayout(),
            _method: 'put',
            logo_file: null,
            stamp_file: null,
            signature_file: null,
            custom_stamp_file: null,
            custom_signature_file: null,
            custom_signature_data: null,
            logo_path: null,
            stamp_path: null,
            signature_path: null,
            custom_stamp_path: null,
            custom_signature_path: null,
            module_templates: buildInitialModuleTemplates(),
        });

    const [logoPreview, setLogoPreview] = useState<string | null>(
        settings.logo_path
            ? `/storage/${settings.logo_path}`
            : DEFAULT_LOGO_PREVIEW,
    );
    const [stampPreview, setStampPreview] = useState<string | null>(
        settings.stamp_path ? `/storage/${settings.stamp_path}` : null,
    );
    const [signaturePreview, setSignaturePreview] = useState<string | null>(
        settings.signature_path ? `/storage/${settings.signature_path}` : null,
    );
    const [customStampPreview, setCustomStampPreview] = useState<string | null>(
        settings.custom_stamp_path ? `/storage/${settings.custom_stamp_path}` : null,
    );
    const [customSignaturePreview, setCustomSignaturePreview] = useState<string | null>(
        settings.custom_signature_path
            ? `/storage/${settings.custom_signature_path}`
            : null,
    );
    const [logoPreviewFileName, setLogoPreviewFileName] = useState<
        string | null
    >(extractFileName(settings.logo_path) ?? DEFAULT_LOGO_FILE_NAME);
    const [stampPreviewFileName, setStampPreviewFileName] = useState<
        string | null
    >(extractFileName(settings.stamp_path));
    const [signaturePreviewFileName, setSignaturePreviewFileName] = useState<
        string | null
    >(extractFileName(settings.signature_path));
    const [customStampPreviewFileName, setCustomStampPreviewFileName] = useState<
        string | null
    >(extractFileName(settings.custom_stamp_path));
    const [customSignaturePreviewFileName, setCustomSignaturePreviewFileName] = useState<
        string | null
    >(extractFileName(settings.custom_signature_path));

    // Track active FileReaders to prevent race conditions
    const activeReadersRef = useRef<Map<string, FileReader>>(new Map());

    const makeFileHandler =
        (
            field:
                | 'logo_file'
                | 'stamp_file'
                | 'signature_file'
                | 'custom_stamp_file'
                | 'custom_signature_file',
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

                if (field === 'custom_signature_file') {
                    setData('custom_signature_data', null);
                }

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
            field:
                | 'logo_file'
                | 'stamp_file'
                | 'signature_file'
                | 'custom_stamp_file'
                | 'custom_signature_file',
            setPreview: (v: string | null) => void,
            existingPreview: string | null,
            setPreviewFileName: (v: string | null) => void,
            existingFileName: string | null,
            pathKey:
                | 'logo_path'
                | 'stamp_path'
                | 'signature_path'
                | 'custom_stamp_path'
                | 'custom_signature_path',
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

                if (field === 'custom_signature_file') {
                    setData('custom_signature_data', null);
                }
            };

    const updateModule = (
        field: keyof ModuleTemplate,
        value: string | boolean,
    ) => {
        setData('module_templates', {
            ...data.module_templates,
            [selectedModule]: {
                ...(data.module_templates[selectedModule] ?? {
                    footer_text: '',
                    show_stamp: false,
                    show_signature: false,
                    show_signature_stamp_name: false,
                    show_signature_stamp_date: false,
                }),
                [field]: value,
            },
        });
    };

    const updateModuleSignatureStampNameDateVisibility = (value: boolean) => {
        setData('module_templates', {
            ...data.module_templates,
            [selectedModule]: {
                ...(data.module_templates[selectedModule] ?? {
                    footer_text: '',
                    show_stamp: false,
                    show_signature: false,
                    show_signature_stamp_name: false,
                    show_signature_stamp_date: false,
                }),
                show_signature_stamp_name: value,
                show_signature_stamp_date: value,
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
        show_signature_stamp_name: false,
        show_signature_stamp_date: false,
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
                            customStampPreview={customStampPreview}
                            customSignaturePreview={customSignaturePreview}
                            onDataChange={(field, value) =>
                                setData(
                                    field as keyof ReportTemplateFormData,
                                    value,
                                )
                            }
                            makeFileHandler={makeFileHandler}
                            makeClearHandler={makeClearHandler}
                            logoPreviewFileName={logoPreviewFileName}
                            stampPreviewFileName={stampPreviewFileName}
                            signaturePreviewFileName={signaturePreviewFileName}
                            customStampPreviewFileName={customStampPreviewFileName}
                            customSignaturePreviewFileName={customSignaturePreviewFileName}
                            initialLogoPreview={
                                settings.logo_path
                                    ? `/storage/${settings.logo_path}`
                                    : DEFAULT_LOGO_PREVIEW
                            }
                            initialStampPreview={
                                settings.stamp_path
                                    ? `/storage/${settings.stamp_path}`
                                    : null
                            }
                            initialSignaturePreview={
                                settings.signature_path
                                    ? `/storage/${settings.signature_path}`
                                    : null
                            }
                            initialCustomStampPreview={
                                settings.custom_stamp_path
                                    ? `/storage/${settings.custom_stamp_path}`
                                    : null
                            }
                            initialCustomSignaturePreview={
                                settings.custom_signature_path
                                    ? `/storage/${settings.custom_signature_path}`
                                    : null
                            }
                            initialLogoPreviewFileName={
                                extractFileName(settings.logo_path) ??
                                DEFAULT_LOGO_FILE_NAME
                            }
                            initialStampPreviewFileName={extractFileName(
                                settings.stamp_path,
                            )}
                            initialSignaturePreviewFileName={extractFileName(
                                settings.signature_path,
                            )}
                            initialCustomStampPreviewFileName={extractFileName(
                                settings.custom_stamp_path,
                            )}
                            initialCustomSignaturePreviewFileName={extractFileName(
                                settings.custom_signature_path,
                            )}
                            initialLogoDatabasePath={settings.logo_path}
                            initialStampDatabasePath={settings.stamp_path}
                            initialSignatureDatabasePath={
                                settings.signature_path
                            }
                            initialCustomStampDatabasePath={
                                settings.custom_stamp_path
                            }
                            initialCustomSignatureDatabasePath={
                                settings.custom_signature_path
                            }
                            setLogoPreview={setLogoPreview}
                            setStampPreview={setStampPreview}
                            setSignaturePreview={setSignaturePreview}
                            setCustomStampPreview={setCustomStampPreview}
                            setCustomSignaturePreview={setCustomSignaturePreview}
                            setLogoPreviewFileName={setLogoPreviewFileName}
                            setStampPreviewFileName={setStampPreviewFileName}
                            setSignaturePreviewFileName={
                                setSignaturePreviewFileName
                            }
                            setCustomStampPreviewFileName={
                                setCustomStampPreviewFileName
                            }
                            setCustomSignaturePreviewFileName={
                                setCustomSignaturePreviewFileName
                            }
                            signatureStampLayout={data.signature_stamp_layout}
                            onSignatureStampLayoutChange={(value) =>
                                setData('signature_stamp_layout', value)
                            }
                            customSignatureStampLayout={
                                data.custom_signature_stamp_layout
                            }
                            onCustomSignatureStampLayoutChange={(layout) =>
                                setData('custom_signature_stamp_layout', layout)
                            }
                            onCustomSignatureDataChange={(value) =>
                                setData('custom_signature_data', value)
                            }
                            FileUploadField={FileUploadField}
                        />

                        <ModuleTemplateSection
                            selectedModule={selectedModule}
                            setSelectedModule={setSelectedModule}
                            activeModule={activeModule}
                            activeDefinition={activeDefinition}
                            signatureStampLayout={data.signature_stamp_layout}
                            isBuiltin={isBuiltin}
                            builtinModules={BUILTIN_MODULES}
                            registeredModules={
                                settings.registered_modules ?? []
                            }
                            updateModule={updateModule}
                            updateModuleSignatureStampNameDateVisibility={
                                updateModuleSignatureStampNameDateVisibility
                            }
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
                                showSignatureStampName:
                                    activeModule.show_signature_stamp_name,
                                showSignatureStampDate:
                                    activeModule.show_signature_stamp_date,
                                signatureStampLayout: data.signature_stamp_layout,
                                customSignatureStampLayout:
                                    data.custom_signature_stamp_layout,
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
                                customStampPreview,
                                customSignaturePreview,
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
