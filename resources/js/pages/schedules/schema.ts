import { z } from 'zod';

export const scheduleSchema = z.object({
    id: z.number().optional(),
    quotation_id: z.number(),
    customer_name: z.string().nullable(),
    maid_name: z.string().nullable(),
    schedule_number: z.string(),
    sales_registration_number: z.string().nullable().optional(),
    monthly_salary: z.coerce.number().nullable(),
    loan_amount: z.coerce.number().nullable(),
    loan_duration_months: z.coerce.number().nullable(),
    monthly_loan_payment: z.coerce.number().nullable(),
    rest_day_of_the_week: z.string().nullable(),
    rest_days_per_month: z.coerce.number().nullable(),
    compensation_off_in_lieu: z.coerce.number().nullable(),
    terms_and_conditions: z.string().nullable(),
    notes: z.string().nullable(),
    is_active: z.boolean().default(true),
    breakdown: z
        .array(
            z.object({
                month: z.number(),
                day: z.number().nullable().optional(),
                month_name: z.string().optional(),
                salary: z.coerce.number().nullable(),
                loan_payment: z.coerce.number().nullable(),
                compensation_off: z.coerce.number().nullable().optional(),
                total_payment: z.coerce.number().nullable(),
                due_date: z.string().nullable().optional(),
            }),
        )
        .optional(),
    created_at: z.string().optional(),
    updated_at: z.string().optional(),
    quotation: z
        .object({
            id: z.number(),
            quotation_number: z.string(),
            commencement_date: z.string().nullable(),
            customer: z.object({
                id: z.number(),
                nric_number: z.string().nullable(),
                user: z.object({
                    id: z.number(),
                    name: z.string(),
                    email: z.string(),
                }),
            }),
            maid: z.object({
                id: z.number(),
                name: z.string(),
                passport_number: z.string().nullable(),
            }),
            order: z
                .object({
                    id: z.number(),
                    order_number: z.string(),
                    handover_date: z.string().nullable().optional(),
                })
                .nullable(),
            quotation_items: z.array(
                z.object({
                    id: z.number(),
                    description: z.string(),
                    quantity: z.coerce.number(),
                    rate: z.coerce.number(),
                }),
            ),
        })
        .optional(),
});

export type ScheduleSchema = z.infer<typeof scheduleSchema>;
