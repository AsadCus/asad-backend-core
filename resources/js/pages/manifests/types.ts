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

export interface PackageForManifestOption extends ValueNumberOptionType {
    departure_date?: string;
    return_date?: string;
    accommodations?: PackageAccommodationOption[];
}

export interface ManifestFormData
    extends Omit<
        ManifestSchema,
        'travelers' | 'rooms' | 'payments' | 'sharing_groups'
    > {
    travelers?: TravelerSchema[];
    roomLists?: Record<string, TravelerSchema[]>;
    airlineList?: TravelerSchema[];
    roomListMakkah?: Record<number, TravelerSchema[]>;
    roomListMadinah?: Record<number, TravelerSchema[]>;
    roomListOthers?: Record<number, TravelerSchema[]>;
}

export interface ManifestFormProps {
    mode: 'create' | 'edit' | 'view';
    initialData?: ManifestFormData;
    dataPackage?: ValueNumberOptionType[];
    onCancel: () => void;
}

export type TravelerWithUI = TravelerSchema & {
    row_key?: string;
    customer_confirmation_id?: number;
    customer_name?: string;
    sharing_group_id?: number;
    sharing_group_key?: string;
    accommodation_key?: string;
    sort_order?: number;
    status?:
        | 'draft'
        | 'pending_payment'
        | 'partially_paid'
        | 'confirmed'
        | 'unavailable'
        | 'cancelled'
        | string;
    role?: string;
    sharing_plan?: string;
};
