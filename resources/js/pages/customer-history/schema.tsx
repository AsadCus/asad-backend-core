export interface HistoryEnquiry {
    id: number;
    enquiry_number: string | null;
    type: string | null;
    status: string | null;
    name: string | null;
    email: string | null;
    contact_number: string | null;
}

export interface HistoryTravel {
    manifest_id: number | null;
    manifest_number: string | null;
    member_name: string | null;
}

export interface HistoryReceipt {
    id: number;
    receipt_number: string | null;
    amount: number;
    receipt_date: string | null;
    payment_method: string | null;
}

export interface HistoryInvoice {
    id: number;
    invoice_number: string | null;
    amount: number;
    status: string | null;
    invoice_date: string | null;
    due_date: string | null;
    receipts: HistoryReceipt[];
}

export interface HistoryPayment {
    quotation: {
        id: number;
        quotation_number: string | null;
        status: string | null;
        payment_plan: string | null;
        quotation_date: string | null;
    };
    order: {
        id: number;
        order_number: string | null;
    } | null;
    invoices: HistoryInvoice[];
}

export interface CustomerHistoryRecord {
    type: 'package' | 'non_package';
    key: string;
    confirmation_id?: number;
    confirmation_number?: string | null;
    date_of_application?: string | null;
    room_type?: string | null;
    category?: string | null;
    member_status?: string | null;
    is_leader?: boolean;
    relationship?: string | null;
    sharing_plan?: string | null;
    sharing_price?: number | null;
    package_id?: number | null;
    package_name: string | null;
    package_number?: string | null;
    package_status?: string | null;
    country_name?: string | null;
    currency_symbol?: string | null;
    departure_date?: string | null;
    return_date?: string | null;
    enquiry: HistoryEnquiry | null;
    travel: HistoryTravel[];
    payments: HistoryPayment[];
    created_at: string;
}

export interface CustomerSearchResult {
    id: number;
    customer_id: number;
    customer_number: string;
    name: string;
    email: string;
    contact: string;
    nric_number: string | null;
    address: string | null;
}
