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
import { Plus } from 'lucide-react';
import { FormEventHandler, useState } from 'react';
import type { FileUploadFieldProps } from './types';

export function FileUploadField({
    id,
    label,
    hint,
    preview,
    previewAlt,
    error,
    onChange,
    onClear,
}: FileUploadFieldProps) {
    return (
        <FormField
            label={label}
            fieldRequirementsProps={{ hint }}
            htmlFor={id}
            error={error}
        >
            <Input
                id={id}
                type="file"
                accept="image/jpeg,image/png,image/jpg"
                onChange={onChange}
                className="block w-full"
            />
            <p className="mt-1 text-sm text-muted-foreground">
                Accepted: JPG, JPEG, PNG. Max 2MB
            </p>
            {preview && (
                <div className="mt-3 flex items-center gap-3">
                    <ImagePreviewDialog
                        imageSrc={preview}
                        imageAlt={previewAlt}
                        title={previewAlt}
                        thumbnailSize={80}
                        rounded="rounded"
                    />
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={onClear}
                    >
                        Clear
                    </Button>
                </div>
            )}
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
                            Display Label <span className="text-red-500">*</span>
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
