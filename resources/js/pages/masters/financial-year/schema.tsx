import { z } from 'zod';

export const financialYearDatatableSchema = z.object({
    id: z.number().optional(),
    year: z.string().min(1, 'The year field is required.'),
    start_date: z.string().nullable().optional(),
    end_date: z.string().nullable().optional(),
    default: z.boolean().nullable().optional(),
});

export const financialYearFormSchema = z.object({
    id: z.number().optional(),
    year: z.string().optional(),
    start_date: z.string().min(1, 'Start date is required.'),
    end_date: z.string().min(1, 'End date is required.'),
    default: z.boolean().nullable().optional(),
});

export type FinancialYearDatatableSchema = z.infer<
    typeof financialYearDatatableSchema
>;
export type FinancialYearFormSchema = z.infer<typeof financialYearFormSchema>;
