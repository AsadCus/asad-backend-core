import { AppHeader } from '@/components/app-header';
import { AppShell } from '@/components/app-shell';
import { Toaster } from '@/components/ui/sonner';
import { type BreadcrumbItem, SharedData } from '@/types';
import { type DocumentationPageProps, type ModulePlaybook, type MenuGroup } from '@/types/documentation';
import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { toast } from 'sonner';
import { Search, ChevronDown, ChevronRight, BookOpen } from 'lucide-react';
import { HomeView } from './views/home-view';
import { ModuleDetailView } from './views/module-detail-view';
import { ProcedureDetailView } from './views/procedure-detail-view';
import { useDocNavigation } from './hooks/use-doc-navigation';
import { slugify, matchesQuery, getModuleIcon } from './lib/doc-utils';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Documentation', href: '/documentation' },
];

function DocLayout({ children }: { children: ReactNode }) {
    const { flash } = usePage<SharedData>().props;

    useEffect(() => {
        if (flash.success) toast.success('Success', { description: flash.success });
        if (flash.error) toast.error('Error', { description: flash.error });
    }, [flash]);

    return (
        <AppShell>
            <AppHeader breadcrumbs={breadcrumbs} />
            <main className="flex-1 bg-slate-50 dark:bg-slate-950">
                {children}
            </main>
            <Toaster />
        </AppShell>
    );
}

/* ─── Helper: find playbook for a MenuGroup ─── */
function findPlaybook(
    documentation: DocumentationPageProps['documentation'],
    group: MenuGroup,
): ModulePlaybook | undefined {
    const menuSlug = slugify(group.menu.replace(/ Modules?$/i, ''));
    return documentation.modulePlaybooks.find((p) => {
        const playbookSlug = slugify(p.title.replace(/ Modules?$/i, ''));
        return playbookSlug === menuSlug || p.id === `${menuSlug}-module` || p.id === menuSlug;
    });
}

/* ─── Sidebar Navigation Component ─── */
function DocSidebar({
    documentation,
    activeModuleSlug,
    activeProcedureSlug,
    searchQuery,
    onSearchChange,
}: {
    documentation: DocumentationPageProps['documentation'];
    activeModuleSlug?: string | null;
    activeProcedureSlug?: string | null;
    searchQuery: string;
    onSearchChange: (q: string) => void;
}) {
    const [expandedModules, setExpandedModules] = useState<Set<string>>(new Set());

    // Auto-expand the active module
    useEffect(() => {
        if (activeModuleSlug) {
            setExpandedModules((prev) => new Set(prev).add(activeModuleSlug));
        }
    }, [activeModuleSlug]);

    const moduleGroups = documentation.menuGroups;

    const filteredGroups = useMemo(() => {
        if (!searchQuery) return moduleGroups;
        return moduleGroups.filter((group) => {
            const playbook = findPlaybook(documentation, group);
            const body = [
                group.menu, group.module, group.purpose,
                ...group.features, ...group.how_to,
                playbook?.overview ?? '',
                ...(playbook?.highlights ?? []),
                ...(playbook?.procedures.flatMap((p) => [
                    p.name,
                    ...p.steps.flatMap((s) => {
                        if (typeof s === 'string') {
                            return [s];
                        }

                        const contentBlocksText = (s.content_blocks ?? [])
                            .filter((block) => block.type === 'text' && Boolean(block.text))
                            .map((block) => block.text as string);

                        return [s.text ?? '', ...contentBlocksText];
                    }),
                ]) ?? []),
            ].join(' ');
            return matchesQuery(body, searchQuery);
        });
    }, [documentation, moduleGroups, searchQuery]);

    return (
        <aside className="sidebar-lhs-parent sticky top-0 flex h-[calc(100vh-4rem)] w-[300px] shrink-0 flex-col overflow-hidden border-r border-sidebar-border/70 bg-white dark:bg-slate-900/80">
            {/* Title */}
            <div className="border-b border-sidebar-border/70 px-4 py-4">
                <div className="flex items-center gap-2">
                    <BookOpen className="h-5 w-5 text-orange-600 dark:text-orange-400" />
                    <h2 className="text-sm font-bold tracking-tight text-foreground">
                        {documentation.manual.title}
                    </h2>
                </div>

            </div>

            {/* Search */}
            <div className="border-b border-sidebar-border/70 px-3 py-3">
                <div className="relative">
                    <Search className="pointer-events-none absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                    <input
                        type="search"
                        value={searchQuery}
                        onChange={(e) => onSearchChange(e.target.value)}
                        placeholder="Search modules..."
                        className="w-full rounded-lg border border-sidebar-border/70 bg-slate-50 py-1.5 pr-3 pl-8 text-xs text-foreground transition-colors placeholder:text-muted-foreground focus:border-orange-300 focus:bg-white focus:ring-1 focus:ring-orange-200 focus:outline-none dark:bg-slate-800 dark:focus:border-orange-700 dark:focus:bg-slate-900 dark:focus:ring-orange-900"
                    />
                </div>
            </div>

            {/* Module List */}
            <nav className="flex-1 overflow-y-auto px-2 py-2 [scrollbar-gutter:stable]">
                {filteredGroups.length === 0 && (
                    <div className="px-2 py-6 text-center">
                        <Search className="mx-auto h-6 w-6 text-muted-foreground/40" />
                        <p className="mt-2 text-xs text-muted-foreground">No modules match "{searchQuery}"</p>
                    </div>
                )}

                {filteredGroups.map((group) => {
                    const gSlug = slugify(group.menu.replace(/ Modules?$/i, ''));
                    const playbook = findPlaybook(documentation, group);
                    const procedures = playbook?.procedures ?? [];
                    const isExpanded = expandedModules.has(gSlug);
                    const isActiveModule = activeModuleSlug === gSlug;
                    const Icon = getModuleIcon(group.menu);

                    return (
                        <div key={group.menu} className={`mb-0.5 rounded-lg ${
                            isActiveModule && !activeProcedureSlug
                                ? 'bg-orange-50 dark:bg-orange-950/40'
                                : 'hover:bg-slate-50 dark:hover:bg-slate-800'
                        }`}>
                            {/* Module item */}
                            <div className="flex items-stretch justify-between">
                                <Link
                                    href={`/documentation/${gSlug}`}
                                    preserveScroll={false}
                                    replace={false}
                                    className={`flex flex-1 items-center gap-2 px-2.5 py-2 text-left text-xs font-medium ${
                                        isActiveModule && !activeProcedureSlug
                                            ? 'text-orange-700 dark:text-orange-300'
                                            : isActiveModule
                                                ? 'text-orange-600 dark:text-orange-400'
                                                : 'text-foreground/80'
                                    }`}
                                >
                                    <Icon className="h-3.5 w-3.5 shrink-0" />
                                    <span className="flex-1 truncate">{group.menu.replace(/ Module$/i, '')}</span>
                                </Link>
                                {procedures.length > 0 && (
                                        <button
                                        type="button"
                                        onClick={(e) => {
                                            e.preventDefault();
                                            e.stopPropagation();
                                            setExpandedModules((prev) => {
                                                const next = new Set(prev);
                                                if (next.has(gSlug)) { next.delete(gSlug); } else { next.add(gSlug); }
                                                return next;
                                            });
                                        }}
                                        className={`flex w-9 shrink-0 items-center justify-center rounded-r-lg ${
                                            isActiveModule && !activeProcedureSlug
                                                ? 'text-orange-700 hover:bg-orange-100 dark:text-orange-300 dark:hover:bg-orange-900/50'
                                                : 'text-muted-foreground hover:bg-slate-200 dark:hover:bg-slate-700'
                                        }`}
                                    >
                                        {isExpanded
                                            ? <ChevronDown className="h-3.5 w-3.5" />
                                            : <ChevronRight className="h-3.5 w-3.5" />}
                                    </button>
                                )}
                            </div>

                            {/* Procedures sub-list */}
                            {isExpanded && procedures.length > 0 && (
                                <div className="ml-4 mt-0.5 space-y-0.5 border-l border-sidebar-border/50 pl-2">
                                    {procedures.map((proc, idx) => {
                                        const pSlug = slugify(proc.name);
                                        const isActive = isActiveModule && activeProcedureSlug === pSlug;

                                        return (
                                            <Link
                                                key={proc.name}
                                                href={`/documentation/${gSlug}/${pSlug}`}
                                                preserveScroll={false}
                                                replace={false}
                                                className={`flex w-full items-center gap-1.5 border-l-2 px-2 py-1.5 text-left text-[11px] ${
                                                    isActive
                                                        ? 'rounded-r-md border-orange-500 bg-orange-50 font-bold text-orange-700 dark:bg-orange-950/40 dark:text-orange-300'
                                                        : 'rounded-r-md border-transparent text-muted-foreground hover:border-slate-200 hover:bg-slate-50 hover:text-foreground dark:hover:border-slate-700 dark:hover:bg-slate-800'
                                                }`}
                                            >
                                                <span className="flex h-4 w-4 shrink-0 items-center justify-center rounded text-[9px] font-bold text-muted-foreground">
                                                    {String(idx + 1).padStart(2, '0')}
                                                </span>
                                                <span className="line-clamp-2">{proc.name}</span>
                                            </Link>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    );
                })}
            </nav>

            {/* Footer info */}
            <div className="border-t border-sidebar-border/70 px-4 py-3">
                <p className="text-[10px] text-muted-foreground">
                    {documentation.manual.copyright}
                </p>
            </div>
        </aside>
    );
}

/* ─── Main Export ─── */

export default function DocumentationV3Index({ documentation, moduleSlug, procedureSlug }: DocumentationPageProps) {
    const nav = useDocNavigation(documentation, moduleSlug, procedureSlug);
    
    // KONDISI A: Halaman Utama / Welcome Landing
    const isHomeView = nav.view === 'home' || !moduleSlug;
    
    // KONDISI B: Mode Browse Guides
    const isDetailView = nav.view === 'module' || nav.view === 'procedure';

    return (
        <DocLayout>
            <Head title={documentation.manual.title} />
            <div className="min-h-[calc(100vh-4rem)]">
                {isHomeView && (
                    /* KONDISI A: Home view — full-width, no sidebar */
                    <HomeView
                        documentation={documentation}
                        searchQuery={nav.searchQuery}
                        onSearchChange={nav.setSearchQuery}
                        onModuleClick={nav.goToModule}
                    />
                )}

                {isDetailView && !isHomeView && (
                    /* KONDISI B: Detail views — two-column with sidebar */
                    <div className="flex h-[calc(100vh-4rem)] overflow-hidden">
                        <DocSidebar
                            documentation={documentation}
                            activeModuleSlug={moduleSlug}
                            activeProcedureSlug={procedureSlug}
                            searchQuery={nav.searchQuery}
                            onSearchChange={nav.setSearchQuery}
                        />
                        <div id="doc-content-area" className="resource-content-wrap min-w-0 flex-1 overflow-y-auto bg-slate-50/60 dark:bg-slate-950">
                            {nav.view === 'module' && nav.selectedModule && (
                                <ModuleDetailView
                                    documentation={documentation}
                                    moduleGroup={nav.selectedModule}
                                    onBack={nav.goHome}
                                    onProcedureClick={nav.goToProcedure}
                                />
                            )}
                            {nav.view === 'procedure' && nav.selectedModule && nav.selectedProcedure !== null && (
                                <ProcedureDetailView
                                    documentation={documentation}
                                    moduleGroup={nav.selectedModule}
                                    procedureIndex={nav.selectedProcedure}
                                    onBackToModule={() => nav.goToModule(nav.selectedModule!)}
                                    onBackToHome={nav.goHome}
                                />
                            )}
                        </div>
                    </div>
                )}
            </div>
        </DocLayout>
    );
}
