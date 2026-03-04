import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { update as updateReportTemplate } from '@/routes/report-template';
import { destroy as destroyModuleRoute } from '@/routes/report-template/modules';
import { Transition } from '@headlessui/react';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
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
    _method: 'put';
    logo_file: File | null;
    stamp_file: File | null;
    signature_file: File | null;
    module_templates: Record<string, ModuleTemplate>;
}

const BUILTIN_MODULES: RegisteredModule[] = [
    { key: 'quotation', label: 'Quotation', document_type: 'QUOTATION' },
    { key: 'invoice', label: 'Invoice', document_type: 'INVOICE' },
    { key: 'receipt', label: 'Official Receipt', document_type: 'OFFICIAL RECEIPT' },
    { key: 'agreement', label: 'Agreement', document_type: 'AGREEMENT' },
    { key: 'sales', label: 'Sales Profile', document_type: 'SALES PROFILE' },
];

export default function ReportTemplate({ settings }: { settings: ReportTemplateSettings }) {
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
                title_color: serverData?.title_color ?? '#40A09D',
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
            _method: 'put',
            logo_file: null,
            stamp_file: null,
            signature_file: null,
            module_templates: buildInitialModuleTemplates(),
        });

    const [logoPreview, setLogoPreview] = useState<string | null>(
        settings.logo_path ? `/storage/${settings.logo_path}` : null,
    );
    const [stampPreview, setStampPreview] = useState<string | null>(
        settings.stamp_path ? `/storage/${settings.stamp_path}` : null,
    );
    const [signaturePreview, setSignaturePreview] = useState<string | null>(
        settings.signature_path ? `/storage/${settings.signature_path}` : null,
    );

    const makeFileHandler =
        (
            field: 'logo_file' | 'stamp_file' | 'signature_file',
            setPreview: (v: string | null) => void,
        ) =>
            (e: React.ChangeEvent<HTMLInputElement>) => {
                const file = e.target.files?.[0];
                if (!file) return;
                setData(field, file);
                const reader = new FileReader();
                reader.onloadend = () => setPreview(reader.result as string);
                reader.readAsDataURL(file);
            };

    const makeClearHandler =
        (
            field: 'logo_file' | 'stamp_file' | 'signature_file',
            setPreview: (v: string | null) => void,
        ) =>
            () => {
                setData(field, null);
                setPreview(null);
            };

    const updateModule = (field: keyof ModuleTemplate, value: string | boolean) => {
        setData('module_templates', {
            ...data.module_templates,
            [selectedModule]: {
                ...(data.module_templates[selectedModule] ?? {
                    title_color: '#40A09D',
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
        post(updateReportTemplate.url(), { forceFormData: true });
    };

    const activeModule = data.module_templates[selectedModule] ?? {
        title_color: '#40A09D',
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
            <SettingsLayout>
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
                            setLogoPreview={setLogoPreview}
                            setStampPreview={setStampPreview}
                            setSignaturePreview={setSignaturePreview}
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
                                titleColor: activeModule.title_color,
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
