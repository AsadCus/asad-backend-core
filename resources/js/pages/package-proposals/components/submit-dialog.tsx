import { ProperInputSelect } from '@/components/proper-input-select';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { submit } from '@/routes/package-proposals';
import { router } from '@inertiajs/react';
import { Mail, X } from 'lucide-react';
import { useMemo, useState } from 'react';

interface ApproverOption {
    id: number;
    name: string;
    email: string;
}

interface SubmitForApprovalDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    proposalId?: number;
    approverOptions: ApproverOption[];
}

export function SubmitForApprovalDialog({
    open,
    onOpenChange,
    proposalId,
    approverOptions,
}: SubmitForApprovalDialogProps) {
    const [selected, setSelected] = useState<string[]>([]);
    const [submitting, setSubmitting] = useState(false);

    const options = useMemo(
        () =>
            approverOptions.map((a) => ({
                value: String(a.id),
                label: `${a.name} — ${a.email}`,
            })),
        [approverOptions],
    );

    const selectedApprovers = useMemo(
        () =>
            selected
                .map((id) => approverOptions.find((a) => String(a.id) === id))
                .filter((a): a is ApproverOption => Boolean(a)),
        [selected, approverOptions],
    );

    const handleClose = () => {
        onOpenChange(false);
        setSelected([]);
    };

    const removeApprover = (id: number) => {
        setSelected((prev) => prev.filter((v) => v !== String(id)));
    };

    const handleSubmit = () => {
        if (!proposalId || selected.length === 0) return;
        setSubmitting(true);
        router.post(
            submit(proposalId).url,
            { approver_user_ids: selected.map((v) => Number(v)) },
            {
                onSuccess: () => handleClose(),
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[95%] flex-col">
                <DialogHeader>
                    <DialogTitle>Submit for Approval</DialogTitle>
                    <DialogDescription>
                        Select superadmin user(s) to notify and seek approval from.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex-1 space-y-4 overflow-y-auto py-1">
                    <ProperInputSelect
                        mode="multi"
                        options={options}
                        value={selected}
                        onValueChange={setSelected}
                        placeholder="Search and select approver(s)..."
                        maxCount={1}
                        singleLine
                        closeOnSelect={false}
                    />

                    {selectedApprovers.length > 0 ? (
                        <div className="space-y-2">
                            {selectedApprovers.map((approver) => (
                                <div
                                    key={approver.id}
                                    className="flex items-center justify-between gap-3 rounded-lg border p-3"
                                >
                                    <div className="flex min-w-0 flex-col">
                                        <span className="truncate font-medium">
                                            {approver.name}
                                        </span>
                                        <span className="flex items-center gap-1 truncate text-sm text-muted-foreground">
                                            <Mail className="h-3 w-3 shrink-0" />
                                            {approver.email}
                                        </span>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="h-8 w-8 shrink-0 text-destructive"
                                        onClick={() => removeApprover(approver.id)}
                                    >
                                        <X className="h-4 w-4" />
                                    </Button>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            {approverOptions.length === 0
                                ? 'No approvers available for this country.'
                                : 'No approvers selected yet.'}
                        </p>
                    )}
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={handleClose}>
                        Cancel
                    </Button>
                    <Button
                        disabled={selected.length === 0 || submitting}
                        onClick={handleSubmit}
                    >
                        {submitting ? 'Submitting...' : 'Submit'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
