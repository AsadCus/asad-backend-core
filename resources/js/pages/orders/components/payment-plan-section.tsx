import { FormField } from '@/components/form-field';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { OptionType } from '@/types';
import { OrderSchema } from '../schema';

interface Props {
    value: string;
    plans: OptionType[];
    disabled: boolean;
    onChange: (value: string) => void;
    renderError: (field: keyof OrderSchema) => React.ReactNode;
}

export function PaymentPlanSection({
    value,
    plans,
    disabled,
    onChange,
    renderError,
}: Props) {
    return (
        <FormField label="Payment Plan">
            <Select value={value} onValueChange={onChange} disabled={disabled}>
                <SelectTrigger>
                    <SelectValue placeholder="Select plan" />
                </SelectTrigger>
                <SelectContent>
                    {plans.map((p) => (
                        <SelectItem key={p.value} value={String(p.value)}>
                            {p.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            {renderError('payment_plan')}
        </FormField>
    );
}
