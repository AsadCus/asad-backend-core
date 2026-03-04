export interface ModuleTemplate {
    title_color: string;
    footer_text: string;
    show_stamp: boolean;
    show_signature: boolean;
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
    documentType: string;
    companyName: string;
    companyPhone: string;
    companyEmail: string;
    companyAddress: string;
    logoPreview: string | null;
    stampPreview: string | null;
    signaturePreview: string | null;
}
