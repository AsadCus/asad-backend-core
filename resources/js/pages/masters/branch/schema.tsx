import { z } from 'zod';

export const branchSchema = z.object({
    id: z.number().optional(),
    name: z.string().min(1, 'The name field is required.'),
    country_id: z.string().optional(),
    country_name: z.string().optional(),
});

export type BranchSchema = z.infer<typeof branchSchema>;
