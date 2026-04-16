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
    start_day: z.string().min(1, 'Start day is required.'),
    start_month: z.string().min(1, 'Start month is required.'),
    end_day: z.string().min(1, 'End day is required.'),
    end_month: z.string().min(1, 'End month is required.'),
    start_date: z.string().nullable().optional(),
    end_date: z.string().nullable().optional(),
    default: z.boolean().nullable().optional(),
});

export type FinancialYearDatatableSchema = z.infer<
    typeof financialYearDatatableSchema
>;
export type FinancialYearFormSchema = z.infer<typeof financialYearFormSchema>;
