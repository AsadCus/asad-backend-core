export interface CustomerHistoryRecord {
    confirmation_id: number;
    confirmation_number: string;
    date_of_application: string | null;
    room_type: string | null;
    category: string | null;
    member_status: string | null;
    is_leader: boolean;
    relationship: string | null;
    sharing_plan: string | null;
    sharing_price: number | null;
    package_id: number | null;
    package_name: string | null;
    package_number: string | null;
    package_status: string | null;
    country_name: string | null;
    currency_symbol: string | null;
    departure_date: string | null;
    return_date: string | null;
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
