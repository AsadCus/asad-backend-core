import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { triggerDownload } from '@/lib/utils';
import { Download, Loader2, Printer } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';

interface MaidBiodataPreviewProps {
    maidId: number;
    maidName?: string;
    isOpen: boolean;
    onOpenChange: (open: boolean) => void;
}

export function MaidBiodataPreview({
    maidId,
    maidName,
    isOpen,
    onOpenChange,
}: MaidBiodataPreviewProps) {
    const [biodataHtml, setBiodataHtml] = useState<string>('');
    const [isLoadingPreview, setIsLoadingPreview] = useState(false);
    const [previewError, setPreviewError] = useState<string>('');
    const [isGenerating, setIsGenerating] = useState(false);

    const loadPreview = useCallback(async () => {
        setIsLoadingPreview(true);
        setPreviewError('');

        try {
            const response = await fetch(`/maid/${maidId}/preview-biodata`);

            if (!response.ok) {
                throw new Error('Failed to load preview');
            }

            const html = await response.text();
            setBiodataHtml(html);
        } catch (error) {
            const errorMessage =
                error instanceof Error
                    ? error.message
                    : 'Failed to load preview';
            setPreviewError(errorMessage);
            toast.error('Preview Error', {
                description: errorMessage,
            });
        } finally {
            setIsLoadingPreview(false);
        }
    }, [maidId]);

    useEffect(() => {
        if (isOpen) {
            loadPreview();
        }
    }, [isOpen, maidId, loadPreview]);

    const handlePrint = useCallback(() => {
        const iframe = document.querySelector<HTMLIFrameElement>(
            'iframe[data-biodata-preview]',
        );

        if (!iframe?.contentWindow) {
            toast.error('Preview is not loaded yet.');
            return;
        }

        const iframeWindow = iframe.contentWindow;

        setTimeout(() => {
            try {
                iframeWindow.focus();
                iframeWindow.print();
            } catch (error) {
                console.error('Print error:', error);
                toast.error('Print Error', {
                    description: 'Unable to print biodata.',
                });
            }
        }, 200);
    }, []);

    const handleGeneratePdf = useCallback(() => {
        setIsGenerating(true);
        triggerDownload(`/maid/${maidId}/generate-pdf`);
        setTimeout(() => setIsGenerating(false), 500);
        toast.success('PDF Generated', {
            description: 'Your PDF biodata has been generated successfully.',
        });
    }, [maidId]);

    const renderPreviewContent = () => {
        if (isLoadingPreview) {
            return (
                <div className="flex h-full items-center justify-center">
                    <div className="space-y-4 text-center">
                        <Loader2 className="mx-auto h-12 w-12 animate-spin text-primary" />
                        <p className="text-base text-muted-foreground">
                            Loading preview...
                        </p>
                    </div>
                </div>
            );
        }

        if (previewError) {
            return (
                <div className="flex h-full items-center justify-center">
                    <div className="max-w-md space-y-4 text-center">
                        <div className="text-red-500">
                            <svg
                                className="mx-auto h-12 w-12"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                        </div>
                        <div>
                            <p className="font-medium text-foreground">
                                Failed to load preview
                            </p>
                            <p className="mt-1 text-base text-muted-foreground">
                                {previewError}
                            </p>
                        </div>
                        <Button
                            onClick={loadPreview}
                            variant="outline"
                            size="sm"
                        >
                            Try Again
                        </Button>
                    </div>
                </div>
            );
        }

        return (
            <iframe
                data-biodata-preview
                srcDoc={biodataHtml}
                className="h-full w-full border-0"
                title="Biodata Preview"
                sandbox="allow-scripts allow-modals allow-forms"
            />
        );
    };

    return (
        <Dialog open={isOpen} onOpenChange={onOpenChange}>
            <DialogContent className="z-50 flex h-full max-h-[96%] flex-col lg:min-w-[66%]">
                <DialogHeader className="flex-shrink-0 pb-4">
                    <DialogTitle className="pr-8">
                        Preview Biodata{maidName ? ` - ${maidName}` : ''}
                    </DialogTitle>
                    <DialogDescription className="sr-only">
                        Displays biodata preview about the selected maid.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex-1 overflow-hidden rounded-md border bg-gray-50 dark:bg-gray-900">
                    {renderPreviewContent()}
                </div>

                <DialogFooter className="flex-shrink-0 gap-2 border-t pt-4">
                    <Button
                        onClick={handlePrint}
                        variant="outline"
                        size="sm"
                        disabled={isLoadingPreview || !!previewError}
                        className="hidden gap-2"
                    >
                        <Printer className="h-4 w-4" />
                        Print
                    </Button>
                    <Button
                        onClick={handleGeneratePdf}
                        disabled={
                            isGenerating || isLoadingPreview || !!previewError
                        }
                        size="sm"
                        className="gap-2"
                    >
                        {isGenerating ? (
                            <>
                                <Loader2 className="h-4 w-4 animate-spin" />
                                Generating...
                            </>
                        ) : (
                            <>
                                <Download className="h-4 w-4" />
                                Generate PDF
                            </>
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
