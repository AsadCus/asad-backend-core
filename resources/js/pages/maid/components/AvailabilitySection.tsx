import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { MaidFormData, SetDataFn } from '../types';

type AvailabilitySectionProps = {
    data: MaidFormData;
    setData: SetDataFn;
    isView: boolean;
    errors: Partial<Record<keyof MaidFormData, string>>;
};

export function AvailabilitySection({ data, setData, isView }: AvailabilitySectionProps) {
    return (
        <section className="space-y-4">
            {/* <h2 className="text-xl border-b pb-2 font-semibold">Section D: Availability Of FDW To Be Interviewed By Prospective Employer</h2> */}
            
            <p className="text-sm text-muted-foreground">
                Please indicate how the FDW can be interviewed by prospective employers
            </p>

            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div className="flex items-center gap-3 rounded-lg border border-border p-3 hover:bg-accent transition-colors">
                    <Checkbox
                        id="interview_not_available"
                        checked={data.interview_not_available || false}
                        onCheckedChange={(checked) => setData('interview_not_available', checked as boolean)}
                        disabled={isView}
                    />
                    <Label htmlFor="interview_not_available" className="text-sm font-medium cursor-pointer">
                        FDW is not available for interview
                    </Label>
                </div>

                <div className="flex items-center gap-3 rounded-lg border border-border p-3 hover:bg-accent transition-colors">
                    <Checkbox
                        id="interview_by_phone"
                        checked={data.interview_by_phone || false}
                        onCheckedChange={(checked) => setData('interview_by_phone', checked as boolean)}
                        disabled={isView}
                    />
                    <Label htmlFor="interview_by_phone" className="text-sm font-medium cursor-pointer">
                        FDW can be interviewed by phone
                    </Label>
                </div>

                <div className="flex items-center gap-3 rounded-lg border border-border p-3 hover:bg-accent transition-colors">
                    <Checkbox
                        id="interview_by_video"
                        checked={data.interview_by_video || false}
                        onCheckedChange={(checked) => setData('interview_by_video', checked as boolean)}
                        disabled={isView}
                    />
                    <Label htmlFor="interview_by_video" className="text-sm font-medium cursor-pointer">
                        FDW can be interviewed by video-conference
                    </Label>
                </div>

                <div className="flex items-center gap-3 rounded-lg border border-border p-3 hover:bg-accent transition-colors">
                    <Checkbox
                        id="interview_in_person"
                        checked={data.interview_in_person || false}
                        onCheckedChange={(checked) => setData('interview_in_person', checked as boolean)}
                        disabled={isView}
                    />
                    <Label htmlFor="interview_in_person" className="text-sm font-medium cursor-pointer">
                        FDW can be interviewed in person
                    </Label>
                </div>
            </div>
        </section>
    );
}
