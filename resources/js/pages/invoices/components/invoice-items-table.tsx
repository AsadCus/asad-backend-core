import { InvoiceItemSchema, InvoiceSchema } from '@/pages/invoices/schema';
import QuotationItemTableForm from '@/pages/quotations/items/form';

interface InvoiceItemsTableProps {
    dataItems: InvoiceItemSchema[];
    onChange: (items: InvoiceItemSchema[]) => void;
    renderError: (path: string) => React.ReactNode;
    disabled?: boolean;
    invoices?: InvoiceSchema[];
    currentInvoiceIndex?: number;
    onMoveItem?: (
        fromInvoiceIndex: number,
        toInvoiceIndex: number,
        itemKeys: string[],
    ) => void;
}

export default function InvoiceItemsTable({
    dataItems,
    onChange,
    renderError,
    disabled = false,
    invoices,
    currentInvoiceIndex,
    onMoveItem,
}: InvoiceItemsTableProps) {
    return (
        <QuotationItemTableForm
            items={dataItems}
            onChange={onChange}
            renderError={renderError}
            disabled={disabled}
            invoices={invoices}
            currentInvoiceIndex={currentInvoiceIndex}
            onMoveItem={onMoveItem}
            showOptionalColumn={false}
            showPlacementFeeColumn={true}
        />
    );
}
