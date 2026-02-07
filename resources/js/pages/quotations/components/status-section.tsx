import { FormSection } from '@/components/form-section';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { OptionType } from '@/types';
import React, { useEffect, useMemo } from 'react';
import { QuotationSchema, SetDataFn } from '../schema';

interface Props {
    data: QuotationSchema;
    mode: 'create' | 'edit' | 'view';
    initialStatus?: string | null;
    isView?: boolean;
    setData: SetDataFn;
    renderError: (field: keyof QuotationSchema) => React.ReactNode;
    statuses?: OptionType[];
    status: 'incomplete' | 'complete' | 'error';
}

export default function StatusSection({
    data,
    mode,
    initialStatus = null,
    isView = false,
    setData,
    renderError,
    statuses = [],
    status,
}: Props) {
    const allowedValues = useMemo(() => {
        if (mode === 'edit' && initialStatus === 'sent') {
            return ['revised'];
        } else if (initialStatus === 'accepted') {
            return ['draft', 'sent', 'accepted'];
        } else if (initialStatus === 'rejected') {
            return ['draft', 'sent', 'rejected'];
        } else if (initialStatus === 'expired') {
            return ['draft', 'sent', 'expired'];
        } else if (initialStatus === 'cancelled') {
            return ['cancelled'];
        }
        return ['draft', 'sent'];
    }, [mode, initialStatus]);

    useEffect(() => {
        if (mode === 'edit' && initialStatus === 'sent') {
            if (data.status !== 'revised') {
                setData('status', 'revised');
            }
        }
    }, [mode, initialStatus, setData, data.status]);

    const selectOptions =
        statuses && statuses.length > 0
            ? statuses
                  .map((s) => ({ value: String(s.value), label: s.label }))
                  .filter((s) => allowedValues.includes(s.value))
            : allowedValues.map((v) => ({ value: v, label: v }));

    const selectDisabled =
        isView || (mode === 'edit' && initialStatus === 'sent');

    return (
        <FormSection
            value="status"
            title="Status"
            description="Quotation status and optional rejection reason"
            status={status}
            required
        >
            <div id="section-status" className="space-y-6">
                <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-3">
                    {/* Status */}
                    <div className="grid w-full items-center gap-3">
                        <Label>Status</Label>
                        <div className="relative">
                            <Select
                                disabled={selectDisabled}
                                value={String(data.status ?? '')}
                                onValueChange={(value) =>
                                    setData('status', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select status" />
                                </SelectTrigger>
                                <SelectContent>
                                    {selectOptions.map((s) => (
                                        <SelectItem
                                            key={s.value}
                                            value={String(s.value)}
                                        >
                                            {s.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {renderError('status')}
                        </div>
                    </div>

                    {/* reason */}
                    {data.status === 'rejected' && (
                        <div className="grid w-full items-center gap-3">
                            <Label>Reason</Label>
                            <div className="relative">
                                <Textarea value={data.reason ?? ''} disabled />
                                {renderError('reason')}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </FormSection>
    );
}
