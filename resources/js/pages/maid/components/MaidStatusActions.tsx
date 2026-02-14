import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import maidRoutes from '@/routes/maid';
import * as updateStatusRoute from '@/routes/maid/update';
import { router } from '@inertiajs/react';
import { format } from 'date-fns';
import {
    CalendarIcon,
    ClipboardList,
    Clock,
    FileText,
    Info,
    Loader2,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { MaidSchema, status as statusOptions } from '../schema';

export type MaidStatusActionType =
    | 'schedule'
    | 'complete'
    | 'cancel'
    | 'update';

export interface MaidStatusActionsProps {
    maid: MaidSchema;
    isOpen: boolean;
    onClose: () => void;
    action: MaidStatusActionType | null;
}

// Helper function to get available actions based on current status
export function getAvailableMaidActions(
    status: string,
): MaidStatusActionType[] {
    switch (status?.toLowerCase()) {
        case 'available':
            return ['schedule']; // Can schedule interview
        case 'interviewing':
            return ['complete', 'cancel']; // Can complete or cancel interview
        case 'pending':
            return ['update']; // Can manually update status
        case 'assigned':
            return ['update']; // Can manually update status
        default:
            return []; // No actions for unknown status
    }
}

export function MaidStatusActions({
    maid,
    isOpen,
    onClose,
    action,
}: MaidStatusActionsProps) {
    const [interviewDate, setInterviewDate] = useState<Date | undefined>(
        undefined,
    );
    const [interviewStartTime, setInterviewStartTime] =
        useState<string>('09:00');
    const [interviewEndTime, setInterviewEndTime] = useState<string>('10:00');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [completeSuccess, setCompleteSuccess] = useState<boolean>(true);
    const [expectedDate, setExpectedDate] = useState<Date | undefined>(
        undefined,
    );
    const [reason, setReason] = useState<string>('');
    const [newStatus, setNewStatus] = useState<string>('');

    const handleScheduleMaid = () => {
        if (!interviewDate) {
            toast.error('Please select interview date');
            return;
        }

        // Parse start time
        const [startHours, startMinutes] = interviewStartTime
            .split(':')
            .map(Number);
        const startDateTime = new Date(interviewDate);
        startDateTime.setHours(startHours, startMinutes, 0, 0);

        // Parse end time
        const [endHours, endMinutes] = interviewEndTime.split(':').map(Number);
        const endDateTime = new Date(interviewDate);
        endDateTime.setHours(endHours, endMinutes, 0, 0);

        // Validate end time is after start time
        if (endDateTime <= startDateTime) {
            toast.error('End time must be after start time');
            return;
        }

        setIsSubmitting(true);
        router.post(
            maidRoutes.schedule.interview.url(maid.id!),
            {
                interview_date: format(startDateTime, 'yyyy-MM-dd HH:mm'),
                interview_end_date: format(endDateTime, 'yyyy-MM-dd HH:mm'),
            },
            {
                onSuccess: () => {
                    onClose();
                    setInterviewDate(undefined);
                    setInterviewStartTime('09:00');
                    setInterviewEndTime('10:00');
                },
                onFinish: () => setIsSubmitting(false),
            },
        );
    };

    const handleCompleteMaid = () => {
        // Validation for success scenario
        if (completeSuccess && !expectedDate) {
            toast.error('Please select expected completion date');
            return;
        }

        setIsSubmitting(true);

        const data: {
            success: boolean;
            handover_date?: string;
            reason?: string;
        } = {
            success: completeSuccess,
        };

        // Only include handover_date and reason if interview is successful
        if (completeSuccess) {
            if (expectedDate) {
                const dateOnly = new Date(expectedDate);
                dateOnly.setHours(0, 0, 0, 0);
                data.handover_date = format(dateOnly, 'yyyy-MM-dd');
            }
            if (reason.trim()) {
                data.reason = reason.trim();
            }
        }

        router.post(maidRoutes.complete.interview.url(maid.id!), data, {
            onSuccess: () => {
                onClose();
                setCompleteSuccess(true);
                setExpectedDate(undefined);
                setReason('');
            },
            onFinish: () => setIsSubmitting(false),
        });
    };

    const handleCancelMaid = () => {
        setIsSubmitting(true);
        router.delete(maidRoutes.cancel.interview.url(maid.id!), {
            onSuccess: () => onClose(),
            onFinish: () => setIsSubmitting(false),
        });
    };

    const handleUpdateMaid = () => {
        if (!newStatus) {
            toast.error('Please select a status');
            return;
        }
        setIsSubmitting(true);
        router.put(
            updateStatusRoute.status.url(maid.id!),
            { status: newStatus },
            {
                onSuccess: () => {
                    onClose();
                    setNewStatus('');
                },
                onFinish: () => setIsSubmitting(false),
            },
        );
    };

    const getDialogTitle = () => {
        switch (action) {
            case 'schedule':
                return 'Schedule Interview';
            case 'complete':
                return 'Complete Interview';
            case 'cancel':
                return 'Cancel Interview';
            case 'update':
                return 'Update Status';
            default:
                return '';
        }
    };

    const getDialogDescription = () => {
        switch (action) {
            case 'schedule':
                return `Schedule an interview date for ${maid.name}.`;
            case 'complete':
                return `Complete the interview for ${maid.name}.`;
            case 'cancel':
                return `Cancel the scheduled interview for ${maid.name}.`;
            case 'update':
                if (maid.status === 'pending') {
                    return `Update status for ${maid.name}.`;
                }
                return `Manually update the status for ${maid.name}. Ensure valid state transitions.`;
            default:
                return '';
        }
    };

    return (
        <Dialog open={isOpen && action !== null} onOpenChange={onClose}>
            <DialogContent className="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>{getDialogTitle()}</DialogTitle>
                    <DialogDescription>
                        {getDialogDescription()}
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-4">
                    {action === 'schedule' && (
                        <div className="space-y-2">
                            <Label>Interview Date & Time</Label>
                            <Popover>
                                <PopoverTrigger asChild>
                                    <Button
                                        variant="outline"
                                        className={cn(
                                            'w-full justify-start text-left font-normal',
                                            !interviewDate &&
                                                'text-muted-foreground',
                                        )}
                                    >
                                        <CalendarIcon className="mr-2 h-4 w-4" />
                                        {interviewDate ? (
                                            <span>
                                                {format(
                                                    interviewDate,
                                                    'MMM dd, yyyy',
                                                )}{' '}
                                                {interviewStartTime} -{' '}
                                                {interviewEndTime}
                                            </span>
                                        ) : (
                                            <span>Pick date and time</span>
                                        )}
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent
                                    className="w-auto overflow-hidden p-0"
                                    align="start"
                                >
                                    <div className="flex flex-col">
                                        <div className="border-b p-3">
                                            <Select
                                                onValueChange={(value) => {
                                                    const date = new Date();
                                                    if (value === '3days')
                                                        date.setDate(
                                                            date.getDate() + 3,
                                                        );
                                                    else if (value === '1week')
                                                        date.setDate(
                                                            date.getDate() + 7,
                                                        );
                                                    else if (value === '1month')
                                                        date.setMonth(
                                                            date.getMonth() + 1,
                                                        );
                                                    else if (
                                                        value === '3months'
                                                    )
                                                        date.setMonth(
                                                            date.getMonth() + 3,
                                                        );
                                                    else if (
                                                        value === 'endmonth'
                                                    )
                                                        date.setMonth(
                                                            date.getMonth() + 1,
                                                            0,
                                                        );
                                                    else if (
                                                        value === 'endnextmonth'
                                                    )
                                                        date.setMonth(
                                                            date.getMonth() + 2,
                                                            0,
                                                        );
                                                    setInterviewDate(date);
                                                }}
                                            >
                                                <SelectTrigger className="w-full">
                                                    <SelectValue placeholder="Quick select" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="3days">
                                                        In 3 days
                                                    </SelectItem>
                                                    <SelectItem value="1week">
                                                        In 1 week
                                                    </SelectItem>
                                                    <SelectItem value="1month">
                                                        In 1 month
                                                    </SelectItem>
                                                    <SelectItem value="3months">
                                                        In 3 months
                                                    </SelectItem>
                                                    <SelectItem value="endmonth">
                                                        End of month
                                                    </SelectItem>
                                                    <SelectItem value="endnextmonth">
                                                        End of next month
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <Calendar
                                            mode="single"
                                            selected={interviewDate}
                                            captionLayout="dropdown"
                                            onSelect={setInterviewDate}
                                            disabled={(date) =>
                                                date < new Date()
                                            }
                                            initialFocus
                                        />
                                        <div className="grid grid-cols-2 gap-2 border-t p-3">
                                            <div className="space-y-1">
                                                <Label
                                                    htmlFor="interview-start-time"
                                                    className="text-base"
                                                >
                                                    Start Time
                                                </Label>
                                                <Input
                                                    id="interview-start-time"
                                                    type="time"
                                                    step="60"
                                                    value={interviewStartTime}
                                                    onChange={(e) =>
                                                        setInterviewStartTime(
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="w-full appearance-none bg-background [&::-webkit-calendar-picker-indicator]:hidden [&::-webkit-calendar-picker-indicator]:appearance-none"
                                                />
                                            </div>
                                            <div className="space-y-1">
                                                <Label
                                                    htmlFor="interview-end-time"
                                                    className="text-base"
                                                >
                                                    End Time
                                                </Label>
                                                <Input
                                                    id="interview-end-time"
                                                    type="time"
                                                    step="60"
                                                    value={interviewEndTime}
                                                    onChange={(e) =>
                                                        setInterviewEndTime(
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="w-full appearance-none bg-background [&::-webkit-calendar-picker-indicator]:hidden [&::-webkit-calendar-picker-indicator]:appearance-none"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </PopoverContent>
                            </Popover>
                        </div>
                    )}

                    {action === 'complete' && (
                        <>
                            <div className="space-y-2">
                                <Label>Interview Result *</Label>
                                <Select
                                    value={
                                        completeSuccess ? 'success' : 'failed'
                                    }
                                    onValueChange={(val) =>
                                        setCompleteSuccess(val === 'success')
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select result" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="success">
                                            <span className="flex items-center gap-2">
                                                <span className="text-green-600">
                                                    ✓
                                                </span>
                                                Success - Move to Pending
                                            </span>
                                        </SelectItem>
                                        <SelectItem value="failed">
                                            <span className="flex items-center gap-2">
                                                <span className="text-red-600">
                                                    ✗
                                                </span>
                                                Unsuccessful - Revert to
                                                Available
                                            </span>
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            {completeSuccess && (
                                <>
                                    <div className="space-y-2">
                                        <Label>Handover Date</Label>
                                        <Popover>
                                            <PopoverTrigger asChild>
                                                <Button
                                                    variant={'outline'}
                                                    className={cn(
                                                        'w-full justify-start text-left font-normal',
                                                        !expectedDate &&
                                                            'text-muted-foreground',
                                                    )}
                                                >
                                                    <CalendarIcon className="mr-2" />
                                                    {expectedDate ? (
                                                        format(
                                                            expectedDate,
                                                            'PPP',
                                                        )
                                                    ) : (
                                                        <span>
                                                            Pick handover date
                                                        </span>
                                                    )}
                                                </Button>
                                            </PopoverTrigger>
                                            <PopoverContent
                                                className="w-auto overflow-hidden p-0"
                                                align="start"
                                            >
                                                <div className="flex flex-col">
                                                    <Calendar
                                                        mode="single"
                                                        selected={expectedDate}
                                                        captionLayout="dropdown"
                                                        onSelect={
                                                            setExpectedDate
                                                        }
                                                        disabled={(date) =>
                                                            date <
                                                            new Date(
                                                                new Date().setHours(
                                                                    0,
                                                                    0,
                                                                    0,
                                                                    0,
                                                                ),
                                                            )
                                                        }
                                                        initialFocus
                                                    />
                                                    <div className="border-t p-3">
                                                        <Select
                                                            onValueChange={(
                                                                value,
                                                            ) => {
                                                                const date =
                                                                    new Date();
                                                                if (
                                                                    value ===
                                                                    '3days'
                                                                )
                                                                    date.setDate(
                                                                        date.getDate() +
                                                                            3,
                                                                    );
                                                                else if (
                                                                    value ===
                                                                    '1week'
                                                                )
                                                                    date.setDate(
                                                                        date.getDate() +
                                                                            7,
                                                                    );
                                                                else if (
                                                                    value ===
                                                                    '1month'
                                                                )
                                                                    date.setMonth(
                                                                        date.getMonth() +
                                                                            1,
                                                                    );
                                                                else if (
                                                                    value ===
                                                                    '3months'
                                                                )
                                                                    date.setMonth(
                                                                        date.getMonth() +
                                                                            3,
                                                                    );
                                                                else if (
                                                                    value ===
                                                                    'endmonth'
                                                                )
                                                                    date.setMonth(
                                                                        date.getMonth() +
                                                                            1,
                                                                        0,
                                                                    );
                                                                else if (
                                                                    value ===
                                                                    'endnextmonth'
                                                                )
                                                                    date.setMonth(
                                                                        date.getMonth() +
                                                                            2,
                                                                        0,
                                                                    );
                                                                setExpectedDate(
                                                                    date,
                                                                );
                                                            }}
                                                        >
                                                            <SelectTrigger className="w-full">
                                                                <SelectValue placeholder="Quick select" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem value="3days">
                                                                    In 3 days
                                                                </SelectItem>
                                                                <SelectItem value="1week">
                                                                    In 1 week
                                                                </SelectItem>
                                                                <SelectItem value="1month">
                                                                    In 1 month
                                                                </SelectItem>
                                                                <SelectItem value="3months">
                                                                    In 3 months
                                                                </SelectItem>
                                                                <SelectItem value="endmonth">
                                                                    End of month
                                                                </SelectItem>
                                                                <SelectItem value="endnextmonth">
                                                                    End of next
                                                                    month
                                                                </SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                </div>
                                            </PopoverContent>
                                        </Popover>
                                        <p className="text-sm text-muted-foreground">
                                            When will the maid be handed over to
                                            the customer?
                                        </p>
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="reason">
                                            Reason / Notes{' '}
                                            <span className="text-sm font-normal text-muted-foreground">
                                                (Optional)
                                            </span>
                                        </Label>
                                        <Textarea
                                            id="reason"
                                            value={reason}
                                            onChange={(e) =>
                                                setReason(e.target.value)
                                            }
                                            placeholder="e.g., Waiting for medical certificate, passport renewal, etc."
                                            className="min-h-[80px] resize-none"
                                            maxLength={1000}
                                        />
                                        <p className="text-right text-sm text-muted-foreground">
                                            {reason.length}/1000 characters
                                        </p>
                                    </div>
                                </>
                            )}

                            {!completeSuccess && (
                                <div className="flex items-start gap-2 rounded-md bg-muted/50 p-3 text-base text-muted-foreground">
                                    <Info className="mt-0.5 h-4 w-4 flex-shrink-0" />
                                    <p>
                                        Maid will be reverted to{' '}
                                        <strong>Available</strong> status and
                                        can be scheduled for a new interview.
                                    </p>
                                </div>
                            )}
                        </>
                    )}

                    {action === 'cancel' && (
                        <p className="text-base text-muted-foreground">
                            This will cancel the scheduled interview and revert
                            the status to "Available".
                        </p>
                    )}

                    {action === 'update' && (
                        <>
                            <div className="space-y-2">
                                <Label>New Status</Label>
                                <Select
                                    value={newStatus}
                                    onValueChange={setNewStatus}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {statusOptions
                                            .filter(
                                                (opt) =>
                                                    opt.value !== maid.status,
                                            )
                                            .map((opt) => (
                                                <SelectItem
                                                    key={opt.value}
                                                    value={opt.value}
                                                >
                                                    {opt.label}
                                                </SelectItem>
                                            ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {maid.status === 'pending' && (
                                <div className="space-y-3 rounded-lg bg-blue-50 p-4 dark:bg-blue-950/30">
                                    <div className="flex items-center gap-2">
                                        <ClipboardList className="h-4 w-4 text-blue-900 dark:text-blue-100" />
                                        <p className="text-base font-medium text-blue-900 dark:text-blue-100">
                                            Status Workflow:
                                        </p>
                                    </div>
                                    <ul className="ml-6 space-y-1 text-base text-blue-800 dark:text-blue-200">
                                        <li>
                                            • <strong>Assigned</strong> -
                                            Documents are ready, maid is
                                            assigned to customer
                                        </li>
                                        <li>
                                            • <strong>Available</strong> -
                                            Cancel pending, make maid available
                                            again
                                        </li>
                                    </ul>
                                    {maid.pending_until && (
                                        <div className="mt-2 flex items-center gap-2 text-sm text-blue-700 dark:text-blue-300">
                                            <Clock className="h-3.5 w-3.5" />
                                            <span>
                                                Expected completion:{' '}
                                                {new Date(
                                                    maid.pending_until,
                                                ).toLocaleDateString('en-US', {
                                                    year: 'numeric',
                                                    month: 'short',
                                                    day: 'numeric',
                                                })}
                                            </span>
                                        </div>
                                    )}
                                    {maid.pending_reason && (
                                        <div className="flex items-start gap-2 text-sm text-blue-700 dark:text-blue-300">
                                            <FileText className="mt-0.5 h-3.5 w-3.5 flex-shrink-0" />
                                            <span>
                                                Reason: {maid.pending_reason}
                                            </span>
                                        </div>
                                    )}
                                </div>
                            )}
                        </>
                    )}
                </div>

                <DialogFooter>
                    <div className="flex justify-end gap-2">
                        <Button
                            variant="outline"
                            onClick={onClose}
                            disabled={isSubmitting}
                        >
                            Cancel
                        </Button>
                        <Button
                            onClick={() => {
                                switch (action) {
                                    case 'schedule':
                                        handleScheduleMaid();
                                        break;
                                    case 'complete':
                                        handleCompleteMaid();
                                        break;
                                    case 'cancel':
                                        handleCancelMaid();
                                        break;
                                    case 'update':
                                        handleUpdateMaid();
                                        break;
                                }
                            }}
                            disabled={isSubmitting}
                        >
                            {isSubmitting && (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            )}
                            {action === 'schedule' && 'Schedule Interview'}
                            {action === 'complete' &&
                                (completeSuccess
                                    ? 'Complete'
                                    : 'Mark as Failed')}
                            {action === 'cancel' && 'Cancel Interview'}
                            {action === 'update' && 'Update Status'}
                        </Button>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
