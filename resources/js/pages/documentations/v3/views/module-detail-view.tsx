import { type DocumentationPageProps, type MenuGroup, type ModulePlaybook } from '@/types/documentation';
import { ChevronRight, Home, ArrowRight, BookOpen } from 'lucide-react';
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

export function ModuleDetailView({
    documentation,
    moduleGroup,
    onBack,
    onProcedureClick,
}: {
    documentation: DocumentationPageProps['documentation'];
    moduleGroup: MenuGroup;
    onBack: () => void;
    onProcedureClick: (index: number) => void;
}) {
    const Icon = getModuleIcon(moduleGroup.menu);
    const playbook = findPlaybook(documentation, moduleGroup);
    const moduleName = moduleGroup.menu.replace(/ Module$/i, '');
    const procedures = playbook?.procedures ?? [];
    const highlights = playbook?.highlights ?? moduleGroup.features;
    const overview = playbook?.overview ?? moduleGroup.purpose;

    return (
        <div className="mx-auto max-w-4xl px-6 py-8">
            {/* Breadcrumb */}
            <Breadcrumb
                items={[
                    { label: 'Home', onClick: onBack },
                    { label: moduleName },
                ]}
            />

            {/* Module Header */}
            <div className="mt-6 rounded-2xl border border-sidebar-border/70 bg-white p-6 shadow-sm dark:bg-slate-900/60">
                <div className="flex items-start gap-4">
                    <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-orange-600 dark:bg-orange-950/50 dark:text-orange-400">
                        <Icon className="h-7 w-7" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground">
                            {moduleName}
                        </h1>
                        <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                            {overview}
                        </p>
                    </div>
                </div>

                {/* Highlights */}
                {highlights.length > 0 && (
                    <div className="mt-5 flex flex-wrap gap-2">
                        {highlights.map((h) => (
                            <span
                                key={h}
                                className="rounded-full bg-orange-50 px-3 py-1 text-xs font-medium text-orange-700 dark:bg-orange-950/40 dark:text-orange-300"
                            >
                                {h}
                            </span>
                        ))}
                    </div>
                )}

                {/* Route link */}
                {moduleGroup.route_path && (
                    <div className="mt-4">
                        <a
                            href={moduleGroup.route_path}
                            className="inline-flex items-center gap-2 rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-orange-700"
                        >
                            Open {moduleName} <ArrowRight className="h-4 w-4" />
                        </a>
                    </div>
                )}
            </div>

            {/* Procedures List */}
            <div className="mt-8">
                <h2 className="text-lg font-semibold text-foreground">
                    {procedures.length > 0 ? 'Procedures & Guides' : 'How-To Steps'}
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    {procedures.length > 0
                        ? `${procedures.length} step-by-step procedures available for this module.`
                        : 'Quick operational steps for this module.'}
                </p>

                <div className="mt-4 space-y-3">
                    {procedures.length > 0 ? (
                        procedures.map((proc, i) => (
                            <button
                                key={proc.name}
                                type="button"
                                onClick={() => onProcedureClick(i)}
                                className="group flex w-full items-center justify-between rounded-xl border border-sidebar-border/70 bg-white p-5 text-left shadow-sm transition-all hover:-translate-y-0.5 hover:border-orange-200 hover:shadow-md dark:bg-slate-900/60 dark:hover:border-orange-700"
                            >
                                <div className="flex items-start gap-4">
                                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-orange-50 text-sm font-bold text-orange-600 dark:bg-orange-950/50 dark:text-orange-400">
                                        {String(i + 1).padStart(2, '0')}
                                    </span>
                                    <div>
                                        <h3 className="font-semibold text-foreground group-hover:text-orange-600 dark:group-hover:text-orange-400">
                                            {proc.name}
                                        </h3>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {proc.steps.length} steps
                                        </p>
                                    </div>
                                </div>
                                <ChevronRight className="h-5 w-5 text-muted-foreground transition-transform group-hover:translate-x-1 group-hover:text-orange-500" />
                            </button>
                        ))
                    ) : (
                        moduleGroup.how_to.map((item, i) => (
                            <div
                                key={item}
                                className="flex items-start gap-4 rounded-xl border border-sidebar-border/70 bg-white p-5 shadow-sm dark:bg-slate-900/60"
                            >
                                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-sm font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    {String(i + 1).padStart(2, '0')}
                                </span>
                                <p className="pt-1.5 text-sm leading-relaxed text-foreground">{item}</p>
                            </div>
                        ))
                    )}
                </div>
            </div>

            {/* Key Features */}
            {moduleGroup.features.length > 0 && procedures.length > 0 && (
                <div className="mt-8 rounded-2xl border border-sidebar-border/70 bg-white p-6 shadow-sm dark:bg-slate-900/60">
                    <div className="flex items-center gap-2 text-foreground">
                        <BookOpen className="h-5 w-5 text-orange-500" />
                        <h2 className="text-lg font-semibold">Key Features</h2>
                    </div>
                    <ul className="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                        {moduleGroup.features.map((f) => (
                            <li key={f} className="flex items-start gap-2 text-sm text-muted-foreground">
                                <ArrowRight className="mt-0.5 h-3.5 w-3.5 shrink-0 text-blue-500" />
                                <span>{f}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {/* Bottom utility */}
            <div className="mt-8 flex items-center justify-end pb-8">
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
