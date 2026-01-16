import { z } from 'zod';

export const financialYearSchema = z.object({
    id: z.number().optional(),
    year: z.string().min(1, 'The year field is required.'),
    default: z.boolean().nullable().optional(),
});

export type FinancialYearSchema = z.infer<typeof financialYearSchema>;
