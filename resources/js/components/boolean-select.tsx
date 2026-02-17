import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface BooleanSelectProps {
    id: string;
    value: boolean;
    onChange: (value: boolean) => void;
    disabled?: boolean;
}

export function BooleanSelect({
    id,
    value,
    onChange,
    disabled = false,
}: BooleanSelectProps) {
    return (
        <Select
            value={value ? 'yes' : 'no'}
            onValueChange={(v) => onChange(v === 'yes')}
            disabled={disabled}
        >
            <SelectTrigger id={id}>
                <SelectValue placeholder="Select option" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="no">No</SelectItem>
                <SelectItem value="yes">Yes</SelectItem>
            </SelectContent>
        </Select>
    );
}
