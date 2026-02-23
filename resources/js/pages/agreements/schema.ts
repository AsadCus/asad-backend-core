import { z } from 'zod';
import { invoiceSchema } from '../invoices/schema';

export const agreementSchema = z.object({
    id: z.number().optional(),
    quotation_id: z.number(),
    agreement_number: z.string(),
    agreement_date: z.string(),
    sales_registration_number: z.string().nullable().optional(),
    expiry_date: z.string().nullable(),
    customer_name: z.string(),
    customer_nric: z.string().nullable(),
    job_description: z.string().nullable(),
    monthly_salary: z.coerce.number(),
    loan_amount: z.coerce.number().nullable(),
    loan_duration_months: z.coerce.number().nullable(),
    monthly_loan_payment: z.coerce.number().nullable(),
    late_payment_interest_amount: z.coerce.number().nullable(),
    payment_method: z.string().nullable(),
    rest_day_of_the_week: z.string().nullable(),
    rest_days_per_month: z.coerce.number().nullable(),
    terms_and_conditions: z.string().nullable(),
    special_conditions: z.string().nullable(),
    clauses: z.string().nullable(),
    status: z.enum([
        'draft',
        'sent',
        'signed',
        'active',
        'terminated',
        'expired',
    ]),
    notes: z.string().nullable(),
    created_at: z.string().optional(),
    updated_at: z.string().optional(),
    placement_fee_invoices: z.array(invoiceSchema).optional(),
    quotation: z
        .object({
            id: z.number(),
            quotation_number: z.string(),
            order: z
                .object({
                    id: z.number(),
                    order_number: z.string(),
                    handover_date: z.string().nullable().optional(),
                })
                .nullable(),
        })
        .optional(),
});

export type AgreementSchema = z.infer<typeof agreementSchema>;
