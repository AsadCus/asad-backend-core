import { type DocumentationPageProps, type MenuGroup, type ModulePlaybook } from '@/types/documentation';
import { Search, BookOpen, ArrowRight, ChevronRight } from 'lucide-react';
import { useMemo } from 'react';
import { getModuleIcon, slugify, matchesQuery } from '../lib/doc-utils';

function findPlaybook(
    documentation: DocumentationPageProps['documentation'],
    group: MenuGroup,
): ModulePlaybook | undefined {
    const menuSlug = slugify(group.menu.replace(/ Modules?$/i, ''));
    return documentation.modulePlaybooks.find(
        (p) => {
            const playbookSlug = slugify(p.title.replace(/ Modules?$/i, ''));
            return playbookSlug === menuSlug || p.id === `${menuSlug}-module` || p.id === menuSlug;
        }
    );
}

/* ─── Hero ─────────────────────────────────────────────────── */

function HeroSection({
    title,
    searchQuery,
    onSearchChange,
}: {
    title: string;
    searchQuery: string;
    onSearchChange: (q: string) => void;
}) {
    return (
        <section className="relative overflow-hidden bg-gradient-to-br from-orange-600 via-orange-700 to-amber-800 dark:from-orange-900 dark:via-amber-950 dark:to-slate-950">
            {/* decorative circles */}
            <div className="pointer-events-none absolute -top-24 -left-24 h-72 w-72 rounded-full bg-white/5" />
            <div className="pointer-events-none absolute -right-16 -bottom-16 h-96 w-96 rounded-full bg-white/5" />
            <div className="pointer-events-none absolute top-1/2 left-1/2 h-48 w-48 -translate-x-1/2 -translate-y-1/2 rounded-full bg-white/5" />

            <div className="relative mx-auto max-w-4xl px-6 py-16 text-center md:py-20">
                <h1 className="text-3xl font-bold tracking-tight text-white md:text-5xl">
                    How can we help?
                </h1>
                <p className="mx-auto mt-4 max-w-2xl text-base leading-relaxed text-orange-100/80 md:text-lg">
                    Browse the {title} — step-by-step guides, module playbooks, and operational workflows for your travel management system.
                </p>

                <div className="relative mx-auto mt-8 max-w-xl">
                    <Search className="pointer-events-none absolute top-1/2 left-4 h-5 w-5 -translate-y-1/2 text-orange-300" />
                    <input
                        type="search"
                        value={searchQuery}
                        onChange={(e) => onSearchChange(e.target.value)}
                        placeholder="Search modules, procedures, guides..."
                        className="w-full rounded-xl border border-white/20 bg-white/10 py-3.5 pr-4 pl-12 text-white shadow-lg backdrop-blur-sm transition-all placeholder:text-orange-200/60 focus:border-white/40 focus:bg-white/15 focus:ring-2 focus:ring-white/20 focus:outline-none"
                    />
                </div>
            </div>
        </section>
    );
}

/* ─── Module Card ──────────────────────────────────────────── */

function ModuleCard({
    group,
    playbook,
    onClick,
}: {
    group: MenuGroup;
    playbook?: ModulePlaybook;
    onClick: () => void;
}) {
    const Icon = getModuleIcon(group.menu);
    const procedureCount = playbook?.procedures?.length ?? group.how_to.length;
    const description = playbook?.overview ?? group.purpose;

    return (
        <button
            type="button"
            onClick={onClick}
            className="group flex flex-col rounded-2xl border border-sidebar-border/70 bg-white p-6 text-left shadow-sm hover:border-orange-200 dark:bg-slate-900/60 dark:hover:border-orange-700"
        >
            <div className="flex items-start justify-between">
                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-orange-50 text-orange-600 group-hover:bg-orange-100 dark:bg-orange-950/50 dark:text-orange-400 dark:group-hover:bg-orange-900/50">
                    <Icon className="h-6 w-6" />
                </div>
                <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    {procedureCount} {procedureCount === 1 ? 'guide' : 'guides'}
                </span>
            </div>

            <h3 className="mt-4 text-lg font-semibold text-foreground group-hover:text-orange-600 dark:group-hover:text-orange-400">
                {group.menu.replace(/ Module$/i, '')}
            </h3>
            <p className="mt-2 line-clamp-2 flex-1 text-sm leading-relaxed text-muted-foreground">
                {description}
            </p>

            <div className="mt-4 flex items-center text-sm font-medium text-orange-600 dark:text-orange-400">
                Browse guides <ChevronRight className="ml-1 h-4 w-4" />
            </div>
        </button>
    );
}

/* ─── Roles Preview ────────────────────────────────────────── */

function RolesPreview({ documentation }: Pick<DocumentationPageProps, 'documentation'>) {
    return (
        <section className="mx-auto max-w-6xl px-6 pb-12">
            <h2 className="text-2xl font-bold tracking-tight text-foreground">
                Roles & Access
            </h2>
            <p className="mt-2 text-sm text-muted-foreground">
                Understand what each role does and what it should focus on every day.
            </p>
            <div className="mt-6 grid grid-cols-1 gap-4 md:grid-cols-4">
                {documentation.roleGuide.map((role) => (
                    <div
                        key={role.role}
                        className="rounded-2xl border border-sidebar-border/70 bg-white p-5 shadow-sm dark:bg-slate-900/60"
                    >
                        <h3 className="text-lg font-semibold text-foreground">{role.role}</h3>
                        <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{role.scope}</p>
                        <ul className="mt-3 space-y-1.5">
                            {role.primary_actions.map((action) => (
                                <li key={action} className="flex items-start gap-2 text-sm text-muted-foreground">
                                    <ArrowRight className="mt-0.5 h-3.5 w-3.5 shrink-0 text-orange-500" />
                                    <span>{action}</span>
                                </li>
                            ))}
                        </ul>
                    </div>
                ))}
            </div>
        </section>
    );
}

/* ─── Footer Info ──────────────────────────────────────────── */

function FooterInfo({ documentation }: Pick<DocumentationPageProps, 'documentation'>) {
    return (
        <footer className="border-t border-sidebar-border/70 bg-slate-50 dark:bg-slate-950/40">
            <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-center gap-6 px-6 py-6 text-sm text-muted-foreground">
                <span>{documentation.manual.copyright}</span>
            </div>
        </footer>
    );
}

/* ─── Home View (main export) ──────────────────────────────── */

export function HomeView({
    documentation,
    searchQuery,
    onSearchChange,
    onModuleClick,
}: {
    documentation: DocumentationPageProps['documentation'];
    searchQuery: string;
    onSearchChange: (q: string) => void;
    onModuleClick: (group: MenuGroup) => void;
}) {
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
        <div>
            <HeroSection
                title={documentation.manual.title}
                searchQuery={searchQuery}
                onSearchChange={onSearchChange}
            />

            {/* Module Grid */}
            <section className="mx-auto max-w-6xl px-6 py-12">
                <h2 className="text-2xl font-bold tracking-tight text-foreground">
                    Browse by Module
                </h2>
                <p className="mt-2 text-sm text-muted-foreground">
                    Select a module to view its guides and step-by-step procedures.
                </p>

                {filteredGroups.length === 0 && (
                    <div className="mt-8 rounded-2xl border border-dashed border-sidebar-border/70 p-12 text-center">
                        <Search className="mx-auto h-10 w-10 text-muted-foreground/40" />
                        <p className="mt-3 text-muted-foreground">No modules match "{searchQuery}"</p>
                    </div>
                )}

                <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {filteredGroups.map((group) => (
                        <ModuleCard
                            key={group.menu}
                            group={group}
                            playbook={findPlaybook(documentation, group)}
                            onClick={() => onModuleClick(group)}
                        />
                    ))}
                </div>
            </section>

            <RolesPreview documentation={documentation} />
            <FooterInfo documentation={documentation} />
        </div>
    );
}
