import { FormField } from '@/components/form-field';
import { ImagePreviewDialog } from '@/components/image-preview-dialog';
import { Input } from '@/components/ui/input';
import { ExternalLink, FileText, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export interface DocumentFieldProps {
    label: string;
    hint?: string;
    accept: string;
    fileValue?: File;
    existingPath?: string;
    isView: boolean;
    disabled: boolean;
    error?: string;
    onSelect: (file: File) => void;
    onClear: () => void;
}

interface PdfPreviewCardProps {
    pdfSrc: string;
    title: string;
}

function PdfPreviewCard({ pdfSrc, title }: PdfPreviewCardProps) {
    return (
        <a
            href={pdfSrc}
            target="_blank"
            rel="noopener noreferrer"
            className="group relative flex cursor-pointer items-center justify-center"
            title={`View ${title}`}
        >
            <div
                className="flex h-20 w-20 items-center justify-center rounded border bg-muted/30 transition-transform duration-300 hover:scale-105 dark:border-white"
            >
                <FileText className="h-10 w-10 text-muted-foreground" />
            </div>
            <div className="absolute inset-0 hidden items-center justify-center rounded bg-black/40 group-hover:flex">
                <ExternalLink className="size-6 text-white" />
            </div>
        </a>
    );
}

export function DocumentField({
    label,
    hint,
    accept,
    fileValue,
    existingPath,
    isView,
    disabled,
    error,
    onSelect,
    onClear,
}: DocumentFieldProps) {
    const [newFilePreview, setNewFilePreview] = useState<string | null>(null);
    const [inputKey, setInputKey] = useState(0);
    const objectUrlRef = useRef<string | null>(null);

    const revokeObjectUrl = () => {
        if (!objectUrlRef.current) {
            return;
        }

        URL.revokeObjectURL(objectUrlRef.current);
        objectUrlRef.current = null;
    };

    useEffect(() => {
        return () => {
            revokeObjectUrl();
        };
    }, []);

    // Use preview for new file, or existing path for uploaded files.
    const previewSrc =
        newFilePreview || (existingPath ? `/storage/${existingPath}` : null);

    const isImage = fileValue
        ? fileValue.type.startsWith('image/') ||
          /\.(jpg|jpeg|png|gif|webp)$/i.test(fileValue.name)
        : existingPath?.match(/\.(jpg|jpeg|png|gif|webp)(\?|$)/i) != null;

    const isPdf = fileValue
        ? fileValue.type === 'application/pdf' || /\.pdf$/i.test(fileValue.name)
        : existingPath?.match(/\.pdf(\?|$)/i) != null;

    // Extract filename for display
    const displayFilename = fileValue
        ? fileValue.name
        : existingPath
            ? decodeURIComponent(existingPath.split('/').pop() || '')
            : '';

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;

        revokeObjectUrl();
        const objectUrl = URL.createObjectURL(file);
        objectUrlRef.current = objectUrl;
        setNewFilePreview(objectUrl);

        onSelect(file);
    };

    const handleClear = () => {
        revokeObjectUrl();
        setNewFilePreview(null);
        setInputKey((prev) => prev + 1); // Reset input
        onClear();
    };

    const hasContent = Boolean(previewSrc);

    return (
        <FormField
            label={label}
            fieldRequirementsProps={{ hint }}
            error={error}
        >
            <div className="relative flex flex-col items-center gap-3 rounded-lg border-2 border-dashed p-4">
                {hasContent ? (
                    <div className="flex w-full flex-col items-center gap-2">
                        <div className="relative">
                            {isImage ? (
                                <ImagePreviewDialog
                                    imageSrc={previewSrc as string}
                                    imageAlt={label}
                                    title={label}
                                    thumbnailSize={80}
                                    rounded="rounded"
                                />
                            ) : isPdf ? (
                                <PdfPreviewCard
                                    pdfSrc={previewSrc as string}
                                    title={label}
                                />
                            ) : previewSrc ? (
                                <a
                                    href={previewSrc}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="flex flex-col items-center gap-2 text-sm text-blue-600 underline dark:text-blue-400"
                                >
                                    <FileText className="h-12 w-12 text-muted-foreground" />
                                    View document
                                </a>
                            ) : null}

                            {!isView && (
                                <button
                                    type="button"
                                    className="absolute -top-2 -right-2 rounded-full bg-red-500 p-1 text-white hover:bg-red-600 focus:outline-none"
                                    onClick={handleClear}
                                    aria-label={`Remove ${label}`}
                                    title={`Remove ${label}`}
                                    disabled={disabled}
                                >
                                    <X className="h-4 w-4" aria-hidden="true" />
                                </button>
                            )}
                        </div>

                        {displayFilename && (
                            <span
                                className="max-w-full truncate text-sm text-muted-foreground"
                                title={displayFilename}
                            >
                                {displayFilename}
                            </span>
                        )}
                    </div>
                ) : (
                    <div className="flex h-24 w-full items-center justify-center text-sm text-muted-foreground">
                        No file uploaded
                    </div>
                )}

                {!isView && (
                    <Input
                        key={inputKey}
                        type="file"
                        accept={accept}
                        onChange={handleFileChange}
                        disabled={disabled}
                        autoComplete="off"
                        className="cursor-pointer"
                    />
                )}
            </div>
        </FormField>
    );
}
