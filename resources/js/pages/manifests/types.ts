import { type ValueNumberOptionType } from '@/types';
import { type ManifestSchema, type TravelerSchema } from './schema';

export interface CustomerMemberData {
    id?: number;
    customer_id?: number;
    is_leader?: boolean;
    status?:
        | 'draft'
        | 'pending_payment'
        | 'partially_paid'
        | 'confirmed'
        | 'unavailable'
        | 'cancelled';
    sharing_plan?: string | null;
    role?: string | null;
    name?: string;
    email?: string;
    contact?: string;
    customer_number?: string;
    customer_confirmation_number?: string;
    nric_number?: string;
    passport_number?: string;
    passport_issue_date?: string;
    passport_expiry_date?: string;
    passport_place_of_issue?: string;
    date_of_birth?: string;
    age?: number;
}

export interface SharingGroupData {
    id: number;
    sharing_plan?: string;
    expected_capacity?: number;
    members?: Array<{
        customer_confirmation_member_id?: number;
    }>;
}

export interface CustomerConfirmationData {
    id: number;
    customer_confirmation_number?: string;
    package_id?: number;
    package_room_type?: string;
    enquiry_id?: number;
    enquiry_type?: string;
    enquiry_status?: string;
    leader_name?: string;
    leader_email?: string;
    leader_contact?: string;
    leader_customer_number?: string;
    member_count?: number;
    created_at?: string;
    members: CustomerMemberData[];
    sharing_groups?: SharingGroupData[];
}

export interface PackageAccommodationOption {
    id?: number;
    location?: string;
    hotel_name?: string;
    type_of_meal?: string;
    check_in?: string;
    check_out?: string;
}

export interface PackageFlightOption {
    id?: number;
    from?: string;
    to?: string;
    description?: string;
    airline?: string;
    pnr?: string;
    departure_datetime?: string;
    arrival_datetime?: string;
}

export interface PackageOfficialOption {
    id: number;
    name?: string;
    contact_number?: string;
}

export interface PackageForManifestOption extends ValueNumberOptionType {
    status?: 'open' | 'closed' | string;
    departure_date?: string;
    return_date?: string;
    accommodations?: PackageAccommodationOption[];
    flights?: PackageFlightOption[];
    officials?: PackageOfficialOption[];
}

export interface ManifestFormData
    extends Omit<
        ManifestSchema,
        'travelers' | 'rooms' | 'payments' | 'sharing_groups'
    > {
    travelers?: TravelerSchema[];
    roomLists?: Record<string, TravelerSchema[]>;
    airlineList?: TravelerSchema[];
    documents?: ManifestDocumentsByField;
    in_charge_official_id?: number | null;
}

export interface ManifestFormProps {
    mode: 'create' | 'edit' | 'view';
    initialData?: ManifestFormData;
    dataPackage?: ValueNumberOptionType[];
    onCancel: () => void;
}

export type TravelerWithUI = TravelerSchema & {
    row_key?: string;
    manifest_traveler_id?: number;
    customer_confirmation_id?: number;
    customer_confirmation_number?: string | null;
    customer_name?: string;
    manifest_sharing_group_id?: number;
    sharing_group_id?: number;
    sharing_group_key?: string;
    group_sort_order?: number;
    accommodation_key?: string;
    sort_order?: number;
    room_relationship?: string | null;
    room_label?: string;
    room_number?: string;
    room_remarks?: string;
    package_category?: string | null;
    date_of_sign_up?: string | null;
    is_first_time_umrah?: boolean | null;
    status?:
        | 'draft'
        | 'pending_payment'
        | 'partially_paid'
        | 'confirmed'
        | 'unavailable'
        | 'cancelled'
        | string;
    role?: string | null;
    relationship?: string | null;
    sharing_plan?: string | null;
    arabic_name?: string | null;
    receipt_documents?: ManifestDocumentItem[];
};

export type ManifestDocumentFieldKey =
    | 'flight_tickets'
    | 'visa'
    | 'hotel'
    | 'passport'
    | 'photo';

export interface ManifestDocumentItem {
    id?: number;
    file?: File | null;
    file_name?: string | null;
    file_path?: string | null;
    removed?: boolean;
}

export type ManifestDocumentsByField = Record<
    ManifestDocumentFieldKey,
    ManifestDocumentItem[]
>;
