import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { download } from '@/routes/packages';
import { Download, Loader2 } from 'lucide-react';
import { useCallback, useState } from 'react';
import { toast } from 'sonner';

export interface PackagePreviewData {
    id?: number;
    package_number?: string;
}

interface PackagePreviewModalProps {
    data: PackagePreviewData;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
}

export default function PackagePreviewModal({
    data,
    open: externalOpen,
    onOpenChange: externalOnOpenChange,
}: PackagePreviewModalProps) {
    const [internalOpen, setInternalOpen] = useState(false);
    const [isGenerating, setIsGenerating] = useState(false);

    const open = externalOpen ?? internalOpen;
    const setOpen = externalOnOpenChange ?? setInternalOpen;
    const previewUrl = data.id ? `/packages/${data.id}/preview` : null;

    const handleGeneratePdf = useCallback(async () => {
        if (!data.id) {
            toast.error('Cannot Generate PDF', {
                description:
                    'Please save the package first before generating PDF.',
            });
            return;
        }

        setIsGenerating(true);

        try {
            const response = await fetch(download(data.id).url);
            if (!response.ok) throw new Error('Failed to generate PDF');
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);

            window.open(url, '_blank');

            const link = document.createElement('a');
            link.href = url;
            link.download = `package-${data.package_number ?? data.id}.pdf`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            toast.success('PDF Generated', {
                description: 'Your package PDF has been opened and downloaded.',
            });
        } catch (error) {
            toast.error('Error generating PDF', {
                description:
                    error instanceof Error ? error.message : 'Unknown error',
            });
        } finally {
            setIsGenerating(false);
        }
    }, [data.id, data.package_number]);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="z-50 flex h-full max-h-[96%] flex-col lg:min-w-[66%]">
                <DialogHeader className="flex-shrink-0">
                    <DialogTitle>Package Preview</DialogTitle>
                    <DialogDescription>{data.package_number}</DialogDescription>
                </DialogHeader>

                <div className="flex-1 overflow-auto bg-gray-100 p-4">
                    <div className="mx-auto max-w-[794px] shadow-md">
                        {previewUrl ? (
                            <iframe
                                src={previewUrl}
                                title="Package Preview"
                                className="h-[1050px] w-full"
                            />
                        ) : (
                            <div className="flex h-64 items-center justify-center rounded-md bg-white p-4 text-sm text-muted-foreground">
                                Save package first to load preview.
                            </div>
                        )}
                    </div>
                </div>

                <DialogFooter className="flex-shrink-0 gap-2 border-t pt-4">
                    <Button
                        onClick={handleGeneratePdf}
                        disabled={isGenerating || !data.id}
                        size="sm"
                        className="gap-2"
                        title={
                            !data.id
                                ? 'Save package first to generate PDF'
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
    );
}
