import { DocumentField } from '@/components/document-field';
import { FormField } from '@/components/form-field';
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
    };
    errors: Record<string, string | undefined>;
    initialLogoDatabasePath: string | null;
    initialStampDatabasePath: string | null;
    initialSignatureDatabasePath: string | null;
    initialCustomStampDatabasePath: string | null;
    initialCustomSignatureDatabasePath: string | null;
    signatureStampLayout: 'default' | 'custom';
    onSignatureStampLayoutChange: (value: 'default' | 'custom') => void;
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
            | 'signature_file'
            | 'custom_stamp_file'
            | 'custom_signature_file',
        setPreviewFileName: (v: string | null) => void,
    ) => (file: File) => void;
    makeClearHandler: (
        field:
            | 'logo_file'
            | 'stamp_file'
            | 'signature_file'
            | 'custom_stamp_file'
            | 'custom_signature_file',
        setPreviewFileName: (v: string | null) => void,
        pathKey:
            | 'logo_path'
            | 'stamp_path'
            | 'signature_path'
            | 'custom_stamp_path'
            | 'custom_signature_path',
        hasDatabaseFile: boolean,
    ) => () => void;
    setLogoPreviewFileName: (v: string | null) => void;
    setStampPreviewFileName: (v: string | null) => void;
    setSignaturePreviewFileName: (v: string | null) => void;
    setCustomStampPreviewFileName: (v: string | null) => void;
    setCustomSignaturePreviewFileName: (v: string | null) => void;
}

export function GlobalBrandingSection({
    data,
    errors,
    logoPreviewFileName,
    stampPreviewFileName,
    signaturePreviewFileName,
    customStampPreviewFileName,
    customSignaturePreviewFileName,
    initialLogoDatabasePath,
    initialStampDatabasePath,
    initialSignatureDatabasePath,
    initialCustomStampDatabasePath,
    initialCustomSignatureDatabasePath,
    signatureStampLayout,
    onSignatureStampLayoutChange,
    customSignatureStampLayout,
    onCustomSignatureStampLayoutChange,
    onCustomSignatureDataChange,
    onDataChange,
    makeFileHandler,
    makeClearHandler,
    setLogoPreviewFileName,
    setStampPreviewFileName,
    setSignaturePreviewFileName,
    setCustomStampPreviewFileName,
    setCustomSignaturePreviewFileName,
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

                    <div className="space-y-7">
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
                        <div className="grid grid-cols-1 gap-7 sm:grid-cols-2 [&>*]:min-w-0">
                            <DocumentField
                                label="Company Stamp"
                                hint="Enable per-module in the section below"
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
                                hint="Enable per-module in the section below"
                                accept="image/jpeg,image/png,image/jpg"
                                fileValue={data.signature_file || undefined}
                                existingPath={initialSignatureDatabasePath || undefined}
                                existingFileName={signaturePreviewFileName || undefined}
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
                    </div>

                    <Separator className="my-1" />

                    <SignatureStampLayoutSection
                        signatureStampLayout={signatureStampLayout}
                        onSignatureStampLayoutChange={onSignatureStampLayoutChange}
                        customSignatureStampLayout={customSignatureStampLayout}
                        onCustomSignatureStampLayoutChange={onCustomSignatureStampLayoutChange}
                        onCustomSignatureDataChange={onCustomSignatureDataChange}
                        customStampPreviewFileName={customStampPreviewFileName}
                        customSignaturePreviewFileName={customSignaturePreviewFileName}
                        initialCustomStampDatabasePath={initialCustomStampDatabasePath}
                        initialCustomSignatureDatabasePath={initialCustomSignatureDatabasePath}
                        makeFileHandler={makeFileHandler}
                        makeClearHandler={makeClearHandler}
                        setCustomStampPreviewFileName={setCustomStampPreviewFileName}
                        setCustomSignaturePreviewFileName={setCustomSignaturePreviewFileName}
                        data={data}
                        errors={errors}
                    />
                </div>
            )}
        </div>
    );
}
