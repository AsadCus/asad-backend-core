import { z } from 'zod';

export const countrySchema = z.object({
    id: z.number().optional(),
    name: z.string().min(1, 'The name field is required.'),
    adjective: z.string().nullable().optional(),
    currency_symbol: z.string().nullable().optional(),
});

export type CountrySchema = z.infer<typeof countrySchema>;
