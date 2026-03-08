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
import { ValidationAlertDialog } from '@/components/validation-alert-dialog';
import { create } from '@/routes/invoice';
import {
    accept,
    cancel,
    edit,
    expire,
    getForShow,
    ready,
    reject,
} from '@/routes/quotation';
import { router } from '@inertiajs/react';
import { Info, Loader2 } from 'lucide-react';
import { useState } from 'react';

export type QuotationStatusActionType =
    | 'ready'
    | 'accept'
    | 'convert'
    | 'reject'
    | 'expire'
    | 'cancel';

interface MissingFieldInfo {
    key: string;
    label: string;
}

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

    if (status === 'draft' || status === 'rejected' || status === 'expired') {
        return ['ready'];
    }

    if (status === 'ready' || status === 'revised') {
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
    const [reasonError, setReasonError] = useState('');
    const [missingFields, setMissingFields] = useState<MissingFieldInfo[]>([]);
    const [showValidationAlert, setShowValidationAlert] = useState(false);

    const validateAndMarkReady = async () => {
        if (!quotationId) {
            setIsSubmitting(false);
            return;
        }

        try {
            // Fetch quotation data for validation
            const response = await fetch(getForShow(quotationId).url, {
                method: 'GET',
            });

            if (!response.ok) {
                throw new Error('Failed to fetch quotation');
            }

            const quotationData = await response.json();
            const payload = quotationData?.data ?? quotationData ?? {};

            const isValueMissing = (value: unknown): boolean => {
                if (value === null || value === undefined) {
                    return true;
                }

                if (typeof value === 'string') {
                    return value.trim().length === 0;
                }

                return false;
            };

            // Check required fields (based on sentRules)
            const requiredFields: MissingFieldInfo[] = [
                { key: 'customer_id', label: 'Customer' },
                { key: 'quotation_date', label: 'Quotation Date' },
                { key: 'expiry_date', label: 'Expiry Date' },
                { key: 'payment_plan', label: 'Payment Plan' },
                { key: 'payment_method', label: 'Payment Method' },
                { key: 'description', label: 'Description' },
            ];

            const nextMissingFields: MissingFieldInfo[] = [];

            requiredFields.forEach(({ key, label }) => {
                const value = payload[key as keyof typeof payload];

                if (isValueMissing(value)) {
                    nextMissingFields.push({ key, label });
                }
            });

            // Check if items exist
            const items = Array.isArray(payload.items) ? payload.items : [];

            if (items.length === 0) {
                nextMissingFields.push({
                    key: 'items',
                    label: 'Quotation Items',
                });
            }

            if (nextMissingFields.length > 0) {
                setMissingFields(nextMissingFields);
                setShowValidationAlert(true);
                onClose();
                setIsSubmitting(false);
                return;
            }

            // All validations passed, proceed with marking as ready
            setMissingFields([]);
            router.put(ready(quotationId).url, {}, finishHandler);
        } catch {
            setIsSubmitting(false);
            setMissingFields([]);
            setShowValidationAlert(true);
        }
    };

    const getTitle = () => {
        switch (action) {
            case 'ready':
                return 'Mark Quotation as Ready';
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
            case 'ready':
                return 'Mark this quotation as ready to proceed with customer decision.';
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

        if (action === 'ready') {
            // Validate quotation before marking as ready
            validateAndMarkReady();
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
                setReasonError('Reason / Notes is required.');
                setIsSubmitting(false);
                return;
            }

            setReasonError('');

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
            setReasonError('');
        },
        onFinish: () => setIsSubmitting(false),
    };

    return (
        <>
            <Dialog open={isOpen && action !== null} onOpenChange={onClose}>
                <DialogContent className="sm:max-w-[450px]">
                    <DialogHeader>
                        <DialogTitle>{getTitle()}</DialogTitle>
                        <DialogDescription>
                            {getDescription()}
                        </DialogDescription>
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
                                            onChange={(e) => {
                                                setReason(e.target.value);
                                                if (reasonError) {
                                                    setReasonError('');
                                                }
                                            }}
                                            placeholder="Explain why this quotation is rejected"
                                            className="min-h-[100px]"
                                            maxLength={1000}
                                        />
                                        {reasonError && (
                                            <p className="text-sm text-red-500">
                                                {reasonError}
                                            </p>
                                        )}
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
                                        This quotation will no longer be valid
                                        and cannot be accepted later.
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
                            {action === 'ready' && 'Mark Ready'}
                            {action === 'accept' && 'Accept'}
                            {action === 'convert' && 'Convert'}
                            {action === 'reject' && 'Reject'}
                            {action === 'expire' && 'Expire'}
                            {action === 'cancel' && 'Void'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ValidationAlertDialog
                open={showValidationAlert}
                onOpenChange={setShowValidationAlert}
                title="Required Fields Are Missing"
                description="This quotation cannot be marked as Ready because required fields are still missing."
                items={missingFields}
                cancelText="Close"
                confirmText="Open Form & Go To Field"
                onConfirm={() => {
                    if (!quotationId) {
                        return;
                    }

                    const firstMissingField =
                        missingFields[0]?.key ?? 'customer_id';

                    router.visit(
                        edit(quotationId, {
                            query: {
                                focus_field: firstMissingField,
                            },
                        }).url,
                    );
                }}
            />
        </>
    );
}
