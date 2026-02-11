import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

interface FormFieldProps {
    label: string;
    required?: boolean;
    description?: string;
    error?: string;
    children: React.ReactNode;
    htmlFor?: string;
}

export function FormField({
    label,
    required = false,
    description,
    error,
    children,
    htmlFor,
}: FormFieldProps) {
    return (
        <div className="grid gap-2">
            <Label
                htmlFor={htmlFor}
                className={cn(
                    'text-sm font-medium',
                    required && 'after:content-["*"] after:ml-1 after:text-red-500'
                )}
            >
                {label}
            </Label>
            {description && (
                <p className="text-xs text-gray-500 -mt-1">{description}</p>
            )}
            {children}
            {error && (
                <p className="mt-1 text-sm font-medium text-red-600">{error}</p>
            )}
        </div>
    );
}
