import type { OptionType } from '@/types';
import { z } from 'zod';
import type { PackageSchema } from '../packages/schema';

// ── Member schema (per-person fields stored in users + customers) ──
export const customerSchema = z.object({
    customer_number: z.string().optional(),
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
    status: z.string().optional(),
    sharing_plan: z.string().nullable().optional(),
    role: z.string().nullable().optional(),
    // Image uploads (File on submit, string path from server)
    passport_file: z.any().optional(),
    photo_file: z.any().optional(),
    passport_path: z.string().nullable().optional(),
    photo_path: z.string().nullable().optional(),
});

export type CustomerSchema = z.infer<typeof customerSchema>;

// ── Customer Confirmation form schema (group-level + members) ──
export const customerConfirmationFormSchema = z.object({
    id: z.number().optional(),
    enquiry_id: z.number().nullable().optional(),
    package_id: z.number().nullable().optional(),
    package_room_type: z.string().nullable().optional(),
    package_category: z.string().nullable().optional(),
    date_of_application: z.string(),
    members: z.array(customerSchema),
    terms_accepted: z.boolean().optional(),
});

export type CustomerConfirmationFormSchema = z.infer<
    typeof customerConfirmationFormSchema
>;

export type CustomerMemberFormData = Omit<
    CustomerSchema,
    'passport_file' | 'photo_file' | 'passport_path' | 'photo_path'
> & {
    passport_file?: File | null;
    photo_file?: File | null;
    passport_path?: string | null;
    photo_path?: string | null;
};

export type CustomerConfirmationFormData = Omit<
    CustomerConfirmationFormSchema,
    'members'
> & {
    members: CustomerMemberFormData[];
    package_data?: PackageSchema;
};

export interface CustomerOption extends OptionType {
    name: string;
    email: string;
    contact_number: string;
    nric_number: string;
    address: string;
    nationality?: string;
    passport_number?: string;
    passport_issue_date?: string;
    passport_expiry_date?: string;
    passport_place_of_issue?: string;
    gender?: string;
    marital_status?: string;
    date_of_birth?: string;
    place_of_birth?: string;
    first_time_umrah?: boolean;
    has_chronic_disease?: boolean;
    chronic_disease_details?: string;
    passport_path?: string;
    photo_path?: string;
}

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

export const sharingPlanOptions = [
    { label: 'Single', value: 'single' },
    { label: 'Double', value: 'double' },
    { label: 'Triple', value: 'triple' },
    { label: 'Quad', value: 'quad' },
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

// ── Datatable schemas (Customer Confirmations index) ──
export interface CustomerConfirmationMemberDatatableSchema {
    id: number;
    group_id: number;
    customer_id: number;
    is_leader: boolean;
    status?: string;
    sharing_plan?: string | null;
    role?: string | null;
    has_quotation?: boolean;
    paid_amount: number;
    total_amount: number;
    name: string;
    email: string;
    contact: string;
    customer_number: string;
}

export interface CustomerConfirmationDatatableSchema {
    id: number;
    enquiry_id: number | null;
    enquiry_type: string | null;
    enquiry_status: string | null;
    enquiry_email: string;
    enquiry_contact: string;
    member_count: number;
    paid_amount: number;
    total_amount: number;
    can_create_quotation: boolean;
    created_at: string;
    members: CustomerConfirmationMemberDatatableSchema[];
}

export const confirmationMemberStatusColors: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-800',
    pending_payment: 'bg-amber-100 text-amber-800',
    partially_paid: 'bg-blue-100 text-blue-800',
    confirmed: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800',
};

export const confirmationMemberStatusLabels: Record<string, string> = {
    draft: 'Draft',
    pending_payment: 'Pending Payment',
    partially_paid: 'Partially Paid',
    confirmed: 'Confirmed',
    cancelled: 'Cancelled',
};

// ── Default empty member ──

export const emptyMember = (isLeader = false): CustomerSchema => ({
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
    status: 'draft',
    sharing_plan: null,
    role: null,
    passport_file: undefined,
    photo_file: undefined,
    passport_path: null,
    photo_path: null,
});
