import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { Info } from 'lucide-react';
import { ReactNode } from 'react';

export interface FieldRequirementsProps {
    required?: boolean;
    hint?: string;
    example?: string;
    format?: string;
    children?: ReactNode;
    ignoreTabFocus?: boolean;
}

export function FieldRequirements({
    required = false,
    hint,
    example,
    format,
    children,
    ignoreTabFocus = true,
}: FieldRequirementsProps) {
    if (!hint && !example && !format && !children) {
        return required ? (
            <span className="text-red-500" title="Required field">
                *
            </span>
        ) : null;
    }

    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger
                    type="button"
                    tabIndex={ignoreTabFocus ? -1 : 0}
                    className="inline-flex items-center gap-1 transition-opacity hover:opacity-80"
                >
                    {required && <span className="text-red-500">*</span>}
                    <Info className="h-3.5 w-3.5 text-muted-foreground hover:text-primary" />
                </TooltipTrigger>
                <TooltipContent
                    side="right"
                    className="max-w-xs border-border bg-popover shadow-lg"
                >
                    <div className="space-y-2 text-base">
                        {required && (
                            <div className="font-semibold text-red-600 dark:text-red-400">
                                * Required field
                            </div>
                        )}
                        {children}
                        {hint && <p className="text-foreground/90">{hint}</p>}
                        {format && (
                            <div className="text-foreground">
                                <span className="font-medium">Format: </span>
                                <code className="rounded border border-border bg-muted px-1.5 py-0.5 font-mono text-sm">
                                    {format}
                                </code>
                            </div>
                        )}
                        {example && (
                            <div className="text-foreground">
                                <span className="font-medium">Example: </span>
                                <span className="text-foreground/80 italic">
                                    {example}
                                </span>
                            </div>
                        )}
                    </div>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}
