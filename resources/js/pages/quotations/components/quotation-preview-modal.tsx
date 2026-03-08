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
import { QuotationItemSchema } from '../items/schema';
import { QuotationSchema } from '../schema';

interface QuotationPreviewModalProps {
    data: QuotationSchema;
    items: QuotationItemSchema[];
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
}

export default function QuotationPreviewModal({
    data,
    items: _items,
    open: externalOpen,
    onOpenChange: externalOnOpenChange,
}: QuotationPreviewModalProps) {
    const [internalOpen, setInternalOpen] = useState(false);
    const [isGenerating, setIsGenerating] = useState(false);

    const open = externalOpen ?? internalOpen;
    const setOpen = externalOnOpenChange ?? setInternalOpen;
    const previewUrl = data.id ? `/quotation/${data.id}/preview` : null;

    const handleGeneratePdf = useCallback(async () => {
        if (!data.id) {
            toast.error('Cannot Generate PDF', {
                description:
                    'Please save the quotation first before generating PDF.',
            });
            return;
        }

        setIsGenerating(true);

        try {
            const response = await fetch(`/quotation/${data.id}/generate-pdf`);
            if (!response.ok) throw new Error('Failed to generate PDF');
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);

            // Open in new tab
            window.open(url, '_blank');

            // Trigger download
            const link = document.createElement('a');
            link.href = url;
            link.download = `quotation-${data.quotation_number}.pdf`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            toast.success('PDF Generated', {
                description:
                    'Your quotation PDF has been opened and downloaded.',
            });
        } catch (error) {
            toast.error('Error generating PDF', {
                description:
                    error instanceof Error ? error.message : 'Unknown error',
            });
        } finally {
            setIsGenerating(false);
        }
    }, [data.id, data.quotation_number]);


    return (
        <>
            {externalOpen === undefined && (
                <div className="flex gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setOpen(true)}
                        className="gap-2"
                    >
                        <Eye className="h-4 w-4" />
                        Preview Quotation
                    </Button>
                </div>
            )}

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="z-50 flex h-full max-h-[96%] flex-col lg:min-w-[66%]">
                    <DialogHeader className="flex-shrink-0">
                        <DialogTitle>Quotation Preview</DialogTitle>
                        <DialogDescription>
                            {data.quotation_number}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex-1 overflow-hidden rounded-md border bg-white">
                        {previewUrl ? (
                            <iframe
                                src={previewUrl}
                                title="Quotation Preview"
                                className="h-full w-full"
                            />
                        ) : (
                            <div className="flex h-full items-center justify-center p-4 text-sm text-muted-foreground">
                                Save quotation first to load preview.
                            </div>
                        )}
                    </div>

                    <DialogFooter className="flex-shrink-0 gap-2 border-t pt-4">
                        <Button
                            onClick={handleGeneratePdf}
                            disabled={isGenerating || !data.id}
                            size="sm"
                            className="gap-2"
                            title={
                                !data.id
                                    ? 'Save quotation first to generate PDF'
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
