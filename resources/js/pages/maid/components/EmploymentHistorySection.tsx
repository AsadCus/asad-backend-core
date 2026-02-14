import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { X } from 'lucide-react';
import { toast } from 'sonner';
import { ProperInput } from '../../../components/proper-input';
import { MaidFormData, SetDataFn } from '../types';

type EmploymentHistorySectionProps = {
    data: MaidFormData;
    setData: SetDataFn;
    isView: boolean;
};

export function EmploymentHistorySection({
    data,
    setData,
    isView,
}: EmploymentHistorySectionProps) {
    const history = data.employment_history || [];

    const calculateExperienceYears = (
        employmentHistory: Array<{ period?: string }>,
    ) => {
        let totalYears = 0;
        employmentHistory.forEach((emp) => {
            if (emp.period) {
                const match = emp.period.match(/(\d{4})\s*-\s*(\d{4})/);
                if (match) {
                    const startYear = parseInt(match[1]);
                    const endYear = parseInt(match[2]);
                    if (
                        !isNaN(startYear) &&
                        !isNaN(endYear) &&
                        endYear >= startYear
                    ) {
                        totalYears += endYear - startYear + 1;
                    }
                }
            }
        });
        return totalYears;
    };

    const updateRow = (index: number, field: string, value: string) => {
        const next = [...history];
        next[index] = { ...next[index], [field]: value };
        setData('employment_history', next);

        if (field === 'period') {
            const years = calculateExperienceYears(next);
            setData('experience_years', years);
        }
    };

    const hasError = (index: number, field: string) => {
        const row = history[index];
        if (!row) return false;
        const hasAnyValue =
            row.country ||
            row.employer ||
            row.period ||
            row.duties ||
            row.remarks;
        if (!hasAnyValue) return false;
        if (field === 'country' && (!row.country || row.country.trim() === ''))
            return true;
        if (
            field === 'employer' &&
            (!row.employer || row.employer.trim() === '')
        )
            return true;
        return false;
    };

    const addRow = () => {
        // Check if there are empty employment records
        const hasEmptyRecord = history.some((emp) => {
            const hasAnyValue =
                emp.country ||
                emp.employer ||
                emp.period ||
                emp.duties ||
                emp.remarks;
            if (!hasAnyValue) return true;
            if (hasAnyValue && (!emp.country || !emp.employer)) return true;
            return false;
        });

        if (hasEmptyRecord) {
            toast.warning(
                'Please complete the existing employment record before adding a new one',
            );
            return;
        }

        const next = [
            ...history,
            { country: '', employer: '', period: '', duties: '', remarks: '' },
        ];
        setData('employment_history', next);
    };

    const removeRow = (index: number) => {
        const row = history[index];
        const hasAnyValue =
            row.country ||
            row.employer ||
            row.period ||
            row.duties ||
            row.remarks;

        if (hasAnyValue) {
            if (
                !confirm(
                    'Are you sure you want to remove this employment record?',
                )
            ) {
                return;
            }
        }

        const next = history.filter((_, rowIndex) => rowIndex !== index);
        setData('employment_history', next);

        const years = calculateExperienceYears(next);
        setData('experience_years', years);
    };

    return (
        <section className="space-y-4">
            {/* C1 Subheader */}
            <div className="border-b pb-2">
                <h3 className="text-base font-semibold">
                    C1. Employment History Overseas
                </h3>
            </div>

            <div className="rounded-md border p-3">
                <p className="mb-2 text-base text-muted-foreground">
                    Date (From-To) | Country (Including FDW's home country) |
                    Employer | Work Duties | Remarks
                </p>
                {history.length === 0 && (
                    <p className="mb-2 text-base text-muted-foreground">
                        No records added.
                    </p>
                )}
                {history.map((row, index) => (
                    <div
                        key={index}
                        className="grid grid-cols-1 gap-3 border-t py-3 md:grid-cols-12"
                    >
                        <div className="md:col-span-2">
                            <Label>Period</Label>
                            <ProperInput
                                placeholder="e.g., 2014-2016"
                                value={row.period || ''}
                                onCommit={(value) =>
                                    updateRow(index, 'period', value)
                                }
                                disabled={isView}
                                type="text"
                            />
                        </div>
                        <div className="md:col-span-2">
                            <Label>
                                Country<span className="text-red-500">*</span>
                            </Label>
                            <ProperInput
                                value={row.country || ''}
                                onCommit={(value) =>
                                    updateRow(index, 'country', value)
                                }
                                disabled={isView}
                                type="text"
                                className={
                                    hasError(index, 'country')
                                        ? 'border-red-500'
                                        : ''
                                }
                            />
                            {hasError(index, 'country') && (
                                <p className="mt-1 text-sm text-red-500">
                                    Country is required
                                </p>
                            )}
                        </div>
                        <div className="md:col-span-2">
                            <Label>Employer</Label>
                            <ProperInput
                                value={row.employer || ''}
                                onCommit={(value) =>
                                    updateRow(index, 'employer', value)
                                }
                                disabled={isView}
                                type="text"
                            />
                        </div>
                        <div className="md:col-span-3">
                            <Label>Work Duties</Label>
                            <ProperInput
                                value={row.duties || ''}
                                onCommit={(value) =>
                                    updateRow(index, 'duties', value)
                                }
                                placeholder="Describe main duties"
                                disabled={isView}
                                textarea={true}
                            />
                        </div>
                        <div className="md:col-span-2">
                            <Label>Remarks</Label>
                            <ProperInput
                                value={row.remarks || ''}
                                onCommit={(value) =>
                                    updateRow(index, 'remarks', value)
                                }
                                disabled={isView}
                                type="text"
                            />
                        </div>
                        <div className="flex items-end justify-end md:col-span-1">
                            {!isView && (
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
                        + Add Employment
                    </Button>
                )}
            </div>

            {/* C2 Subheader */}
            <div className="mt-6 border-b pb-2">
                <h3 className="text-base font-semibold">
                    C2. Employment History in Singapore
                </h3>
            </div>

            <div className="space-y-3">
                <p className="text-base text-muted-foreground">
                    Previous working experience in Singapore (The EA is required
                    to obtain the FDW's employment history from MOM and furnish
                    the employer with the employment history of the FDW. The
                    employer may also verify the FDW's employment history in
                    Singapore through WPOL using SingPass)
                </p>
                <div className="grid w-full items-center gap-3">
                    <Label>Has Singapore Experience</Label>
                    <div className="flex items-center gap-4">
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="singapore_experience_yes"
                                checked={Boolean(data.singapore_experience)}
                                onCheckedChange={(checked) =>
                                    setData(
                                        'singapore_experience',
                                        checked as boolean,
                                    )
                                }
                                disabled={isView}
                            />
                            <Label
                                htmlFor="singapore_experience_yes"
                                className="cursor-pointer text-base font-normal"
                            >
                                Yes
                            </Label>
                        </div>
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="singapore_experience_no"
                                checked={!data.singapore_experience}
                                onCheckedChange={(checked) =>
                                    setData(
                                        'singapore_experience',
                                        !checked as boolean,
                                    )
                                }
                                disabled={isView}
                            />
                            <Label
                                htmlFor="singapore_experience_no"
                                className="cursor-pointer text-base font-normal"
                            >
                                No
                            </Label>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
