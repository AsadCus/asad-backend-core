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

        (data.flights ?? []).forEach((flight, index) => {
            if ((flight.doa_by ?? '').length > 255) {
                ctx.addIssue({
                    code: z.ZodIssueCode.custom,
                    path: ['flights', index, 'doa_by'],
                    message: 'DOA by cannot exceed 255 characters.',
                });
            }

            if ((flight.ic ?? '').length > 255) {
                ctx.addIssue({
                    code: z.ZodIssueCode.custom,
                    path: ['flights', index, 'ic'],
                    message: 'IC cannot exceed 255 characters.',
                });
            }
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

        (data.pif?.tour_leaders ?? []).forEach((tourLeader, index) => {
            if ((tourLeader.type ?? '').length > 255) {
                ctx.addIssue({
                    code: z.ZodIssueCode.custom,
                    path: ['pif', 'tour_leaders', index, 'type'],
                    message: 'Tour leader type cannot exceed 255 characters.',
                });
            }

            if ((tourLeader.name ?? '').length > 255) {
                ctx.addIssue({
                    code: z.ZodIssueCode.custom,
                    path: ['pif', 'tour_leaders', index, 'name'],
                    message: 'Tour leader name cannot exceed 255 characters.',
                });
            }

            if ((tourLeader.contact_number ?? '').length > 255) {
                ctx.addIssue({
                    code: z.ZodIssueCode.custom,
                    path: ['pif', 'tour_leaders', index, 'contact_number'],
                    message: 'Tour leader contact number cannot exceed 255 characters.',
                });
            }
        });
    },
);
