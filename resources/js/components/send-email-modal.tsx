import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { router, useForm } from '@inertiajs/react';
import { Copy, Eye, Link as LinkIcon, Loader2, Send } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';

interface Template {
    value: string;
    label: string;
    message: string;
}

interface RecipientGroup {
    email: string;
    name: string;
    documents: string[];
}

interface EmailData {
    to: string;
    cc: string;
    subject: string;
    templates: Template[];
    default_template: string;
    customer_name: string;
    recipient_groups?: RecipientGroup[];
}

interface SendEmailModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    documentType: 'invoice' | 'receipt' | 'quotation';
    documentIds: number[];
    documentNumber: string | null;
}

export default function SendEmailModal({
    open,
    onOpenChange,
    documentType,
    documentIds,
    documentNumber,
}: SendEmailModalProps) {
    const [isLoading, setIsLoading] = useState(false);
    const [emailData, setEmailData] = useState<EmailData | null>(null);
    const [isPreviewing, setIsPreviewing] = useState(false);
    const [previewHtml, setPreviewHtml] = useState<string | null>(null);

    const [publicLink, setPublicLink] = useState<string | null>(null);
    const [isGeneratingLink, setIsGeneratingLink] = useState(false);

    const isBulk = documentIds.length > 1;

    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm({
            to: '',
            cc: '',
            subject: '',
            template: '',
            message: '',
        });

    useEffect(() => {
        if (open && documentIds.length > 0) {
            setIsLoading(true);
            setPreviewHtml(null);
            setIsPreviewing(false);
            setPublicLink(null);

            const endpoint = isBulk
                ? `/${documentType}/bulk/email-data?ids=${documentIds.join(',')}`
                : `/${documentType}/${documentIds[0]}/email-data`;

            fetch(endpoint, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then((res) => {
                    if (!res.ok) throw new Error('Failed to fetch email data');
                    return res.json();
                })
                .then((fetchedData: EmailData) => {
                    setEmailData(fetchedData);

                    const defaultTemplate =
                        fetchedData.templates.find(
                            (t) => t.value === fetchedData.default_template,
                        ) || fetchedData.templates[0];

                    setData({
                        to: fetchedData.to,
                        cc: fetchedData.cc,
                        subject: fetchedData.subject,
                        template: defaultTemplate?.value || '',
                        message: defaultTemplate?.message || '',
                    });
                })
                .catch((err) => {
                    toast.error('Error fetching email data', {
                        description: err.message,
                    });
                    onOpenChange(false);
                })
                .finally(() => {
                    setIsLoading(false);
                });
        } else if (!open) {
            reset();
            clearErrors();
            setEmailData(null);
            setPreviewHtml(null);
            setIsPreviewing(false);
            setPublicLink(null);
        }
    }, [
        open,
        documentIds,
        documentType,
        isBulk,
        clearErrors,
        onOpenChange,
        reset,
        setData,
    ]);

    const handleTemplateChange = (value: string) => {
        const selectedTemplate = emailData?.templates.find(
            (t) => t.value === value,
        );
        if (selectedTemplate) {
            setData((prev) => ({
                ...prev,
                template: value,
                message: selectedTemplate.message,
            }));
        }
    };

    const handlePreview = useCallback(async () => {
        if (documentIds.length === 0) return;

        setIsPreviewing(true);
        try {
            const endpoint = isBulk
                ? `/${documentType}/bulk/email-preview`
                : `/${documentType}/${documentIds[0]}/email-preview`;

            const payload = isBulk
                ? {
                      ids: documentIds,
                      subject: data.subject,
                      template: data.template,
                      message: data.message,
                  }
                : {
                      to: data.to,
                      cc: data.cc,
                      subject: data.subject,
                      template: data.template,
                      message: data.message,
                  };

            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') || '',
                },
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => null);
                throw new Error(
                    errorData?.message || 'Failed to generate preview',
                );
            }

            const result = await response.json();
            setPreviewHtml(result.html);
        } catch (error) {
            toast.error('Preview Error', {
                description:
                    error instanceof Error ? error.message : 'Unknown error',
            });
            setIsPreviewing(false);
        }
    }, [documentIds, documentType, isBulk, data]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const endpoint = isBulk
            ? `/${documentType}/bulk/send-email`
            : `/${documentType}/${documentIds[0]}/send-email`;

        if (isBulk) {
            router.post(
                endpoint,
                {
                    ...data,
                    ids: documentIds,
                },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        onOpenChange(false);
                    },
                    onError: () => {
                        // map errors to form if needed
                    },
                },
            );
        } else {
            post(endpoint, {
                preserveScroll: true,
                onSuccess: () => {
                    onOpenChange(false);
                },
            });
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="z-50 flex h-full max-h-[90%] min-w-[95%] flex-col lg:min-w-[60%]">
                <DialogHeader className="flex-shrink-0">
                    <DialogTitle>
                        Send{' '}
                        {documentType === 'invoice'
                            ? 'Invoice'
                            : documentType === 'receipt'
                              ? 'Receipt'
                              : 'Quotation'}
                        {isBulk ? 's (Bulk)' : ''}
                    </DialogTitle>
                    <DialogDescription>
                        {isBulk
                            ? `Sending ${documentIds.length} documents`
                            : `${documentNumber} - ${emailData?.customer_name}`}
                    </DialogDescription>
                </DialogHeader>

                <div className="flex-1 overflow-auto p-4 pt-0">
                    {isLoading ? (
                        <div className="flex h-64 items-center justify-center">
                            <Loader2 className="h-8 w-8 animate-spin text-gray-400" />
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {previewHtml ? (
                                <div className="mt-4 space-y-4">
                                    <div className="flex items-center justify-between border-b pb-2">
                                        <div className="font-medium">
                                            Email Preview
                                        </div>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => {
                                                setPreviewHtml(null);
                                                setIsPreviewing(false);
                                            }}
                                        >
                                            Back to Edit
                                        </Button>
                                    </div>
                                    <div className="rounded-md border bg-gray-50 p-4">
                                        <div className="mb-4 space-y-1 border-b pb-4 text-sm">
                                            <div>
                                                <span className="font-medium text-gray-500">
                                                    To:
                                                </span>{' '}
                                                {data.to}
                                            </div>
                                            {data.cc && (
                                                <div>
                                                    <span className="font-medium text-gray-500">
                                                        Cc:
                                                    </span>{' '}
                                                    {data.cc}
                                                </div>
                                            )}
                                            <div>
                                                <span className="font-medium text-gray-500">
                                                    Subject:
                                                </span>{' '}
                                                {data.subject}
                                            </div>
                                            <div className="mt-2 flex items-center gap-2">
                                                <span className="font-medium text-gray-500">
                                                    Attachment:
                                                </span>
                                                <span className="inline-flex items-center rounded-full border bg-primary/10 px-2.5 py-0.5 text-xs font-semibold text-primary">
                                                    {documentType}_
                                                    {documentNumber}.pdf
                                                </span>
                                            </div>
                                        </div>
                                        <iframe
                                            srcDoc={previewHtml}
                                            className="min-h-[400px] w-full border-0 bg-white"
                                            title="Email Preview"
                                        />
                                    </div>
                                </div>
                            ) : (
                                <form
                                    id="send-email-form"
                                    onSubmit={handleSubmit}
                                    className="mt-4 space-y-4"
                                >
                                    {!isBulk && (
                                        <div className="grid gap-2">
                                            <Label htmlFor="to">
                                                To{' '}
                                                <span className="text-red-500">
                                                    *
                                                </span>
                                            </Label>
                                            <Input
                                                id="to"
                                                type="email"
                                                value={data.to}
                                                onChange={(e) =>
                                                    setData(
                                                        'to',
                                                        e.target.value,
                                                    )
                                                }
                                                required
                                            />
                                            {errors.to && (
                                                <span className="text-xs text-red-500">
                                                    {errors.to}
                                                </span>
                                            )}
                                        </div>
                                    )}

                                    {isBulk && (
                                        <div className="grid gap-2">
                                            <Label>To</Label>
                                            <div className="rounded-md border bg-gray-50 px-3 py-2 text-sm text-gray-500">
                                                Emails will be grouped and sent
                                                to the following recipients:
                                                {emailData?.recipient_groups && (
                                                    <ul className="mt-2 list-disc space-y-1 pl-5">
                                                        {emailData.recipient_groups.map(
                                                            (group, idx) => (
                                                                <li key={idx}>
                                                                    <span className="font-medium text-gray-700">
                                                                        {
                                                                            group.email
                                                                        }
                                                                    </span>{' '}
                                                                    (
                                                                    {group.name}
                                                                    ) -{' '}
                                                                    {
                                                                        group
                                                                            .documents
                                                                            .length
                                                                    }{' '}
                                                                    document(s):{' '}
                                                                    {group.documents.join(
                                                                        ', ',
                                                                    )}
                                                                </li>
                                                            ),
                                                        )}
                                                    </ul>
                                                )}
                                            </div>
                                        </div>
                                    )}

                                    {!isBulk && (
                                        <div className="grid gap-2">
                                            <Label htmlFor="cc">
                                                Cc (comma-separated)
                                            </Label>
                                            <Input
                                                id="cc"
                                                type="text"
                                                value={data.cc}
                                                onChange={(e) =>
                                                    setData(
                                                        'cc',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="email1@example.com, email2@example.com"
                                            />
                                            {errors.cc && (
                                                <span className="text-xs text-red-500">
                                                    {errors.cc}
                                                </span>
                                            )}
                                        </div>
                                    )}

                                    <div className="grid gap-2">
                                        <Label htmlFor="subject">
                                            Subject{' '}
                                            <span className="text-red-500">
                                                *
                                            </span>
                                        </Label>
                                        <Input
                                            id="subject"
                                            type="text"
                                            value={data.subject}
                                            onChange={(e) =>
                                                setData(
                                                    'subject',
                                                    e.target.value,
                                                )
                                            }
                                            required
                                        />
                                        {errors.subject && (
                                            <span className="text-xs text-red-500">
                                                {errors.subject}
                                            </span>
                                        )}
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="template">
                                            Template
                                        </Label>
                                        <Select
                                            value={data.template}
                                            onValueChange={handleTemplateChange}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select a template" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {emailData?.templates.map(
                                                    (template) => (
                                                        <SelectItem
                                                            key={template.value}
                                                            value={
                                                                template.value
                                                            }
                                                        >
                                                            {template.label}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                        {errors.template && (
                                            <span className="text-xs text-red-500">
                                                {errors.template}
                                            </span>
                                        )}
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="message">
                                            Message Body{' '}
                                            <span className="text-red-500">
                                                *
                                            </span>
                                        </Label>
                                        <Textarea
                                            id="message"
                                            value={data.message}
                                            onChange={(e) =>
                                                setData(
                                                    'message',
                                                    e.target.value,
                                                )
                                            }
                                            className="min-h-[200px]"
                                            required
                                        />
                                        <p className="text-xs text-gray-500">
                                            This message will be included in the
                                            email body. The PDF document will be
                                            automatically attached.
                                        </p>
                                        {errors.message && (
                                            <span className="text-xs text-red-500">
                                                {errors.message}
                                            </span>
                                        )}
                                    </div>

                                    <div className="grid gap-2">
                                        <Label>Attachment</Label>
                                        <div>
                                            {isBulk ? (
                                                <span className="inline-flex items-center rounded-full border bg-primary/10 px-2.5 py-0.5 text-xs font-semibold text-primary">
                                                    Multiple PDF attachments per
                                                    recipient
                                                </span>
                                            ) : (
                                                <span className="inline-flex items-center rounded-full border bg-primary/10 px-2.5 py-0.5 text-xs font-semibold text-primary">
                                                    {documentType}_
                                                    {documentNumber}.pdf
                                                </span>
                                            )}
                                        </div>
                                    </div>

                                    {publicLink && (
                                        <div className="mt-6 grid gap-2 rounded-md border bg-blue-50/50 p-4">
                                            <Label>Public Link</Label>
                                            <div className="flex gap-2">
                                                <Input
                                                    value={publicLink}
                                                    readOnly
                                                    className="bg-white"
                                                />
                                                <Button
                                                    type="button"
                                                    onClick={() => {
                                                        navigator.clipboard.writeText(
                                                            publicLink,
                                                        );
                                                        toast.success(
                                                            'Link copied to clipboard',
                                                        );
                                                    }}
                                                >
                                                    <Copy className="h-4 w-4" />
                                                </Button>
                                            </div>
                                            <p className="text-xs text-gray-500">
                                                This link will expire in 7 days.
                                            </p>
                                        </div>
                                    )}
                                </form>
                            )}
                        </div>
                    )}
                </div>

                <DialogFooter className="flex-shrink-0 gap-2 border-t pt-4">
                    {!isBulk && !publicLink && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={async () => {
                                setIsGeneratingLink(true);
                                try {
                                    const res = await fetch(
                                        `/${documentType}/${documentIds[0]}/copy-link`,
                                        {
                                            headers: {
                                                Accept: 'application/json',
                                                'X-Requested-With':
                                                    'XMLHttpRequest',
                                            },
                                        },
                                    );
                                    if (!res.ok)
                                        throw new Error(
                                            'Failed to generate link',
                                        );
                                    const { url } = await res.json();
                                    setPublicLink(url);
                                } catch {
                                    toast.error('Failed to generate link');
                                } finally {
                                    setIsGeneratingLink(false);
                                }
                            }}
                            disabled={
                                isLoading || processing || isGeneratingLink
                            }
                            className="mr-auto gap-2"
                        >
                            {isGeneratingLink ? (
                                <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                                <LinkIcon className="h-4 w-4" />
                            )}
                            Get Public Link
                        </Button>
                    )}
                    {!previewHtml && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handlePreview}
                            disabled={isLoading || isPreviewing || processing}
                            className={`gap-2 ${isBulk || publicLink ? 'mr-auto' : ''}`}
                        >
                            {isPreviewing ? (
                                <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                                <Eye className="h-4 w-4" />
                            )}
                            Preview Email
                        </Button>
                    )}
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={() => onOpenChange(false)}
                        disabled={processing}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        form="send-email-form"
                        disabled={isLoading || processing || !!previewHtml}
                        className="gap-2"
                    >
                        {processing ? (
                            <>
                                <Loader2 className="h-4 w-4 animate-spin" />
                                Sending...
                            </>
                        ) : (
                            <>
                                <Send className="h-4 w-4" />
                                Send Email
                            </>
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
