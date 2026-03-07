import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { useEffect, useState } from 'react';

export function ProperInput({
    id,
    value,
    onCommit,
    placeholder,
    disabled,
    textarea = false,
    type = 'text',
    size = 'default',
    className,
    inputProps,
}: {
    id?: string;
    value: string | number | null;
    onCommit: (v: string) => void;
    placeholder?: string;
    disabled?: boolean;
    textarea?: boolean;
    type?: 'text' | 'number';
    size?: 'compact' | 'default';
    className?: string;
    inputProps?: React.InputHTMLAttributes<HTMLInputElement>;
    rows?: number;
}) {
    const [local, setLocal] = useState(String(value ?? ''));

    useEffect(() => {
        setLocal(String(value ?? ''));
    }, [value]);

    const handleChange = (newValue: string) => {
        setLocal(newValue);
        onCommit(newValue);
    };

    if (textarea) {
        return (
            <Textarea
                id={id}
                value={local}
                placeholder={placeholder}
                disabled={disabled}
                autoComplete="off"
                className={cn(
                    size === 'compact'
                        ? 'min-h-[36px] px-2 py-1 text-base sm:min-h-[48px]'
                        : '',
                    className,
                )}
                onChange={(e) => handleChange(e.target.value)}
            />
        );
    }

    return (
        <Input
            id={id}
            type={type}
            value={local}
            placeholder={placeholder}
            disabled={disabled}
            autoComplete="off"
            className={cn(
                size === 'compact' ? 'h-6 px-2 py-1 text-base sm:h-7' : '',
                className,
            )}
            inputMode={type === 'number' ? 'decimal' : undefined}
            onChange={(e) => handleChange(e.target.value)}
            {...inputProps}
        />
    );
}
