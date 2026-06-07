import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { reject } from '@/routes/package-proposals';
import { router } from '@inertiajs/react';
import { useState } from 'react';

interface RejectDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    proposalId?: number;
}

export function RejectDialog({
    open,
    onOpenChange,
    proposalId,
}: RejectDialogProps) {
    const [reason, setReason] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const handleClose = () => {
        onOpenChange(false);
        setReason('');
    };

    const handleReject = () => {
        if (!proposalId || !reason.trim()) return;
        setSubmitting(true);
        router.post(
            reject(proposalId).url,
            { rejection_reason: reason },
            {
                onSuccess: () => handleClose(),
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Reject Proposal</DialogTitle>
                    <DialogDescription>
                        Please provide a reason for rejecting this proposal.
                    </DialogDescription>
                </DialogHeader>
                <Textarea
                    value={reason}
                    onChange={(e) => setReason(e.target.value)}
                    rows={3}
                    placeholder="Reason for rejection..."
                />
                <DialogFooter>
                    <Button variant="outline" onClick={handleClose}>
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        disabled={!reason.trim() || submitting}
                        onClick={handleReject}
                    >
                        {submitting ? 'Rejecting...' : 'Reject'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
