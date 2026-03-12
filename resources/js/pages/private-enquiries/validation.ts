import { z } from 'zod';
import { privateEnquirySchema } from './schema';

export const privateEnquiryValidationSchema = privateEnquirySchema.superRefine(
    (data, ctx) => {
        // name
        if (!data.name || data.name.trim().length === 0) {
            ctx.addIssue({
                path: ['name'],
                message: 'Full name is required',
                code: z.ZodIssueCode.custom,
            });
        }

        // contact_number
        if (!data.contact_number || data.contact_number.trim().length === 0) {
            ctx.addIssue({
                path: ['contact_number'],
                message: 'Contact number is required',
                code: z.ZodIssueCode.custom,
            });
        }

        // email
        if (!data.email || data.email.trim().length === 0) {
            ctx.addIssue({
                path: ['email'],
                message: 'Email is required',
                code: z.ZodIssueCode.custom,
            });
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email)) {
            ctx.addIssue({
                path: ['email'],
                message: 'Invalid email address',
                code: z.ZodIssueCode.custom,
            });
        }

        // passport_expiry_date
        if (
            !data.passport_expiry_date ||
            data.passport_expiry_date.trim().length === 0
        ) {
            ctx.addIssue({
                path: ['passport_expiry_date'],
                message: 'Passport expiry date is required',
                code: z.ZodIssueCode.custom,
            });
        }

        // departure_date
        if (!data.departure_date || data.departure_date.trim().length === 0) {
            ctx.addIssue({
                path: ['departure_date'],
                message: 'Departure date is required',
                code: z.ZodIssueCode.custom,
            });
        }

        // return_date
        if (!data.return_date || data.return_date.trim().length === 0) {
            ctx.addIssue({
                path: ['return_date'],
                message: 'Return date is required',
                code: z.ZodIssueCode.custom,
            });
        }

        // no_of_pax
        if (
            data.no_of_pax === undefined ||
            data.no_of_pax === null ||
            data.no_of_pax < 1
        ) {
            ctx.addIssue({
                path: ['no_of_pax'],
                message: 'Number of pax is required',
                code: z.ZodIssueCode.custom,
            });
        }

        // no_of_children
        if (data.no_of_children !== undefined && data.no_of_children < 0) {
            ctx.addIssue({
                path: ['no_of_children'],
                message: 'Number of children cannot be negative',
                code: z.ZodIssueCode.custom,
            });
        }

        // airline
        if (!data.airline || data.airline.trim().length === 0) {
            ctx.addIssue({
                path: ['airline'],
                message: 'Airline is required',
                code: z.ZodIssueCode.custom,
            });
        }

        // class
        if (!data.class || data.class.trim().length === 0) {
            ctx.addIssue({
                path: ['class'],
                message: 'Class is required',
                code: z.ZodIssueCode.custom,
            });
        }

        // mekkah_or_madinah_first
        if (
            !data.mekkah_or_madinah_first ||
            data.mekkah_or_madinah_first.trim().length === 0
        ) {
            ctx.addIssue({
                path: ['mekkah_or_madinah_first'],
                message: 'This field is required',
                code: z.ZodIssueCode.custom,
            });
        }

        // no_of_nights_mekkah
        if (
            !data.no_of_nights_mekkah ||
            data.no_of_nights_mekkah.trim().length === 0
        ) {
            ctx.addIssue({
                path: ['no_of_nights_mekkah'],
                message: 'Number of nights in Mekkah is required',
                code: z.ZodIssueCode.custom,
            });
        }

        // hotel_mekkah
        if (!data.hotel_mekkah || data.hotel_mekkah.trim().length === 0) {
            ctx.addIssue({
                path: ['hotel_mekkah'],
                message: 'Hotel in Mekkah is required',
                code: z.ZodIssueCode.custom,
            });
        }

        // meals_mekkah
        if (!data.meals_mekkah || data.meals_mekkah.trim().length === 0) {
            ctx.addIssue({
                path: ['meals_mekkah'],
                message: 'Meals in Mekkah is required',
                code: z.ZodIssueCode.custom,
            });
        }

        // no_of_nights_madinah
        if (
            !data.no_of_nights_madinah ||
            data.no_of_nights_madinah.trim().length === 0
        ) {
            ctx.addIssue({
                path: ['no_of_nights_madinah'],
                message: 'Number of nights in Madinah is required',
                code: z.ZodIssueCode.custom,
            });
        }

        // hotel_madinah
        if (!data.hotel_madinah || data.hotel_madinah.trim().length === 0) {
            ctx.addIssue({
                path: ['hotel_madinah'],
                message: 'Hotel in Madinah is required',
                code: z.ZodIssueCode.custom,
            });
        }

        // meals_madinah
        if (!data.meals_madinah || data.meals_madinah.trim().length === 0) {
            ctx.addIssue({
                path: ['meals_madinah'],
                message: 'Meals in Madinah is required',
                code: z.ZodIssueCode.custom,
            });
        }

        // land_transfer
        if (!data.land_transfer || data.land_transfer.trim().length === 0) {
            ctx.addIssue({
                path: ['land_transfer'],
                message: 'Land transfer is required',
                code: z.ZodIssueCode.custom,
            });
        }

        // need_wheelchair
        if (!data.need_wheelchair || data.need_wheelchair.trim().length === 0) {
            ctx.addIssue({
                path: ['need_wheelchair'],
                message: 'This field is required',
                code: z.ZodIssueCode.custom,
            });
        }
    },
);
