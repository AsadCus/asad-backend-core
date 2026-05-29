import { Combobox } from '@/components/combobox';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { router } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useMemo, useState } from 'react';

export interface SalespersonOption {
    value: number | string;
    label: string;
    country_id?: number | null;
    branch_id?: number | null;
    country_ids?: number[] | null;
    branch_ids?: number[] | null;
}

interface QuotationHandleDialogProps {
    quotationId?: number;
    quotationNumber?: string;
    quotationCountryId?: number | null;
    quotationBranchId?: number | null;
    scopeMode: 'country' | 'branch';
    salespersons: SalespersonOption[];
    isOpen: boolean;
    onClose: () => void;
}

function intArray(value: number[] | null | undefined): number[] {
    if (!Array.isArray(value)) return [];
    return value
        .map((id) => Number(id))
        .filter((id) => Number.isFinite(id) && id > 0);
}

function salespersonScopeMatches(
    option: SalespersonOption,
    quotationCountryId: number | null,
    quotationBranchId: number | null,
    scopeMode: 'country' | 'branch',
): boolean {
    if (scopeMode === 'branch') {
        if (!quotationBranchId) return false;
        const branchIds = new Set([
            ...intArray(option.branch_ids),
            ...(option.branch_id ? [Number(option.branch_id)] : []),
        ]);
        return branchIds.has(quotationBranchId);
    }

    if (!quotationCountryId) return false;
    const countryIds = new Set([
        ...intArray(option.country_ids),
        ...(option.country_id ? [Number(option.country_id)] : []),
    ]);
    return countryIds.has(quotationCountryId);
}

export function QuotationHandleDialog({
    quotationId,
    quotationNumber,
    quotationCountryId,
    quotationBranchId,
    scopeMode,
    salespersons,
    isOpen,
    onClose,
}: QuotationHandleDialogProps) {
    const [selectedSalespersonId, setSelectedSalespersonId] = useState<
        string | number
    >('');
    const [error, setError] = useState<string>('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    const filteredOptions = useMemo(
        () =>
            salespersons
                .filter((option) =>
                    salespersonScopeMatches(
                        option,
                        quotationCountryId ?? null,
                        quotationBranchId ?? null,
                        scopeMode,
                    ),
                )
                .map((option) => ({
                    label: option.label,
                    value: String(option.value),
                })),
        [salespersons, quotationCountryId, quotationBranchId, scopeMode],
    );

    const handleClose = () => {
        setSelectedSalespersonId('');
        setError('');
        setIsSubmitting(false);
        onClose();
    };

    const handleSubmit = () => {
        if (!quotationId) return;
        const numericId = Number(selectedSalespersonId);
        if (!numericId || Number.isNaN(numericId)) {
            setError('Please select a salesperson.');
            return;
        }

        setError('');
        setIsSubmitting(true);

        router.post(
            `/quotation/${quotationId}/handle`,
            { salesperson_id: numericId },
            {
                onSuccess: () => handleClose(),
                onError: (errors) => {
                    setError(
                        (errors.salesperson_id as string) ||
                            'Failed to assign salesperson.',
                    );
                },
                onFinish: () => setIsSubmitting(false),
            },
        );
    };

    return (
        <Dialog
            open={isOpen}
            onOpenChange={(open) => {
                if (!open) handleClose();
            }}
        >
            <DialogContent className="sm:max-w-[450px]">
                <DialogHeader>
                    <DialogTitle>Handle Quotation</DialogTitle>
                    <DialogDescription>
                        Assign a salesperson to quotation
                        {quotationNumber ? ` #${quotationNumber}` : ''}. Only
                        sales/admin users in the same{' '}
                        {scopeMode === 'branch' ? 'branch' : 'country'} as the
                        quotation are listed.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-3 py-2">
                    <Label htmlFor="handle-salesperson">Salesperson *</Label>
                    <Combobox
                        value={
                            selectedSalespersonId === ''
                                ? ''
                                : String(selectedSalespersonId)
                        }
                        onChange={(value) => {
                            setSelectedSalespersonId(value);
                            if (error) setError('');
                        }}
                        options={filteredOptions}
                        placeholder={
                            filteredOptions.length === 0
                                ? 'No matching salesperson in scope'
                                : 'Select salesperson'
                        }
                    />
                    {error && <p className="text-sm text-red-500">{error}</p>}
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={handleClose}
                        disabled={isSubmitting}
                    >
                        Cancel
                    </Button>
                    <Button
                        onClick={handleSubmit}
                        disabled={isSubmitting || filteredOptions.length === 0}
                    >
                        {isSubmitting && (
                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                        )}
                        Assign
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
