import { FormField } from '@/components/form-field';
import { ImagePreviewDialog } from '@/components/image-preview-dialog';
import { Input } from '@/components/ui/input';
import { FileText, X } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';

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
    const hasFile = !!fileValue;
    const hasExisting = !!existingPath;
    const hasContent = hasFile || hasExisting;

    // Increment on clear so the native <input> remounts and resets to "No file chosen"
    const [clearCount, setClearCount] = useState(0);

    const handleClear = useCallback(() => {
        setClearCount((c) => c + 1);
        onClear();
    }, [onClear]);

    // Create preview URL with proper cleanup
    const previewUrl = useMemo(() => {
        if (hasFile) {
            return URL.createObjectURL(fileValue);
        }
        return existingPath ?? undefined;
    }, [hasFile, fileValue, existingPath]);

    // Cleanup blob URL when component unmounts or file changes
    useEffect(() => {
        return () => {
            if (hasFile && previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }
        };
    }, [hasFile, previewUrl]);

    const isImage = hasFile
        ? fileValue.type.startsWith('image/')
        : existingPath?.match(/\.(jpg|jpeg|png|gif|webp)(\?|$)/i) != null;

    // Extract readable filename
    const displayFilename = hasFile
        ? fileValue.name
        : existingPath
          ? decodeURIComponent(existingPath.split('/').pop() || '')
          : '';

    return (
        <FormField
            label={label}
            fieldRequirementsProps={{ hint }}
            error={error}
        >
            <div className="relative flex flex-col items-center gap-3 rounded-lg border-2 border-dashed p-4">
                {/* Preview area */}
                {hasContent ? (
                    <div className="flex w-full flex-col items-center gap-2">
                        <div className="relative">
                            {previewUrl && isImage ? (
                                <ImagePreviewDialog
                                    imageSrc={previewUrl}
                                    imageAlt={label}
                                />
                            ) : previewUrl ? (
                                <a
                                    href={previewUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="flex flex-col items-center gap-2 text-sm text-blue-600 underline dark:text-blue-400"
                                >
                                    <FileText className="h-12 w-12 text-muted-foreground" />
                                    View document
                                </a>
                            ) : null}

                            {/* Remove button – top-right of the preview */}
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

                        {/* Show filename */}
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

                {/* Visible file input – browser shows filename when a file is chosen */}
                {!isView && (
                    <Input
                        key={clearCount}
                        type="file"
                        accept={accept}
                        disabled={disabled}
                        className="cursor-pointer"
                        onChange={(e) => {
                            const file = e.target.files?.[0];
                            if (file) {
                                onSelect(file);
                            }
                        }}
                    />
                )}
            </div>
        </FormField>
    );
}
