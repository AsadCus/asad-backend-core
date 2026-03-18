export interface ModuleTemplate {
    footer_text: string;
    show_stamp: boolean;
    show_signature: boolean;
    show_signature_stamp_name: boolean;
    show_signature_stamp_date: boolean;
}

export interface SignatureStampPlacement {
    x: number;
    y: number;
    width: number;
    height: number;
    z: number;
}

export type SignatureStampPlacementPreset =
    | 'left_side'
    | 'right_side'
    | 'stack_each_other'
    | 'up_side'
    | 'down_side';

export interface SignatureStampLabelsConfig {
    show_name: boolean;
    show_date: boolean;
    full_name: string;
    date: string;
}

export interface SignatureStampLayoutConfig {
    unit: 'percent' | 'px';
    placement: SignatureStampPlacementPreset;
    labels: SignatureStampLabelsConfig;
    stamp: SignatureStampPlacement;
    signature: SignatureStampPlacement;
}

export interface RegisteredModule {
    key: string;
    label: string;
    document_type: string;
}

export interface FileUploadFieldProps {
    id: string;
    label: string;
    hint: string;
    preview: string | null;
    previewFileName?: string | null;
    previewAlt: string;
    error?: string;
    onChange: (e: React.ChangeEvent<HTMLInputElement>) => void;
    onClear: () => void;
}

export interface PdfPreviewProps {
    titleColor: string;
    footerText: string;
    showStamp: boolean;
    showSignature: boolean;
    showSignatureStampName: boolean;
    showSignatureStampDate: boolean;
    signatureStampLayout: 'default' | 'custom';
    customSignatureStampLayout: SignatureStampLayoutConfig;
    documentType: string;
    companyName: string;
    companyPhone: string;
    companyEmail: string;
    companyAddress: string;
    logoPreview: string | null;
    stampPreview: string | null;
    signaturePreview: string | null;
    customStampPreview: string | null;
    customSignaturePreview: string | null;
}
