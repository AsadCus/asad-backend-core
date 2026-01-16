import { ReactNode } from 'react';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { Info } from 'lucide-react';

interface FieldRequirementsProps {
    required?: boolean;
    hint?: string;
    example?: string;
    format?: string;
    children?: ReactNode;
}

export function FieldRequirements({
    required = false,
    hint,
    example,
    format,
    children,
}: FieldRequirementsProps) {
    if (!hint && !example && !format && !children) {
        return required ? (
            <span className="text-red-500" title="Required field">*</span>
        ) : null;
    }

    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger type="button" className="inline-flex items-center gap-1 hover:opacity-80 transition-opacity">
                    {required && <span className="text-red-500">*</span>}
                    <Info className="h-3.5 w-3.5 text-muted-foreground hover:text-primary" />
                </TooltipTrigger>
                <TooltipContent 
                    side="right" 
                    className="max-w-xs bg-popover border-border shadow-lg"
                >
                    <div className="space-y-2 text-sm">
                        {required && (
                            <div className="font-semibold text-red-600 dark:text-red-400">
                                * Required field
                            </div>
                        )}
                        {children}
                        {hint && (
                            <p className="text-foreground/90">
                                {hint}
                            </p>
                        )}
                        {format && (
                            <div className="text-foreground">
                                <span className="font-medium">Format: </span>
                                <code className="rounded bg-muted px-1.5 py-0.5 text-xs font-mono border border-border">
                                    {format}
                                </code>
                            </div>
                        )}
                        {example && (
                            <div className="text-foreground">
                                <span className="font-medium">Example: </span>
                                <span className="text-foreground/80 italic">{example}</span>
                            </div>
                        )}
                    </div>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}
