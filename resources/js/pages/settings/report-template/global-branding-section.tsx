import { DocumentField } from '@/components/document-field';
import { FormField } from '@/components/form-field';
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
        logo_file?: File | null;
        stamp_file?: File | null;
        signature_file?: File | null;
        custom_signature_data?: string | null;
        custom_signature_path?: string | null;
        logo_path?: string | null;
        stamp_path?: string | null;
        signature_path?: string | null;
    };
    errors: Record<string, string | undefined>;
    initialLogoDatabasePath: string | null;
    initialStampDatabasePath: string | null;
    initialSignatureDatabasePath: string | null;
    customSignatureStampLayout: SignatureStampLayoutConfig;
    onCustomSignatureStampLayoutChange: (
        value: SignatureStampLayoutConfig,
    ) => void;
    onCustomSignatureDataChange: (value: string | null) => void;
    onDataChange: (field: string, value: string) => void;
    makeFileHandler: (
        field: 'logo_file' | 'stamp_file' | 'signature_file',
        setPreviewFileName: (v: string | null) => void,
    ) => (file: File) => void;
    makeClearHandler: (
        field: 'logo_file' | 'stamp_file' | 'signature_file',
        setPreviewFileName: (v: string | null) => void,
        pathKey: 'logo_path' | 'stamp_path' | 'signature_path',
        hasDatabaseFile: boolean,
    ) => () => void;
    logoPreviewFileName: string | null;
    stampPreviewFileName: string | null;
    signaturePreviewFileName: string | null;
    setLogoPreviewFileName: (v: string | null) => void;
    setStampPreviewFileName: (v: string | null) => void;
    setSignaturePreviewFileName: (v: string | null) => void;
}

export function GlobalBrandingSection({
    data,
    errors,
    logoPreviewFileName,
    stampPreviewFileName,
    signaturePreviewFileName,
    initialLogoDatabasePath,
    initialStampDatabasePath,
    initialSignatureDatabasePath,
    customSignatureStampLayout,
    onCustomSignatureStampLayoutChange,
    onCustomSignatureDataChange,
    onDataChange,
    makeFileHandler,
    makeClearHandler,
    setLogoPreviewFileName,
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
                <p className="text-sm font-semibold">Global Branding</p>
                <p className="mt-0.5 text-xs text-muted-foreground">
                    Company info and assets shared across all document types
                </p>
            </div>

            <div className="divide-y">
                {/* Company Info */}
                <div className="space-y-5 p-8">
                    <FormField
                        label="Company Name"
                        fieldRequirementsProps={{ required: true }}
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
                        htmlFor="company_address"
                        error={errors.company_address}
                    >
                        <Textarea
                            id="company_address"
                            value={data.company_address}
                            onChange={(e) =>
                                onDataChange('company_address', e.target.value)
                            }
                            rows={3}
                        />
                    </FormField>

                    <div className="grid grid-cols-2 gap-3">
                        <FormField
                            label="Phone"
                            htmlFor="company_phone"
                            error={errors.company_phone}
                        >
                            <Input
                                id="company_phone"
                                value={data.company_phone}
                                onChange={(e) =>
                                    onDataChange('company_phone', e.target.value)
                                }
                            />
                        </FormField>

                        <FormField
                            label="Email"
                            htmlFor="company_email"
                            error={errors.company_email}
                        >
                            <Input
                                id="company_email"
                                type="email"
                                value={data.company_email}
                                onChange={(e) =>
                                    onDataChange('company_email', e.target.value)
                                }
                            />
                        </FormField>
                    </div>
                </div>

                {/* Brand Color */}
                <div className="p-8">
                    <FormField
                        label="Brand Color"
                        fieldRequirementsProps={{
                            hint: 'Applied to all document title bars',
                        }}
                        htmlFor="brand_color"
                        error={errors.brand_color}
                    >
                        <div className="flex items-center gap-2">
                            {/* Color swatch preview */}
                            <div
                                className="h-9 w-9 flex-shrink-0 rounded-md border shadow-sm"
                                style={{
                                    backgroundColor: data.brand_color || '#c05427',
                                }}
                            />
                            <Input
                                id="brand_color"
                                type="color"
                                value={data.brand_color || '#c05427'}
                                onChange={(e) =>
                                    onDataChange('brand_color', e.target.value)
                                }
                                className="h-9 w-10 cursor-pointer rounded border p-0.5"
                            />
                            <Input
                                type="text"
                                value={data.brand_color || '#c05427'}
                                onChange={(e) =>
                                    onDataChange('brand_color', e.target.value)
                                }
                                placeholder="#c05427"
                                className="flex-1 font-mono text-sm"
                                maxLength={7}
                            />
                        </div>
                    </FormField>
                </div>

                {/* Company Logo */}
                <div className="p-8">
                    <DocumentField
                        label="Company Logo"
                        hint="Displayed top-left on all PDFs"
                        accept="image/jpeg,image/png,image/jpg"
                        fileValue={data.logo_file || undefined}
                        existingPath={initialLogoDatabasePath || undefined}
                        existingFileName={logoPreviewFileName || undefined}
                        isView={false}
                        disabled={false}
                        error={errors.logo_file}
                        onSelect={makeFileHandler('logo_file', setLogoPreviewFileName)}
                        onClear={makeClearHandler(
                            'logo_file',
                            setLogoPreviewFileName,
                            'logo_path',
                            !!initialLogoDatabasePath,
                        )}
                    />
                </div>

                {/* Signature & Stamp Layout */}
                <div className="p-8">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm font-medium">Signature &amp; Stamp</p>
                            <p className="mt-0.5 text-xs text-muted-foreground">
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
                                    <Settings2 className="h-3.5 w-3.5" />
                                    Configure
                                </Button>
                            </DialogTrigger>
                            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-5xl">
                                <DialogHeader className="mb-4">
                                    <DialogTitle className="text-xl">
                                        Signature and Stamp Layout
                                    </DialogTitle>
                                    <DialogDescription>
                                        Adjust the positions, upload files, and draw
                                        your signature for document reports.
                                    </DialogDescription>
                                </DialogHeader>

                                <div className="mb-2 grid grid-cols-1 items-start gap-6 rounded-xl border bg-muted/10 p-5 sm:grid-cols-2">
                                    <DocumentField
                                        label="Company Stamp"
                                        hint="Image file for the company stamp"
                                        accept="image/jpeg,image/png,image/jpg"
                                        fileValue={data.stamp_file || undefined}
                                        existingPath={
                                            data.stamp_path !== ''
                                                ? initialStampDatabasePath || undefined
                                                : undefined
                                        }
                                        existingFileName={
                                            stampPreviewFileName || undefined
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
                                        fileValue={data.signature_file || undefined}
                                        existingPath={
                                            data.signature_path !== ''
                                                ? initialSignatureDatabasePath || undefined
                                                : undefined
                                        }
                                        existingFileName={
                                            signaturePreviewFileName || undefined
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
                                    signaturePreviewPath={signaturePreviewUrl}
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

                    {/* Visual indicator if files are uploaded */}
                    {(initialStampDatabasePath || initialSignatureDatabasePath || data.stamp_file || data.signature_file) && (
                        <div className="mt-3 flex flex-wrap gap-2">
                            {(initialStampDatabasePath || data.stamp_file) && (
                                <span className="inline-flex items-center gap-1 rounded-full bg-green-50 px-2.5 py-1 text-[11px] font-medium text-green-700 ring-1 ring-green-200">
                                    <span className="h-1.5 w-1.5 rounded-full bg-green-500" />
                                    Stamp uploaded
                                </span>
                            )}
                            {(initialSignatureDatabasePath || data.signature_file || data.custom_signature_data) && (
                                <span className="inline-flex items-center gap-1 rounded-full bg-green-50 px-2.5 py-1 text-[11px] font-medium text-green-700 ring-1 ring-green-200">
                                    <span className="h-1.5 w-1.5 rounded-full bg-green-500" />
                                    Signature uploaded
                                </span>
                            )}
                        </div>
                    )}
                </div>

                {/* Pro Tip */}
                <div className="bg-gradient-to-r from-blue-50/80 to-blue-50/40 px-6 py-5">
                    <div className="flex items-start gap-3">
                        <div className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-500 text-white">
                            <span className="text-xs font-bold">i</span>
                        </div>
                        <div>
                            <p className="mb-1 text-xs font-semibold text-blue-700">
                                Pro Tip
                            </p>
                            <p className="text-xs leading-relaxed text-blue-600/90">
                                Global branding applies to all document types unless
                                overridden in the Module Template section.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
