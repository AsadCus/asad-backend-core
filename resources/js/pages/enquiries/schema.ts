import { generalEnquirySchema } from '@/pages/general-enquiries/schema';
import { privateEnquirySchema } from '@/pages/private-enquiries/schema';
import { OptionType } from '@/types';
import { z } from 'zod';

export const statusColors: Record<string, string> = {
    new_lead: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
    contacted:
        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    negotiating:
        'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    confirmed:
        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
};

export const statusOptions: OptionType[] = [
    { label: 'New Lead', value: 'new_lead' },
    { label: 'Contacted', value: 'contacted' },
    { label: 'Negotiating', value: 'negotiating' },
    { label: 'Confirmed', value: 'confirmed' },
];

export const typeOptions = [
    { label: 'General', value: 'General' },
    { label: 'Private', value: 'Private' },
];

export const typeColors: Record<string, string> = {
    General: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    Private:
        'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
};

export const enquiryTypeLabels: Record<string, string> = {
    general: 'General',
    private: 'Private',
};

export const enquiryStatusLabels: Record<string, string> = {
    new_lead: 'New Lead',
    contacted: 'Contacted',
    negotiating: 'Negotiating',
    confirmed: 'Confirmed',
};

// ── Datatable schemas (All Enquiries index) ─────────────────────────────────

export interface EnquirySchema {
    id: number;
    type: string;
    status: string;
    status_label: string;
    name: string;
    contact: string;
    email: string;
    child_id: number | null;
    package_id: number | null;
    package_name: string | null;
    latest_remark: string | null;
    handled_by_name: string | null;
    created_at: string;
}

export interface EnquiryDetails {
    id: number;
    type: string;
    name: string;
    email: string;
    contact: string;
    status: string;
    package_name?: string | null;
    created_at?: string | null;
}

export const generalEnquiryDatatableSchema = generalEnquirySchema.required();
export type GeneralEnquiryDatatableSchema = z.infer<
    typeof generalEnquiryDatatableSchema
>;

export const privateEnquiryDatatableSchema = privateEnquirySchema.required();
export type PrivateEnquiryDatatableSchema = z.infer<
    typeof privateEnquiryDatatableSchema
>;
