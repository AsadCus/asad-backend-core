import { Label } from '@/components/ui/label';
import { FieldError } from './FieldError';
import { ProperInput } from '../../../components/proper-input';
import { MaidFormData, SetDataFn } from '../types';

type OtherRemarksSectionProps = {
    data: MaidFormData;
    setData: SetDataFn;
    isView: boolean;
    errors: Partial<Record<keyof MaidFormData, string>>;
};

export function OtherRemarksSection({ data, setData, isView, errors }: OtherRemarksSectionProps) {
    return (
        <section className="space-y-4">
            {/* <h2 className="text-xl border-b pb-2 font-semibold">Section E: Other Remarks</h2> */}
            <div className="grid w-full gap-3">
                <Label htmlFor="availability_remarks">Additional Remarks</Label>
                <ProperInput
                    id="availability_remarks"
                    value={data.availability_remarks ?? ''}
                    onCommit={(value) =>
                        setData('availability_remarks', value)
                    }
                    placeholder="Enter any additional remarks about availability, scheduling preferences, or special conditions"
                    disabled={isView}
                    textarea={true}
                />
                <p className="text-right text-xs text-muted-foreground">
                    {(data.availability_remarks ?? '').length}/200 characters
                </p>
                <FieldError message={errors.availability_remarks} />
            </div>
        </section>
    );
}
