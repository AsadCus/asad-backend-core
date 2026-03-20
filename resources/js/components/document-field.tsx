import { FormField } from '@/components/form-field';
import { ImagePreviewDialog } from '@/components/image-preview-dialog';
import { ProperInput } from '@/components/proper-input';
import { Input } from '@/components/ui/input';
import { ExternalLink, FileText, UploadCloud, X, ZoomIn } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export interface DocumentFieldProps {
    label: string;
    hint?: string;
    accept: string;
    fileValue?: File;
    existingPath?: string;
    existingFileName?: string | null;
    useFileNameInput?: boolean;
    fileNameValue?: string | null;
    isView: boolean;
    disabled: boolean;
    error?: string;
    onSelect: (file: File) => void;
    onFileNameChange?: (fileName: string | null) => void;
    onClear: () => void;
}

interface PdfPreviewCardProps {
    pdfSrc: string;
    title: string;
}

const PREVENT_UPLOAD_CLICK_ATTR = 'data-prevent-upload-click';

function getFileNameWithoutExtension(fileName: string): string {
    const trimmed = fileName.trim();

    if (trimmed === '') {
        return '';
    }

    const dotIndex = trimmed.lastIndexOf('.');

    if (dotIndex <= 0) {
        return trimmed;
    }

    return trimmed.slice(0, dotIndex);
}

function PdfPreviewCard({ pdfSrc, title }: PdfPreviewCardProps) {
    return (
        <a
            href={pdfSrc}
            target="_blank"
            rel="noopener noreferrer"
            className="group relative flex cursor-pointer items-center justify-center"
            title={`View ${title}`}
            onClick={(e) => e.stopPropagation()}
            {...{ [PREVENT_UPLOAD_CLICK_ATTR]: 'true' }}
        >
            <div className="flex h-20 w-20 items-center justify-center rounded border bg-muted/30 transition-transform duration-300 hover:scale-105 dark:border-white">
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
    existingFileName,
    useFileNameInput = false,
    fileNameValue,
    isView,
    disabled,
    error,
    onSelect,
    onFileNameChange,
    onClear,
}: DocumentFieldProps) {
    const [newFilePreview, setNewFilePreview] = useState<string | null>(null);
    const [inputKey, setInputKey] = useState(0);
    const [isDragOver, setIsDragOver] = useState(false);
    const [isImagePreviewOpen, setIsImagePreviewOpen] = useState(false);
    const inputRef = useRef<HTMLInputElement | null>(null);
    const objectUrlRef = useRef<string | null>(null);
    const suppressUploadUntilRef = useRef(0);

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

    useEffect(() => {
        if (!(fileValue instanceof File)) {
            setNewFilePreview(null);

            return;
        }

        revokeObjectUrl();
        const objectUrl = URL.createObjectURL(fileValue);
        objectUrlRef.current = objectUrl;
        setNewFilePreview(objectUrl);
    }, [fileValue]);

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
    const displayFilenameRaw = fileValue
        ? fileValue.name
        : fileNameValue?.trim()
          ? fileNameValue
          : existingFileName?.trim()
            ? existingFileName
            : existingPath
              ? decodeURIComponent(existingPath.split('/').pop() || '')
              : '';

    const displayFilename = getFileNameWithoutExtension(displayFilenameRaw);

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;

        revokeObjectUrl();
        const objectUrl = URL.createObjectURL(file);
        objectUrlRef.current = objectUrl;
        setNewFilePreview(objectUrl);

        onSelect(file);
    };

    const handleInputClick = () => {
        if (isView || disabled) {
            return;
        }

        if (isImagePreviewOpen || Date.now() < suppressUploadUntilRef.current) {
            return;
        }

        inputRef.current?.click();
    };

    const shouldPreventUploadTrigger = (
        target: EventTarget | null,
    ): boolean => {
        if (!(target instanceof Element)) {
            return false;
        }

        return target.closest(`[${PREVENT_UPLOAD_CLICK_ATTR}="true"]`) !== null;
    };

    const handleDragOver = (e: React.DragEvent<HTMLDivElement>) => {
        if (isView || disabled) {
            return;
        }

        e.preventDefault();
        setIsDragOver(true);
    };

    const handleDragLeave = (e: React.DragEvent<HTMLDivElement>) => {
        if (isView || disabled) {
            return;
        }

        e.preventDefault();
        setIsDragOver(false);
    };

    const handleDrop = (e: React.DragEvent<HTMLDivElement>) => {
        if (isView || disabled) {
            return;
        }

        e.preventDefault();
        setIsDragOver(false);

        const file = e.dataTransfer.files?.[0];
        if (!file) {
            return;
        }

        revokeObjectUrl();
        const objectUrl = URL.createObjectURL(file);
        objectUrlRef.current = objectUrl;
        setNewFilePreview(objectUrl);
        onSelect(file);
    };

    const handleClear = (e: React.MouseEvent<HTMLButtonElement>) => {
        e.stopPropagation();
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
            <Input
                key={inputKey}
                ref={inputRef}
                type="file"
                accept={accept}
                onChange={handleFileChange}
                disabled={disabled}
                autoComplete="off"
                className="sr-only"
                tabIndex={-1}
            />

            <div
                role={isView ? undefined : 'button'}
                tabIndex={isView || disabled ? -1 : 0}
                className={`relative flex flex-col items-center gap-3 rounded-lg border-2 border-dashed p-4 transition-colors ${
                    isView || disabled ? '' : 'cursor-pointer'
                } ${
                    isDragOver
                        ? 'border-primary bg-primary/5'
                        : 'border-border hover:border-primary/60'
                }`}
                onClick={(e) => {
                    if (shouldPreventUploadTrigger(e.target)) {
                        return;
                    }

                    handleInputClick();
                }}
                onKeyDown={(e) => {
                    if (isView || disabled) {
                        return;
                    }

                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        handleInputClick();
                    }
                }}
                onDragOver={handleDragOver}
                onDragLeave={handleDragLeave}
                onDrop={handleDrop}
                aria-label={isView ? undefined : `Upload ${label}`}
            >
                {hasContent ? (
                    <div className="flex w-full flex-col items-center gap-2">
                        <div
                            className="relative"
                            {...{ [PREVENT_UPLOAD_CLICK_ATTR]: 'true' }}
                        >
                            {isImage ? (
                                <>
                                    <button
                                        type="button"
                                        className="group relative flex cursor-pointer items-center justify-center"
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            setIsImagePreviewOpen(true);
                                        }}
                                        {...{
                                            [PREVENT_UPLOAD_CLICK_ATTR]: 'true',
                                        }}
                                    >
                                        <img
                                            src={previewSrc as string}
                                            alt={label}
                                            className="h-20 w-20 rounded border object-cover object-[center_0%] transition-transform duration-300 dark:border-white"
                                        />
                                        <div className="absolute inset-0 hidden items-center justify-center rounded bg-black/40 group-hover:flex">
                                            <ZoomIn className="size-6 text-white" />
                                        </div>
                                    </button>

                                    <ImagePreviewDialog
                                        imageSrc={previewSrc as string}
                                        imageAlt={label}
                                        title={label}
                                        open={isImagePreviewOpen}
                                        onOpenChange={(open) => {
                                            setIsImagePreviewOpen(open);

                                            if (!open) {
                                                suppressUploadUntilRef.current =
                                                    Date.now() + 300;
                                            }
                                        }}
                                    />
                                </>
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
                                    onClick={(e) => e.stopPropagation()}
                                    {...{ [PREVENT_UPLOAD_CLICK_ATTR]: 'true' }}
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
                                    {...{ [PREVENT_UPLOAD_CLICK_ATTR]: 'true' }}
                                >
                                    <X className="h-4 w-4" aria-hidden="true" />
                                </button>
                            )}
                        </div>

                        {displayFilename && (
                            <span
                                className="max-w-full truncate text-sm text-muted-foreground"
                                title={displayFilename}
                                onClick={(e) => e.stopPropagation()}
                                {...{ [PREVENT_UPLOAD_CLICK_ATTR]: 'true' }}
                            >
                                {displayFilename}
                            </span>
                        )}

                        {useFileNameInput && !isView && (
                            <div
                                className="w-full"
                                onClick={(e) => e.stopPropagation()}
                                onKeyDown={(e) => e.stopPropagation()}
                                {...{ [PREVENT_UPLOAD_CLICK_ATTR]: 'true' }}
                            >
                                <ProperInput
                                    value={getFileNameWithoutExtension(
                                        fileNameValue ?? '',
                                    )}
                                    onCommit={(value) =>
                                        onFileNameChange?.(
                                            value.trim() === ''
                                                ? null
                                                : value.trim(),
                                        )
                                    }
                                    placeholder={`Custom ${label.toLowerCase()} file name`}
                                    disabled={disabled}
                                />
                            </div>
                        )}
                    </div>
                ) : (
                    <div className="flex h-24 w-full flex-col items-center justify-center gap-2 text-sm text-muted-foreground">
                        <UploadCloud className="h-6 w-6" />
                        <span>Click or drag & drop to upload</span>
                    </div>
                )}
            </div>
        </FormField>
    );
}
