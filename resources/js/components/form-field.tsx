import {
    FieldRequirements,
    FieldRequirementsProps,
} from '@/components/field-requirements';
import { Label } from '@/components/ui/label';

interface FormFieldProps {
    label: string;
    fieldRequirementsProps?: FieldRequirementsProps;
    labelAction?: React.ReactNode;
    error?: string;
    children: React.ReactNode;
    htmlFor?: string;
    className?: string;
    layout?: 'stacked' | 'inline';
    ignoreFieldRequirementsTabFocus?: boolean;
}

export function FormField({
    label,
    fieldRequirementsProps,
    labelAction,
    error,
    children,
    htmlFor,
    className = '',
    layout = 'stacked',
    ignoreFieldRequirementsTabFocus = true,
}: FormFieldProps) {
    const isInline = layout === 'inline';

    return (
        <div
            className={`grid w-full items-center gap-3 ${
                isInline ? 'md:grid-cols-[minmax(0,1fr)_auto] md:gap-4' : ''
            } ${className}`}
        >
            <div className="flex items-center gap-3">
                <Label htmlFor={htmlFor}>
                    {label}
                    {fieldRequirementsProps && (
                        <FieldRequirements
                            {...fieldRequirementsProps}
                            ignoreTabFocus={ignoreFieldRequirementsTabFocus}
                        />
                    )}
                </Label>
                {labelAction}
            </div>
            <div className="relative min-w-0">
                {children}
                {error && <p className="mt-1 text-sm text-red-500 truncate">{error}</p>}
            </div>
        </div>
    );
}
