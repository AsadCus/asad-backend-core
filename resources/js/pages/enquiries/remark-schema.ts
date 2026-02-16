import { z } from 'zod';

export const enquiryRemarkSchema = z.object({
    id: z.number().optional(),
    enquiry_id: z.number().optional(),
    created_by: z.number().optional(),
    creator_name: z.string().optional(),
    status_at_time: z.string().optional(),
    remark: z.string().optional(),
    created_at: z.string().optional(),
    updated_at: z.string().optional(),
});

export type EnquiryRemarkSchema = z.infer<typeof enquiryRemarkSchema>;

export const enquiryRemarkValidationSchema = z.object({
    remark: z
        .string()
        .min(1, 'Remark is required')
        .max(2000, 'Remark must be at most 2000 characters'),
});
