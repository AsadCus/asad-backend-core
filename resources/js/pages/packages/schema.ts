import { z } from 'zod';

export const accommodationSchema = z.object({
    id: z.number().optional(),
    location: z.string().optional(),
    hotel_name: z.string().optional(),
    ic: z.string().nullable().optional(),
    remarks: z.string().nullable().optional(),
    type_of_meal: z.string().nullable().optional(),
    check_in: z.string().nullable().optional(),
    check_out: z.string().nullable().optional(),
});

export const flightSchema = z.object({
    id: z.number().optional(),
    from: z.string().nullable().optional(),
    to: z.string().nullable().optional(),
    description: z.string().nullable().optional(),
    airline: z.string().nullable().optional(),
    pnr: z.string().nullable().optional(),
    departure_datetime: z.string().nullable().optional(),
    arrival_datetime: z.string().nullable().optional(),
    remarks: z.string().nullable().optional(),
});

export const trainTicketSchema = z.object({
    id: z.number().optional(),
    from: z.string().nullable().optional(),
    to: z.string().nullable().optional(),
    travel_date: z.string().nullable().optional(),
    travel_time: z.string().nullable().optional(),
    remarks: z.string().nullable().optional(),
});

export const transportationPlanSchema = z.object({
    id: z.number().optional(),
    from: z.string().nullable().optional(),
    to: z.string().nullable().optional(),
    travel_date: z.string().nullable().optional(),
    travel_time: z.string().nullable().optional(),
    remarks: z.string().nullable().optional(),
});

export const rawdahTasreehSchema = z.object({
    id: z.number().optional(),
    date: z.string().nullable().optional(),
    women_passengers: z.coerce.number().nullable().optional(),
    women_time: z.string().nullable().optional(),
    men_passengers: z.coerce.number().nullable().optional(),
    men_time: z.string().nullable().optional(),
    remarks: z.string().nullable().optional(),
});

export const officialSchema = z.object({
    id: z.number().optional(),
    type: z.string().nullable().optional(),
    name: z.string().nullable().optional(),
    hotel: z.string().nullable().optional(),
    hotel_map: z.record(z.string(), z.string()).optional(),
    contact_number: z.string().nullable().optional(),
    nationality: z.string().nullable().optional(),
    passport_number: z.string().nullable().optional(),
    gender: z.string().nullable().optional(),
    date_of_birth: z.string().nullable().optional(),
    passport_issue_date: z.string().nullable().optional(),
    passport_expiry_date: z.string().nullable().optional(),
    passport_place_of_issue: z.string().nullable().optional(),
    place_of_birth: z.string().nullable().optional(),
});

export const packageSchema = z.object({
    id: z.number().optional(),
    package_number: z.string().optional(),
    package_number_format_id: z.number().nullable().optional(),
    name: z.string().optional(),
    status: z.string().optional(),
    country_id: z.string().optional(),

    price_single: z.union([z.string(), z.number()]).nullable().optional(),
    price_double: z.union([z.string(), z.number()]).nullable().optional(),
    price_triple: z.union([z.string(), z.number()]).nullable().optional(),
    price_quad: z.union([z.string(), z.number()]).nullable().optional(),
    child_with_bed_price: z
        .union([z.string(), z.number()])
        .nullable()
        .optional(),
    child_no_bed_price: z.union([z.string(), z.number()]).nullable().optional(),
    infant_price: z.union([z.string(), z.number()]).nullable().optional(),

    // Package Information
    departure_date: z.string().nullable().optional(),
    return_date: z.string().nullable().optional(),
    total_seats: z.number().nullable().optional(),
    seats_left: z.number().nullable().optional(),
    occupied_seats: z.number().nullable().optional(),

    // Visa
    visa_type: z.string().nullable().optional(),

    // Vehicle
    vehicle_type: z.string().nullable().optional(),
    vehicle_driver_name: z.string().nullable().optional(),
    vehicle_driver_contact_number: z.string().nullable().optional(),

    // Train
    ticket_type: z.string().nullable().optional(),
    train_description: z.string().nullable().optional(),

    // Inclusions
    included: z.string().nullable().optional(),
    not_included: z.string().nullable().optional(),
    offer: z.string().nullable().optional(),

    // Remarks
    remarks: z.string().nullable().optional(),

    // Accommodations
    accommodations: z.array(accommodationSchema).optional(),

    // Flight Details
    flights: z.array(flightSchema).optional(),

    // Train Tickets
    train_tickets: z.array(trainTicketSchema).optional(),

    // Transportation Plans
    transportation_plans: z.array(transportationPlanSchema).optional(),

    // Rawdah Tasreeh
    rawdah_tasreehs: z.array(rawdahTasreehSchema).optional(),
    rawdah_member_counts: z
        .object({
            total: z.number().optional(),
            women: z.number().optional(),
            men: z.number().optional(),
        })
        .optional(),

    // Officials
    officials: z.array(officialSchema).optional(),
});

export type PackageSchema = z.infer<typeof packageSchema>;
export type AccommodationSchema = z.infer<typeof accommodationSchema>;
export type FlightSchema = z.infer<typeof flightSchema>;
export type TrainTicketSchema = z.infer<typeof trainTicketSchema>;
export type TransportationPlanSchema = z.infer<typeof transportationPlanSchema>;
export type RawdahTasreehSchema = z.infer<typeof rawdahTasreehSchema>;
export type OfficialSchema = z.infer<typeof officialSchema>;

export const packageStatusOptions = [
    { label: 'Open', value: 'open' },
    { label: 'Full', value: 'full' },
    { label: 'Closed', value: 'closed' },
    { label: 'Ongoing', value: 'ongoing' },
    { label: 'Completed', value: 'completed' },
];

export const packageStatusColors: Record<string, string> = {
    open: 'bg-green-100 text-green-800 dark:border dark:border-green-400/25 dark:bg-green-500/16 dark:text-green-200',
    full: 'bg-amber-100 text-amber-800 dark:border dark:border-amber-400/25 dark:bg-amber-500/16 dark:text-amber-200',
    closed: 'bg-red-100 text-red-800 dark:border dark:border-red-400/25 dark:bg-red-500/16 dark:text-red-200',
    ongoing:
        'bg-cyan-100 text-cyan-800 dark:border dark:border-cyan-400/25 dark:bg-cyan-500/16 dark:text-cyan-200',
    completed:
        'bg-blue-100 text-blue-800 dark:border dark:border-blue-400/25 dark:bg-blue-500/16 dark:text-blue-200',
};

export const packageStatusLabels: Record<string, string> = {
    open: 'Open',
    full: 'Full',
    closed: 'Closed',
    ongoing: 'Ongoing',
    completed: 'Completed',
};

export const packageTicketTypeLabels: Record<string, string> = {
    one_way: 'One Way',
    two_way: 'Two Way',
};

export const packageTrainTicketTypeOptions = [
    { label: 'One Way', value: 'one_way' },
    { label: 'Two Way', value: 'two_way' },
];

export const sharingPlanOptions = [
    { label: 'Single', value: 'single' },
    { label: 'Double', value: 'double' },
    { label: 'Triple', value: 'triple' },
    { label: 'Quad', value: 'quad' },
    { label: 'Child with Bed', value: 'child_with_bed' },
    { label: 'Child without Bed', value: 'child_no_bed' },
    { label: 'Infant', value: 'infant' },
];

export const sharingPlanLabels: Record<string, string> = {
    single: 'Single',
    double: 'Double',
    triple: 'Triple',
    quad: 'Quad',
    child_with_bed: 'Child with Bed',
    child_no_bed: 'Child without Bed',
    infant: 'Infant',
};

export const sharingPlanBadgeColors: Record<string, string> = {
    single: 'bg-blue-100 text-blue-800 dark:border dark:border-blue-400/25 dark:bg-blue-500/14 dark:text-blue-200',
    double: 'bg-purple-100 text-purple-800 dark:border dark:border-purple-400/25 dark:bg-purple-500/14 dark:text-purple-200',
    triple: 'bg-indigo-100 text-indigo-800 dark:border dark:border-indigo-400/25 dark:bg-indigo-500/14 dark:text-indigo-200',
    quad: 'bg-pink-100 text-pink-800 dark:border dark:border-pink-400/25 dark:bg-pink-500/14 dark:text-pink-200',
    child_with_bed:
        'bg-yellow-100 text-yellow-800 dark:border dark:border-yellow-400/25 dark:bg-yellow-500/14 dark:text-yellow-200',
    child_no_bed:
        'bg-green-100 text-green-800 dark:border dark:border-green-400/25 dark:bg-green-500/14 dark:text-green-200',
    infant: 'bg-orange-100 text-orange-800 dark:border dark:border-orange-400/25 dark:bg-orange-500/14 dark:text-orange-200',
};

export const sharingPlanPriceLabels = [
    {
        key: 'price_single',
        label: 'Single Sharing (Per Pax)',
    },
    {
        key: 'price_double',
        label: 'Double Sharing (Per Pax)',
    },
    {
        key: 'price_triple',
        label: 'Triple Sharing (Per Pax)',
    },
    { key: 'price_quad', label: 'Quad Sharing (Per Pax)' },
];

export const infantAndChildPriceLabels = [
    {
        key: 'child_with_bed_price',
        label: 'Child (7-11 years) w/ Bed',
    },
    {
        key: 'child_no_bed_price',
        label: 'Child (2-6 years) w/o Bed',
    },
    { key: 'infant_price', label: 'Infant (0-2 years) old ' },
];

export const sharingPlanCapacity: Record<string, number> = {
    single: 1,
    double: 2,
    triple: 3,
    quad: 4,
};

export const officialTypeOptions = [
    { label: 'Mutawif', value: 'mutawif' },
    { label: 'Mutawifah', value: 'mutawifah' },
    { label: 'Official', value: 'official' },
];

export const packageMealPlanOptions = [
    { value: 'Breakfast Only', label: 'Breakfast Only' },
    { value: 'Half Board', label: 'Half Board (Breakfast & Dinner)' },
    { value: 'Full Board', label: 'Full Board (3 Meals)' },
];
