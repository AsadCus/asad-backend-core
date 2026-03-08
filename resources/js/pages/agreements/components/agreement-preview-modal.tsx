import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Download, Eye, Loader2 } from 'lucide-react';
import { useCallback, useState } from 'react';
import { toast } from 'sonner';
import { AgreementSchema } from '../schema';

interface AgreementPreviewModalProps {
    agreement: AgreementSchema;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
}

export default function AgreementPreviewModal({
    agreement,
    open: externalOpen,
    onOpenChange: externalOnOpenChange,
}: AgreementPreviewModalProps) {
    const [internalOpen, setInternalOpen] = useState(false);
    const [isGenerating, setIsGenerating] = useState(false);

    const open = externalOpen ?? internalOpen;
    const setOpen = externalOnOpenChange ?? setInternalOpen;
    const previewUrl = agreement.id ? `/agreement/${agreement.id}/preview` : null;

    const handleGeneratePdf = useCallback(async () => {
        if (!agreement.id) {
            toast.error('Cannot Generate PDF', {
                description:
                    'Please save the agreement first before generating PDF.',
            });
            return;
        }

        setIsGenerating(true);

        try {
            const response = await fetch(
                `/agreement/${agreement.id}/export-pdf`,
            );
            if (!response.ok) throw new Error('Failed to generate PDF');
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);

            // Open in new tab
            window.open(url, '_blank');

            // Trigger download
            const link = document.createElement('a');
            link.href = url;
            link.download = `agreement-${agreement.agreement_number}.pdf`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            toast.success('PDF Generated', {
                description:
                    'Your agreement PDF has been opened and downloaded.',
            });
        } catch (error) {
            toast.error('Error generating PDF', {
                description:
                    error instanceof Error
                        ? error.message
                        : 'Failed to generate the agreement PDF.',
            });
        } finally {
            setIsGenerating(false);
        }
    }, [agreement.id, agreement.agreement_number]);


    return (
        <>
            {externalOpen === undefined && (
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => setOpen(true)}
                    className="gap-2"
                >
                    <Eye className="h-4 w-4" />
                    Preview Agreement
                </Button>
            )}

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="z-50 flex h-full max-h-[96%] flex-col lg:min-w-[66%]">
                    <DialogHeader className="flex-shrink-0">
                        <DialogTitle>Agreement Preview</DialogTitle>
                        <DialogDescription>
                            {agreement.agreement_number}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex-1 overflow-hidden rounded-md border bg-white">
                        {previewUrl ? (
                            <iframe
                                src={previewUrl}
                                title="Agreement Preview"
                                className="h-full w-full"
                            />
                        ) : (
                            <div className="flex h-full items-center justify-center p-4 text-sm text-muted-foreground">
                                Save agreement first to load preview.
                            </div>
                        )}
                    </div>

                    <DialogFooter className="flex-shrink-0 gap-2 border-t pt-4">
                        <Button
                            onClick={handleGeneratePdf}
                            disabled={isGenerating || !agreement.id}
                            size="sm"
                            className="gap-2"
                            title={
                                !agreement.id
                                    ? 'Save agreement first to generate PDF'
                                    : ''
                            }
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
        </>
    );
}
