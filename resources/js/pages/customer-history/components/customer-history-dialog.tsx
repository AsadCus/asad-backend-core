import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { History, Mail, MapPin, Phone } from 'lucide-react';
import CustomerHistoryTimeline from './customer-history-timeline';

interface CustomerHistoryDialogProps {
    isOpen: boolean;
    onClose: () => void;
    customerId: number | undefined;
    customerName?: string;
    customerEmail?: string;
    customerContact?: string;
    customerAddress?: string;
}

export default function CustomerHistoryDialog({
    isOpen,
    onClose,
    customerId,
    customerName,
    customerEmail,
    customerContact,
    customerAddress,
}: CustomerHistoryDialogProps) {
    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="flex max-h-[90%] flex-col md:max-h-[85vh] md:max-w-4xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-xl">
                        <History className="h-5 w-5" />
                        History Record
                    </DialogTitle>
                    <DialogDescription className="sr-only">
                        View the full history record — enquiries, packages,
                        travel, and payments — for this customer.
                    </DialogDescription>
                </DialogHeader>

                {customerName && (
                    <div className="rounded-lg border border-gray-100 bg-gray-50/50 px-5 py-4 dark:border-gray-800 dark:bg-gray-900/50">
                        <h3 className="mb-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {customerName}
                        </h3>
                        <div className="flex flex-wrap gap-x-6 gap-y-1.5 text-sm">
                            {customerEmail && (
                                <span className="flex items-center gap-1.5 text-muted-foreground">
                                    <Mail className="h-3.5 w-3.5 shrink-0" />
                                    Email :{' '}
                                    <span className="text-gray-800 dark:text-gray-200">
                                        {customerEmail}
                                    </span>
                                </span>
                            )}
                            {customerContact && customerContact !== '-' && (
                                <span className="flex items-center gap-1.5 text-muted-foreground">
                                    <Phone className="h-3.5 w-3.5 shrink-0" />
                                    Contact :{' '}
                                    <span className="text-gray-800 dark:text-gray-200">
                                        {customerContact}
                                    </span>
                                </span>
                            )}
                            {customerAddress && (
                                <span className="flex items-center gap-1.5 text-muted-foreground">
                                    <MapPin className="h-3.5 w-3.5 shrink-0" />
                                    Address :{' '}
                                    <span className="text-gray-800 dark:text-gray-200">
                                        {customerAddress}
                                    </span>
                                </span>
                            )}
                        </div>
                    </div>
                )}

                <div className="flex-1 overflow-y-auto">
                    <CustomerHistoryTimeline
                        isOpen={isOpen}
                        customerId={customerId}
                    />
                </div>
            </DialogContent>
        </Dialog>
    );
}
