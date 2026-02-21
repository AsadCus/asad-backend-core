import { z } from 'zod';

// ── Member schema (per-person fields stored in users + customers) ──

export const customerMemberSchema = z.object({
    member_id: z.number().optional(),
    customer_id: z.number().optional(),
    is_leader: z.boolean(),
    name: z.string(),
    email: z.string(),
    contact_number: z.string(),
    nric_number: z.string(),
    address: z.string(),
    nationality: z.string(),
    passport_number: z.string(),
    passport_issue_date: z.string(),
    passport_expiry_date: z.string(),
    passport_place_of_issue: z.string(),
    gender: z.string(),
    marital_status: z.string(),
    date_of_birth: z.string(),
    place_of_birth: z.string(),
    first_time_umrah: z.boolean().nullable().optional(),
    has_chronic_disease: z.boolean().nullable().optional(),
    chronic_disease_details: z.string().nullable().optional(),
});

export type CustomerMemberSchema = z.infer<typeof customerMemberSchema>;

// ── Customer Group form schema (group-level + members) ──

export const customerGroupFormSchema = z.object({
    id: z.number().optional(),
    enquiry_id: z.number().nullable().optional(),
    package_id: z.number().nullable().optional(),
    package_room_type: z.string().nullable().optional(),
    package_category: z.string().nullable().optional(),
    date_of_application: z.string(),
    members: z.array(customerMemberSchema),
    terms_accepted: z.boolean().optional(),
});

export type CustomerGroupFormSchema = z.infer<typeof customerGroupFormSchema>;

// ── Options ──

export const packageRoomTypeOptions = [
    { label: 'Single', value: 'single' },
    { label: 'Double Sharing', value: 'double' },
    { label: 'Triple Sharing', value: 'triple' },
    { label: 'Quad Sharing', value: 'quad' },
];

export const packageCategoryOptions = [
    { label: 'Classic Umrah', value: 'classic_umrah' },
    { label: 'Deluxe Umrah', value: 'deluxe_umrah' },
];

export const genderOptions = [
    { label: 'Male', value: 'male' },
    { label: 'Female', value: 'female' },
];

export const maritalStatusOptions = [
    { label: 'Single', value: 'single' },
    { label: 'Married', value: 'married' },
    { label: 'Divorced', value: 'divorced' },
    { label: 'Widowed', value: 'widowed' },
];

// ── Datatable schemas (Customer Groups index) ──

export interface CustomerGroupMemberDatatableSchema {
    id: number;
    customer_id: number;
    is_leader: boolean;
    name: string;
    email: string;
    contact: string;
    customer_number: string;
    nric_number: string;
}

export interface CustomerGroupDatatableSchema {
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
    members: CustomerGroupMemberDatatableSchema[];
}

// ── Default empty member ──

export const emptyMember = (isLeader = false): CustomerMemberSchema => ({
    is_leader: isLeader,
    name: '',
    email: '',
    contact_number: '',
    nric_number: '',
    address: '',
    nationality: '',
    passport_number: '',
    passport_issue_date: '',
    passport_expiry_date: '',
    passport_place_of_issue: '',
    gender: '',
    marital_status: '',
    date_of_birth: '',
    place_of_birth: '',
    first_time_umrah: null,
    has_chronic_disease: false,
    chronic_disease_details: '',
});
