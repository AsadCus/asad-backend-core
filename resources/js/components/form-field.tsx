import {
    FieldRequirements,
    FieldRequirementsProps,
} from '@/components/field-requirements';
import { Label } from '@/components/ui/label';

interface FormFieldProps {
    label: string;
    fieldRequirementsProps?: FieldRequirementsProps;
    error?: string;
    children: React.ReactNode;
    htmlFor?: string;
    className?: string;
}

export function FormField({
    label,
    fieldRequirementsProps,
    error,
    children,
    htmlFor,
    className = '',
}: FormFieldProps) {
    return (
        <div className={`grid w-full min-w-0 items-center gap-3 ${className}`}>
            <Label htmlFor={htmlFor}>
                {label}
                {fieldRequirementsProps && (
                    <FieldRequirements {...fieldRequirementsProps} />
                )}
            </Label>
            <div className="relative w-full min-w-0">
                {children}
                {error && <p className="mt-1 text-sm text-red-500">{error}</p>}
            </div>
        </div>
    );
}
