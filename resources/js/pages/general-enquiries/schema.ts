import { z } from 'zod';

export const generalEnquiryValidationSchema = z.object({
    id: z.number().optional(),
    full_name: z
        .string()
        .min(1, 'Full name is required')
        .min(2, 'Full name must be at least 2 characters'),
    mobile: z
        .string()
        .min(1, 'Mobile number is required')
        .regex(/^[+]?[\d\s\-()]+$/, 'Invalid mobile number format'),
    email: z
        .string()
        .min(1, 'Email is required')
        .email('Invalid email address'),
    preferred_destinations: z
        .string()
        .min(1, 'Preferred destinations are required')
        .min(3, 'Please provide at least 3 characters'),
    preferred_travelling_date: z
        .string()
        .min(1, 'Preferred travelling date is required'),
    no_of_adults: z
        .number()
        .min(0, 'Number of adults cannot be negative'),
    no_of_children: z
        .number()
        .min(0, 'Number of children cannot be negative'),
    requires_mobility_assistance: z
        .string()
        .nullable()
        .optional(),
});

export type GeneralEnquirySchema = z.infer<typeof generalEnquiryValidationSchema>;
