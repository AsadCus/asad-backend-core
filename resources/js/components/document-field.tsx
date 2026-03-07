import { FormField } from '@/components/form-field';
import { ImagePreviewDialog } from '@/components/image-preview-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ExternalLink, FileText } from 'lucide-react';
import { useState } from 'react';

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
                className="flex items-center justify-center rounded border bg-muted/30 transition-transform duration-300 hover:scale-105 dark:border-white"
                style={{ width: '80px', height: '80px' }}
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

    // Use preview for new file, or existing path for uploaded files
    const previewSrc = newFilePreview || (existingPath ? `/storage/${existingPath}` : null);

    const isImage = fileValue
        ? fileValue.type.startsWith('image/')
        : existingPath?.match(/\.(jpg|jpeg|png|gif|webp)(\?|$)/i) != null;

    const isPdf = fileValue
        ? fileValue.type === 'application/pdf'
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

        onSelect(file);

        // Create preview for new file
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onloadend = () => setNewFilePreview(reader.result as string);
            reader.readAsDataURL(file);
        } else {
            setNewFilePreview(null);
        }
    };

    const handleClear = () => {
        setNewFilePreview(null);
        setInputKey((prev) => prev + 1); // Reset input
        onClear();
    };

    return (
        <FormField
            label={label}
            fieldRequirementsProps={{ hint }}
            error={error}
        >
            <div className="space-y-3">
                {!isView && (
                    <Input
                        key={inputKey}
                        type="file"
                        accept={accept}
                        onChange={handleFileChange}
                        disabled={disabled}
                        className="block w-full cursor-pointer"
                    />
                )}
                
                {previewSrc && (
                    <div className="space-y-2">
                        <div className="flex items-center gap-3">
                            {isImage ? (
                                <ImagePreviewDialog
                                    imageSrc={previewSrc}
                                    imageAlt={label}
                                    title={label}
                                    thumbnailSize={80}
                                    rounded="rounded"
                                />
                            ) : isPdf ? (
                                <PdfPreviewCard
                                    pdfSrc={previewSrc}
                                    title={label}
                                />
                            ) : null}

                            <div className="flex flex-col gap-2">
                                {displayFilename && (
                                    <span
                                        className="max-w-[200px] truncate text-sm text-muted-foreground"
                                        title={displayFilename}
                                    >
                                        {displayFilename}
                                    </span>
                                )}

                                {!isView && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handleClear}
                                        disabled={disabled}
                                    >
                                        Clear
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>
                )}

                {/* Show message when no file */}
                {!previewSrc && isView && (
                    <p className="text-sm text-muted-foreground">
                        No file uploaded
                    </p>
                )}
            </div>
        </FormField>
    );
}
