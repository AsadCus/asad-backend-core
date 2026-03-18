import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { transitionStatus } from '@/routes/enquiries';
import { router } from '@inertiajs/react';
import { Info, Loader2 } from 'lucide-react';
import { useState } from 'react';

export type EnquiryStatusActionType = 'contacted' | 'confirmed';

interface EnquiryStatusActionProps {
    enquiryId?: number;
    enquiryType?: string;
    action: EnquiryStatusActionType | null;
    isOpen: boolean;
    onClose: () => void;
    onConfirmed?: (enquiryId: number) => void;
}

export function getAvailableEnquiryActions(
    status?: string,
): EnquiryStatusActionType[] {
    if (!status) return [];

    if (status === 'new_lead') return ['contacted'];
    if (status === 'contacted') return ['confirmed'];

    return [];
}

export function EnquiryStatusAction({
    enquiryId,
    enquiryType,
    action,
    isOpen,
    onClose,
    onConfirmed,
}: EnquiryStatusActionProps) {
    const [isSubmitting, setIsSubmitting] = useState(false);

    const getTitle = () => {
        switch (action) {
            case 'contacted':
                return 'Mark as Contacted';
            case 'confirmed':
                return 'Confirm Enquiry';
            default:
                return '';
        }
    };

    const getDescription = () => {
        const isPrivate = (enquiryType ?? '').toLowerCase() === 'private';

        switch (action) {
            case 'contacted':
                return 'Mark this enquiry as contacted. The customer has been reached out to.';
            case 'confirmed':
                return isPrivate
                    ? 'Confirm this private enquiry. You will continue through a 2-step flow: package setup first, then customer confirmation.'
                    : 'Confirm this enquiry. This will open the Customer Confirmation Form to create a customer group.';
            default:
                return '';
        }
    };

    const handleSubmit = () => {
        if (!enquiryId || !action) return;
        setIsSubmitting(true);

        if (action === 'confirmed') {
            // For confirmed: skip status transition — it's done atomically in
            // the confirm endpoint when the customer confirmation form is submitted.
            // Just open the Customer Confirmation Form dialog.
            onClose();
            onConfirmed?.(enquiryId);
            setIsSubmitting(false);

            return;
        }

        // For contacted: just transition status
        router.put(
            transitionStatus(enquiryId).url,
            { status: action },
            {
                onSuccess: () => {
                    onClose();
                },
                onFinish: () => setIsSubmitting(false),
            },
        );
    };

    return (
        <Dialog open={isOpen && action !== null} onOpenChange={onClose}>
            <DialogContent className="sm:max-w-[450px]">
                <DialogHeader>
                    <DialogTitle>{getTitle()}</DialogTitle>
                    <DialogDescription>{getDescription()}</DialogDescription>
                </DialogHeader>

                {action === 'confirmed' && (
                    <div className="space-y-4 py-4">
                        <div className="flex items-start gap-2 rounded-md bg-muted/50 p-3 text-base text-muted-foreground">
                            <Info className="mt-0.5 h-4 w-4 shrink-0" />
                            <p>
                                {(enquiryType ?? '').toLowerCase() === 'private'
                                    ? 'Private enquiry workflow: Step 1 creates an exclusive package for this enquiry. Step 2 confirms customers and links them to that package.'
                                    : 'General enquiry workflow: after confirming, you will be prompted to complete the Customer Confirmation Form to create a customer group.'}
                            </p>
                        </div>
                    </div>
                )}

                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={onClose}
                        disabled={isSubmitting}
                    >
                        Cancel
                    </Button>

                    <Button onClick={handleSubmit} disabled={isSubmitting}>
                        {isSubmitting && (
                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                        )}
                        {action === 'contacted' && 'Mark Contacted'}
                        {action === 'confirmed' && 'Confirm'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
