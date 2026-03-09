import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { useEffect, useState, useRef } from 'react';

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
    onFocus,
    debounceMs = 0,
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
    onFocus?: () => void;
    rows?: number;
    debounceMs?: number;
}) {
    const [local, setLocal] = useState(String(value ?? ''));
    const [isFocused, setIsFocused] = useState(false);
    const debounceTimer = useRef<NodeJS.Timeout | null>(null);

    useEffect(() => {
        if (!isFocused) {
            setLocal(String(value ?? ''));
        }
    }, [value, isFocused]);

    useEffect(() => {
        return () => {
            if (debounceTimer.current) {
                clearTimeout(debounceTimer.current);
            }
        };
    }, []);

    const handleChange = (newValue: string) => {
        setLocal(newValue);
        
        if (debounceMs > 0) {
            if (debounceTimer.current) {
                clearTimeout(debounceTimer.current);
            }
            debounceTimer.current = setTimeout(() => {
                onCommit(newValue);
            }, debounceMs);
        }
    };

    const handleBlur = () => {
        setIsFocused(false);
        if (debounceTimer.current) {
            clearTimeout(debounceTimer.current);
        }
        onCommit(local);
    };

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter') {
            setIsFocused(false);
            if (debounceTimer.current) {
                clearTimeout(debounceTimer.current);
            }
            onCommit(local);
        }
    };

    const handleFocus = () => {
        setIsFocused(true);
        onFocus?.();
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
                onBlur={handleBlur}
                onFocus={handleFocus}
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
            onBlur={handleBlur}
            onKeyDown={handleKeyDown}
            onFocus={handleFocus}
            {...inputProps}
        />
    );
}
