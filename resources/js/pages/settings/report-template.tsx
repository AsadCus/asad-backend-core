import HeadingSmall from '@/components/heading-small';
import { ImagePreviewDialog } from '@/components/image-preview-dialog';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { update as updateReportTemplate } from '@/routes/report-template';
import {
    destroy as destroyModuleRoute,
    store as storeModuleRoute,
} from '@/routes/report-template/modules';
import { Transition } from '@headlessui/react';
import { Head, router, useForm } from '@inertiajs/react';
import { ChevronDown, ChevronUp, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface ModuleTemplate {
    title_color: string;
    footer_text: string;
    show_stamp: boolean;
    show_signature: boolean;
}

interface RegisteredModule {
    key: string;
    label: string;
    document_type: string;
}

interface ReportTemplateSettings {
    company_name: string;
    company_address: string;
    company_phone: string;
    company_email: string;
    footer_text: string;
    logo_path: string | null;
    stamp_path: string | null;
    signature_path: string | null;
    module_templates: Record<string, ModuleTemplate>;
    registered_modules: RegisteredModule[];
}

interface ReportTemplateData {
    settings: ReportTemplateSettings;
}

interface ReportTemplateFormData {
    company_name: string;
    company_address: string;
    company_phone: string;
    company_email: string;
    footer_text: string;
    _method: 'put';
    logo_file: File | null;
    stamp_file: File | null;
    signature_file: File | null;
    module_templates: Record<string, ModuleTemplate>;
}

// ─────────────────────────────────────────────────────────────────────────────
// Built-in modules (hardcoded, cannot be deleted)
// ─────────────────────────────────────────────────────────────────────────────

const BUILTIN_MODULES: RegisteredModule[] = [
    { key: 'quotation', label: 'Quotation', document_type: 'QUOTATION' },
    { key: 'invoice', label: 'Invoice', document_type: 'INVOICE' },
    {
        key: 'receipt',
        label: 'Official Receipt',
        document_type: 'OFFICIAL RECEIPT',
    },
    { key: 'agreement', label: 'Agreement', document_type: 'AGREEMENT' },
    { key: 'sales', label: 'Sales Profile', document_type: 'SALES PROFILE' },
];

// ─────────────────────────────────────────────────────────────────────────────
// PDF Preview Component
// ─────────────────────────────────────────────────────────────────────────────

interface PdfPreviewProps {
    titleColor: string;
    footerText: string;
    showStamp: boolean;
    showSignature: boolean;
    documentType: string;
    companyName: string;
    companyPhone: string;
    companyEmail: string;
    companyAddress: string;
    logoPreview: string | null;
    stampPreview: string | null;
    signaturePreview: string | null;
}

function PdfPreview({
    titleColor,
    footerText,
    showStamp,
    showSignature,
    documentType,
    companyName,
    companyPhone,
    companyEmail,
    companyAddress,
    logoPreview,
    stampPreview,
    signaturePreview,
}: PdfPreviewProps) {
    return (
        <div className="w-full overflow-hidden rounded-lg border bg-white shadow-sm">
            <div className="flex items-center justify-between border-b bg-muted/50 px-3 py-2">
                <span className="text-sm font-medium text-muted-foreground">
                    PDF Preview
                </span>
                <span className="text-sm text-muted-foreground italic">
                    Sample only
                </span>
            </div>
            <div
                className="space-y-3 p-4"
                style={{ fontFamily: 'Arial, sans-serif', fontSize: '9px' }}
            >
                <div className="flex items-start justify-between gap-2">
                    <div className="flex-shrink-0">
                        {logoPreview ? (
                            <img
                                src={logoPreview}
                                alt="Logo"
                                className="h-10 w-auto object-contain"
                            />
                        ) : (
                            <div className="flex h-10 w-20 items-center justify-center rounded bg-gray-100 text-[8px] text-gray-400">
                                Your Logo
                            </div>
                        )}
                    </div>
                    <div className="text-right text-[8px] leading-tight text-gray-600">
                        <div className="text-[9px] font-bold text-gray-800">
                            {companyName || 'Company Name'}
                        </div>
                        <div className="whitespace-pre-line">
                            {companyAddress || 'Company Address, Singapore'}
                        </div>
                        {companyPhone && (
                            <div className="mt-0.5">Tel: {companyPhone}</div>
                        )}
                        {companyEmail && <div>Email: {companyEmail}</div>}
                        <div className="mt-0.5 font-semibold">
                            LICENCE NO. 25C2708
                        </div>
                    </div>
                </div>
                <div
                    className="py-1.5 text-center text-[9px] font-bold tracking-widest text-white"
                    style={{ backgroundColor: titleColor || '#40A09D' }}
                >
                    {documentType || 'DOCUMENT'}
                </div>
                <div className="flex gap-4 text-[8px]">
                    <div className="flex-1 space-y-0.5">
                        <div className="flex">
                            <span className="w-14 font-semibold">Customer</span>
                            <span>: Sample Customer</span>
                        </div>
                        <div className="flex">
                            <span className="w-14 font-semibold">Address</span>
                            <span>: 123 Sample St</span>
                        </div>
                    </div>
                    <div className="space-y-0.5">
                        <div className="flex">
                            <span className="w-20 font-semibold">
                                Doc Number
                            </span>
                            <span>: DOC-2025-0001</span>
                        </div>
                        <div className="flex">
                            <span className="w-20 font-semibold">Date</span>
                            <span>: 01/01/2025</span>
                        </div>
                    </div>
                </div>
                <div className="space-y-0.5 border-t border-b border-gray-800 py-1.5 text-[8px]">
                    <div className="flex justify-between border-b border-gray-800 pb-0.5 font-semibold">
                        <span>Item Description</span>
                        <span>Amount</span>
                    </div>
                    <div className="flex justify-between">
                        <span>1. Sample Item</span>
                        <span>SGD 1,000.00</span>
                    </div>
                </div>
                <div className="text-right text-[9px] font-bold">
                    Total: SGD 1,000.00
                </div>
                <div className="space-y-1.5 border-t pt-2 text-[8px] text-gray-600">
                    {footerText ? (
                        <p className="leading-tight whitespace-pre-wrap">
                            {footerText}
                        </p>
                    ) : (
                        <p className="leading-tight text-gray-400 italic">
                            Footer text will appear here...
                        </p>
                    )}
                    {(showStamp || showSignature) && (
                        <div className="mt-2 flex gap-4">
                            {showStamp &&
                                (stampPreview ? (
                                    <img
                                        src={stampPreview}
                                        alt="Stamp"
                                        className="h-8 w-auto object-contain opacity-70"
                                    />
                                ) : (
                                    <div className="flex h-8 w-14 items-center justify-center rounded border border-dashed border-gray-300 text-[7px] text-gray-400">
                                        Stamp
                                    </div>
                                ))}
                            {showSignature && (
                                <div>
                                    {signaturePreview ? (
                                        <img
                                            src={signaturePreview}
                                            alt="Signature"
                                            className="h-8 w-auto object-contain opacity-70"
                                        />
                                    ) : (
                                        <div className="flex h-8 w-16 items-center justify-center rounded border border-dashed border-gray-300 text-[7px] text-gray-400">
                                            Signature
                                        </div>
                                    )}
                                    <div className="mt-0.5 text-[7px] text-gray-400">
                                        Authorised Signature
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// File Upload Field
// ─────────────────────────────────────────────────────────────────────────────

interface FileUploadFieldProps {
    id: string;
    label: string;
    hint: string;
    preview: string | null;
    previewAlt: string;
    error?: string;
    onChange: (e: React.ChangeEvent<HTMLInputElement>) => void;
    onClear: () => void;
}

function FileUploadField({
    id,
    label,
    hint,
    preview,
    previewAlt,
    error,
    onChange,
    onClear,
}: FileUploadFieldProps) {
    return (
        <div>
            <Label htmlFor={id}>{label}</Label>
            <p className="mt-0.5 mb-2 text-sm text-muted-foreground">{hint}</p>
            <Input
                id={id}
                type="file"
                accept="image/jpeg,image/png,image/jpg"
                onChange={onChange}
                className="mt-1 block w-full"
            />
            <p className="mt-1 text-sm text-muted-foreground">
                Accepted: JPG, JPEG, PNG. Max 2MB
            </p>
            <InputError message={error} className="mt-2" />
            {preview && (
                <div className="mt-3 flex items-center gap-3">
                    <ImagePreviewDialog
                        imageSrc={preview}
                        imageAlt={previewAlt}
                        title={previewAlt}
                        thumbnailSize={80}
                        rounded="rounded"
                    />
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={onClear}
                    >
                        Clear
                    </Button>
                </div>
            )}
        </div>
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// Add Module Dialog
// ─────────────────────────────────────────────────────────────────────────────

function AddModuleDialog() {
    const [open, setOpen] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [fields, setFields] = useState({
        key: '',
        label: '',
        document_type: '',
    });
    const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        setSubmitting(true);
        setFieldErrors({});
        router.post(
            storeModuleRoute().url,
            {
                key: fields.key,
                label: fields.label,
                document_type: fields.document_type,
            },
            {
                // No preserveState — let Inertia reload the page so new
                // registered_modules props come in, which changes the `key`
                // on this component causing React to remount it (open → false).
                onError: (errs) => {
                    setFieldErrors(errs as Record<string, string>);
                    setSubmitting(false);
                },
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="flex items-center gap-1.5"
                >
                    <Plus className="h-3.5 w-3.5" />
                    Add Module
                </Button>
            </DialogTrigger>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Add Document Module</DialogTitle>
                    <DialogDescription>
                        Register a new document type for template customisation.
                        The PDF blade template for this module still needs to be
                        created by a developer.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4 pt-2">
                    <div>
                        <Label htmlFor="mod_key">
                            Module Key <span className="text-red-500">*</span>
                        </Label>
                        <p className="mt-0.5 mb-1 text-sm text-muted-foreground">
                            Unique identifier, lowercase letters, numbers,
                            underscores only. E.g.{' '}
                            <code className="rounded bg-muted px-1 text-sm">
                                manifest
                            </code>
                        </p>
                        <Input
                            id="mod_key"
                            value={fields.key}
                            onChange={(e) =>
                                setFields((f) => ({
                                    ...f,
                                    key: e.target.value
                                        .toLowerCase()
                                        .replace(/[^a-z0-9_]/g, ''),
                                }))
                            }
                            placeholder="manifest"
                            required
                        />
                        <InputError
                            message={fieldErrors.key}
                            className="mt-1"
                        />
                    </div>
                    <div>
                        <Label htmlFor="mod_label">
                            Display Label{' '}
                            <span className="text-red-500">*</span>
                        </Label>
                        <p className="mt-0.5 mb-1 text-sm text-muted-foreground">
                            Shown in the module dropdown. E.g.{' '}
                            <code className="rounded bg-muted px-1 text-sm">
                                Manifest
                            </code>
                        </p>
                        <Input
                            id="mod_label"
                            value={fields.label}
                            onChange={(e) =>
                                setFields((f) => ({
                                    ...f,
                                    label: e.target.value,
                                }))
                            }
                            placeholder="Manifest"
                            required
                        />
                        <InputError
                            message={fieldErrors.label}
                            className="mt-1"
                        />
                    </div>
                    <div>
                        <Label htmlFor="mod_doctype">
                            Document Type Label{' '}
                            <span className="text-red-500">*</span>
                        </Label>
                        <p className="mt-0.5 mb-1 text-sm text-muted-foreground">
                            Appears as title in the PDF. E.g.{' '}
                            <code className="rounded bg-muted px-1 text-sm">
                                MANIFEST
                            </code>
                        </p>
                        <Input
                            id="mod_doctype"
                            value={fields.document_type}
                            onChange={(e) =>
                                setFields((f) => ({
                                    ...f,
                                    document_type: e.target.value.toUpperCase(),
                                }))
                            }
                            placeholder="MANIFEST"
                            required
                        />
                        <InputError
                            message={fieldErrors.document_type}
                            className="mt-1"
                        />
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={submitting}>
                            Add Module
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Page
// ─────────────────────────────────────────────────────────────────────────────

export default function ReportTemplate({ settings }: ReportTemplateData) {
    const [brandingOpen, setBrandingOpen] = useState(true);

    // Combine built-in + custom registered modules
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

    const {
        data,
        setData,
        post,
        transform,
        errors,
        processing,
        recentlySuccessful,
    } = useForm<ReportTemplateFormData>({
        company_name: settings.company_name,
        company_address: settings.company_address || '',
        company_phone: settings.company_phone || '',
        company_email: settings.company_email || '',
        footer_text: settings.footer_text || '',
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

    const updateModule = (
        field: keyof ModuleTemplate,
        value: string | boolean,
    ) => {
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
                // Reset to first built-in so we don't land on a deleted module
                setSelectedModule(BUILTIN_MODULES[0].key);
            },
        });
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        transform((currentData: ReportTemplateFormData) => {
            const transformedData: Record<string, unknown> = {
                company_name: currentData.company_name,
                company_address: currentData.company_address || '',
                company_phone: currentData.company_phone || '',
                company_email: currentData.company_email || '',
                footer_text: currentData.footer_text || '',
                _method: 'put',
                module_templates: currentData.module_templates,
            };

            if (currentData.logo_file instanceof File) {
                transformedData.logo_file = currentData.logo_file;
            }

            if (currentData.stamp_file instanceof File) {
                transformedData.stamp_file = currentData.stamp_file;
            }

            if (currentData.signature_file instanceof File) {
                transformedData.signature_file = currentData.signature_file;
            }

            return transformedData;
        });

        post(updateReportTemplate.url(), {
            forceFormData: true,
            onFinish: () => {
                transform((currentData: ReportTemplateFormData) => currentData);
            },
        });
    };

    const activeModule = data.module_templates[selectedModule] ?? {
        title_color: '#40A09D',
        footer_text: '',
        show_stamp: false,
        show_signature: false,
    };
    const activeDefinition = allModules.find((m) => m.key === selectedModule);
    const isBuiltin = BUILTIN_MODULES.some((m) => m.key === selectedModule);

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
                        {/* ── Section 1: Global Branding ── */}
                        <div className="overflow-hidden rounded-lg border shadow-sm">
                            <button
                                type="button"
                                onClick={() => setBrandingOpen((v) => !v)}
                                className="flex w-full items-center justify-between bg-muted/40 px-4 py-3 text-left transition-colors hover:bg-muted/60"
                            >
                                <div>
                                    <p className="text-base font-semibold">
                                        Global Branding
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        Company info and assets shared across
                                        all document types
                                    </p>
                                </div>
                                {brandingOpen ? (
                                    <ChevronUp className="h-4 w-4 flex-shrink-0 text-muted-foreground" />
                                ) : (
                                    <ChevronDown className="h-4 w-4 flex-shrink-0 text-muted-foreground" />
                                )}
                            </button>

                            {brandingOpen && (
                                <div className="space-y-5 p-5">
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div className="sm:col-span-2">
                                            <Label htmlFor="company_name">
                                                Company Name{' '}
                                                <span className="text-red-500">
                                                    *
                                                </span>
                                            </Label>
                                            <Input
                                                id="company_name"
                                                value={data.company_name}
                                                onChange={(e) =>
                                                    setData(
                                                        'company_name',
                                                        e.target.value,
                                                    )
                                                }
                                                className="mt-1"
                                                required
                                            />
                                            <InputError
                                                message={errors.company_name}
                                                className="mt-2"
                                            />
                                        </div>
                                        <div className="sm:col-span-2">
                                            <Label htmlFor="company_address">
                                                Company Address
                                            </Label>
                                            <Textarea
                                                id="company_address"
                                                value={data.company_address}
                                                onChange={(e) =>
                                                    setData(
                                                        'company_address',
                                                        e.target.value,
                                                    )
                                                }
                                                className="mt-1"
                                                rows={3}
                                            />
                                            <InputError
                                                message={errors.company_address}
                                                className="mt-2"
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="company_phone">
                                                Company Phone
                                            </Label>
                                            <Input
                                                id="company_phone"
                                                value={data.company_phone}
                                                onChange={(e) =>
                                                    setData(
                                                        'company_phone',
                                                        e.target.value,
                                                    )
                                                }
                                                className="mt-1"
                                            />
                                            <InputError
                                                message={errors.company_phone}
                                                className="mt-2"
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="company_email">
                                                Company Email
                                            </Label>
                                            <Input
                                                id="company_email"
                                                type="email"
                                                value={data.company_email}
                                                onChange={(e) =>
                                                    setData(
                                                        'company_email',
                                                        e.target.value,
                                                    )
                                                }
                                                className="mt-1"
                                            />
                                            <InputError
                                                message={errors.company_email}
                                                className="mt-2"
                                            />
                                        </div>
                                    </div>

                                    <Separator />

                                    <div className="grid grid-cols-1 gap-5 sm:grid-cols-3">
                                        <FileUploadField
                                            id="logo_file"
                                            label="Company Logo"
                                            hint="Displayed top-left on all PDFs."
                                            preview={logoPreview}
                                            previewAlt="Company Logo"
                                            error={errors.logo_file}
                                            onChange={makeFileHandler(
                                                'logo_file',
                                                setLogoPreview,
                                            )}
                                            onClear={makeClearHandler(
                                                'logo_file',
                                                setLogoPreview,
                                            )}
                                        />
                                        <FileUploadField
                                            id="stamp_file"
                                            label="Company Stamp"
                                            hint="Enable per-module in the section below."
                                            preview={stampPreview}
                                            previewAlt="Company Stamp"
                                            error={errors.stamp_file}
                                            onChange={makeFileHandler(
                                                'stamp_file',
                                                setStampPreview,
                                            )}
                                            onClear={makeClearHandler(
                                                'stamp_file',
                                                setStampPreview,
                                            )}
                                        />
                                        <FileUploadField
                                            id="signature_file"
                                            label="Authorised Signature"
                                            hint="Enable per-module in the section below."
                                            preview={signaturePreview}
                                            previewAlt="Authorised Signature"
                                            error={errors.signature_file}
                                            onChange={makeFileHandler(
                                                'signature_file',
                                                setSignaturePreview,
                                            )}
                                            onClear={makeClearHandler(
                                                'signature_file',
                                                setSignaturePreview,
                                            )}
                                        />
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* ── Section 2: Module Templates ── */}
                        <div className="overflow-hidden rounded-lg border shadow-sm">
                            {/* Header with Module Selector + Add/Delete */}
                            <div className="flex flex-wrap items-center justify-between gap-3 border-b bg-muted/40 px-4 py-3">
                                <div>
                                    <p className="text-base font-semibold">
                                        Module Template
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        Customize PDF appearance per document
                                        type
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Select
                                        value={selectedModule}
                                        onValueChange={setSelectedModule}
                                    >
                                        <SelectTrigger className="w-44">
                                            <SelectValue placeholder="Select module" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {BUILTIN_MODULES.length > 0 && (
                                                <div className="px-2 py-1 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                                    Built-in
                                                </div>
                                            )}
                                            {BUILTIN_MODULES.map((m) => (
                                                <SelectItem
                                                    key={m.key}
                                                    value={m.key}
                                                >
                                                    {m.label}
                                                </SelectItem>
                                            ))}
                                            {settings.registered_modules
                                                ?.length > 0 && (
                                                <div className="mt-1 border-t px-2 py-1 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                                    Custom
                                                </div>
                                            )}
                                            {(
                                                settings.registered_modules ??
                                                []
                                            ).map((m) => (
                                                <SelectItem
                                                    key={m.key}
                                                    value={m.key}
                                                >
                                                    {m.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>

                                    {/* Delete custom module */}
                                    {!isBuiltin && (
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            size="sm"
                                            onClick={() =>
                                                handleDeleteModule(
                                                    selectedModule,
                                                )
                                            }
                                            className="flex items-center gap-1.5"
                                        >
                                            <Trash2 className="h-3.5 w-3.5" />
                                            Delete
                                        </Button>
                                    )}

                                    {/* Add new module — key forces remount when module count changes, closing the dialog */}
                                    <AddModuleDialog
                                        key={
                                            settings.registered_modules
                                                ?.length ?? 0
                                        }
                                    />
                                </div>
                            </div>

                            {/* Module Settings + Preview */}
                            <div className="p-5">
                                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                                    {/* Left: Settings */}
                                    <div className="space-y-5">
                                        {/* Title Bar Color */}
                                        <div>
                                            <Label htmlFor="title_color">
                                                Title Bar Color
                                            </Label>
                                            <p className="mt-0.5 mb-2 text-sm text-muted-foreground">
                                                Background color for the{' '}
                                                <strong>
                                                    {activeDefinition?.label ??
                                                        selectedModule}
                                                </strong>{' '}
                                                document title bar.
                                            </p>
                                            <div className="flex items-center gap-3">
                                                <div
                                                    className="h-9 w-12 flex-shrink-0 rounded border shadow-sm"
                                                    style={{
                                                        backgroundColor:
                                                            activeModule?.title_color ||
                                                            '#40A09D',
                                                    }}
                                                />
                                                <Input
                                                    id="title_color"
                                                    type="color"
                                                    value={
                                                        activeModule?.title_color ||
                                                        '#40A09D'
                                                    }
                                                    onChange={(e) =>
                                                        updateModule(
                                                            'title_color',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="h-9 w-14 cursor-pointer rounded border p-0.5"
                                                />
                                                <Input
                                                    type="text"
                                                    value={
                                                        activeModule?.title_color ||
                                                        '#40A09D'
                                                    }
                                                    onChange={(e) =>
                                                        updateModule(
                                                            'title_color',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="#40A09D"
                                                    className="flex-1 font-mono text-base"
                                                    maxLength={7}
                                                />
                                            </div>
                                        </div>

                                        {/* Footer Text */}
                                        <div>
                                            <Label htmlFor="module_footer_text">
                                                Footer Text
                                            </Label>
                                            <p className="mt-0.5 mb-2 text-sm text-muted-foreground">
                                                Custom footer for{' '}
                                                <strong>
                                                    {activeDefinition?.label ??
                                                        selectedModule}
                                                </strong>
                                                . Leave blank to use default.
                                            </p>
                                            <Textarea
                                                id="module_footer_text"
                                                value={
                                                    activeModule?.footer_text ||
                                                    ''
                                                }
                                                onChange={(e) =>
                                                    updateModule(
                                                        'footer_text',
                                                        e.target.value,
                                                    )
                                                }
                                                rows={4}
                                                placeholder="e.g. Payment terms, bank details, or custom notes..."
                                            />
                                        </div>

                                        <Separator />

                                        {/* Toggles */}
                                        <div className="space-y-3">
                                            <h4 className="text-base font-medium">
                                                Document Elements
                                            </h4>
                                            <div className="flex items-center justify-between rounded-lg border p-3 shadow-sm">
                                                <div>
                                                    <p className="text-base font-medium">
                                                        Show Company Stamp
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        Display stamp from
                                                        Branding section.
                                                    </p>
                                                </div>
                                                <Switch
                                                    checked={
                                                        activeModule?.show_stamp ??
                                                        false
                                                    }
                                                    onCheckedChange={(v) =>
                                                        updateModule(
                                                            'show_stamp',
                                                            v,
                                                        )
                                                    }
                                                />
                                            </div>
                                            <div className="flex items-center justify-between rounded-lg border p-3 shadow-sm">
                                                <div>
                                                    <p className="text-base font-medium">
                                                        Show Authorised
                                                        Signature
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        Display signature from
                                                        Branding section.
                                                    </p>
                                                </div>
                                                <Switch
                                                    checked={
                                                        activeModule?.show_signature ??
                                                        false
                                                    }
                                                    onCheckedChange={(v) =>
                                                        updateModule(
                                                            'show_signature',
                                                            v,
                                                        )
                                                    }
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    {/* Right: Live Preview */}
                                    <div className="space-y-2">
                                        <Label className="text-base font-medium">
                                            Live Preview
                                        </Label>
                                        <p className="text-sm text-muted-foreground">
                                            Updates as you change settings
                                            above.
                                        </p>
                                        <PdfPreview
                                            titleColor={
                                                activeModule.title_color
                                            }
                                            footerText={
                                                activeModule.footer_text
                                            }
                                            showStamp={activeModule.show_stamp}
                                            showSignature={
                                                activeModule.show_signature
                                            }
                                            documentType={
                                                activeDefinition?.document_type ??
                                                selectedModule.toUpperCase()
                                            }
                                            companyName={data.company_name}
                                            companyPhone={data.company_phone}
                                            companyEmail={data.company_email}
                                            companyAddress={
                                                data.company_address
                                            }
                                            logoPreview={logoPreview}
                                            stampPreview={stampPreview}
                                            signaturePreview={signaturePreview}
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* ── Save Button ── */}
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
