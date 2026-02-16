import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { MessageSquarePlus } from 'lucide-react';
import EnquiryRemarksTimeline from './enquiry-remarks-timeline';

interface EnquiryRemarksDialogProps {
    isOpen: boolean;
    onClose: () => void;
    enquiryId: number | undefined;
    enquiryName?: string;
}

export default function EnquiryRemarksDialog({
    isOpen,
    onClose,
    enquiryId,
    enquiryName,
}: EnquiryRemarksDialogProps) {
    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="flex max-h-[90%] flex-col md:max-h-[80vh] md:max-w-3xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <MessageSquarePlus className="h-5 w-5" />
                        Enquiry Remarks
                        {enquiryName && (
                            <span className="font-normal text-muted-foreground">
                                — {enquiryName}
                            </span>
                        )}
                    </DialogTitle>
                    <DialogDescription className="sr-only">
                        View and manage remarks for this enquiry.
                    </DialogDescription>
                </DialogHeader>

                {/* Enquiry Remarks Timeline */}
                <EnquiryRemarksTimeline isOpen={isOpen} enquiryId={enquiryId} />
            </DialogContent>
        </Dialog>
    );
}
