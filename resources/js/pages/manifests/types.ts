import { type ValueNumberOptionType } from '@/types';
import { type ManifestSchema, type MemberSchema } from './schema';

export interface CustomerMemberData {
    id?: number;
    customer_id?: number;
    is_leader?: boolean;
    status?: 'pending_payment' | 'partially_paid' | 'fully_paid' | 'cancelled';
    sharing_plan?: string | null;
    relationship?: string | null;
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
    extends Omit<ManifestSchema, 'members' | 'rooms' | 'sharing_groups'> {
    manifest_members?: MemberSchema[];
    documents?: ManifestDocumentsByField;
    in_charge_official_id?: number | null;
    manifest_sharing_groups?: CanonicalManifestSharingGroup[];
    manifest_rooms?: CanonicalManifestRoom[];
    manifest_member_receipts?: ManifestMemberReceiptMap;
}

export type ManifestMemberReceiptMap = ManifestMemberReceiptRow[];

export interface ManifestMemberReceiptRow {
    manifest_member_id?: number | null;
    customer_confirmation_member_id?: number | null;
    receipt_documents?: ManifestDocumentItem[];
}

export interface CanonicalManifestSection {
    id?: number | null;
    package_id?: number | null;
    in_charge_official_id?: number | null;
    manifest_number?: string | null;
    number_format_id?: number | null;
    status?: string | null;
    notes?: string | null;
}

export interface CanonicalManifestSharingGroupMemberPatch {
    name_as_per_passport?: string | null;
    arabic_name?: string | null;
    contact_no?: string | null;
    passport_number?: string | null;
    nationality?: string | null;
    gender?: string | null;
    date_of_birth?: string | null;
    date_of_issue?: string | null;
    date_of_expiry?: string | null;
    issue_place?: string | null;
    birth_place?: string | null;
    address?: string | null;
    first_time_umrah?: boolean | null;
    has_chronic_disease?: boolean | null;
    is_using_wheelchair?: boolean | null;
    chronic_disease_details?: string | null;
    passport_path?: string | null;
    photo_path?: string | null;
    status?: string | null;
}

export interface CanonicalManifestSharingGroupMember {
    id?: number | null;
    customer_confirmation_member_id?: number | null;
    package_official_id?: number | null;
    relationship?: string | null;
    sharing_plan?: string | null;
    sort_order?: number | null;
    remarks?: string | null;
    status?: string | null;
    patch?: CanonicalManifestSharingGroupMemberPatch;
}

export interface CanonicalManifestSharingGroup {
    id?: number | null;
    customer_confirmation_id?: number | null;
    sort_order?: number | null;
    group_relationship?: string | null;
    remarks?: string | null;
    members?: CanonicalManifestSharingGroupMember[];
}

export interface CanonicalManifestRoomMember {
    id?: number | null;
    room_member_id?: number | null;
    manifest_member_id?: number | null;
    customer_confirmation_member_id?: number | null;
    package_official_id?: number | null;
    sort_order?: number | null;
    remarks?: string | null;
}

export interface CanonicalManifestRoom {
    id?: number | null;
    location?: string | null;
    sort_order?: number | null;
    group_relationship?: string | null;
    relationship?: string | null;
    room_label?: string | null;
    room_number?: string | null;
    room_type?: string | null;
    bed_type?: string | null;
    sharing_plan?: string | null;
    capacity?: number | null;
    meal?: string | null;
    number_of_beds_checked?: boolean;
    remarks?: string | null;
    members?: CanonicalManifestRoomMember[];
}

export interface ManifestFormProps {
    mode: 'edit' | 'view';
    initialData?: ManifestFormData;
    dataPackage?: ValueNumberOptionType[];
    onCancel: () => void;
}

export type MemberWithUI = MemberSchema & {
    row_key?: string;
    manifest_member_id?: number;
    manifest_room_id?: number;
    room_member_id?: number;
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
        | 'pending_payment'
        | 'partially_paid'
        | 'fully_paid'
        | 'cancelled'
        | string;
    relationship?: string | null;
    group_relationship?: string | null;
    sharing_plan?: string | null;
    arabic_name?: string | null;
    receipt_documents?: ManifestDocumentItem[];
};

export type ManifestDocumentFieldKey =
    | 'train_tickets'
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
