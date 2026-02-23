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
import { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
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

    const { auth } = usePage<SharedData>().props;
    const userId = auth?.user.id;

    const handledBy = isTableRow<TData>(row)
        ? (row.original as WithHandledBy)?.handled_by
        : (row as TData & WithHandledBy).handled_by;

    const hasMaidStatusActions =
        actions.includes('maid-status-schedule') ||
        actions.includes('maid-status-complete') ||
        actions.includes('maid-status-finalize') ||
        actions.includes('maid-status-cancel') ||
        actions.includes('maid-status-update');

    const hasQuotationStatusActions =
        actions.includes('quotation-status-accept') ||
        actions.includes('quotation-status-convert') ||
        actions.includes('quotation-status-reject') ||
        actions.includes('quotation-status-expire') ||
        actions.includes('quotation-status-cancel');

    const hasEnquiryStatusActions =
        actions.includes('enquiry-status-contacted') ||
        actions.includes('enquiry-status-negotiating') ||
        actions.includes('enquiry-status-confirmed');

    return (
        <>
            {actions.includes('preview') && (
                <Item onClick={() => onAction?.('preview', row)}>Preview</Item>
            )}

            {actions.includes('view') && (
                <Item onClick={() => onAction?.('view', row)}>View</Item>
            )}

            {actions.includes('handle-customer') && !handledBy && (
                <Item onClick={() => onAction?.('handle-customer', row)}>
                    Handle
                </Item>
            )}

            {actions.includes('create-quotation') && (
                <Item onClick={() => onAction?.('create-quotation', row)}>
                    Create Quotation
                </Item>
            )}

            {actions.includes('edit') && (
                <Item onClick={() => onAction?.('edit', row)}>Edit</Item>
            )}

            {actions.includes('copy-public-edit-link') && (
                <Item onClick={() => onAction?.('copy-public-edit-link', row)}>
                    Copy Public Edit Link
                </Item>
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

            {actions.includes('quotation-create') && (
                <Item onClick={() => onAction?.('quotation-create', row)}>
                    Create Quotation
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

            {hasQuotationStatusActions && (
                <>
                    <Separator />
                    <Sub>
                        <SubTrigger>Quotation Status</SubTrigger>
                        <SubContent>
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

                            {/* {actions.includes('quotation-status-expire') && (
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
                            )} */}

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

                            {actions.includes('enquiry-status-negotiating') && (
                                <Item
                                    onClick={() =>
                                        onAction?.(
                                            'enquiry-status-negotiating',
                                            row,
                                        )
                                    }
                                >
                                    Mark as Negotiating
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

            {actions.includes('add-remark') && (
                <Item onClick={() => onAction?.('add-remark', row)}>
                    Remark
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

            {hasMaidStatusActions && (
                <>
                    <Separator />

                    <Sub>
                        <SubTrigger>Status Actions</SubTrigger>
                        <SubContent>
                            {actions.includes('maid-status-schedule') && (
                                <Item
                                    onClick={() =>
                                        onAction?.('maid-status-schedule', row)
                                    }
                                >
                                    Schedule Interview
                                </Item>
                            )}

                            {actions.includes('maid-status-complete') && (
                                <Item
                                    onClick={() =>
                                        onAction?.('maid-status-complete', row)
                                    }
                                >
                                    Complete Interview
                                </Item>
                            )}

                            {actions.includes('maid-status-finalize') && (
                                <Item
                                    onClick={() =>
                                        onAction?.('maid-status-finalize', row)
                                    }
                                >
                                    Finalize Documents
                                </Item>
                            )}

                            {actions.includes('maid-status-cancel') && (
                                <Item
                                    onClick={() =>
                                        onAction?.('maid-status-cancel', row)
                                    }
                                >
                                    Cancel Interview
                                </Item>
                            )}

                            {actions.includes('maid-status-update') && (
                                <Item
                                    onClick={() =>
                                        onAction?.('maid-status-update', row)
                                    }
                                >
                                    Update Status
                                </Item>
                            )}
                        </SubContent>
                    </Sub>
                </>
            )}
        </>
    );
}
