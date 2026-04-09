import { z } from 'zod';

export const opsAccommodationSchema = z.object({
    id: z.number(),
    location: z.string().nullable().optional(),
    hotel_name: z.string().nullable().optional(),
    ic: z.string().nullable().optional(),
    type_of_meal: z.string().nullable().optional(),
    check_in: z.string().nullable().optional(),
    check_out: z.string().nullable().optional(),
    nights: z.number().nullable().optional(),
    room_counts: z
        .object({
            single: z.number().optional(),
            double: z.number().optional(),
            triple: z.number().optional(),
            quad: z.number().optional(),
            child_with_bed: z.number().optional(),
            child_no_bed: z.number().optional(),
            infant: z.number().optional(),
        })
        .optional(),
    remarks: z.string().nullable().optional(),
});

export const opsOfficialSchema = z.object({
    id: z.number(),
    name: z.string().nullable().optional(),
    hotel: z.string().nullable().optional(),
    hotels_by_location: z
        .array(
            z.object({
                location: z.string(),
                hotel: z.string().nullable().optional(),
            }),
        )
        .optional(),
});

export const opsFlightSchema = z.object({
    id: z.number(),
    description: z.string().nullable().optional(),
    from: z.string().nullable().optional(),
    departure_datetime: z.string().nullable().optional(),
    airline: z.string().nullable().optional(),
    pnr: z.string().nullable().optional(),
    ic: z.string().nullable().optional(),
    to: z.string().nullable().optional(),
    arrival_datetime: z.string().nullable().optional(),
    remarks: z.string().nullable().optional(),
});

export const opsRawdahTasreehSchema = z.object({
    id: z.number(),
    date: z.string().nullable().optional(),
    women_passengers: z.number().nullable().optional(),
    women_time: z.string().nullable().optional(),
    men_passengers: z.number().nullable().optional(),
    men_time: z.string().nullable().optional(),
    remarks: z.string().nullable().optional(),
});

export const opsTransportationPlanSchema = z.object({
    id: z.number(),
    from: z.string().nullable().optional(),
    to: z.string().nullable().optional(),
    travel_date: z.string().nullable().optional(),
    travel_time: z.string().nullable().optional(),
    remarks: z.string().nullable().optional(),
});

export const opsTourLeaderSchema = z.object({
    type: z.string().nullable().optional(),
    name: z.string().nullable().optional(),
    contact_number: z.string().nullable().optional(),
});

export const opsPifSchema = z.object({
    tour_leaders: z.array(opsTourLeaderSchema).optional(),
});

export const opsTrainTicketSchema = z.object({
    id: z.number(),
    from: z.string().nullable().optional(),
    to: z.string().nullable().optional(),
    travel_date: z.string().nullable().optional(),
    travel_time: z.string().nullable().optional(),
});

export const opsDocumentItemSchema = z.object({
    id: z.number().optional(),
    file: z.any().optional(),
    file_name: z.string().nullable().optional(),
    file_path: z.string().nullable().optional(),
    removed: z.boolean().optional(),
});

export const opsBudgetItemSchema = z.object({
    item_name: z.string().nullable().optional(),
    unit_price: z.coerce.number().nullable().optional(),
    quantity: z.coerce.number().nullable().optional(),
    remarks: z.string().nullable().optional(),
    sort_order: z.coerce.number().nullable().optional(),
});

export const opsBudgetTitleSchema = z.object({
    title: z.string().nullable().optional(),
    sort_order: z.coerce.number().nullable().optional(),
    items: z.array(opsBudgetItemSchema).optional(),
});

export const opsPassengerSummarySchema = z.object({
    adult_total: z.number().optional(),
    adult_male: z.number().optional(),
    adult_female: z.number().optional(),
    child_total: z.number().optional(),
    child_boy: z.number().optional(),
    child_girl: z.number().optional(),
    child_with_bed_total: z.number().optional(),
    child_no_bed_total: z.number().optional(),
    infant_total: z.number().optional(),
    official_total: z.number().optional(),
    wheelchair_non_official_total: z.number().optional(),
    grand_total: z.number().optional(),
});

export const opsMovementSchema = z.object({
    id: z.number(),
    manifest_id: z.number().nullable().optional(),
    ops_movement_number: z.string().nullable().optional(),
    package_number: z.string().nullable().optional(),
    manifest_number: z.string().nullable().optional(),
    name: z.string().nullable().optional(),
    status: z.string().nullable().optional(),
    departure_date: z.string().nullable().optional(),
    return_date: z.string().nullable().optional(),
    departure_return_range: z.string().nullable().optional(),
    first_hotel_name: z.string().nullable().optional(),
    visa_type: z.string().nullable().optional(),
    ops_base: z.string().nullable().optional(),
    infotech_ref: z.string().nullable().optional(),
    location: z.string().nullable().optional(),
    doa_by: z.string().nullable().optional(),
    doa_datetime: z.string().nullable().optional(),
    vehicle_type: z.string().nullable().optional(),
    vehicle_driver_name: z.string().nullable().optional(),
    vehicle_driver_contact_number: z.string().nullable().optional(),
    train_description: z.string().nullable().optional(),
    visa_submitted_to_z_umrah: z.boolean().optional(),
    visa_approved: z.boolean().optional(),
    passengers: opsPassengerSummarySchema.optional(),
    accommodations: z.array(opsAccommodationSchema).optional(),
    officials: z.array(opsOfficialSchema).optional(),
    flights: z.array(opsFlightSchema).optional(),
    rawdah_tasreehs: z.array(opsRawdahTasreehSchema).optional(),
    transportation_plans: z.array(opsTransportationPlanSchema).optional(),
    train_tickets: z.array(opsTrainTicketSchema).optional(),
    documents: z
        .object({
            itinerary: z.array(opsDocumentItemSchema).optional(),
            booklet: z.array(opsDocumentItemSchema).optional(),
        })
        .optional(),
    budget: z.array(opsBudgetTitleSchema).optional(),
    budget_currency: z.string().nullable().optional(),
    pif: opsPifSchema.optional(),
});

export type OpsMovementSchema = z.infer<typeof opsMovementSchema>;
export type OpsAccommodationSchema = z.infer<typeof opsAccommodationSchema>;
export type OpsOfficialSchema = z.infer<typeof opsOfficialSchema>;
export type OpsFlightSchema = z.infer<typeof opsFlightSchema>;
export type OpsTrainTicketSchema = z.infer<typeof opsTrainTicketSchema>;
export type OpsRawdahTasreehSchema = z.infer<typeof opsRawdahTasreehSchema>;
export type OpsTransportationPlanSchema = z.infer<
    typeof opsTransportationPlanSchema
>;
export type OpsTourLeaderSchema = z.infer<typeof opsTourLeaderSchema>;
export type OpsPifSchema = z.infer<typeof opsPifSchema>;
export type OpsDocumentItemSchema = z.infer<typeof opsDocumentItemSchema>;
export type OpsBudgetItemSchema = z.infer<typeof opsBudgetItemSchema>;
export type OpsBudgetTitleSchema = z.infer<typeof opsBudgetTitleSchema>;
