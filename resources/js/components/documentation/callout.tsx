import { AlertTriangle, Info, Sparkles } from 'lucide-react';
import type React from 'react';

type CalloutType = 'info' | 'warning' | 'tip';

interface CalloutProps {
    type: CalloutType;
    children: React.ReactNode;
}

const calloutStyles: Record<
    CalloutType,
    { bg: string; icon: React.ReactNode }
> = {
    info: {
        bg: 'bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-900/20 dark:border-blue-800',
        icon: <Info className="h-5 w-5" />,
    },
    warning: {
        bg: 'bg-amber-50 border-amber-200 text-amber-800 dark:bg-amber-900/20 dark:border-amber-800',
        icon: <AlertTriangle className="h-5 w-5" />,
    },
    tip: {
        bg: 'bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-900/20 dark:border-emerald-800',
        icon: <Sparkles className="h-5 w-5" />,
    },
};

export function Callout({ type, children }: CalloutProps) {
    const style = calloutStyles[type];

    return (
        <div className={`my-4 flex gap-3 rounded-lg border p-4 ${style.bg}`}>
            <div className="shrink-0">{style.icon}</div>
            <div className="text-sm leading-relaxed">{children}</div>
        </div>
    );
}
