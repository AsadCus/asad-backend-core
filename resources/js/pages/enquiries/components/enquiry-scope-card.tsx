import { FormField } from '@/components/form-field';
import { ProperInputSelect } from '@/components/proper-input-select';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { OptionType } from '@/types';

interface EnquiryScopeCardProps {
    scopeMode: 'country' | 'branch';
    branchOptions?: OptionType[];
    countryOptions?: OptionType[];
    branchId?: number | null;
    countryId?: number | null;
    isView: boolean;
    processing: boolean;
    onBranchChange: (branchId: number | null) => void;
    onCountryChange: (countryId: number | null) => void;
    renderError: (path: string) => React.ReactNode;
}

export default function EnquiryScopeCard({
    scopeMode,
    branchOptions = [],
    countryOptions = [],
    branchId,
    countryId,
    isView,
    processing,
    onBranchChange,
    onCountryChange,
    renderError,
}: EnquiryScopeCardProps) {
    const disabled = isView || processing;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-xl">Country of Enquiry</CardTitle>
                <CardDescription>
                    Assign this enquiry to a {scopeMode}.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {scopeMode === 'branch' ? (
                    <FormField
                        label="Branch"
                        fieldRequirementsProps={{ required: true }}
                    >
                        <ProperInputSelect
                            options={branchOptions}
                            value={branchId ? String(branchId) : ''}
                            onValueChange={(value) => {
                                if (Array.isArray(value)) {
                                    return;
                                }

                                const nextId = Number(value);
                                onBranchChange(
                                    Number.isFinite(nextId) && nextId > 0
                                        ? nextId
                                        : null,
                                );
                            }}
                            placeholder="Select branch"
                            disabled={disabled}
                        />
                        {renderError('branch_id')}
                    </FormField>
                ) : (
                    <FormField
                        label="Country"
                        fieldRequirementsProps={{ required: true }}
                    >
                        <ProperInputSelect
                            options={countryOptions}
                            value={countryId ? String(countryId) : ''}
                            onValueChange={(value) => {
                                if (Array.isArray(value)) {
                                    return;
                                }

                                const nextId = Number(value);
                                onCountryChange(
                                    Number.isFinite(nextId) && nextId > 0
                                        ? nextId
                                        : null,
                                );
                            }}
                            placeholder="Select country"
                            disabled={disabled}
                        />
                        {renderError('country_id')}
                    </FormField>
                )}
            </CardContent>
        </Card>
    );
}
