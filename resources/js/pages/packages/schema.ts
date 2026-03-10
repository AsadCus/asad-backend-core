import { z } from 'zod';

export const accommodationSchema = z.object({
    id: z.number().optional(),
    location: z.string().optional(),
    hotel_name: z.string().optional(),
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
});

export const officialSchema = z.object({
    id: z.number().optional(),
    type: z.string().nullable().optional(),
    name: z.string().nullable().optional(),
    contact_number: z.string().nullable().optional(),
});

export const packageSchema = z.object({
    id: z.number().optional(),
    package_number: z.string().optional(),
    name: z.string().optional(),
    status: z.string().optional(),

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

    // Train
    ticket_type: z.string().nullable().optional(),

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

    // Officials
    officials: z.array(officialSchema).optional(),
});

export type PackageSchema = z.infer<typeof packageSchema>;
export type AccommodationSchema = z.infer<typeof accommodationSchema>;
export type FlightSchema = z.infer<typeof flightSchema>;
export type OfficialSchema = z.infer<typeof officialSchema>;

export const packageStatusOptions = [
    { label: 'Open', value: 'open' },
    { label: 'Closed', value: 'closed' },
];

export const packageStatusColors: Record<string, string> = {
    open: 'bg-green-100 text-green-800',
    closed: 'bg-red-100 text-red-800',
};

export const packageStatusLabels: Record<string, string> = {
    open: 'Open',
    closed: 'Closed',
};

export const packageTicketTypeLabels: Record<string, string> = {
    speed_train: 'Speed Train',
};

export const sharingPlanOptions = [
    { label: 'Single', value: 'single' },
    { label: 'Double', value: 'double' },
    { label: 'Triple', value: 'triple' },
    { label: 'Quad', value: 'quad' },
];

export const sharingPlanLabels: Record<string, string> = {
    single: 'Single',
    double: 'Double',
    triple: 'Triple',
    quad: 'Quad',
};

export const sharingPlanBadgeColors: Record<string, string> = {
    single: 'bg-blue-100 text-blue-800',
    double: 'bg-purple-100 text-purple-800',
    triple: 'bg-indigo-100 text-indigo-800',
    quad: 'bg-pink-100 text-pink-800',
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
