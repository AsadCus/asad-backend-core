import { DocumentField } from '@/components/document-field';
import { FormField } from '@/components/form-field';
import { ProperInput } from '@/components/proper-input';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Settings2 } from 'lucide-react';
import { SignatureStampLayoutSection } from './signature-stamp-layout-section';
import type { SignatureStampLayoutConfig } from './types';

interface GlobalBrandingSectionProps {
    data: {
        company_name: string;
        company_address: string;
        company_phone: string;
        company_email: string;
        brand_color: string;
        page_margin_preset?: 'narrow' | 'normal' | 'wide';
        section_spacing_preset?: 'compact' | 'normal' | 'relaxed';
        qr_alignment?: 'left' | 'center' | 'right';
        qr_width?: number;
        qr_height?: number;
        logo_file?: File | null;
        qr_file?: File | null;
        stamp_file?: File | null;
        signature_file?: File | null;
        custom_signature_data?: string | null;
        custom_signature_path?: string | null;
        logo_path?: string | null;
        qr_image_path?: string | null;
        stamp_path?: string | null;
        signature_path?: string | null;
    };
    errors: Record<string, string | undefined>;
    initialLogoDatabasePath: string | null;
    initialQrDatabasePath: string | null;
    initialStampDatabasePath: string | null;
    initialSignatureDatabasePath: string | null;
    customSignatureStampLayout: SignatureStampLayoutConfig;
    onCustomSignatureStampLayoutChange: (
        value: SignatureStampLayoutConfig,
    ) => void;
    onCustomSignatureDataChange: (value: string | null) => void;
    onDataChange: (field: string, value: string) => void;
    makeFileHandler: (
        field: 'logo_file' | 'qr_file' | 'stamp_file' | 'signature_file',
        setPreviewFileName: (v: string | null) => void,
    ) => (file: File) => void;
    makeClearHandler: (
        field: 'logo_file' | 'qr_file' | 'stamp_file' | 'signature_file',
        setPreviewFileName: (v: string | null) => void,
        pathKey:
            | 'logo_path'
            | 'qr_image_path'
            | 'stamp_path'
            | 'signature_path',
        hasDatabaseFile: boolean,
    ) => () => void;
    logoPreviewFileName: string | null;
    qrPreviewFileName: string | null;
    stampPreviewFileName: string | null;
    signaturePreviewFileName: string | null;
    setLogoPreviewFileName: (v: string | null) => void;
    setQrPreviewFileName: (v: string | null) => void;
    setStampPreviewFileName: (v: string | null) => void;
    setSignaturePreviewFileName: (v: string | null) => void;
}

export function GlobalBrandingSection({
    data,
    errors,
    logoPreviewFileName,
    qrPreviewFileName,
    stampPreviewFileName,
    signaturePreviewFileName,
    initialLogoDatabasePath,
    initialQrDatabasePath,
    initialStampDatabasePath,
    initialSignatureDatabasePath,
    customSignatureStampLayout,
    onCustomSignatureStampLayoutChange,
    onCustomSignatureDataChange,
    onDataChange,
    makeFileHandler,
    makeClearHandler,
    setLogoPreviewFileName,
    setQrPreviewFileName,
    setStampPreviewFileName,
    setSignaturePreviewFileName,
}: GlobalBrandingSectionProps) {
    const stampPreviewUrl = data.stamp_file
        ? URL.createObjectURL(data.stamp_file)
        : data.stamp_path !== '' && initialStampDatabasePath
          ? `/storage/${initialStampDatabasePath}`
          : null;

    const signaturePreviewUrl =
        data.custom_signature_data ||
        (data.custom_signature_path && data.custom_signature_path !== ''
            ? `/storage/${data.custom_signature_path}`
            : data.signature_file instanceof File
              ? URL.createObjectURL(data.signature_file)
              : data.signature_path !== '' && initialSignatureDatabasePath
                ? `/storage/${initialSignatureDatabasePath}`
                : null);

    return (
        <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
            {/* Section Header */}
            <div className="border-b bg-muted/40 px-6 py-5">
                <p className="text-base font-medium">Global Branding</p>
                <p className="mt-0.5 text-base text-muted-foreground">
                    Company info and assets shared across all document types
                </p>
            </div>

            <div className="divide-y">
                <div className="space-y-6 p-8">
                    <div className="grid grid-cols-1 items-start gap-6 lg:grid-cols-2">
                        <FormField
                            label="Company Name"
                            fieldRequirementsProps={{
                                required: true,
                                hint: 'Legal or brand name shown in report header.',
                            }}
                            htmlFor="company_name"
                            error={errors.company_name}
                        >
                            <Input
                                id="company_name"
                                value={data.company_name}
                                onChange={(e) =>
                                    onDataChange('company_name', e.target.value)
                                }
                                required
                            />
                        </FormField>

                        <FormField
                            label="Company Address"
                            fieldRequirementsProps={{
                                hint: 'Printed below the company name in report header.',
                            }}
                            htmlFor="company_address"
                            error={errors.company_address}
                        >
                            <Textarea
                                id="company_address"
                                value={data.company_address}
                                onChange={(e) =>
                                    onDataChange(
                                        'company_address',
                                        e.target.value,
                                    )
                                }
                                rows={3}
                            />
                        </FormField>
                    </div>

                    <div className="grid grid-cols-1 items-start gap-6 lg:grid-cols-2">
                        <FormField
                            label="Phone"
                            fieldRequirementsProps={{
                                hint: 'Contact number displayed in report header.',
                            }}
                            htmlFor="company_phone"
                            error={errors.company_phone}
                        >
                            <Input
                                id="company_phone"
                                value={data.company_phone}
                                onChange={(e) =>
                                    onDataChange(
                                        'company_phone',
                                        e.target.value,
                                    )
                                }
                            />
                        </FormField>

                        <FormField
                            label="Email"
                            fieldRequirementsProps={{
                                hint: 'Contact email displayed in report header.',
                            }}
                            htmlFor="company_email"
                            error={errors.company_email}
                        >
                            <Input
                                id="company_email"
                                type="email"
                                value={data.company_email}
                                onChange={(e) =>
                                    onDataChange(
                                        'company_email',
                                        e.target.value,
                                    )
                                }
                            />
                        </FormField>
                    </div>

                    <div className="grid grid-cols-1 items-start gap-6 lg:grid-cols-2">
                        <DocumentField
                            label="Company Logo"
                            hint="Displayed top-left on all PDFs"
                            accept="image/jpeg,image/png,image/jpg"
                            fileValue={data.logo_file || undefined}
                            existingPath={
                                data.logo_path !== ''
                                    ? initialLogoDatabasePath || undefined
                                    : undefined
                            }
                            existingFileName={logoPreviewFileName || undefined}
                            isView={false}
                            disabled={false}
                            error={errors.logo_file}
                            onSelect={makeFileHandler(
                                'logo_file',
                                setLogoPreviewFileName,
                            )}
                            onClear={makeClearHandler(
                                'logo_file',
                                setLogoPreviewFileName,
                                'logo_path',
                                !!initialLogoDatabasePath,
                            )}
                        />

                        <FormField
                            label="Brand Color"
                            fieldRequirementsProps={{
                                hint: 'Applied to all document title bars',
                            }}
                            htmlFor="brand_color"
                            error={errors.brand_color}
                        >
                            <div className="flex items-center gap-2">
                                <div
                                    className="h-9 w-9 shrink-0 rounded-md border shadow-sm"
                                    style={{
                                        backgroundColor:
                                            data.brand_color || '#c05427',
                                    }}
                                />
                                <Input
                                    id="brand_color"
                                    type="color"
                                    value={data.brand_color || '#c05427'}
                                    onChange={(e) =>
                                        onDataChange(
                                            'brand_color',
                                            e.target.value,
                                        )
                                    }
                                    className="h-9 w-10 cursor-pointer rounded border p-0.5"
                                />
                                <Input
                                    type="text"
                                    value={data.brand_color || '#c05427'}
                                    onChange={(e) =>
                                        onDataChange(
                                            'brand_color',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="#c05427"
                                    className="flex-1 font-mono text-sm"
                                    maxLength={7}
                                />
                            </div>
                        </FormField>
                    </div>

                    <div className="grid grid-cols-1 items-start gap-6 lg:grid-cols-2">
                        <FormField
                            label="Page Margin"
                            fieldRequirementsProps={{
                                hint: 'Word-like A4 margin preset (applies to all sides)',
                            }}
                            htmlFor="page_margin_preset"
                            error={errors.page_margin_preset}
                        >
                            <Select
                                value={data.page_margin_preset || 'normal'}
                                onValueChange={(value) =>
                                    onDataChange('page_margin_preset', value)
                                }
                            >
                                <SelectTrigger id="page_margin_preset">
                                    <SelectValue placeholder="Select page margin" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="narrow">
                                        Narrow (1.27 cm)
                                    </SelectItem>
                                    <SelectItem value="normal">
                                        Normal (2.54 cm)
                                    </SelectItem>
                                    <SelectItem value="wide">
                                        Wide (3.17 cm)
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </FormField>

                        <FormField
                            label="Section Spacing"
                            fieldRequirementsProps={{
                                hint: 'Spacing for report components/rows (excluding items)',
                            }}
                            htmlFor="section_spacing_preset"
                            error={errors.section_spacing_preset}
                        >
                            <Select
                                value={data.section_spacing_preset || 'normal'}
                                onValueChange={(value) =>
                                    onDataChange('section_spacing_preset', value)
                                }
                            >
                                <SelectTrigger id="section_spacing_preset">
                                    <SelectValue placeholder="Select section spacing" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="compact">Compact</SelectItem>
                                    <SelectItem value="normal">Normal</SelectItem>
                                    <SelectItem value="relaxed">Relaxed</SelectItem>
                                </SelectContent>
                            </Select>
                        </FormField>
                    </div>

                    <div className="rounded-xl border bg-muted/20 p-4">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <p className="text-base font-medium">
                                    Signature &amp; Stamp Configure
                                </p>
                                <p className="mt-0.5 text-base text-muted-foreground">
                                    Upload files and configure placement layout
                                </p>
                            </div>

                            <Dialog>
                                <DialogTrigger asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="flex items-center gap-1.5"
                                    >
                                        <Settings2 className="h-4 w-4" />
                                        Configure
                                    </Button>
                                </DialogTrigger>
                                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-5xl">
                                    <DialogHeader className="mb-4">
                                        <DialogTitle className="text-xl">
                                            Signature and Stamp Layout
                                        </DialogTitle>
                                        <DialogDescription>
                                            Adjust the positions, upload files,
                                            and draw your signature for document
                                            reports.
                                        </DialogDescription>
                                    </DialogHeader>

                                    <div className="mb-2 grid grid-cols-1 items-start gap-6 rounded-xl border bg-muted/10 p-5 sm:grid-cols-2">
                                        <DocumentField
                                            label="Company Stamp"
                                            hint="Image file for the company stamp"
                                            accept="image/jpeg,image/png,image/jpg"
                                            fileValue={
                                                data.stamp_file || undefined
                                            }
                                            existingPath={
                                                data.stamp_path !== ''
                                                    ? initialStampDatabasePath ||
                                                      undefined
                                                    : undefined
                                            }
                                            existingFileName={
                                                stampPreviewFileName ||
                                                undefined
                                            }
                                            isView={false}
                                            disabled={false}
                                            error={errors.stamp_file}
                                            onSelect={makeFileHandler(
                                                'stamp_file',
                                                setStampPreviewFileName,
                                            )}
                                            onClear={makeClearHandler(
                                                'stamp_file',
                                                setStampPreviewFileName,
                                                'stamp_path',
                                                !!initialStampDatabasePath,
                                            )}
                                        />
                                        <DocumentField
                                            label="Authorised Signature"
                                            hint="Image file for the signature (or draw below)"
                                            accept="image/jpeg,image/png,image/jpg"
                                            fileValue={
                                                data.signature_file || undefined
                                            }
                                            existingPath={
                                                data.signature_path !== ''
                                                    ? initialSignatureDatabasePath ||
                                                      undefined
                                                    : undefined
                                            }
                                            existingFileName={
                                                signaturePreviewFileName ||
                                                undefined
                                            }
                                            isView={false}
                                            disabled={false}
                                            error={errors.signature_file}
                                            onSelect={makeFileHandler(
                                                'signature_file',
                                                setSignaturePreviewFileName,
                                            )}
                                            onClear={makeClearHandler(
                                                'signature_file',
                                                setSignaturePreviewFileName,
                                                'signature_path',
                                                !!initialSignatureDatabasePath,
                                            )}
                                        />
                                    </div>

                                    <SignatureStampLayoutSection
                                        customSignatureStampLayout={
                                            customSignatureStampLayout
                                        }
                                        onCustomSignatureStampLayoutChange={
                                            onCustomSignatureStampLayoutChange
                                        }
                                        onCustomSignatureDataChange={
                                            onCustomSignatureDataChange
                                        }
                                        stampPreviewPath={stampPreviewUrl}
                                        signaturePreviewPath={
                                            signaturePreviewUrl
                                        }
                                        customSignatureData={
                                            data.custom_signature_data ?? null
                                        }
                                        errors={errors}
                                    />

                                    <div className="mt-6 flex justify-end border-t pt-4">
                                        <DialogTrigger asChild>
                                            <Button type="button">Done</Button>
                                        </DialogTrigger>
                                    </div>
                                </DialogContent>
                            </Dialog>
                        </div>

                        {(initialStampDatabasePath ||
                            initialSignatureDatabasePath ||
                            data.stamp_file ||
                            data.signature_file) && (
                            <div className="mt-3 flex flex-wrap gap-2">
                                {(initialStampDatabasePath ||
                                    data.stamp_file) && (
                                    <span className="inline-flex items-center gap-1 rounded-full bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700 ring-1 ring-green-200">
                                        <span className="h-1.5 w-1.5 rounded-full bg-green-500" />
                                        Stamp uploaded
                                    </span>
                                )}
                                {(initialSignatureDatabasePath ||
                                    data.signature_file ||
                                    data.custom_signature_data) && (
                                    <span className="inline-flex items-center gap-1 rounded-full bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700 ring-1 ring-green-200">
                                        <span className="h-1.5 w-1.5 rounded-full bg-green-500" />
                                        Signature uploaded
                                    </span>
                                )}
                            </div>
                        )}
                    </div>
                </div>

                <div className="p-8">
                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <DocumentField
                            label="QR Image"
                            hint="Displayed near signature and stamp in report footer"
                            accept=".jpeg,.jpg,.png,.webp"
                            fileValue={data.qr_file || undefined}
                            existingPath={
                                data.qr_image_path !== ''
                                    ? initialQrDatabasePath || undefined
                                    : undefined
                            }
                            existingFileName={qrPreviewFileName || undefined}
                            isView={false}
                            disabled={false}
                            error={errors.qr_file}
                            onSelect={makeFileHandler(
                                'qr_file',
                                setQrPreviewFileName,
                            )}
                            onClear={makeClearHandler(
                                'qr_file',
                                setQrPreviewFileName,
                                'qr_image_path',
                                !!initialQrDatabasePath,
                            )}
                        />

                        <div className="space-y-4">
                            <FormField
                                label="QR Alignment"
                                fieldRequirementsProps={{
                                    hint: 'Horizontal placement for QR image in report footer area.',
                                }}
                                htmlFor="qr_alignment"
                                error={errors.qr_alignment}
                            >
                                <Select
                                    value={data.qr_alignment || 'center'}
                                    onValueChange={(value) =>
                                        onDataChange('qr_alignment', value)
                                    }
                                >
                                    <SelectTrigger id="qr_alignment">
                                        <SelectValue placeholder="Select alignment" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="left">
                                            Left
                                        </SelectItem>
                                        <SelectItem value="center">
                                            Center
                                        </SelectItem>
                                        <SelectItem value="right">
                                            Right
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <div className="grid grid-cols-2 items-start gap-4">
                                <FormField
                                    label="Width (px)"
                                    fieldRequirementsProps={{
                                        hint: 'Controls QR image width in pixels.',
                                    }}
                                    htmlFor="qr_width"
                                    error={errors.qr_width}
                                >
                                    <Input
                                        id="qr_width"
                                        type="number"
                                        value={data.qr_width ?? 120}
                                        onChange={(e) =>
                                            onDataChange(
                                                'qr_width',
                                                e.target.value,
                                            )
                                        }
                                        min={50}
                                        max={500}
                                    />
                                </FormField>
                                <FormField
                                    label="Height (px)"
                                    fieldRequirementsProps={{
                                        hint: 'Height is auto-calculated to preserve aspect ratio.',
                                    }}
                                    htmlFor="qr_height"
                                    error={errors.qr_height}
                                >
                                    <ProperInput
                                        id="qr_height"
                                        type="text"
                                        value="Auto"
                                        onCommit={() => {}}
                                        disabled
                                    />
                                </FormField>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Info */}
                <div className="bg-gradient-to-r from-blue-50/80 to-blue-50/40 px-6 py-5">
                    <div className="flex items-start gap-3">
                        <div className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-500 text-white">
                            <span className="text-xs font-bold">i</span>
                        </div>
                        <div>
                            <p className="mb-1 text-xs font-semibold text-blue-700">
                                Info
                            </p>
                            <p className="text-xs leading-relaxed text-blue-600/90">
                                Global branding applies to all document types
                                unless overridden in the Module Template
                                section.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
