import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Row } from '@tanstack/react-table';
import { EllipsisVertical } from 'lucide-react';
import { ActionMenuItems } from './action-menu-items';

export type ActionType =
    | 'add'
    | 'preview'
    | 'create-receipt'
    | 'recreate-receipt'
    | 'receipt-preview'
    | 'view'
    | 'edit'
    | 'delete'
    | 'download'
    | 'handle-customer'
    | 'create-quotation'
    | 'enable-customer'
    | 'disable-customer'
    | 'set-default-year'
    | 'quotation-status-accept'
    | 'quotation-status-draft'
    | 'quotation-status-ready'
    | 'quotation-status-convert'
    | 'quotation-status-reject'
    | 'quotation-status-expire'
    | 'quotation-status-cancel'
    | 'quotation-handle'
    | 'enquiry-status-contacted'
    | 'enquiry-status-confirmed'
    | 'add-remark'
    | 'copy-customer-confirmation-public-edit-link'
    | 'move'
    | 'move-members'
    | 'combine-quotations'
    | 'combine-confirmations'
    | 'sync-billing'
    | 'cancel-member'
    | 'refund'
    | 'create-balance-invoice'
    | 'export-member-receipts-pdf'
    | 'send-email'
    | 'copy-link';

interface ActionColumnProps<TData> {
    row: Row<TData> | TData;
    actions?: ActionType[];
    onAction?: (action: ActionType, row: Row<TData> | TData) => void;
}

export function ActionColumn<TData>({
    row,
    actions = [],
    onAction,
}: ActionColumnProps<TData>) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" size="icon" className="h-8 w-8 p-0">
                    <span className="sr-only">Open menu</span>
                    <EllipsisVertical className="h-4 w-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <ActionMenuItems
                    row={row}
                    actions={actions}
                    onAction={onAction}
                    mode="dropdown"
                />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
