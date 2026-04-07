import {
    ContextMenuItem,
    ContextMenuSeparator,
    ContextMenuSub,
    ContextMenuSubContent,
    ContextMenuSubTrigger,
} from '@/components/ui/context-menu';
import {
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
} from '@/components/ui/dropdown-menu';
import { Row } from '@tanstack/react-table';
import { ActionType } from './action-column';

interface ActionMenuItemsProps<TData> {
    row: Row<TData> | TData;
    actions: ActionType[];
    onAction?: (action: ActionType, row: Row<TData> | TData) => void;
    mode: 'dropdown' | 'context';
}

function isTableRow<TData>(obj: unknown): obj is Row<TData> {
    return typeof obj === 'object' && obj !== null && 'original' in obj;
}

type WithHandledBy = { handled_by?: string | null };

export function ActionMenuItems<TData>({
    row,
    actions,
    onAction,
    mode,
}: ActionMenuItemsProps<TData>) {
    const Item = mode === 'dropdown' ? DropdownMenuItem : ContextMenuItem;
    const Separator =
        mode === 'dropdown' ? DropdownMenuSeparator : ContextMenuSeparator;

    const Sub = mode === 'dropdown' ? DropdownMenuSub : ContextMenuSub;
    const SubTrigger =
        mode === 'dropdown' ? DropdownMenuSubTrigger : ContextMenuSubTrigger;
    const SubContent =
        mode === 'dropdown' ? DropdownMenuSubContent : ContextMenuSubContent;

    const handledBy = isTableRow<TData>(row)
        ? (row.original as WithHandledBy)?.handled_by
        : (row as TData & WithHandledBy).handled_by;

    const hasQuotationStatusActions =
        actions.includes('quotation-status-draft') ||
        actions.includes('quotation-status-ready') ||
        actions.includes('quotation-status-accept') ||
        actions.includes('quotation-status-convert') ||
        actions.includes('quotation-status-reject') ||
        actions.includes('quotation-status-expire') ||
        actions.includes('quotation-status-cancel');

    const hasEnquiryStatusActions =
        actions.includes('enquiry-status-contacted') ||
        actions.includes('enquiry-status-confirmed');

    return (
        <>
            {actions.includes('preview') && (
                <Item onClick={() => onAction?.('preview', row)}>Preview</Item>
            )}

            {actions.includes('create-receipt') && (
                <Item onClick={() => onAction?.('create-receipt', row)}>
                    Create Receipt
                </Item>
            )}

            {actions.includes('receipt-preview') && (
                <Item onClick={() => onAction?.('receipt-preview', row)}>
                    Receipt Preview
                </Item>
            )}

            {actions.includes('view') && (
                <Item onClick={() => onAction?.('view', row)}>View</Item>
            )}

            {actions.includes('edit') && (
                <Item onClick={() => onAction?.('edit', row)}>Edit</Item>
            )}

            {hasQuotationStatusActions && (
                <>
                    <Separator />
                    <Sub>
                        <SubTrigger>Change Status</SubTrigger>
                        <SubContent>
                            {actions.includes('quotation-status-draft') && (
                                <Item
                                    onClick={() =>
                                        onAction?.(
                                            'quotation-status-draft',
                                            row,
                                        )
                                    }
                                >
                                    Move to Draft
                                </Item>
                            )}

                            {actions.includes('quotation-status-ready') && (
                                <Item
                                    onClick={() =>
                                        onAction?.(
                                            'quotation-status-ready',
                                            row,
                                        )
                                    }
                                >
                                    Mark as Ready
                                </Item>
                            )}

                            {actions.includes('quotation-status-accept') && (
                                <Item
                                    onClick={() =>
                                        onAction?.(
                                            'quotation-status-accept',
                                            row,
                                        )
                                    }
                                >
                                    Accept Quotation
                                </Item>
                            )}

                            {actions.includes('quotation-status-convert') && (
                                <Item
                                    onClick={() =>
                                        onAction?.(
                                            'quotation-status-convert',
                                            row,
                                        )
                                    }
                                >
                                    Convert to Invoice
                                </Item>
                            )}

                            {actions.includes('quotation-status-reject') && (
                                <Item
                                    onClick={() =>
                                        onAction?.(
                                            'quotation-status-reject',
                                            row,
                                        )
                                    }
                                >
                                    Reject Quotation
                                </Item>
                            )}

                            {actions.includes('quotation-status-expire') && (
                                <Item
                                    onClick={() =>
                                        onAction?.(
                                            'quotation-status-expire',
                                            row,
                                        )
                                    }
                                >
                                    Expire Quotation
                                </Item>
                            )}

                            {actions.includes('quotation-status-cancel') && (
                                <Item
                                    onClick={() =>
                                        onAction?.(
                                            'quotation-status-cancel',
                                            row,
                                        )
                                    }
                                >
                                    Void Quotation
                                </Item>
                            )}
                        </SubContent>
                    </Sub>
                </>
            )}

            {hasEnquiryStatusActions && (
                <>
                    <Separator />
                    <Sub>
                        <SubTrigger>Enquiry Status</SubTrigger>
                        <SubContent>
                            {actions.includes('enquiry-status-contacted') && (
                                <Item
                                    onClick={() =>
                                        onAction?.(
                                            'enquiry-status-contacted',
                                            row,
                                        )
                                    }
                                >
                                    Mark as Contacted
                                </Item>
                            )}

                            {actions.includes('enquiry-status-confirmed') && (
                                <Item
                                    onClick={() =>
                                        onAction?.(
                                            'enquiry-status-confirmed',
                                            row,
                                        )
                                    }
                                >
                                    Confirm Enquiry
                                </Item>
                            )}
                        </SubContent>
                    </Sub>
                </>
            )}

            {actions.includes('download') && (
                <Item onClick={() => onAction?.('download', row)}>
                    Download PDF
                </Item>
            )}

            {actions.includes('set-default-year') && (
                <Item onClick={() => onAction?.('set-default-year', row)}>
                    Set Default
                </Item>
            )}

            {actions.includes('create-quotation') && (
                <Item onClick={() => onAction?.('create-quotation', row)}>
                    Create Quotation
                </Item>
            )}

            {actions.includes(
                'copy-customer-confirmation-public-edit-link',
            ) && (
                <Item
                    onClick={() =>
                        onAction?.(
                            'copy-customer-confirmation-public-edit-link',
                            row,
                        )
                    }
                >
                    Copy Public Edit Link
                </Item>
            )}

            {actions.includes('add-remark') && (
                <Item onClick={() => onAction?.('add-remark', row)}>
                    Remark
                </Item>
            )}

            {actions.includes('move-members') && (
                <Item onClick={() => onAction?.('move-members', row)}>
                    Move to Holding Area
                </Item>
            )}

            {actions.includes('sync-billing') && (
                <Item onClick={() => onAction?.('sync-billing', row)}>
                    Sync Billing
                </Item>
            )}

            {actions.includes('create-balance-invoice') && (
                <Item onClick={() => onAction?.('create-balance-invoice', row)}>
                    Create Balance Invoice
                </Item>
            )}

            {actions.includes('refund') && (
                <Item onClick={() => onAction?.('refund', row)}>Refund</Item>
            )}

            {actions.includes('refund-overpaid') && (
                <Item onClick={() => onAction?.('refund-overpaid', row)}>
                    Refund Overpaid
                </Item>
            )}

            {actions.includes('cancel-member') && (
                <Item onClick={() => onAction?.('cancel-member', row)}>
                    Cancel
                </Item>
            )}

            {actions.includes('handle-customer') && !handledBy && (
                <Item onClick={() => onAction?.('handle-customer', row)}>
                    Handle
                </Item>
            )}

            {actions.includes('enable-customer') && (
                <Item onClick={() => onAction?.('enable-customer', row)}>
                    Enable Customer
                </Item>
            )}

            {actions.includes('disable-customer') && (
                <Item onClick={() => onAction?.('disable-customer', row)}>
                    Disable Customer
                </Item>
            )}

            {actions.includes('delete') && (
                <Item
                    onClick={() => onAction?.('delete', row)}
                    className="text-red-600"
                >
                    Delete
                </Item>
            )}
        </>
    );
}
