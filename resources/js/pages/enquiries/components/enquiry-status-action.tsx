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

export type EnquiryStatusActionType = 'contacted' | 'negotiating' | 'confirmed';

interface EnquiryStatusActionProps {
    enquiryId?: number;
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
    if (status === 'contacted') return ['negotiating'];
    if (status === 'negotiating') return ['confirmed'];

    return [];
}

export function EnquiryStatusAction({
    enquiryId,
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
            case 'negotiating':
                return 'Mark as Negotiating';
            case 'confirmed':
                return 'Confirm Enquiry';
            default:
                return '';
        }
    };

    const getDescription = () => {
        switch (action) {
            case 'contacted':
                return 'Mark this enquiry as contacted. The customer has been reached out to.';
            case 'negotiating':
                return 'Move this enquiry to negotiating stage. Terms are being discussed with the customer.';
            case 'confirmed':
                return 'Confirm this enquiry. This will open the Customer Confirmation Form to create a customer group.';
            default:
                return '';
        }
    };

    const handleSubmit = () => {
        if (!enquiryId || !action) return;
        setIsSubmitting(true);

        if (action === 'confirmed') {
            // For confirmed: transition status, then trigger the confirmation form
            router.put(
                transitionStatus(enquiryId).url,
                { status: 'confirmed' },
                {
                    onSuccess: () => {
                        onClose();
                        onConfirmed?.(enquiryId);
                    },
                    onFinish: () => setIsSubmitting(false),
                },
            );

            return;
        }

        // For contacted / negotiating: just transition status
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
                        <div className="flex items-start gap-2 rounded-md bg-muted/50 p-3 text-sm text-muted-foreground">
                            <Info className="mt-0.5 h-4 w-4 shrink-0" />
                            <p>
                                After confirming, you will be prompted to fill
                                in the Customer Confirmation Form to create a
                                customer group with leader and participants.
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
                        {action === 'negotiating' && 'Mark Negotiating'}
                        {action === 'confirmed' && 'Confirm'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
