import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Download, Eye, Loader2, Printer } from 'lucide-react';
import { useCallback, useRef, useState } from 'react';
import { toast } from 'sonner';
import { ScheduleSchema } from '../schema';
import SchedulePreview from './schedule-preview';

interface SchedulePreviewModalProps {
    schedule: ScheduleSchema;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
}

export default function SchedulePreviewModal({
    schedule,
    open: externalOpen,
    onOpenChange: externalOnOpenChange,
}: SchedulePreviewModalProps) {
    const [internalOpen, setInternalOpen] = useState(false);
    const [isGenerating, setIsGenerating] = useState(false);
    const previewRef = useRef<HTMLDivElement>(null);

    const open = externalOpen ?? internalOpen;
    const setOpen = externalOnOpenChange ?? setInternalOpen;

    const handleGeneratePdf = useCallback(async () => {
        if (!schedule.quotation_id && !schedule.id) {
            toast.error('Cannot Generate PDF', {
                description:
                    'Please save the schedule first before generating PDF.',
            });
            return;
        }

        const scheduleId = schedule.quotation_id || schedule.id;
        setIsGenerating(true);

        try {
            const response = await fetch(`/schedule/${scheduleId}/export-pdf`);
            if (!response.ok) throw new Error('Failed to generate PDF');
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);

            // Open in new tab
            window.open(url, '_blank');

            // Trigger download
            const link = document.createElement('a');
            link.href = url;
            link.download = `schedule-${schedule.schedule_number}.pdf`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            toast.success('PDF Generated', {
                description:
                    'Your schedule PDF has been opened and downloaded.',
            });
        } catch (error) {
            toast.error('Error generating PDF', {
                description:
                    error instanceof Error
                        ? error.message
                        : 'Failed to generate the schedule PDF.',
            });
        } finally {
            setIsGenerating(false);
        }
    }, [schedule.quotation_id, schedule.id, schedule.schedule_number]);

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
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => setOpen(true)}
                    className="gap-2"
                >
                    <Eye className="h-4 w-4" />
                    Preview Schedule
                </Button>
            )}

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="z-50 flex h-full max-h-[96%] flex-col lg:min-w-[66%]">
                    <DialogHeader className="flex-shrink-0">
                        <DialogTitle>Schedule Preview</DialogTitle>
                        <DialogDescription>
                            {schedule.schedule_number}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex-1 overflow-auto rounded-md border bg-gray-50 dark:bg-gray-900">
                        <div className="flex justify-center p-4">
                            <SchedulePreview
                                ref={previewRef}
                                schedule={schedule}
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
                            disabled={
                                isGenerating ||
                                (!schedule.quotation_id && !schedule.id)
                            }
                            size="sm"
                            className="gap-2"
                            title={
                                !schedule.quotation_id && !schedule.id
                                    ? 'Save schedule first to generate PDF'
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
