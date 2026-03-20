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
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { ChevronDown, ChevronUp } from 'lucide-react';
import { useState } from 'react';
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
        field:
            | 'logo_file'
            | 'stamp_file'
            | 'signature_file',
        setPreviewFileName: (v: string | null) => void,
    ) => (file: File) => void;
    makeClearHandler: (
        field:
            | 'logo_file'
            | 'stamp_file'
            | 'signature_file',
        setPreviewFileName: (v: string | null) => void,
        pathKey:
            | 'logo_path'
            | 'stamp_path'
            | 'signature_path',
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
    const [brandingOpen, setBrandingOpen] = useState(true);

    const stampPreviewUrl = data.stamp_file
        ? URL.createObjectURL(data.stamp_file)
        : initialStampDatabasePath
            ? `/storage/${initialStampDatabasePath}`
            : null;

    const signaturePreviewUrl =
        data.custom_signature_data ||
        (data.custom_signature_path
            ? `/storage/${data.custom_signature_path}`
            : data.signature_file instanceof File
                ? URL.createObjectURL(data.signature_file)
                : initialSignatureDatabasePath
                    ? `/storage/${initialSignatureDatabasePath}`
                    : null);

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

                    <div className="space-y-7 mt-5">

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
                            onClear={makeClearHandler('logo_file', setLogoPreviewFileName, 'logo_path', !!initialLogoDatabasePath)}
                        />
                    </div>

                    <Separator className="my-1" />

                    <div className="flex flex-col gap-3 rounded-xl border bg-card p-5 shadow-sm mt-5">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-base font-medium">Signature and Stamp Layout</p>
                                <p className="text-sm text-muted-foreground mt-1">
                                    Configure placement, draw your signature, <br /> and set name/date labels.
                                </p>
                            </div>

                            <Dialog>
                                <DialogTrigger asChild>
                                    <Button type="button" variant="outline">
                                        Configure Layout
                                    </Button>
                                </DialogTrigger>
                                <DialogContent className="sm:max-w-5xl max-h-[90vh] overflow-y-auto">
                                    <DialogHeader className="mb-4">
                                        <DialogTitle className="text-xl">Signature and Stamp Layout</DialogTitle>
                                        <DialogDescription>
                                            Adjust the positions, upload files, and draw your signature for document reports.
                                        </DialogDescription>
                                    </DialogHeader>

                                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 mb-2 p-5 border rounded-xl bg-muted/10">
                                        <DocumentField
                                            label="Company Stamp"
                                            hint="Image file for the company stamp"
                                            accept="image/jpeg,image/png,image/jpg"
                                            fileValue={data.stamp_file || undefined}
                                            existingPath={initialStampDatabasePath || undefined}
                                            existingFileName={stampPreviewFileName || undefined}
                                            isView={false}
                                            disabled={false}
                                            error={errors.stamp_file}
                                            onSelect={makeFileHandler('stamp_file', setStampPreviewFileName)}
                                            onClear={makeClearHandler('stamp_file', setStampPreviewFileName, 'stamp_path', !!initialStampDatabasePath)}
                                        />
                                        <DocumentField
                                            label="Authorised Signature"
                                            hint="Image file for the signature (or draw below)"
                                            accept="image/jpeg,image/png,image/jpg"
                                            fileValue={data.signature_file || undefined}
                                            existingPath={initialSignatureDatabasePath || undefined}
                                            existingFileName={signaturePreviewFileName || undefined}
                                            isView={false}
                                            disabled={false}
                                            error={errors.signature_file}
                                            onSelect={makeFileHandler('signature_file', setSignaturePreviewFileName)}
                                            onClear={makeClearHandler('signature_file', setSignaturePreviewFileName, 'signature_path', !!initialSignatureDatabasePath)}
                                        />
                                    </div>

                                    <SignatureStampLayoutSection
                                        customSignatureStampLayout={customSignatureStampLayout}
                                        onCustomSignatureStampLayoutChange={onCustomSignatureStampLayoutChange}
                                        onCustomSignatureDataChange={onCustomSignatureDataChange}
                                        stampPreviewPath={stampPreviewUrl}
                                        signaturePreviewPath={signaturePreviewUrl}
                                        customSignatureData={data.custom_signature_data ?? null}
                                        errors={errors}
                                    />

                                    <div className="flex justify-end pt-4 border-t mt-6">
                                        <DialogTrigger asChild>
                                            <Button type="button">Done</Button>
                                        </DialogTrigger>
                                    </div>
                                </DialogContent>
                            </Dialog>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
