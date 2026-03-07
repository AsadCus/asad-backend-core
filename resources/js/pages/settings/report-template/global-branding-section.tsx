import { FormField } from '@/components/form-field';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { ChevronDown, ChevronUp } from 'lucide-react';
import { useState } from 'react';
import type { FileUploadFieldProps } from './types';

interface GlobalBrandingSectionProps {
    data: {
        company_name: string;
        company_address: string;
        company_phone: string;
        company_email: string;
        brand_color: string;
    };
    errors: Record<string, string | undefined>;
    logoPreview: string | null;
    stampPreview: string | null;
    signaturePreview: string | null;
    logoPreviewFileName: string | null;
    stampPreviewFileName: string | null;
    signaturePreviewFileName: string | null;
    initialLogoPreview: string | null;
    initialStampPreview: string | null;
    initialSignaturePreview: string | null;
    initialLogoPreviewFileName: string | null;
    initialStampPreviewFileName: string | null;
    initialSignaturePreviewFileName: string | null;
    onDataChange: (field: string, value: string) => void;
    makeFileHandler: (
        field: 'logo_file' | 'stamp_file' | 'signature_file',
        setPreview: (v: string | null) => void,
        setPreviewFileName: (v: string | null) => void,
    ) => (e: React.ChangeEvent<HTMLInputElement>) => void;
    makeClearHandler: (
        field: 'logo_file' | 'stamp_file' | 'signature_file',
        setPreview: (v: string | null) => void,
        existingPreview: string | null,
        setPreviewFileName: (v: string | null) => void,
        existingFileName: string | null,
    ) => () => void;
    setLogoPreview: (v: string | null) => void;
    setStampPreview: (v: string | null) => void;
    setSignaturePreview: (v: string | null) => void;
    setLogoPreviewFileName: (v: string | null) => void;
    setStampPreviewFileName: (v: string | null) => void;
    setSignaturePreviewFileName: (v: string | null) => void;
    FileUploadField: React.ComponentType<FileUploadFieldProps>;
}

export function GlobalBrandingSection({
    data,
    errors,
    logoPreview,
    stampPreview,
    signaturePreview,
    logoPreviewFileName,
    stampPreviewFileName,
    signaturePreviewFileName,
    initialLogoPreview,
    initialStampPreview,
    initialSignaturePreview,
    initialLogoPreviewFileName,
    initialStampPreviewFileName,
    initialSignaturePreviewFileName,
    onDataChange,
    makeFileHandler,
    makeClearHandler,
    setLogoPreview,
    setStampPreview,
    setSignaturePreview,
    setLogoPreviewFileName,
    setStampPreviewFileName,
    setSignaturePreviewFileName,
    FileUploadField,
}: GlobalBrandingSectionProps) {
    const [brandingOpen, setBrandingOpen] = useState(true);

    return (
        <div className="overflow-hidden rounded-lg border bg-card shadow-sm">
            <button
                type="button"
                onClick={() => setBrandingOpen((v) => !v)}
                className="flex w-full items-center justify-between bg-muted/40 px-4 py-3 text-left transition-colors hover:bg-muted/60"
            >
                <div>
                    <p className="text-base font-semibold">Global Branding</p>
                    <p className="text-sm text-muted-foreground">
                        Company info and assets shared across all document types
                    </p>
                </div>
                {brandingOpen ? (
                    <ChevronUp className="h-4 w-4 flex-shrink-0 text-muted-foreground" />
                ) : (
                    <ChevronDown className="h-4 w-4 flex-shrink-0 text-muted-foreground" />
                )}
            </button>

            {brandingOpen && (
                <div className="space-y-7 p-7">
                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <FormField
                            label="Company Name"
                            fieldRequirementsProps={{ required: true }}
                            htmlFor="company_name"
                            error={errors.company_name}
                            className="sm:col-span-2"
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
                            className="sm:col-span-2"
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
                        <FormField
                            label="Company Phone"
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
                            label="Company Email"
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
                        <FormField
                            label="Brand Color (Title Bar)"
                            fieldRequirementsProps={{
                                hint: 'Global color for all document title bars',
                            }}
                            htmlFor="brand_color"
                            error={errors.brand_color}
                            className="sm:col-span-2"
                        >
                            <div className="flex items-center gap-3">
                                <div
                                    className="h-10 w-16 flex-shrink-0 rounded border shadow-sm"
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
                                    className="h-10 w-16 cursor-pointer rounded border p-1"
                                />
                                <Input
                                    type="text"
                                    value={data.brand_color || '#c05427'}
                                    onChange={(e) =>
                                        onDataChange('brand_color', e.target.value)
                                    }
                                    placeholder="#c05427"
                                    className="flex-1 font-mono"
                                    maxLength={7}
                                />
                            </div>
                        </FormField>
                    </div>

                    <Separator className="my-1" />

                    <div className="grid grid-cols-1 gap-7 md:grid-cols-2 xl:grid-cols-3">
                        <FileUploadField
                            id="logo_file"
                            label="Company Logo"
                            hint="Displayed top-left on all PDFs"
                            preview={logoPreview}
                            previewFileName={logoPreviewFileName}
                            previewAlt="Company Logo"
                            error={errors.logo_file}
                            onChange={makeFileHandler('logo_file', setLogoPreview, setLogoPreviewFileName)}
                            onClear={makeClearHandler('logo_file', setLogoPreview, initialLogoPreview, setLogoPreviewFileName, initialLogoPreviewFileName)}
                        />
                        <FileUploadField
                            id="stamp_file"
                            label="Company Stamp"
                            hint="Enable per-module in the section below"
                            preview={stampPreview}
                            previewFileName={stampPreviewFileName}
                            previewAlt="Company Stamp"
                            error={errors.stamp_file}
                            onChange={makeFileHandler('stamp_file', setStampPreview, setStampPreviewFileName)}
                            onClear={makeClearHandler('stamp_file', setStampPreview, initialStampPreview, setStampPreviewFileName, initialStampPreviewFileName)}
                        />
                        <FileUploadField
                            id="signature_file"
                            label="Authorised Signature"
                            hint="Enable per-module in the section below"
                            preview={signaturePreview}
                            previewFileName={signaturePreviewFileName}
                            previewAlt="Authorised Signature"
                            error={errors.signature_file}
                            onChange={makeFileHandler(
                                'signature_file',
                                setSignaturePreview,
                                setSignaturePreviewFileName,
                            )}
                            onClear={makeClearHandler(
                                'signature_file',
                                setSignaturePreview,
                                initialSignaturePreview,
                                setSignaturePreviewFileName,
                                initialSignaturePreviewFileName,
                            )}
                        />
                    </div>
                </div>
            )}
        </div>
    );
}
