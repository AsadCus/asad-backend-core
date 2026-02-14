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
import { create } from '@/routes/order';
import { OptionType } from '@/types';
import { router } from '@inertiajs/react';
import { useState } from 'react';

interface OrderCreateDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    quotationOptions: OptionType[];
}

export default function OrderCreateDialog({
    open,
    onOpenChange,
    quotationOptions,
}: OrderCreateDialogProps) {
    const [quotationId, setQuotationId] = useState<number | null>(null);

    const handleCreate = () => {
        if (!quotationId) return;

        router.get(create().url, {
            quotation_id: quotationId,
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Create Order</DialogTitle>
                    <DialogDescription>
                        Select quotation first to create order.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    <div className="grid gap-2">
                        <label className="text-base font-medium">
                            Quotation
                        </label>
                        <Combobox
                            value={quotationId ? String(quotationId) : ''}
                            onChange={(v) => {
                                setQuotationId(Number(v));
                            }}
                            options={quotationOptions}
                            placeholder="Select quotation"
                        />
                    </div>
                </div>

                <DialogFooter className="mt-4 flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        onClick={handleCreate}
                        disabled={!quotationId}
                    >
                        Continue
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
