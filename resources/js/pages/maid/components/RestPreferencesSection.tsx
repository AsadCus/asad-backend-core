import { Label } from '@/components/ui/label';
import { FieldRequirements } from '../../../components/field-requirements';
import { ProperInput } from '../../../components/proper-input';
import { MaidFormData, SetDataFn } from '../types';
import { FieldError } from './FieldError';

type RestPreferencesSectionProps = {
    data: MaidFormData;
    setData: SetDataFn;
    isView: boolean;
    errors: Partial<Record<keyof MaidFormData, string>>;
};

export function RestPreferencesSection({
    data,
    setData,
    isView,
    errors,
}: RestPreferencesSectionProps) {
    return (
        <section className="space-y-4">
            <h3 className="border-b pb-2 text-lg font-semibold">A3. Others</h3>
            <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-3">
                <div className="grid w-full items-center gap-3">
                    <Label
                        htmlFor="rest_days_per_month"
                        className="flex items-center gap-1"
                    >
                        21. Preferences for rest day(s) per month
                        <FieldRequirements
                            hint="Number of rest days preferred per month"
                            example="4"
                        />
                    </Label>
                    <div className="relative">
                        <ProperInput
                            id="rest_days_per_month"
                            value={data.rest_days_per_month ?? ''}
                            onCommit={(value) =>
                                setData('rest_days_per_month', value)
                            }
                            placeholder="Rest Days Per Month"
                            disabled={isView}
                            type="number"
                        />
                        <span className="absolute top-1/2 right-10 -translate-y-1/2 text-sm text-gray-500">
                            days
                        </span>
                        <FieldError message={errors.rest_days_per_month} />
                    </div>
                </div>

                <div className="grid w-full items-center gap-3">
                    <Label htmlFor="other_remarks">22. Any Other remarks</Label>
                    <div className="relative">
                        <ProperInput
                            id="other_remarks"
                            value={data.other_remarks ?? ''}
                            onCommit={(value) =>
                                setData('other_remarks', value)
                            }
                            placeholder="Enter any other remarks"
                            disabled={isView}
                            textarea={true}
                        />
                        <p className="text-right text-xs text-muted-foreground">
                            {(data.other_remarks ?? '').length}/200 characters
                        </p>
                        <FieldError message={errors.other_remarks} />
                    </div>
                </div>
            </div>
        </section>
    );
}
