import { z } from 'zod';
import { opsMovementSchema } from './schema';

export const opsMovementValidationSchema = opsMovementSchema.superRefine(
    (data, ctx) => {
        if ((data.ops_base ?? '').length > 255) {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                path: ['ops_base'],
                message: 'Ops base cannot exceed 255 characters.',
            });
        }

        if ((data.infotech_ref ?? '').length > 255) {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                path: ['infotech_ref'],
                message: 'Infotech ref cannot exceed 255 characters.',
            });
        }

        if ((data.location ?? '').length > 255) {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                path: ['location'],
                message: 'Location cannot exceed 255 characters.',
            });
        }

        if ((data.doa_by ?? '').length > 255) {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                path: ['doa_by'],
                message: 'Doa by cannot exceed 255 characters.',
            });
        }

        (data.flights ?? []).forEach((flight, index) => {
            if ((flight.ic ?? '').length > 255) {
                ctx.addIssue({
                    code: z.ZodIssueCode.custom,
                    path: ['flights', index, 'ic'],
                    message: 'IC cannot exceed 255 characters.',
                });
            }
        });

        (data.officials ?? []).forEach((official, officialIndex) => {
            if ((official.hotel ?? '').length > 255) {
                ctx.addIssue({
                    code: z.ZodIssueCode.custom,
                    path: ['officials', officialIndex, 'hotel'],
                    message: 'Hotel cannot exceed 255 characters.',
                });
            }

            (official.hotels_by_location ?? []).forEach(
                (hotelRow, rowIndex) => {
                    if ((hotelRow.location ?? '').length > 255) {
                        ctx.addIssue({
                            code: z.ZodIssueCode.custom,
                            path: [
                                'officials',
                                officialIndex,
                                'hotels_by_location',
                                rowIndex,
                                'location',
                            ],
                            message: 'Location cannot exceed 255 characters.',
                        });
                    }

                    if ((hotelRow.hotel ?? '').length > 255) {
                        ctx.addIssue({
                            code: z.ZodIssueCode.custom,
                            path: [
                                'officials',
                                officialIndex,
                                'hotels_by_location',
                                rowIndex,
                                'hotel',
                            ],
                            message: 'Hotel cannot exceed 255 characters.',
                        });
                    }
                },
            );
        });

        (data.budget ?? []).forEach((section, sectionIndex) => {
            (section.items ?? []).forEach((item, itemIndex) => {
                const unitPrice = Number(item.unit_price ?? 0);
                const quantity = Number(item.quantity ?? 0);

                if (!Number.isFinite(unitPrice) || unitPrice < 0) {
                    ctx.addIssue({
                        code: z.ZodIssueCode.custom,
                        path: [
                            'budget',
                            sectionIndex,
                            'items',
                            itemIndex,
                            'unit_price',
                        ],
                        message: 'Unit price must be a non-negative number.',
                    });
                }

                if (!Number.isFinite(quantity) || quantity < 0) {
                    ctx.addIssue({
                        code: z.ZodIssueCode.custom,
                        path: [
                            'budget',
                            sectionIndex,
                            'items',
                            itemIndex,
                            'quantity',
                        ],
                        message: 'Quantity must be a non-negative number.',
                    });
                }
            });
        });
    },
);
