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
import { Download, Eye, Loader2, Printer } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { ReceiptSchema } from '../schema';
import ReceiptPreview from './receipt-preview';

interface BrandingData {
    company_name: string;
    company_address: string;
    company_phone: string;
    company_email: string;
    logo_url: string | null;
    stamp_url: string | null;
    signature_url: string | null;
    module_templates: {
        receipt: {
            title_color: string;
            footer_text: string;
            show_stamp: boolean;
            show_signature: boolean;
        };
    };
}

interface ReceiptPreviewModalProps {
    receipt: ReceiptSchema;
    items: InvoiceItemSchema[];
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
}

export default function ReceiptPreviewModal({
    receipt,
    items,
    open: externalOpen,
    onOpenChange: externalOnOpenChange,
}: ReceiptPreviewModalProps) {
    const [internalOpen, setInternalOpen] = useState(false);
    const [isGenerating, setIsGenerating] = useState(false);
    const [branding, setBranding] = useState<BrandingData | null>(null);
    const previewRef = useRef<HTMLDivElement>(null);

    const open = externalOpen ?? internalOpen;
    const setOpen = externalOnOpenChange ?? setInternalOpen;

    useEffect(() => {
        if (open && !branding) {
            const fetchBranding = async () => {
                try {
                    const response = await fetch('/api/report-template/branding');
                    if (response.ok) {
                        const data = await response.json();
                        setBranding(data);
                    }
                } catch (error) {
                    console.error('Failed to fetch branding:', error);
                }
            };
            fetchBranding();
        }
    }, [open, branding]);

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

    const handlePrint = useCallback(() => {
        if (previewRef.current) {
            const printWindow = window.open('', '', 'width=900,height=1200');
            if (printWindow) {
                const printContent = previewRef.current.innerHTML;
                printWindow.document.write(`
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset="utf-8" />
                        <meta name="viewport" content="width=device-width, initial-scale=1" />
                        <script src="https://cdn.tailwindcss.com"></script>
                        <style>
                            body { margin: 0; padding: 0; font-family: Arial, sans-serif; }
                            @media print {
                                @page { size: A4; margin: 0; }
                                body { margin: 0; padding: 0; }
                            }
                        </style>
                    </head>
                    <body>${printContent}</body>
                    </html>
                `);
                printWindow.document.close();
                printWindow.focus();
                setTimeout(() => printWindow.print(), 250);
            }
        } else {
            toast.error('Preview is not loaded yet.');
        }
    }, []);

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

                    <div className="flex-1 overflow-auto rounded-md border bg-gray-50 dark:bg-gray-900">
                        <div className="flex justify-center p-4">
                            <ReceiptPreview
                                ref={previewRef}
                                data={receipt}
                                items={items}
                                branding={branding}
                            />
                        </div>
                    </div>

                    <DialogFooter className="flex-shrink-0 gap-2 border-t pt-4">
                        <Button
                            onClick={handlePrint}
                            variant="outline"
                            size="sm"
                            className="hidden gap-2"
                        >
                            <Printer className="h-4 w-4" />
                            Print
                        </Button>
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
