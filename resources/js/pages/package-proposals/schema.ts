import { z } from 'zod';

export const expenditureItemSchema = z.object({
    item_name: z.string().nullable().optional(),
    unit_price: z.coerce.number().nullable().optional(),
    quantity: z.coerce.number().nullable().optional(),
    remarks: z.string().nullable().optional(),
    sort_order: z.coerce.number().nullable().optional(),
});

export const expenditureExtensionSchema = z.object({
    name: z.string().nullable().optional(),
    calculation_mode: z.string().nullable().optional(),
    calculation_value: z.coerce.number().nullable().optional(),
    sort_order: z.coerce.number().nullable().optional(),
});

export const expenditureSectionSchema = z.object({
    title: z.string().nullable().optional(),
    sort_order: z.coerce.number().nullable().optional(),
    items: z.array(expenditureItemSchema).optional(),
    extensions: z.array(expenditureExtensionSchema).optional(),
});

export const proposalOfficialSchema = z.object({
    type: z.string().nullable().optional(),
    name: z.string().nullable().optional(),
});

export const passengerSimulationSchema = z.object({
    single: z.coerce.number().nullable().optional(),
    double: z.coerce.number().nullable().optional(),
    triple: z.coerce.number().nullable().optional(),
    quad: z.coerce.number().nullable().optional(),
    child_with_bed: z.coerce.number().nullable().optional(),
    child_no_bed: z.coerce.number().nullable().optional(),
    infant: z.coerce.number().nullable().optional(),
});

export const packageProposalSchema = z.object({
    id: z.number().optional(),
    proposal_number: z.string().optional(),
    name: z.string().optional(),
    status: z.string().optional(),
    status_label: z.string().optional(),
    country_id: z.union([z.string(), z.number()]).nullable().optional(),
    country_name: z.string().nullable().optional(),
    currency_symbol: z.string().nullable().optional(),

    departure_date: z.string().nullable().optional(),
    return_date: z.string().nullable().optional(),
    total_seats: z.union([z.string(), z.number()]).nullable().optional(),

    price_single: z.union([z.string(), z.number()]).nullable().optional(),
    price_double: z.union([z.string(), z.number()]).nullable().optional(),
    price_triple: z.union([z.string(), z.number()]).nullable().optional(),
    price_quad: z.union([z.string(), z.number()]).nullable().optional(),
    child_with_bed_price: z
        .union([z.string(), z.number()])
        .nullable()
        .optional(),
    child_no_bed_price: z
        .union([z.string(), z.number()])
        .nullable()
        .optional(),
    infant_price: z.union([z.string(), z.number()]).nullable().optional(),

    expenditure: z.array(expenditureSectionSchema).optional(),
    passenger_simulation: passengerSimulationSchema.optional(),
    officials: z.array(proposalOfficialSchema).optional(),

    approver_user_ids: z.array(z.number()).optional(),
    submitted_at: z.string().nullable().optional(),
    submitted_by_name: z.string().nullable().optional(),
    approved_rejected_at: z.string().nullable().optional(),
    approved_rejected_by_name: z.string().nullable().optional(),
    rejection_reason: z.string().nullable().optional(),

    created_by: z.number().nullable().optional(),
    created_by_name: z.string().nullable().optional(),
    package_id: z.number().nullable().optional(),
    package_number: z.string().nullable().optional(),
    remarks: z.string().nullable().optional(),
    created_at: z.string().nullable().optional(),
});

export type PackageProposalSchema = z.infer<typeof packageProposalSchema>;
export type ExpenditureItemSchema = z.infer<typeof expenditureItemSchema>;
export type ExpenditureExtensionSchema = z.infer<
    typeof expenditureExtensionSchema
>;
export type ExpenditureSectionSchema = z.infer<typeof expenditureSectionSchema>;
export type ProposalOfficialSchema = z.infer<typeof proposalOfficialSchema>;
export type PassengerSimulationSchema = z.infer<
    typeof passengerSimulationSchema
>;

export const proposalStatusOptions = [
    { label: 'Draft', value: 'draft' },
    { label: 'Pending Approval', value: 'pending_approval' },
    { label: 'Approved', value: 'approved' },
    { label: 'Rejected', value: 'rejected' },
];

export const proposalStatusColors: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-800 dark:border dark:border-gray-400/25 dark:bg-gray-500/16 dark:text-gray-200',
    pending_approval:
        'bg-amber-100 text-amber-800 dark:border dark:border-amber-400/25 dark:bg-amber-500/16 dark:text-amber-200',
    approved:
        'bg-green-100 text-green-800 dark:border dark:border-green-400/25 dark:bg-green-500/16 dark:text-green-200',
    rejected:
        'bg-red-100 text-red-800 dark:border dark:border-red-400/25 dark:bg-red-500/16 dark:text-red-200',
};

export const proposalStatusLabels: Record<string, string> = {
    draft: 'Draft',
    pending_approval: 'Pending Approval',
    approved: 'Approved',
    rejected: 'Rejected',
};

export const simulationPriceKeyMap: Record<
    string,
    keyof PackageProposalSchema
> = {
    single: 'price_single',
    double: 'price_double',
    triple: 'price_triple',
    quad: 'price_quad',
    child_with_bed: 'child_with_bed_price',
    child_no_bed: 'child_no_bed_price',
    infant: 'infant_price',
};

export const simulationLabels: Record<string, string> = {
    single: 'Single',
    double: 'Double',
    triple: 'Triple',
    quad: 'Quad',
    child_with_bed: 'Child with Bed',
    child_no_bed: 'Child without Bed',
    infant: 'Infant',
};

export const defaultExpenditure: ExpenditureSectionSchema[] = [
    {
        title: 'Flight Costs',
        sort_order: 1,
        items: [
            {
                item_name: 'Airline Tickets',
                unit_price: 0,
                quantity: 0,
                sort_order: 1,
            },
            {
                item_name: 'Airport Tax',
                unit_price: 0,
                quantity: 0,
                sort_order: 2,
            },
        ],
        extensions: [],
    },
    {
        title: 'Hotel Costs',
        sort_order: 2,
        items: [
            {
                item_name: 'Makkah Hotel',
                unit_price: 0,
                quantity: 0,
                sort_order: 1,
            },
            {
                item_name: 'Madinah Hotel',
                unit_price: 0,
                quantity: 0,
                sort_order: 2,
            },
        ],
        extensions: [],
    },
    {
        title: 'Ground Transport',
        sort_order: 3,
        items: [
            {
                item_name: 'Bus/Coach',
                unit_price: 0,
                quantity: 0,
                sort_order: 1,
            },
            {
                item_name: 'Airport Transfer',
                unit_price: 0,
                quantity: 0,
                sort_order: 2,
            },
        ],
        extensions: [],
    },
    {
        title: 'Miscellaneous',
        sort_order: 4,
        items: [
            {
                item_name: 'Visa Fee',
                unit_price: 0,
                quantity: 0,
                sort_order: 1,
            },
            {
                item_name: 'Travel Insurance',
                unit_price: 0,
                quantity: 0,
                sort_order: 2,
            },
            {
                item_name: 'Meals',
                unit_price: 0,
                quantity: 0,
                sort_order: 3,
            },
        ],
        extensions: [],
    },
];
