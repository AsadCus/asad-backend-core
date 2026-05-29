import { FormField } from '@/components/form-field';
import { ImagePreviewDialog } from '@/components/image-preview-dialog';
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
import { Label } from '@/components/ui/label';
import { store as storeModuleRoute } from '@/routes/report-template/modules';
import { router } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import { FormEventHandler, useEffect, useRef, useState } from 'react';
import type { FileUploadFieldProps } from './types';

export function FileUploadField({
    id,
    label,
    hint,
    preview,
    previewFileName,
    previewAlt,
    error,
    onChange,
    onClear,
}: FileUploadFieldProps) {
    const [inputKey, setInputKey] = useState(0);
    const [localPreview, setLocalPreview] = useState<string | null>(null);
    const [isObjectUrl, setIsObjectUrl] = useState(false);
    const objectUrlRef = useRef<string | null>(null);

    const revokeObjectUrl = () => {
        if (!objectUrlRef.current) {
            return;
        }

        URL.revokeObjectURL(objectUrlRef.current);
        objectUrlRef.current = null;
        setIsObjectUrl(false);
    };

    // Sync localPreview with preview prop when preview changes from parent
    useEffect(() => {
        if (!isObjectUrl && preview) {
            setLocalPreview(preview);
        }
    }, [preview, isObjectUrl]);

    useEffect(() => {
        return () => {
            revokeObjectUrl();
        };
    }, []);

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];

        if (file) {
            revokeObjectUrl();
            const objectUrl = URL.createObjectURL(file);
            objectUrlRef.current = objectUrl;
            setLocalPreview(objectUrl);
            setIsObjectUrl(true);
        }

        onChange(e);
    };

    const handleClear = () => {
        revokeObjectUrl();
        setLocalPreview(null);
        setInputKey((prev) => prev + 1);
        onClear();
    };

    const previewToShow = localPreview ?? preview;

    return (
        <FormField
            label={label}
            fieldRequirementsProps={{ hint }}
            htmlFor={id}
            error={error}
        >
            <div className="relative flex w-full min-w-0 flex-col items-center gap-3 overflow-hidden rounded-lg border-2 border-dashed p-4">
                <Input
                    key={inputKey}
                    id={id}
                    type="file"
                    accept="image/jpeg,image/png,image/jpg"
                    onChange={handleFileChange}
                    autoComplete="off"
                    className="w-full cursor-pointer text-xs file:max-w-[100px] file:truncate sm:text-sm sm:file:max-w-none"
                />

                <p className="w-full text-center text-xs leading-relaxed text-muted-foreground">
                    Accepted: JPG, JPEG, PNG. Max 2MB
                </p>

                {previewToShow && (
                    <div className="flex w-full min-w-0 flex-col items-center gap-2">
                        <div className="relative">
                            <ImagePreviewDialog
                                imageSrc={previewToShow}
                                imageAlt={previewAlt}
                                title={previewAlt}
                                thumbnailSize={80}
                                rounded="rounded"
                            />

                            <button
                                type="button"
                                className="absolute -top-2 -right-2 rounded-full bg-red-500 p-1 text-white hover:bg-red-600 focus:outline-none"
                                onClick={handleClear}
                                aria-label={`Remove ${label}`}
                                title={`Remove ${label}`}
                            >
                                <X className="h-4 w-4" aria-hidden="true" />
                            </button>
                        </div>

                        {previewFileName && (
                            <span
                                className="block w-full max-w-full truncate text-center text-xs text-muted-foreground sm:text-sm"
                                title={previewFileName}
                            >
                                {previewFileName}
                            </span>
                        )}
                    </div>
                )}
            </div>
        </FormField>
    );
}

export function AddModuleDialog() {
    const [open, setOpen] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [fields, setFields] = useState({
        key: '',
        label: '',
        document_type: '',
    });
    const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        setSubmitting(true);
        setFieldErrors({});
        router.post(
            storeModuleRoute().url,
            {
                key: fields.key,
                label: fields.label,
                document_type: fields.document_type,
            },
            {
                onError: (errs) => {
                    setFieldErrors(errs as Record<string, string>);
                    setSubmitting(false);
                },
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    size="sm"
                    className="flex items-center gap-1.5"
                >
                    <Plus className="h-3.5 w-3.5" />
                    Add Module
                </Button>
            </DialogTrigger>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Add Document Module</DialogTitle>
                    <DialogDescription>
                        Register a new document type for template customisation.
                        The PDF blade template for this module still needs to be
                        created by a developer.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4 pt-2">
                    <div>
                        <Label htmlFor="mod_key">
                            Module Key <span className="text-red-500">*</span>
                        </Label>
                        <p className="mt-0.5 mb-1 text-sm text-muted-foreground">
                            Unique identifier, lowercase letters, numbers,
                            underscores only. E.g.{' '}
                            <code className="rounded bg-muted px-1 text-sm">
                                manifest
                            </code>
                        </p>
                        <Input
                            id="mod_key"
                            value={fields.key}
                            onChange={(e) =>
                                setFields((f) => ({
                                    ...f,
                                    key: e.target.value
                                        .toLowerCase()
                                        .replace(/[^a-z0-9_]/g, ''),
                                }))
                            }
                            placeholder="manifest"
                            required
                        />
                        {fieldErrors.key && (
                            <p className="mt-1 text-sm text-red-500">
                                {fieldErrors.key}
                            </p>
                        )}
                    </div>
                    <div>
                        <Label htmlFor="mod_label">
                            Display Label{' '}
                            <span className="text-red-500">*</span>
                        </Label>
                        <p className="mt-0.5 mb-1 text-sm text-muted-foreground">
                            Shown in the module dropdown. E.g.{' '}
                            <code className="rounded bg-muted px-1 text-sm">
                                Manifest
                            </code>
                        </p>
                        <Input
                            id="mod_label"
                            value={fields.label}
                            onChange={(e) =>
                                setFields((f) => ({
                                    ...f,
                                    label: e.target.value,
                                }))
                            }
                            placeholder="Manifest"
                            required
                        />
                        {fieldErrors.label && (
                            <p className="mt-1 text-sm text-red-500">
                                {fieldErrors.label}
                            </p>
                        )}
                    </div>
                    <div>
                        <Label htmlFor="mod_doctype">
                            Document Type Label{' '}
                            <span className="text-red-500">*</span>
                        </Label>
                        <p className="mt-0.5 mb-1 text-sm text-muted-foreground">
                            Appears as title in the PDF. E.g.{' '}
                            <code className="rounded bg-muted px-1 text-sm">
                                MANIFEST
                            </code>
                        </p>
                        <Input
                            id="mod_doctype"
                            value={fields.document_type}
                            onChange={(e) =>
                                setFields((f) => ({
                                    ...f,
                                    document_type: e.target.value.toUpperCase(),
                                }))
                            }
                            placeholder="MANIFEST"
                            required
                        />
                        {fieldErrors.document_type && (
                            <p className="mt-1 text-sm text-red-500">
                                {fieldErrors.document_type}
                            </p>
                        )}
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={submitting}>
                            Add Module
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
