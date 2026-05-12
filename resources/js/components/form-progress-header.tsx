import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { cn } from '@/lib/utils';
import { AlertCircle, CheckCircle2 } from 'lucide-react';
import { useMemo } from 'react';

export interface SectionProgress {
    id: string;
    title: string;
    status: 'incomplete' | 'complete' | 'error';
}

interface FormProgressHeaderProps {
    title?: string;
    sections: SectionProgress[];
    currentSection?: string;
    isDraft?: boolean;
    onSectionClick?: (sectionId: string) => void;
}

export function FormProgressHeader({
    title,
    sections,
    currentSection,
    isDraft = false,
    onSectionClick,
}: FormProgressHeaderProps) {
    const progress = useMemo(() => {
        const completed = sections.filter(
            (s) => s.status === 'complete',
        ).length;
        return (completed / sections.length) * 100;
    }, [sections]);

    const errorCount = useMemo(() => {
        return sections.filter((s) => s.status === 'error').length;
    }, [sections]);

    const completedCount = useMemo(() => {
        return sections.filter((s) => s.status === 'complete').length;
    }, [sections]);

    return (
        <div className="sticky top-0 z-50 mb-2 rounded-md border border-primary bg-primary/5 px-4 backdrop-blur-3xl">
            <div className="space-y-4 py-4">
                {/* Header Info */}
                <div className="flex items-center justify-between">
                    <div className="space-y-1">
                        <h3 className="text-lg font-semibold">{title} Form</h3>
                        <div className="flex items-center gap-2 text-base text-muted-foreground">
                            <span>
                                {completedCount} of {sections.length} sections
                                completed
                            </span>
                            {errorCount > 0 && (
                                <>
                                    <span>•</span>
                                    <span className="flex items-center gap-1 font-medium text-red-600 dark:text-red-400">
                                        <AlertCircle className="h-4 w-4" />
                                        {errorCount}{' '}
                                        {errorCount === 1 ? 'error' : 'errors'}
                                    </span>
                                </>
                            )}
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        {isDraft && (
                            <Badge variant="secondary" className="gap-1">
                                <span className="h-2 w-2 animate-pulse rounded-full bg-yellow-500" />
                                Draft saved
                            </Badge>
                        )}
                        <Badge variant="outline">
                            {Math.round(progress)}% Complete
                        </Badge>
                    </div>
                </div>

                {/* Progress Bar */}
                <Progress value={progress} className="h-2" />

                {/* Section Navigation */}
                <div className="flex flex-wrap gap-2">
                    {sections.map((section) => (
                        <button
                            key={section.id}
                            type="button"
                            onClick={() => onSectionClick?.(section.id)}
                            className={cn(
                                'flex items-center gap-1.5 rounded-md px-3 py-1.5 text-base font-medium transition-all',
                                'border border-primary/40 bg-primary/5 hover:bg-primary/10 hover:shadow-sm',
                                currentSection === section.id &&
                                    'bg-primary text-primary-foreground ring-2 ring-primary/35',
                                section.status === 'error' &&
                                    'border-red-400 bg-red-50 text-red-700 hover:bg-red-100 dark:border-red-800 dark:bg-red-950/50 dark:text-red-300 dark:hover:bg-red-950/70',
                                section.status === 'complete' &&
                                    'border-green-400 bg-green-50 text-green-700 hover:bg-green-100 dark:border-green-800 dark:bg-green-950/50 dark:text-green-300 dark:hover:bg-green-950/70',
                            )}
                        >
                            {section.status === 'complete' && (
                                <CheckCircle2 className="h-3.5 w-3.5" />
                            )}
                            {section.status === 'error' && (
                                <AlertCircle className="h-3.5 w-3.5" />
                            )}
                            {section.title}
                        </button>
                    ))}
                </div>
            </div>
        </div>
    );
}
