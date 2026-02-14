import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { create } from '@/routes/invoice';
import { accept, cancel, expire, reject } from '@/routes/quotation';
import { router } from '@inertiajs/react';
import { Info, Loader2 } from 'lucide-react';
import { useState } from 'react';

export type QuotationStatusActionType =
    | 'accept'
    | 'convert'
    | 'reject'
    | 'expire'
    | 'cancel';

interface QuotationStatusActionProps {
    quotationId?: number;
    action: QuotationStatusActionType | null;
    isOpen: boolean;
    onClose: () => void;
}

export function getAvailableQuotationActions(
    status?: string,
): QuotationStatusActionType[] {
    if (!status) return [];

    if (status === 'sent' || status === 'revised') {
        return ['accept', 'reject', 'expire', 'cancel'];
    }

    if (status === 'accepted') {
        return ['convert', 'cancel'];
    }

    if (status === 'converted') {
        return ['cancel'];
    }

    return [];
}

export function QuotationStatusAction({
    quotationId,
    action,
    isOpen,
    onClose,
}: QuotationStatusActionProps) {
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [reason, setReason] = useState('');

    const getTitle = () => {
        switch (action) {
            case 'accept':
                return 'Accept Quotation';
            case 'convert':
                return 'Convert Quotation';
            case 'reject':
                return 'Reject Quotation';
            case 'expire':
                return 'Expire Quotation';
            case 'cancel':
                return 'Void Quotation';
            default:
                return '';
        }
    };

    const getDescription = () => {
        switch (action) {
            case 'accept':
                return 'Accept this quotation and proceed to the next step.';
            case 'convert':
                return 'Convert this quotation and proceed to invoice form.';
            case 'reject':
                return 'Reject this quotation and provide a reason.';
            case 'expire':
                return 'Mark this quotation as expired.';
            case 'cancel':
                return 'Void this quotation. All related invoices will be cancelled and financial transactions will be removed.';
            default:
                return '';
        }
    };

    const handleSubmit = () => {
        setIsSubmitting(true);

        if (!quotationId) {
            setIsSubmitting(false);
            return;
        }

        if (action === 'accept') {
            router.put(
                accept(quotationId).url,
                { quotation_id: Number(quotationId) },
                finishHandler,
            );
        }

        if (action === 'convert') {
            router.get(
                create().url,
                { quotation_id: Number(quotationId) },
                finishHandler,
            );
        }

        if (action === 'reject') {
            if (!reason.trim()) {
                alert('Please provide a reason');
                setIsSubmitting(false);
                return;
            }

            router.put(
                reject(quotationId).url,
                { reason: reason.trim() },
                finishHandler,
            );
        }

        if (action === 'expire') {
            router.put(expire(quotationId).url, {}, finishHandler);
        }

        if (action === 'cancel') {
            router.put(cancel(quotationId).url, {}, finishHandler);
        }
    };

    const finishHandler = {
        onSuccess: () => {
            onClose();
            setReason('');
        },
        onFinish: () => setIsSubmitting(false),
    };

    return (
        <Dialog open={isOpen && action !== null} onOpenChange={onClose}>
            <DialogContent className="sm:max-w-[450px]">
                <DialogHeader>
                    <DialogTitle>{getTitle()}</DialogTitle>
                    <DialogDescription>{getDescription()}</DialogDescription>
                </DialogHeader>

                {(action === 'reject' || action === 'expire') && (
                    <div className="space-y-4 py-4">
                        {action === 'reject' && (
                            <>
                                <div className="space-y-2">
                                    <Label htmlFor="reason">
                                        Reason / Notes *
                                    </Label>
                                    <Textarea
                                        id="reason"
                                        value={reason}
                                        onChange={(e) =>
                                            setReason(e.target.value)
                                        }
                                        placeholder="Explain why this quotation is rejected"
                                        className="min-h-[100px]"
                                        maxLength={1000}
                                    />
                                    <p className="text-right text-sm text-muted-foreground">
                                        {reason.length}/1000 characters
                                    </p>
                                </div>
                            </>
                        )}

                        {action === 'expire' && (
                            <div className="flex items-start gap-2 rounded-md bg-muted/50 p-3 text-base text-muted-foreground">
                                <Info className="mt-0.5 h-4 w-4" />
                                <p>
                                    This quotation will no longer be valid and
                                    cannot be accepted later.
                                </p>
                            </div>
                        )}
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
                        {action === 'accept' && 'Accept'}
                        {action === 'convert' && 'Convert'}
                        {action === 'reject' && 'Reject'}
                        {action === 'expire' && 'Expire'}
                        {action === 'cancel' && 'Void'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
