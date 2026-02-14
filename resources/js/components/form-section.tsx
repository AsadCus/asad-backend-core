import {
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
import { cn } from '@/lib/utils';
import { AlertCircle, CheckCircle2, Circle } from 'lucide-react';
import { ReactNode } from 'react';

export type SectionStatus = 'incomplete' | 'complete' | 'error';

interface FormSectionProps {
    value: string;
    title: string;
    description?: string;
    children: ReactNode;
    status?: SectionStatus;
    required?: boolean;
}

export function FormSection({
    value,
    title,
    description,
    children,
    status = 'incomplete',
    required = true,
}: FormSectionProps) {
    const getStatusIcon = () => {
        switch (status) {
            case 'complete':
                return (
                    <CheckCircle2 className="h-5 w-5 text-green-600 dark:text-green-400" />
                );
            case 'error':
                return (
                    <AlertCircle className="h-5 w-5 text-red-600 dark:text-red-400" />
                );
            default:
                return (
                    <Circle className="h-5 w-5 text-gray-400 dark:text-gray-500" />
                );
        }
    };

    return (
        <AccordionItem
            id={`section-${value}`}
            value={value}
            className={cn(
                'scroll-mt-24 rounded-lg border transition-all hover:shadow-sm',
                '!border-b',
                status === 'error' &&
                    'border-red-400 bg-red-50/70 dark:border-red-800 dark:bg-red-950/30',
                status === 'complete' &&
                    'border-green-400 bg-green-50/70 dark:border-green-800 dark:bg-green-950/30',
            )}
        >
            <AccordionTrigger className="px-6 py-4 transition-colors hover:bg-accent/50 hover:no-underline">
                <div className="flex w-full items-center gap-3">
                    {getStatusIcon()}
                    <div className="flex flex-col items-start text-left">
                        <div className="flex items-center gap-2">
                            <span className="font-semibold text-foreground">
                                {title}
                            </span>
                            {required && (
                                <span className="text-sm font-bold text-red-600 dark:text-red-400">
                                    *
                                </span>
                            )}
                        </div>
                        {description && (
                            <span className="text-base text-muted-foreground">
                                {description}
                            </span>
                        )}
                    </div>
                </div>
            </AccordionTrigger>
            <AccordionContent className="px-6 pb-6">
                {children}
            </AccordionContent>
        </AccordionItem>
    );
}
