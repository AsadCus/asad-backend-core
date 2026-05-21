import { type DocumentationPageProps, type MenuGroup, type ModulePlaybook } from '@/types/documentation';
import { ChevronRight, Home, ExternalLink, ArrowLeft, ArrowRight } from 'lucide-react';
import { Link } from '@inertiajs/react';
import { getModuleIcon, slugify } from '../lib/doc-utils';

function findPlaybook(
    documentation: DocumentationPageProps['documentation'],
    group: MenuGroup,
): ModulePlaybook | undefined {
    const menuSlug = slugify(group.menu.replace(/ Modules?$/i, ''));
    return documentation.modulePlaybooks.find(
        (p) => {
            const playbookSlug = slugify(p.title.replace(/ Modules?$/i, ''));
            return playbookSlug === menuSlug || p.id === `${menuSlug}-module` || p.id === menuSlug;
        },
    );
}

function Breadcrumb({ items }: { items: { label: string; onClick?: () => void }[] }) {
    return (
        <nav className="flex items-center gap-1.5 text-sm text-muted-foreground">
            {items.map((item, i) => (
                <span key={item.label} className="flex items-center gap-1.5">
                    {i > 0 && <ChevronRight className="h-3.5 w-3.5" />}
                    {item.onClick ? (
                        <button
                            type="button"
                            onClick={item.onClick}
                            className="transition-colors hover:text-orange-600 dark:hover:text-orange-400"
                        >
                            {i === 0 ? <Home className="h-4 w-4" /> : item.label}
                        </button>
                    ) : (
                        <span className="font-medium text-foreground">{item.label}</span>
                    )}
                </span>
            ))}
        </nav>
    );
}

export function ProcedureDetailView({
    documentation,
    moduleGroup,
    procedureIndex,
    onBackToModule,
    onBackToHome,
    onProcedureChange,
}: {
    documentation: DocumentationPageProps['documentation'];
    moduleGroup: MenuGroup;
    procedureIndex: number;
    onBackToModule: () => void;
    onBackToHome: () => void;
    onProcedureChange: (group: MenuGroup, index: number) => void;
}) {
    const Icon = getModuleIcon(moduleGroup.menu);
    const playbook = findPlaybook(documentation, moduleGroup);
    const moduleName = moduleGroup.menu.replace(/ Module$/i, '');
    const procedure = playbook?.procedures?.[procedureIndex];
    // Cross-module navigation
    const menuGroups = documentation.menuGroups;
    const currentModuleIndex = menuGroups.findIndex(g => g.menu === moduleGroup.menu);

    let prevModuleGroup: MenuGroup | null = null;
    let prevProcedureIndex = 0;
    for (let i = currentModuleIndex - 1; i >= 0; i--) {
        const pb = findPlaybook(documentation, menuGroups[i]);
        if (pb && pb.procedures.length > 0) {
            prevModuleGroup = menuGroups[i];
            prevProcedureIndex = pb.procedures.length - 1;
            break;
        }
    }

    let nextModuleGroup: MenuGroup | null = null;
    for (let i = currentModuleIndex + 1; i < menuGroups.length; i++) {
        const pb = findPlaybook(documentation, menuGroups[i]);
        if (pb && pb.procedures.length > 0) {
            nextModuleGroup = menuGroups[i];
            break;
        }
    }

    const canGoPrevInModule = Boolean(playbook && procedureIndex > 0);
    const canGoNextInModule = Boolean(playbook && procedureIndex < playbook.procedures.length - 1);
    const hasPrevious = canGoPrevInModule || prevModuleGroup !== null;
    const hasNext = canGoNextInModule || nextModuleGroup !== null;

    if (!procedure) {
        return (
            <div className="mx-auto max-w-4xl px-6 py-8">
                <Breadcrumb items={[
                    { label: 'Home', onClick: onBackToHome },
                    { label: moduleName, onClick: onBackToModule },
                    { label: 'Not Found' },
                ]} />
                <div className="mt-8 rounded-2xl border border-dashed border-sidebar-border/70 p-12 text-center">
                    <p className="text-muted-foreground">Procedure not found.</p>
                    <button type="button" onClick={onBackToModule} className="mt-4 text-sm text-orange-600 hover:underline">
                        ← Back to {moduleName}
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div className="mx-auto max-w-4xl px-6 py-8">
            {/* Breadcrumb */}
            <Breadcrumb items={[
                { label: 'Home', onClick: onBackToHome },
                { label: moduleName, onClick: onBackToModule },
                { label: procedure.name },
            ]} />

            {/* Procedure Header */}
            <div className="mt-6 rounded-2xl border border-sidebar-border/70 bg-white p-6 shadow-sm dark:bg-slate-900/60">
                <div className="flex items-start gap-4">
                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-orange-600 dark:bg-orange-950/50 dark:text-orange-400">
                        <Icon className="h-6 w-6" />
                    </div>
                    <div className="flex-1">
                        <div className="flex flex-wrap items-start justify-between gap-2">
                            <h1 className="text-2xl font-bold tracking-tight text-foreground">
                                {procedure.name}
                            </h1>
                            <span className="rounded-full bg-orange-50 px-3 py-1 text-xs font-semibold text-orange-600 dark:bg-orange-950/40 dark:text-orange-400">
                                {procedure.steps.length} steps
                            </span>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {moduleName} — operational procedure
                        </p>
                    </div>
                </div>

                {playbook && (hasPrevious || hasNext) && (
                    <div className="mt-5 flex flex-wrap items-center gap-2">
                        <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                            Step guide {procedureIndex + 1} of {playbook.procedures.length}
                        </span>
                        <div className="ml-auto flex flex-wrap gap-2">
                            <button
                                type="button"
                                onClick={() => {
                                    if (!hasPrevious) return;
                                    if (canGoPrevInModule) {
                                        onProcedureChange(moduleGroup, procedureIndex - 1);
                                    } else if (prevModuleGroup) {
                                        onProcedureChange(prevModuleGroup, prevProcedureIndex);
                                    }
                                }}
                                disabled={!hasPrevious}
                                className="inline-flex items-center gap-2 rounded-lg border border-sidebar-border/70 px-3 py-2 text-xs font-medium text-muted-foreground shadow-sm transition-colors hover:bg-slate-50 hover:text-foreground disabled:cursor-not-allowed disabled:opacity-40 dark:hover:bg-slate-900"
                            >
                                <ArrowLeft className="h-3.5 w-3.5" />
                                Previous
                            </button>
                            <button
                                type="button"
                                onClick={() => {
                                    if (!hasNext) return;
                                    if (canGoNextInModule) {
                                        onProcedureChange(moduleGroup, procedureIndex + 1);
                                    } else if (nextModuleGroup) {
                                        onProcedureChange(nextModuleGroup, 0);
                                    }
                                }}
                                disabled={!hasNext}
                                className="inline-flex items-center gap-2 rounded-lg border border-sidebar-border/70 px-3 py-2 text-xs font-medium text-muted-foreground shadow-sm transition-colors hover:bg-slate-50 hover:text-foreground disabled:cursor-not-allowed disabled:opacity-40 dark:hover:bg-slate-900"
                            >
                                Next
                                <ArrowRight className="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>
                )}
            </div>

            {/* Screenshot Frame (Placeholder or Real) */}
            <div className="mt-8">
                {procedure.screenshot ? (
                    <div className="overflow-hidden rounded-2xl border border-sidebar-border/70 bg-white shadow-sm dark:bg-slate-900/60">
                        <img 
                            src={procedure.screenshot} 
                            alt={`Screenshot for ${procedure.name}`} 
                            className="h-auto w-full object-cover"
                        />
                    </div>
                ) : (
                    <div className="flex aspect-video w-full flex-col items-center justify-center rounded-2xl border-2 border-dashed border-sidebar-border/70 bg-slate-50/50 text-muted-foreground dark:bg-slate-900/20">
                        <div className="rounded-full bg-slate-100 p-3 dark:bg-slate-800">
                            <Icon className="h-6 w-6 opacity-50" />
                        </div>
                        <p className="mt-4 text-sm font-medium">Screenshot / Visual Guide</p>
                        <p className="mt-1 text-xs opacity-70">Image placeholder for this procedure</p>
                    </div>
                )}
            </div>

            {/* Steps */}
            <div className="mt-8">
                <h2 className="text-lg font-semibold text-foreground">Step-by-Step Instructions</h2>

                <div className="relative mt-5">
                    {/* Vertical line */}
                    <div className="absolute top-0 bottom-0 left-[1.125rem] w-px bg-orange-100 dark:bg-orange-900/50" />

                    <div className="space-y-4">
                        {procedure.steps.map((step, i) => {
                            const text = typeof step === 'string' ? step : step.text;
                            const path = typeof step === 'string' ? undefined : step.path;

                            return (
                                <div key={i} className="relative flex items-start gap-4 pl-0">
                                    {/* Step number */}
                                    <div className="relative z-10 flex h-9 w-9 shrink-0 items-center justify-center rounded-full border-2 border-orange-200 bg-white text-sm font-bold text-orange-600 dark:border-orange-800 dark:bg-slate-900 dark:text-orange-400">
                                        {i + 1}
                                    </div>

                                    {/* Step content */}
                                    <div className="flex-1 rounded-xl border border-sidebar-border/70 bg-white p-4 shadow-sm dark:bg-slate-900/60">
                                        <p className="text-sm leading-relaxed text-foreground">{text}</p>
                                        {path && (
                                            <Link
                                                href={path}
                                                className="mt-2 inline-flex items-center gap-1.5 text-xs font-medium text-orange-600 transition-colors hover:text-orange-800 dark:text-orange-400 dark:hover:text-orange-300"
                                            >
                                                <ExternalLink className="h-3 w-3" />
                                                Open in app
                                            </Link>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>

            {/* Bottom utility */}
            <div className="mt-8 flex items-center justify-between pb-8">
                <button
                    type="button"
                    onClick={onBackToModule}
                    className="inline-flex items-center gap-2 rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium text-muted-foreground shadow-sm transition-colors hover:bg-slate-50 hover:text-foreground dark:hover:bg-slate-900"
                >
                    ← Back to {moduleName}
                </button>
                <button
                    type="button"
                    onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })}
                    className="rounded-lg px-4 py-2 text-sm font-medium text-orange-600 transition-colors hover:bg-orange-50 dark:text-orange-400 dark:hover:bg-orange-950/30"
                >
                    Back to top
                </button>
            </div>
        </div>
    );
}
