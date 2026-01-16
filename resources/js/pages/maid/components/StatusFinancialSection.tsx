import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { FieldRequirements } from '../../../components/field-requirements';
import { ProperInput } from '../../../components/proper-input';
import { status as statusOptions } from '../schema';
import { MaidFormData, SetDataFn } from '../types';
import { FieldError } from './FieldError';

type Supplier = {
    value: number;
    label: string;
};

type StatusFinancialSectionProps = {
    data: MaidFormData;
    setData: SetDataFn;
    isView: boolean;
    errors: Partial<Record<keyof MaidFormData, string>>;
    suppliers?: Supplier[];
    validateField?: (field: keyof MaidFormData) => boolean;
};

export function StatusFinancialSection({
    data,
    setData,
    isView,
    errors,
    suppliers = [],
}: StatusFinancialSectionProps) {
    // Check if the supplier_id exists in the suppliers list
    const supplierIdStr = data.supplier_id ? String(data.supplier_id) : '';
    const supplierExists = suppliers.some(
        (s) => String(s.value) === supplierIdStr,
    );

    const handleRemainingLoanChange = (value: string) => {
        setData('remaining_loan', value);
        if (value && data.monthly_salary) {
            const loan = parseFloat(value);
            const salary = parseFloat(String(data.monthly_salary));
            if (!isNaN(loan) && !isNaN(salary)) {
                setData('cost_of_maid', String(loan * salary));
            }
        } else {
            setData('cost_of_maid', '');
        }
    };

    const handleMonthlySalaryChange = (value: string) => {
        setData('monthly_salary', value);
        if (value && data.remaining_loan) {
            const salary = parseFloat(value);
            const loan = parseFloat(String(data.remaining_loan));
            if (!isNaN(salary) && !isNaN(loan)) {
                setData('cost_of_maid', String(loan * salary));
            }
        } else {
            setData('cost_of_maid', '');
        }
    };

    return (
        <section className="space-y-4">
            <p className="text-l mt-4 border-b py-2 font-semibold">
                Status &amp; Financial
            </p>
            <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-3">
                <div className="grid w-full items-center gap-3">
                    <Label className="flex items-center gap-1.5">
                        Status
                        <FieldRequirements
                            required
                            hint="Current employment status of the maid"
                            example="Available, Working, etc."
                        />
                    </Label>
                    <div className="relative">
                        <Select
                            disabled={isView}
                            value={String(data.status || '')}
                            onValueChange={(value) => setData('status', value)}
                            required
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select Status" />
                            </SelectTrigger>
                            <SelectContent>
                                {statusOptions.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <FieldError message={errors.status} />
                    </div>
                </div>

                <div className="grid w-full items-center gap-3">
                    <Label className="flex items-center gap-1.5">
                        Supplier
                        <FieldRequirements
                            required
                            hint="Select the supplier/agency providing this maid"
                            example="Agency name from the list"
                        />
                    </Label>
                    <div className="relative">
                        <Select
                            disabled={isView}
                            value={supplierExists && supplierIdStr ? supplierIdStr : ''}
                            onValueChange={(value) =>
                                setData('supplier_id', value)
                            }
                            required
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select Supplier" />
                            </SelectTrigger>
                            <SelectContent>
                                {suppliers.map((supplier) => (
                                    <SelectItem
                                        key={supplier.value}
                                        value={String(supplier.value)}
                                    >
                                        {supplier.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <FieldError message={errors.supplier_id} />
                    </div>
                </div>

                <div className="grid w-full items-center gap-3">
                    <Label
                        htmlFor="remaining_loan"
                        className="flex items-center gap-1.5"
                    >
                        Remaining Loan (Months)
                        <FieldRequirements
                            hint="Number of months remaining to pay off the maid's loan"
                            format="Decimal number"
                            example="6, 12.5, 18"
                        />
                    </Label>
                    <div className="relative">
                        <ProperInput
                            id="remaining_loan"
                            value={data.remaining_loan ?? ''}
                            onCommit={(value) =>
                                handleRemainingLoanChange(value)
                            }
                            placeholder="e.g., 12 or 12.5"
                            disabled={isView}
                            type="number"
                        />
                        <FieldError message={errors.remaining_loan} />
                    </div>
                </div>

                <div className="grid w-full items-center gap-3">
                    <Label
                        htmlFor="monthly_salary"
                        className="flex items-center gap-1.5"
                    >
                        Monthly Salary
                        <FieldRequirements
                            hint="Monthly salary amount for the maid"
                            format="Numeric amount"
                            example="600, 800"
                        />
                    </Label>
                    <div className="relative">
                        <ProperInput
                            id="monthly_salary"
                            value={data.monthly_salary ?? ''}
                            onCommit={(value) =>
                                handleMonthlySalaryChange(value)
                            }
                            placeholder="e.g., 600"
                            disabled={isView}
                            type="number"
                        />
                        <FieldError message={errors.monthly_salary} />
                    </div>
                </div>

                <div className="grid w-full items-center gap-3">
                    <Label
                        htmlFor="cost_of_maid"
                        className="flex items-center gap-1.5"
                    >
                        Cost of Maid
                        <FieldRequirements
                            hint="Calculated from Remaining Loan × Monthly Salary, but can be edited"
                            format="Numeric amount"
                        />
                    </Label>
                    <div className="relative">
                        <ProperInput
                            id="cost_of_maid"
                            value={data.cost_of_maid ?? ''}
                            onCommit={(value) => setData('cost_of_maid', value)}
                            placeholder="Cost of Maid"
                            disabled={isView}
                            type="number"
                        />
                        <FieldError message={errors.cost_of_maid} />
                    </div>
                </div>
            </div>
        </section>
    );
}
