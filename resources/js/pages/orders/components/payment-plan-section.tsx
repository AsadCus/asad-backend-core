import { Label } from '@/components/ui/label';
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
        <div className="grid gap-2">
            <Label>Payment Plan</Label>
            <div className="relative">
                <Select
                    value={value}
                    onValueChange={onChange}
                    disabled={disabled}
                >
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
            </div>
        </div>
    );
}
