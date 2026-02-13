/**
 * Shared Enquiry types, constants, and interfaces used across all enquiry index pages.
 */

// Status colors used by all enquiry index pages
export const statusColors: Record<string, string> = {
    new_lead: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
    contacted:
        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    negotiating:
        'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    confirmed:
        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
};

// Status filter options
export const statusOptions = [
    { label: 'New Lead', value: 'new_lead' },
    { label: 'Contacted', value: 'contacted' },
    { label: 'Negotiating', value: 'negotiating' },
    { label: 'Confirmed', value: 'confirmed' },
];

// Type colors used by all-enquiries index
export const typeColors: Record<string, string> = {
    General: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    Private:
        'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
};

// Type filter options
export const typeOptions = [
    { label: 'General', value: 'General' },
    { label: 'Private', value: 'Private' },
];

// Status option interface (from backend)
export interface StatusOption {
    label: string;
    value: string;
}

// ------------------------------------------------------------------
// Enquiry Dashboard index (parent enquiry datatable schema)
// ------------------------------------------------------------------
export interface EnquirySchema {
    id: number;
    type: 'General' | 'Private';
    status: string;
    status_label: string;
    full_name: string;
    contact: string;
    email: string;
    child_id: number | null;
    created_at: string;
}

// ------------------------------------------------------------------
// General Enquiries index (child datatable schema)
// ------------------------------------------------------------------
export interface GeneralEnquiryDatatableSchema {
    id: number;
    enquiry_id: number | null;
    status: string;
    status_label: string;
    full_name: string;
    mobile: string;
    email: string;
    preferred_destinations: string;
    preferred_travelling_date: string;
    no_of_adults: number;
    no_of_children: number;
    requires_mobility_assistance: string | null;
    created_at: string;
    updated_at: string;
}

export interface GeneralEnquiriesProps {
    data: {
        enquiriesForDatatable: GeneralEnquiryDatatableSchema[];
    };
}

// ------------------------------------------------------------------
// Private Enquiries index (child datatable schema)
// ------------------------------------------------------------------
export interface PrivateEnquiryDatatableSchema {
    id: number;
    enquiry_id: number | null;
    status: string;
    status_label: string;
    full_name: string;
    contact_number: string;
    email: string;
    passport_expiry_date: string;
    departure_date: string;
    return_date: string;
    no_of_pax: number;
    no_of_children: number;
    airline: string;
    class: string;
    require_mutawif: boolean;
    require_umrah_course: boolean;
    require_umrah_official: boolean;
    makkah_or_madinah_first: string;
    no_of_nights_makkah: string;
    hotel_makkah: string;
    meals_makkah: string;
    no_of_nights_madinah: string;
    hotel_madinah: string;
    meals_madinah: string;
    land_transfer: string;
    add_on_speed_train: boolean;
    require_meet_greet: boolean;
    require_mutawiffah_ustazah_rawdah: boolean;
    madinah_tour_with_mutawif: boolean;
    makkah_tour_with_mutawif: boolean;
    has_chronic_disease: boolean;
    chronic_disease_details: string | null;
    need_wheelchair: string;
    other_remarks: string | null;
    created_at: string;
    updated_at: string;
}

export interface PrivateEnquiriesProps {
    data: {
        enquiriesForDatatable: PrivateEnquiryDatatableSchema[];
    };
}

// ------------------------------------------------------------------
// Customer Group schemas (used by customer index)
// ------------------------------------------------------------------
export interface CustomerGroupMemberSchema {
    id: number;
    customer_id: number;
    is_leader: boolean;
    name: string;
    email: string;
    contact: string;
    customer_number: string;
    nric_number: string;
}

export interface CustomerGroupSchema {
    id: number;
    enquiry_id: number | null;
    enquiry_type: string | null;
    enquiry_status: string | null;
    leader_name: string;
    leader_email: string;
    leader_contact: string;
    leader_customer_number: string;
    member_count: number;
    created_at: string;
    members: CustomerGroupMemberSchema[];
}
