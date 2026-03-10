import { FormField } from '@/components/form-field';
import { FormSection } from '@/components/form-section';
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
    const effectiveStatus = String(data.status ?? initialStatus ?? '');

    const allowedValues = useMemo(() => {
        if (mode === 'edit' && initialStatus === 'ready') {
            return ['revised'];
        } else if (initialStatus === 'converted') {
            return ['converted'];
        } else if (initialStatus === 'accepted') {
            return ['draft', 'ready', 'accepted'];
        } else if (initialStatus === 'rejected') {
            return ['draft', 'ready', 'rejected'];
        } else if (initialStatus === 'expired') {
            return ['draft', 'ready', 'expired'];
        } else if (initialStatus === 'cancelled') {
            return ['cancelled'];
        }
        return ['draft', 'ready'];
    }, [mode, initialStatus]);

    useEffect(() => {
        if (mode === 'edit' && initialStatus === 'ready') {
            if (data.status !== 'revised') {
                setData('status', 'revised');
            }
        }
    }, [mode, initialStatus, setData, data.status]);

    const visibleAllowedValues = useMemo(() => {
        const values = [...allowedValues];

        if (effectiveStatus && !values.includes(effectiveStatus)) {
            values.push(effectiveStatus);
        }

        return values;
    }, [allowedValues, effectiveStatus]);

    const selectOptions =
        statuses && statuses.length > 0
            ? statuses
                  .map((s) => ({ value: String(s.value), label: s.label }))
                  .filter((s) => visibleAllowedValues.includes(s.value))
            : visibleAllowedValues.map((v) => ({ value: v, label: v }));

    const selectDisabled =
        isView ||
        (mode === 'edit' && initialStatus === 'ready') ||
        effectiveStatus === 'converted';

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
                    <FormField label="Status">
                        <Select
                            disabled={selectDisabled}
                            value={effectiveStatus}
                            onValueChange={(value) => setData('status', value)}
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
                    </FormField>

                    {/* reason */}
                    {data.status === 'rejected' && (
                        <FormField label="Reason">
                            <Textarea value={data.reason ?? ''} disabled />
                            {renderError('reason')}
                        </FormField>
                    )}
                </div>
            </div>
        </FormSection>
    );
}
