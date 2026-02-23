import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { OptionType } from '@/types';
import { useState } from 'react';
import GeneralEnquiryForm from '../../general-enquiries/form';
import type { GeneralEnquirySchema } from '../../general-enquiries/schema';
import PrivateEnquiryForm from '../../private-enquiries/form';
import type { PrivateEnquirySchema } from '../../private-enquiries/schema';
import { statusColors, typeColors } from '../schema';
import EnquiryRemarksTimeline from './enquiry-remarks-timeline';
import {
    EnquiryStatusAction,
    EnquiryStatusActionType,
    getAvailableEnquiryActions,
} from './enquiry-status-action';

export interface EnquiryViewDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    enquiryId?: number;
    enquiryType?: string;
    statusLabel?: string;
    statusValue?: string;
    childData?: Record<string, unknown> | null;
    isLoadingChild?: boolean;
    packageOptions?: OptionType[];
    showStatusActions?: boolean;
    onStatusActionConfirmed?: (enquiryId: number) => void;
}

export default function EnquiryViewDialog({
    open,
    onOpenChange,
    enquiryId,
    enquiryType,
    statusLabel,
    statusValue,
    childData,
    isLoadingChild,
    packageOptions = [],
    showStatusActions = false,
    onStatusActionConfirmed,
}: EnquiryViewDialogProps) {
    const [statusAction, setStatusAction] =
        useState<EnquiryStatusActionType | null>(null);
    const [statusDialogOpen, setStatusDialogOpen] = useState(false);
    const [statusActionEnquiryId, setStatusActionEnquiryId] = useState<
        number | undefined
    >();

    const typeKey = enquiryType === 'general' ? 'General' : 'Private';
    const availableActions =
        showStatusActions && statusValue
            ? getAvailableEnquiryActions(statusValue)
            : [];

    return (
        <>
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent className="flex max-h-[95%] max-w-[95%] min-w-[95%] flex-col overflow-y-hidden">
                    <DialogHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <DialogTitle>Enquiry Details</DialogTitle>
                                <DialogDescription className="sr-only">
                                    Displays detailed information about the
                                    selected enquiry.
                                </DialogDescription>
                            </div>
                            <div className="mr-8 flex items-center gap-2">
                                {enquiryType && (
                                    <Badge
                                        className={`${typeColors[typeKey] ?? ''} rounded-full px-3 py-1 text-base`}
                                    >
                                        {typeKey}
                                    </Badge>
                                )}
                                {statusLabel && (
                                    <Badge
                                        className={`${statusColors[statusValue ?? ''] ?? ''} rounded-full px-3 py-1 text-base`}
                                    >
                                        {statusLabel}
                                    </Badge>
                                )}
                                {availableActions.map((action) => (
                                    <Button
                                        key={action}
                                        size="sm"
                                        variant="outline"
                                        onClick={() => {
                                            setStatusAction(action);
                                            setStatusActionEnquiryId(enquiryId);
                                            setStatusDialogOpen(true);
                                        }}
                                    >
                                        {action === 'contacted' &&
                                            'Mark Contacted'}
                                        {action === 'negotiating' &&
                                            'Mark Negotiating'}
                                        {action === 'confirmed' && 'Confirm'}
                                    </Button>
                                ))}
                            </div>
                        </div>
                    </DialogHeader>

                    <div className="h-full w-full flex-1 overflow-y-auto">
                        {isLoadingChild && (
                            <div className="flex h-full items-center justify-center text-muted-foreground">
                                Loading enquiry details...
                            </div>
                        )}
                        {!isLoadingChild && childData && (
                            <>
                                {enquiryType === 'general' ? (
                                    <GeneralEnquiryForm
                                        mode="view"
                                        initialData={
                                            childData as unknown as GeneralEnquirySchema
                                        }
                                        packageOptions={packageOptions}
                                    />
                                ) : (
                                    <PrivateEnquiryForm
                                        mode="view"
                                        initialData={
                                            childData as unknown as PrivateEnquirySchema
                                        }
                                    />
                                )}

                                {enquiryId && (
                                    <Card className="mb-2">
                                        <CardHeader>
                                            <CardTitle className="text-xl">
                                                Enquiry Remarks Timeline
                                            </CardTitle>
                                            <CardDescription>
                                                View the history of remarks for
                                                this enquiry.
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            <EnquiryRemarksTimeline
                                                isOpen={open}
                                                enquiryId={enquiryId}
                                            />
                                        </CardContent>
                                    </Card>
                                )}
                            </>
                        )}
                        {!isLoadingChild && !childData && (
                            <div className="flex h-full items-center justify-center text-muted-foreground">
                                Failed to load enquiry details.
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>

            {showStatusActions && enquiryId && (
                <EnquiryStatusAction
                    enquiryId={statusActionEnquiryId}
                    action={statusAction}
                    isOpen={statusDialogOpen}
                    onClose={() => {
                        setStatusDialogOpen(false);
                        setStatusAction(null);
                    }}
                    onConfirmed={(id) => {
                        onStatusActionConfirmed?.(id);
                    }}
                />
            )}
        </>
    );
}
