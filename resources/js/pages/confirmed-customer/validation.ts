import type { CustomerConfirmationDatatableSchema } from '../customer/schema';

type QuotationValidationResult = {
    isValid: boolean;
    errorMessage: string | null;
    payerToMembers: Record<number, number[]>;
};

export function validateQuotationGenerationPayload(
    group: CustomerConfirmationDatatableSchema | null,
    payerMapping: Record<number, number | null>,
): QuotationValidationResult {
    if (!group) {
        return {
            isValid: false,
            errorMessage: 'Customer confirmation data is not available.',
            payerToMembers: {},
        };
    }

    const activeMembers = group.members.filter(
        (member) => member.status !== 'cancelled' && !member.has_quotation,
    );

    if (activeMembers.length === 0) {
        return {
            isValid: false,
            errorMessage: 'All active members already have quotations.',
            payerToMembers: {},
        };
    }

    const missingPricingPlanMembers = activeMembers
        .filter((member) => String(member.sharing_plan ?? '').trim() === '')
        .map((member) => member.name)
        .filter((name) => String(name).trim().length > 0);

    if (missingPricingPlanMembers.length > 0) {
        return {
            isValid: false,
            errorMessage: `Cannot create quotation: pricing plan is missing for ${missingPricingPlanMembers.join(', ')}.`,
            payerToMembers: {},
        };
    }

    const payerToMembers: Record<number, number[]> = {};

    for (const member of activeMembers) {
        const payerId = payerMapping[member.id];

        if (payerId === null || payerId === undefined) {
            continue;
        }

        if (!payerToMembers[payerId]) {
            payerToMembers[payerId] = [];
        }

        payerToMembers[payerId].push(member.id);
    }

    if (Object.keys(payerToMembers).length === 0) {
        return {
            isValid: false,
            errorMessage: 'No payment assignments found.',
            payerToMembers: {},
        };
    }

    return {
        isValid: true,
        errorMessage: null,
        payerToMembers,
    };
}
