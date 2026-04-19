import HeadingSmall from '@/components/heading-small';
import { ReportTemplatePreviewModal } from '@/pages/settings/report-template/preview-modal';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { update as updateReportTemplate } from '@/routes/report-template';
import { destroy as destroyModuleRoute } from '@/routes/report-template/modules';
import { Transition } from '@headlessui/react';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import { AddModuleDialog } from './report-template/components';
import { GlobalBrandingSection } from './report-template/global-branding-section';
import { ModuleTemplateSection } from './report-template/module-template-section';
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
    page_margin_preset?: 'narrow' | 'normal' | 'wide';
    section_spacing_preset?: 'compact' | 'normal' | 'relaxed';
    qr_alignment: 'left' | 'center' | 'right';
    qr_width?: number;
    qr_height?: number;
    custom_signature_stamp_layout: SignatureStampLayoutConfig | null;
    logo_path: string | null;
    qr_image_path: string | null;
    stamp_path: string | null;
    signature_path: string | null;
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
    page_margin_preset: 'narrow' | 'normal' | 'wide';
    section_spacing_preset: 'compact' | 'normal' | 'relaxed';
    qr_alignment: 'left' | 'center' | 'right';
    qr_width: number;
    qr_height: number;
    custom_signature_stamp_layout: SignatureStampLayoutConfig;
    _method: 'put';
    logo_file: File | null;
    qr_file: File | null;
    stamp_file: File | null;
    signature_file: File | null;
    custom_signature_data: string | null;
    logo_path: string | null;
    qr_image_path: string | null;
    stamp_path: string | null;
    signature_path: string | null;
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
    { key: 'payment_summary', label: 'Payment Summary', document_type: 'PAYMENT SUMMARY' },
    { key: 'package', label: 'Package', document_type: 'PACKAGE' },
    { key: 'manifest_arabic_names', label: 'Manifest - Arabic Names', document_type: 'MANIFEST' },
    { key: 'manifest_namelist_course_items', label: 'Manifest - Namelist & Course Items', document_type: 'MANIFEST' },
    { key: 'manifest_room_check', label: 'Manifest - Room Check', document_type: 'MANIFEST' },
    { key: 'ops_movement', label: 'Ops Movement', document_type: 'OPS MOVEMENT' },
    { key: 'ops_movement_budget', label: 'Ops Movement - Budget', document_type: 'OPS MOVEMENT' },
    { key: 'ops_movement_pif', label: 'Ops Movement - PIF', document_type: 'OPS MOVEMENT' },
];

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
    signatureLineWidth: 2,
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
            signatureLineWidth: incoming.signatureLineWidth ?? 2,
        };
    };

    const [selectedModule, setSelectedModule] = useState<string>(
        allModules[0]?.key ?? 'quotation',
    );

    // Preview modal state — null means closed
    const [previewModule, setPreviewModule] = useState<string | null>(null);

    const buildInitialModuleTemplates = (): Record<string, ModuleTemplate> => {
        const defaults: Record<string, ModuleTemplate> = {};
        allModules.forEach(({ key }) => {
            const serverData = settings.module_templates?.[key];
            defaults[key] = {
                footer_text: serverData?.footer_text ?? '',
                show_stamp: Boolean(serverData?.show_stamp ?? false),
                show_signature: Boolean(serverData?.show_signature ?? false),
                show_qr: Boolean(serverData?.show_qr ?? true),
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
            page_margin_preset: settings.page_margin_preset || 'normal',
            section_spacing_preset: settings.section_spacing_preset || 'normal',
            qr_alignment: settings.qr_alignment || 'center',
            qr_width: settings.qr_width ?? 120,
            qr_height: settings.qr_height ?? 120,
            custom_signature_stamp_layout: resolveCustomLayout(),
            _method: 'put',
            logo_file: null,
            qr_file: null,
            stamp_file: null,
            signature_file: null,
            custom_signature_data: null,
            logo_path: settings.logo_path ?? null,
            qr_image_path: settings.qr_image_path ?? null,
            stamp_path: settings.stamp_path ?? null,
            signature_path: settings.signature_path ?? null,
            custom_signature_path: settings.custom_signature_path ?? null,
            module_templates: buildInitialModuleTemplates(),
        });

    const [logoPreviewFileName, setLogoPreviewFileName] = useState<
        string | null
    >(extractFileName(settings.logo_path) ?? DEFAULT_LOGO_FILE_NAME);
    const [stampPreviewFileName, setStampPreviewFileName] = useState<
        string | null
    >(extractFileName(settings.stamp_path));
    const [qrPreviewFileName, setQrPreviewFileName] = useState<
        string | null
    >(extractFileName(settings.qr_image_path));
    const [signaturePreviewFileName, setSignaturePreviewFileName] = useState<
        string | null
    >(extractFileName(settings.signature_path));


    const makeFileHandler =
        (
            field:
                | 'logo_file'
                | 'qr_file'
                | 'stamp_file'
                | 'signature_file',
            setPreviewFileName: (v: string | null) => void,
        ) =>
            (file: File) => {
                if (!file) return;

                setData(field, file as never);

                setPreviewFileName(file.name);
            };

    const makeClearHandler =
        (
            field:
                | 'logo_file'
                | 'qr_file'
                | 'stamp_file'
                | 'signature_file',
            setPreviewFileName: (v: string | null) => void,
            pathKey:
                | 'logo_path'
                | 'qr_image_path'
                | 'stamp_path'
                | 'signature_path',
            hasDatabaseFile: boolean,
        ) =>
            () => {
                setData(field, null as never);
                setPreviewFileName(null);

                if (hasDatabaseFile) {
                    setData(pathKey, '' as never);
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
                    show_qr: true,
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
                    show_qr: true,
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
        show_qr: true,
        show_signature_stamp_name: false,
        show_signature_stamp_date: false,
    };
    const activeDefinition = allModules.find((m) => m.key === selectedModule);
    const registeredModulesCount = settings.registered_modules?.length ?? 0;

    // Module definition for the preview modal
    const previewModuleDefinition = previewModule
        ? allModules.find((m) => m.key === previewModule)
        : null;

    return (
        <AppLayout>
            <Head title="Report Template Settings" />
            <SettingsLayout wide fullWidth>
                <div className="space-y-6">
                    <div className="flex items-center justify-between">
                        <HeadingSmall
                            title="Report Template"
                            description="Configure your document branding and module-specific layouts."
                        />
                        <div className="flex items-center gap-3">
                            <Transition
                                show={recentlySuccessful}
                                enter="transition ease-in-out"
                                enterFrom="opacity-0"
                                leave="transition ease-in-out"
                                leaveTo="opacity-0"
                            >
                                <p className="text-sm text-green-600">
                                    Changes saved.
                                </p>
                            </Transition>
                            <Button
                                type="submit"
                                form="report-template-form"
                                disabled={processing}
                                className="shadow-sm"
                            >
                                {processing ? 'Saving...' : 'Save All Changes'}
                            </Button>
                        </div>
                    </div>

                    <form id="report-template-form" onSubmit={submit}>
                        {/* GRID: Wider container with better proportions */}
                        <div className="mx-auto w-full max-w-[2200px]">
                            <div className="grid grid-cols-1 gap-8 2xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)] 2xl:items-start">

                                {/* LEFT COLUMN: Global Branding */}
                                <aside className="w-full min-w-0">
                                    <GlobalBrandingSection
                                    data={data}
                                    errors={errors}
                                    onDataChange={(field, value) =>
                                        setData(
                                            field as keyof ReportTemplateFormData,
                                            value,
                                        )
                                    }
                                    makeFileHandler={
                                        makeFileHandler as Parameters<
                                            typeof GlobalBrandingSection
                                        >[0]['makeFileHandler']
                                    }
                                    makeClearHandler={
                                        makeClearHandler as Parameters<
                                            typeof GlobalBrandingSection
                                        >[0]['makeClearHandler']
                                    }
                                    logoPreviewFileName={logoPreviewFileName}
                                    stampPreviewFileName={stampPreviewFileName}
                                    qrPreviewFileName={qrPreviewFileName}
                                    signaturePreviewFileName={
                                        signaturePreviewFileName
                                    }
                                    initialLogoDatabasePath={settings.logo_path}
                                    initialQrDatabasePath={settings.qr_image_path}
                                    initialStampDatabasePath={
                                        settings.stamp_path
                                    }
                                    initialSignatureDatabasePath={
                                        settings.signature_path
                                    }
                                    setLogoPreviewFileName={
                                        setLogoPreviewFileName
                                    }
                                    setStampPreviewFileName={
                                        setStampPreviewFileName
                                    }
                                    setQrPreviewFileName={
                                        setQrPreviewFileName
                                    }
                                    setSignaturePreviewFileName={
                                        setSignaturePreviewFileName
                                    }
                                    customSignatureStampLayout={
                                        data.custom_signature_stamp_layout
                                    }
                                    onCustomSignatureStampLayoutChange={(layout) =>
                                        setData(
                                            'custom_signature_stamp_layout',
                                            layout,
                                        )
                                    }
                                    onCustomSignatureDataChange={(value) =>
                                        setData('custom_signature_data', value)
                                    }
                                />
                                </aside>

                                {/* RIGHT COLUMN: Module Templates */}
                                <main className="w-full min-w-0">
                                    <ModuleTemplateSection
                                    selectedModule={selectedModule}
                                    setSelectedModule={setSelectedModule}
                                    onPreview={(key) => setPreviewModule(key)}
                                    activeModule={activeModule}
                                    activeDefinition={activeDefinition}
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
                                        <AddModuleDialog
                                            key={registeredModulesCount}
                                        />
                                    }
                                    />
                                </main>
                            </div>
                        </div>
                    </form>
                </div>
            </SettingsLayout>

            {/* Preview Modal */}
            {previewModule !== null && previewModuleDefinition && (
                <ReportTemplatePreviewModal
                    open={previewModule !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setPreviewModule(null);
                        }
                    }}
                    moduleLabel={previewModuleDefinition.label}
                    selectedModule={previewModule}
                    brand_color={data.brand_color || '#c05427'}
                    company_name={data.company_name}
                    company_address={data.company_address}
                    company_phone={data.company_phone}
                    company_email={data.company_email}
                    page_margin_preset={data.page_margin_preset}
                    section_spacing_preset={data.section_spacing_preset}
                    custom_signature_stamp_layout={
                        data.custom_signature_stamp_layout
                    }
                    module_templates={data.module_templates}
                />
            )}
        </AppLayout>
    );
}

