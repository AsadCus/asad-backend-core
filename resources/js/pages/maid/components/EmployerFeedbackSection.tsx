import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { X } from 'lucide-react';
import { toast } from 'sonner';
import { ProperInput } from '../../../components/proper-input';
import { MaidFormData, SetDataFn } from '../types';

type EmployerFeedbackSectionProps = {
    data: MaidFormData;
    setData: SetDataFn;
    isView: boolean;
};

type EmployerFeedbackRecord = {
    employer: string;
    feedback: string;
};

export function EmployerFeedbackSection({ data, setData, isView }: EmployerFeedbackSectionProps) {
    const feedbacks = (data.employer_feedback || []) as EmployerFeedbackRecord[];
    
    // Initialize with 2 empty records if needed
    const ensureMinimumRecords = () => {
        const records = [...feedbacks];
        while (records.length < 2) {
            records.push({ employer: '', feedback: '' });
        }
        return records;
    };

    const displayFeedbacks = ensureMinimumRecords();

    const updateRow = (index: number, field: string, value: string) => {
        const next = [...displayFeedbacks];
        next[index] = { ...next[index], [field]: value };
        setData('employer_feedback', next);
    };

    const addRow = () => {
        // Only block add if there is a record with one field filled and the other empty
        const hasIncompleteRecord = displayFeedbacks.some(emp => {
            const employerFilled = emp.employer && emp.employer.trim() !== '';
            const feedbackFilled = emp.feedback && emp.feedback.trim() !== '';
            // Block if only one is filled
            return (employerFilled !== feedbackFilled);
        });
        
        if (hasIncompleteRecord) {
            toast.warning('Please complete both employer and feedback before adding a new one');
            return;
        }
        
        setData('employer_feedback', [
            ...displayFeedbacks,
            { employer: '', feedback: '' },
        ]);
    };

    const removeRow = (index: number) => {
        // Don't allow removing if only 2 records remain
        if (displayFeedbacks.length <= 2) {
            toast.warning('At least 2 employer feedback records must be maintained');
            return;
        }

        const row = displayFeedbacks[index];
        const hasAnyValue = row.employer || row.feedback;
        
        if (hasAnyValue) {
            if (!confirm('Are you sure you want to remove this employer feedback record?')) {
                return;
            }
        }
        
        setData(
            'employer_feedback',
            displayFeedbacks.filter((_, rowIndex) => rowIndex !== index),
        );
    };

    const hasError = (index: number, field: string) => {
        const row = displayFeedbacks[index];
        if (!row) return false;
        const hasAnyValue = row.employer || row.feedback;
        if (!hasAnyValue) return false;
        if (field === 'employer' && (!row.employer || row.employer.trim() === '')) return true;
        if (field === 'feedback' && (!row.feedback || row.feedback.trim() === '')) return true;
        return false;
    };

    return (
        <section className="space-y-4">
            {/* C3 Subheader */}
            <div className="border-b pb-2">
                <h3 className="text-sm font-semibold">
                    C3. Feedback from previous employers in Singapore
                </h3>
            </div>

            <p className="text-sm text-muted-foreground">
                Feedback was/was not obtained by the EA from the previous employers. 
                If feedback was obtained (attach testimonial if possible), please indicate the feedback in the table below:
            </p>
            
            <div className="rounded-md border p-3">
                {displayFeedbacks.map((row, index) => (
                    <div
                        key={index}
                        className="grid grid-cols-1 gap-3 border-t py-3 first:border-t-0 md:grid-cols-12"
                    >
                        <div className="md:col-span-2 flex items-center">
                            <Label className="font-medium">
                                Employer {index + 1}
                            </Label>
                        </div>
                        <div className="md:col-span-9">
                            <Label className="mb-2 block">
                                Feedback
                                {hasError(index, 'feedback') && <span className="text-red-500">*</span>}
                            </Label>
                            <ProperInput
                                placeholder="Enter feedback from employer"
                                value={row.feedback || ''}
                                onCommit={(value) =>
                                    updateRow(index, 'feedback', value)
                                }
                                disabled={isView}
                                textarea={true}
                                className={hasError(index, 'feedback') ? 'border-red-500' : ''}
                            />
                            {hasError(index, 'feedback') && (
                                <p className="text-xs text-red-500 mt-1">Feedback is required when employer is provided</p>
                            )}
                        </div>
                        <div className="flex items-end justify-end md:col-span-1">
                            {!isView && index >= 2 && (
                                <Button
                                    type="button"
                                    variant="destructive"
                                    onClick={() => removeRow(index)}
                                >
                                    <X />
                                </Button>
                            )}
                        </div>
                    </div>
                ))}
                {!isView && (
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={addRow}
                        className="mt-2"
                    >
                        + Add Employer Feedback
                    </Button>
                )}
            </div>
        </section>
    );
}
