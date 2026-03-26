import { z } from 'zod';

export const generalEnquirySchema = z.object({
    id: z.number().optional(),
    enquiry_id: z.number().nullable().optional(),
    enquiry_number: z.string().nullable().optional(),
    number_format_id: z.number().nullable().optional(),
    status: z.string().optional(),
    status_label: z.string().optional(),
    package_id: z.number().nullable().optional(),
    name: z.string().optional(),
    contact_number: z.string().optional(),
    email: z.string().optional(),
    preferred_destinations: z.string().optional(),
    preferred_travelling_date: z.string().optional(),
    no_of_adults: z.number().optional(),
    no_of_children: z.number().optional(),
    requires_mobility_assistance: z.string().nullable().optional(),
    handled_by_name: z.string().optional(),
    created_at: z.string().optional(),
    updated_at: z.string().optional(),
});

export type GeneralEnquirySchema = z.infer<typeof generalEnquirySchema>;
