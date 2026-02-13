import { z } from 'zod';

export const generalEnquirySchema = z.object({
    id: z.number().optional(),
    enquiry_id: z.number().optional(),
    status: z.string().optional(),
    status_label: z.string().optional(),
    full_name: z.string().optional(),
    mobile: z.string().optional(),
    email: z.string().optional(),
    preferred_destinations: z.string().optional(),
    preferred_travelling_date: z.string().optional(),
    no_of_adults: z.number().optional(),
    no_of_children: z.number().optional(),
    requires_mobility_assistance: z.string().nullable().optional(),
});

export type GeneralEnquirySchema = z.infer<typeof generalEnquirySchema>;
