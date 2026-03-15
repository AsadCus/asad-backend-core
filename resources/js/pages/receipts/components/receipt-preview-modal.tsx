import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { InvoiceItemSchema } from '@/pages/invoices/schema';
import { Download, Eye, Loader2 } from 'lucide-react';
import { useCallback, useState } from 'react';
import { toast } from 'sonner';
import { ReceiptSchema } from '../schema';

interface ReceiptPreviewModalProps {
    receipt: ReceiptSchema;
    items: InvoiceItemSchema[];
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
}

export default function ReceiptPreviewModal({
    receipt,
    open: externalOpen,
    onOpenChange: externalOnOpenChange,
}: ReceiptPreviewModalProps) {
    const [internalOpen, setInternalOpen] = useState(false);
    const [isGenerating, setIsGenerating] = useState(false);

    const open = externalOpen ?? internalOpen;
    const setOpen = externalOnOpenChange ?? setInternalOpen;
    const previewUrl = receipt.id ? `/receipt/${receipt.id}/preview` : null;

    const handleGeneratePdf = useCallback(async () => {
        if (!receipt.id) {
            toast.error('Cannot Generate PDF', {
                description:
                    'Please save the receipt first before generating PDF.',
            });
            return;
        }

        setIsGenerating(true);

        try {
            const response = await fetch(`/receipt/${receipt.id}/generate-pdf`);
            if (!response.ok) throw new Error('Failed to generate PDF');
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);

            // Open in new tab
            window.open(url, '_blank');

            // Trigger download
            const link = document.createElement('a');
            link.href = url;
            link.download = `receipt-${receipt.receipt_number}.pdf`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            toast.success('PDF Generated', {
                description: 'Your receipt PDF has been opened and downloaded.',
            });
        } catch (error) {
            toast.error('Error generating PDF', {
                description:
                    error instanceof Error
                        ? error.message
                        : 'Failed to generate the receipt PDF.',
            });
        } finally {
            setIsGenerating(false);
        }
    }, [receipt.id, receipt.receipt_number]);

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
                        Preview Receipt
                    </Button>
                </div>
            )}

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="z-50 flex h-full max-h-[96%] flex-col lg:min-w-[66%]">
                    <DialogHeader className="flex-shrink-0">
                        <DialogTitle>Receipt Preview</DialogTitle>
                        <DialogDescription>
                            {receipt.receipt_number}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex-1 overflow-auto bg-gray-100 p-4">
                        <div className="mx-auto max-w-[794px] shadow-md">
                            {previewUrl ? (
                                <iframe
                                    src={previewUrl}
                                    title="Receipt Preview"
                                    className="h-[1050px] w-full"
                                />
                            ) : (
                                <div className="flex h-64 items-center justify-center rounded-md bg-white p-4 text-sm text-muted-foreground">
                                    Save receipt first to load preview.
                                </div>
                            )}
                        </div>
                    </div>

                    <DialogFooter className="flex-shrink-0 gap-2 border-t pt-4">
                        <Button
                            onClick={handleGeneratePdf}
                            disabled={isGenerating || !receipt.id}
                            size="sm"
                            className="gap-2"
                            title={
                                !receipt.id
                                    ? 'Save receipt first to generate PDF'
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
