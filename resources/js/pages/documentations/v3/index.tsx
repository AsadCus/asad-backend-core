import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import {
    type DocumentationPageProps,
    type MenuGroup,
    type ModulePlaybook,
} from '@/types/documentation';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowUp,
    BookOpen,
    FolderTree,
    LayoutGrid,
    Lightbulb,
    ListChecks,
    ScrollText,
    Search,
    Sparkles,
    Users,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Documentation',
        href: '/documentations',
    },
];

const slugify = (text: string): string =>
    text
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

const getModuleId = (menu: string): string => `module-${slugify(menu)}`;

const getProcedureId = (menu: string, name: string): string =>
    `${getModuleId(menu)}-${slugify(name)}`;

const getSectionLabel = (menu: string): string => menu.replace(/ module$/i, '');

const topLevelSections = [
    { id: 'introduction', label: 'Introduction', icon: BookOpen },
    { id: 'roles-access', label: 'Roles & Access', icon: Users },
    { id: 'menu-structure', label: 'Menu Structure', icon: LayoutGrid },
];

const referenceSections = [
    { id: 'core-workflows', label: 'Core Workflows', icon: Sparkles },
    { id: 'how-to-guides', label: 'How-To Guides', icon: ScrollText },
    { id: 'status-guidance', label: 'Status Guidance', icon: ListChecks },
    { id: 'operational-tips', label: 'Operational Tips', icon: Lightbulb },
];

const normalizeText = (value: string): string => value.toLowerCase().trim();

const matchesQuery = (value: string, query: string): boolean =>
    normalizeText(value).includes(normalizeText(query));

function findPlaybook(
    documentation: DocumentationPageProps['documentation'],
    group: MenuGroup,
): ModulePlaybook | undefined {
    const menuSlug = slugify(group.menu);

    return documentation.modulePlaybooks.find(
        (playbook) =>
            playbook.id === `${menuSlug}-module` ||
            slugify(playbook.title) === `${menuSlug}-module`,
    );
}

function ModuleSidebarChildren({
    group,
    playbook,
    activeSection,
    searchQuery,
}: {
    group: MenuGroup;
    playbook?: ModulePlaybook;
    activeSection: string;
    searchQuery: string;
}) {
    const children = playbook?.procedures?.length
        ? playbook.procedures.map((procedure) => ({
              id: getProcedureId(group.menu, procedure.name),
              label: procedure.name,
          }))
        : group.how_to.map((item) => ({
              id: `${getModuleId(group.menu)}-${slugify(item)}`,
              label: item,
          }));

    const visibleChildren = searchQuery
        ? children.filter((child) => matchesQuery(child.label, searchQuery))
        : children;

    if (visibleChildren.length === 0) {
        return null;
    }

    return (
        <div className="mt-2 space-y-1 border-l border-sidebar-border/70 pl-3">
            {visibleChildren.map((child) => (
                <SidebarButton
                    key={child.id}
                    id={child.id}
                    label={child.label}
                    nested
                    active={activeSection === child.id}
                    searchQuery={searchQuery}
                />
            ))}
        </div>
    );
}

function SidebarButton({
    id,
    label,
    icon: Icon,
    nested = false,
    active = false,
    searchQuery = '',
}: {
    id: string;
    label: string;
    icon?: React.ElementType;
    nested?: boolean;
    active?: boolean;
    searchQuery?: string;
}) {
    const handleClick = () => {
        const element = document.getElementById(id);

        element?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        window.history.replaceState(null, '', `#${id}`);
    };

    const isVisible = !searchQuery || matchesQuery(label, searchQuery);

    return (
        <button
            type="button"
            onClick={handleClick}
            className={`flex w-full items-center gap-2 rounded-lg border border-transparent px-3 py-2 text-left text-sm transition-colors ${
                active
                    ? 'bg-primary/10 font-medium text-primary'
                    : nested
                      ? 'text-muted-foreground hover:bg-muted/60 hover:text-foreground'
                      : 'text-foreground hover:bg-muted'
            } ${isVisible ? '' : 'hidden'}`}
            data-active-id={id}
        >
            {Icon ? <Icon className="h-4 w-4 shrink-0" /> : null}
            <span className="leading-5">{label}</span>
        </button>
    );
}

function SectionCard({
    id,
    title,
    description,
    children,
}: {
    id: string;
    title: string;
    description?: string;
    children: React.ReactNode;
}) {
    return (
        <section
            id={id}
            className="scroll-mt-24 rounded-2xl border border-sidebar-border/70 bg-white p-5 shadow-sm dark:bg-black/20"
        >
            <div className="flex flex-col gap-2 border-b border-sidebar-border/70 pb-4">
                <h2 className="text-2xl font-semibold tracking-tight text-foreground">
                    {title}
                </h2>
                {description ? (
                    <p className="text-sm leading-6 text-muted-foreground">
                        {description}
                    </p>
                ) : null}
            </div>
            <div className="pt-4">{children}</div>
        </section>
    );
}

function BulletList({ items }: { items: string[] }) {
    return (
        <ul className="space-y-2 text-sm leading-6 text-muted-foreground">
            {items.map((item) => (
                <li key={item} className="flex gap-2">
                    <span className="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-primary" />
                    <span>{item}</span>
                </li>
            ))}
        </ul>
    );
}

type StepItem = string | { text: string; path?: string };

function NumberedList({ items }: { items: StepItem[] }) {
    return (
        <ol className="space-y-3 text-sm leading-6 text-muted-foreground">
            {items.map((item, index) => (
                <li
                    key={typeof item === 'string' ? item : item.text}
                    className="flex gap-3"
                >
                    <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">
                        {index + 1}
                    </span>
                    <span className="pt-0.5">
                        {typeof item === 'string' ? item : item.text}
                    </span>
                </li>
            ))}
        </ol>
    );
}

function IntroductionSection({
    documentation,
}: Pick<DocumentationPageProps, 'documentation'>) {
    return (
        <SectionCard
            id="introduction"
            title={documentation.manual.title}
            description={documentation.introduction}
        >
            <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                <InfoPill
                    label="Version"
                    value={documentation.manual.version}
                />
                <InfoPill label="Date" value={documentation.manual.date} />
                <InfoPill label="Author" value={documentation.manual.author} />
            </div>
        </SectionCard>
    );
}

function InfoPill({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-xl border border-sidebar-border/70 bg-sidebar-accent/20 p-4">
            <div className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {label}
            </div>
            <div className="mt-1 text-lg font-semibold text-foreground">
                {value}
            </div>
        </div>
    );
}

function RolesSection({
    roles,
}: {
    roles: DocumentationPageProps['documentation']['roleGuide'];
}) {
    return (
        <SectionCard
            id="roles-access"
            title="Roles & Access"
            description="Use this section to understand what each role does and what it should focus on every day."
        >
            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                {roles.map((role) => (
                    <article
                        key={role.role}
                        className="rounded-xl border border-sidebar-border/70 p-4"
                    >
                        <h3 className="text-lg font-semibold text-foreground">
                            {role.role}
                        </h3>
                        <p className="mt-2 text-sm leading-6 text-muted-foreground">
                            {role.scope}
                        </p>
                        <div className="mt-4">
                            <h4 className="text-sm font-semibold text-foreground">
                                Primary Actions
                            </h4>
                            <div className="mt-2">
                                <BulletList items={role.primary_actions} />
                            </div>
                        </div>
                    </article>
                ))}
            </div>
        </SectionCard>
    );
}

function MenuStructureSection({
    documentation,
}: Pick<DocumentationPageProps, 'documentation'>) {
    return (
        <SectionCard
            id="menu-structure"
            title="Menu Structure"
            description="This is the top-level navigation map of the system. Each menu opens a practical module or workflow area."
        >
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                {documentation.menuStructure.map((group) => (
                    <article
                        key={group.menu}
                        className="rounded-xl border border-sidebar-border/70 p-4"
                    >
                        <h3 className="text-lg font-semibold text-foreground">
                            {group.menu}
                        </h3>
                        <div className="mt-3">
                            <BulletList items={group.children} />
                        </div>
                    </article>
                ))}
            </div>
        </SectionCard>
    );
}

function ModuleSection({
    documentation,
    group,
    searchQuery,
}: {
    documentation: DocumentationPageProps['documentation'];
    group: MenuGroup;
    searchQuery: string;
}) {
    const playbook = findPlaybook(documentation, group);
    const moduleId = getModuleId(group.menu);
    const guidedSteps = group.how_to.map((item) => ({
        id: `${moduleId}-${slugify(item)}`,
        label: item,
    }));

    return (
        <SectionCard
            id={moduleId}
            title={group.menu}
            description={group.purpose}
        >
            <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <article className="rounded-xl border border-sidebar-border/70 p-4">
                    <h3 className="text-base font-semibold text-foreground">
                        Key Features
                    </h3>
                    <div className="mt-3">
                        <BulletList items={group.features} />
                    </div>
                </article>

                <article className="rounded-xl border border-sidebar-border/70 p-4">
                    <h3 className="text-base font-semibold text-foreground">
                        How To
                    </h3>
                    <div className="mt-3">
                        <NumberedList items={group.how_to} />
                    </div>
                </article>
            </div>

            {group.route_path ? (
                <div className="mt-4">
                    <Link
                        href={group.route_path}
                        className="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-4 py-2 text-sm font-medium text-primary transition-colors hover:bg-primary/15"
                    >
                        Open {group.menu}
                    </Link>
                </div>
            ) : null}

            {playbook ? (
                <div className="mt-6 rounded-xl border border-primary/20 bg-primary/5 p-4">
                    <div className="flex items-center gap-2 text-sm font-semibold text-primary">
                        <Sparkles className="h-4 w-4" />
                        Module Playbook
                    </div>
                    <p className="mt-2 text-sm leading-6 text-muted-foreground">
                        {playbook.overview}
                    </p>

                    <div className="mt-4 grid grid-cols-1 gap-4">
                        {playbook.procedures.map((procedure) => (
                            <article
                                key={procedure.name}
                                id={getProcedureId(group.menu, procedure.name)}
                                className="scroll-mt-24 rounded-xl border border-sidebar-border/70 bg-white p-4 dark:bg-black/30"
                            >
                                <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <h4 className="text-base font-semibold text-foreground">
                                            {procedure.name}
                                        </h4>
                                        <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                            {group.menu} operational steps.
                                        </p>
                                    </div>
                                    <span className="rounded-full bg-sidebar-accent/40 px-3 py-1 text-xs font-medium text-muted-foreground">
                                        {procedure.steps.length} steps
                                    </span>
                                </div>
                                <div className="mt-4">
                                    <NumberedList items={procedure.steps} />
                                </div>
                            </article>
                        ))}
                    </div>
                </div>
            ) : null}

            {!playbook ? (
                <div className="mt-6 rounded-xl border border-sidebar-border/70 p-4">
                    <h3 className="text-base font-semibold text-foreground">
                        Guided Steps
                    </h3>
                    <div className="mt-3 space-y-3">
                        {guidedSteps
                            .filter(
                                (step) =>
                                    !searchQuery ||
                                    matchesQuery(step.label, searchQuery),
                            )
                            .map((step, index) => (
                                <article
                                    key={step.id}
                                    id={step.id}
                                    className="scroll-mt-24 rounded-xl border border-sidebar-border/50 bg-sidebar-accent/10 p-3"
                                >
                                    <div className="flex gap-3 text-sm leading-6 text-muted-foreground">
                                        <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">
                                            {index + 1}
                                        </span>
                                        <span className="pt-0.5">
                                            {step.label}
                                        </span>
                                    </div>
                                </article>
                            ))}
                    </div>
                </div>
            ) : null}
        </SectionCard>
    );
}

function CoreWorkflowsSection({
    workflows,
}: {
    workflows: DocumentationPageProps['documentation']['coreWorkflows'];
}) {
    return (
        <SectionCard
            id="core-workflows"
            title="Core Workflows"
            description="These are the end-to-end flows that connect sales, operations, and reporting.
            "
        >
            <div className="grid grid-cols-1 gap-4">
                {workflows.map((workflow) => (
                    <article
                        key={workflow.name}
                        className="rounded-xl border border-sidebar-border/70 p-4"
                    >
                        <h3 className="text-lg font-semibold text-foreground">
                            {workflow.name}
                        </h3>
                        <p className="mt-2 text-sm leading-6 text-muted-foreground">
                            Goal: {workflow.goal}
                        </p>
                        <div className="mt-4">
                            <NumberedList items={workflow.steps} />
                        </div>
                    </article>
                ))}
            </div>
        </SectionCard>
    );
}

function HowToGuidesSection({
    guides,
}: {
    guides: DocumentationPageProps['documentation']['howToGuides'];
}) {
    return (
        <SectionCard
            id="how-to-guides"
            title="How-To Guides"
            description="Task-focused guides for common operational actions."
        >
            <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
                {guides.map((guide) => (
                    <article
                        key={guide.task}
                        className="rounded-xl border border-sidebar-border/70 p-4"
                    >
                        <h3 className="text-lg font-semibold text-foreground">
                            {guide.task}
                        </h3>
                        <div className="mt-4">
                            <NumberedList items={guide.steps} />
                        </div>
                    </article>
                ))}
            </div>
        </SectionCard>
    );
}

function StatusGuidanceSection({
    statuses,
}: {
    statuses: DocumentationPageProps['documentation']['commonStatuses'];
}) {
    return (
        <SectionCard
            id="status-guidance"
            title="Status Guidance"
            description="Use these notes to keep status usage consistent across the team."
        >
            <div className="grid grid-cols-1 gap-4 xl:grid-cols-3">
                {statuses.map((status) => (
                    <article
                        key={status.topic}
                        className="rounded-xl border border-sidebar-border/70 p-4"
                    >
                        <h3 className="text-lg font-semibold text-foreground">
                            {status.topic}
                        </h3>
                        <div className="mt-3">
                            <BulletList items={status.notes} />
                        </div>
                    </article>
                ))}
            </div>
        </SectionCard>
    );
}

function OperationalTipsSection({ tips }: { tips: string[] }) {
    return (
        <SectionCard
            id="operational-tips"
            title="Operational Tips"
            description="A short list of practical reminders that help keep data clean and processes predictable."
        >
            <BulletList items={tips} />
        </SectionCard>
    );
}

export default function DocumentationV3Index({
    documentation,
}: DocumentationPageProps) {
    const contentRef = useRef<HTMLDivElement>(null);
    const [activeSection, setActiveSection] = useState('introduction');
    const [searchQuery, setSearchQuery] = useState('');
    const [showScrollTop, setShowScrollTop] = useState(false);

    const moduleGroups = documentation.menuGroups;

    const filteredTopLevelSections = useMemo(() => {
        if (!searchQuery) {
            return topLevelSections;
        }

        return topLevelSections.filter((section) =>
            matchesQuery(section.label, searchQuery),
        );
    }, [searchQuery]);

    const filteredReferenceSections = useMemo(() => {
        if (!searchQuery) {
            return referenceSections;
        }

        return referenceSections.filter((section) =>
            matchesQuery(section.label, searchQuery),
        );
    }, [searchQuery]);

    const filteredModuleGroups = useMemo(() => {
        if (!searchQuery) {
            return moduleGroups;
        }

        return moduleGroups.filter((group) => {
            const playbook = findPlaybook(documentation, group);
            const searchBody = [
                group.menu,
                group.module,
                group.purpose,
                ...group.features,
                ...group.how_to,
                playbook?.overview ?? '',
                ...(playbook?.highlights ?? []),
                ...(playbook?.procedures.flatMap((procedure) => [
                    procedure.name,
                    ...procedure.steps.map((step) =>
                        typeof step === 'string' ? step : step.text,
                    ),
                ]) ?? []),
            ].join(' ');

            return matchesQuery(searchBody, searchQuery);
        });
    }, [documentation, moduleGroups, searchQuery]);

    const allSectionIds = useMemo(() => {
        return [
            'introduction',
            'roles-access',
            'menu-structure',
            ...moduleGroups.flatMap((group) => {
                const playbook = findPlaybook(documentation, group);

                return [
                    getModuleId(group.menu),
                    ...(playbook?.procedures?.map((procedure) =>
                        getProcedureId(group.menu, procedure.name),
                    ) ??
                        group.how_to.map(
                            (item) =>
                                `${getModuleId(group.menu)}-${slugify(item)}`,
                        )),
                ];
            }),
            'core-workflows',
            'how-to-guides',
            'status-guidance',
            'operational-tips',
        ];
    }, [documentation, moduleGroups]);

    useEffect(() => {
        const root = contentRef.current;

        if (!root) {
            return;
        }

        const handleScroll = () => {
            setShowScrollTop(root.scrollTop > 320);
        };

        handleScroll();

        const observer = new IntersectionObserver(
            (entries) => {
                const visibleEntry = entries
                    .filter((entry) => entry.isIntersecting)
                    .sort(
                        (first, second) =>
                            second.intersectionRatio - first.intersectionRatio,
                    )[0];

                if (visibleEntry?.target?.id) {
                    setActiveSection(visibleEntry.target.id);
                }
            },
            {
                root,
                threshold: [0.25, 0.5, 0.75],
            },
        );

        allSectionIds.forEach((sectionId) => {
            const element = document.getElementById(sectionId);

            if (element) {
                observer.observe(element);
            }
        });

        root.addEventListener('scroll', handleScroll);

        return () => {
            root.removeEventListener('scroll', handleScroll);
            observer.disconnect();
        };
    }, [allSectionIds]);

    useEffect(() => {
        const hash = window.location.hash.replace('#', '');

        if (hash) {
            setActiveSection(hash);
        }
    }, []);

    useEffect(() => {
        const handleHashChange = () => {
            const hash = window.location.hash.replace('#', '');

            if (hash) {
                setActiveSection(hash);
            }
        };

        window.addEventListener('hashchange', handleHashChange);

        return () => window.removeEventListener('hashchange', handleHashChange);
    }, []);

    const isActive = (id: string): boolean =>
        activeSection === id || activeSection.startsWith(`${id}-`);

    const scrollToTop = () => {
        contentRef.current?.scrollTo({ top: 0, behavior: 'smooth' });
        setActiveSection('introduction');
        window.history.replaceState(null, '', '#introduction');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={documentation.manual.title} />

            <div className="flex min-h-[calc(100vh-theme(spacing.20))] flex-col gap-2 p-2 md:flex-row md:items-start">
                <aside className="flex w-full flex-col overflow-hidden rounded-xl border border-sidebar-border/70 bg-white shadow-sm md:h-[calc(100vh-theme(spacing.20))] md:w-80 md:self-start dark:bg-black/20">
                    <div className="border-b border-sidebar-border/70 p-4">
                        <div className="relative">
                            <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <input
                                type="search"
                                value={searchQuery}
                                onChange={(event) =>
                                    setSearchQuery(event.target.value)
                                }
                                placeholder="Search documentation..."
                                className="w-full rounded-lg border border-sidebar-border/70 bg-transparent py-2 pr-3 pl-9 text-sm transition-colors outline-none focus:border-primary focus:ring-1 focus:ring-primary"
                            />
                        </div>
                    </div>

                    <nav className="h-full flex-1 space-y-3 overflow-y-auto p-4">
                        <div>
                            <h2 className="mb-2 px-3 text-xs font-semibold tracking-[0.2em] text-muted-foreground uppercase">
                                Overview
                            </h2>
                            <div className="space-y-1">
                                {filteredTopLevelSections.map((section) => (
                                    <SidebarButton
                                        key={section.id}
                                        id={section.id}
                                        label={section.label}
                                        icon={section.icon}
                                        active={isActive(section.id)}
                                        searchQuery={searchQuery}
                                    />
                                ))}
                            </div>
                        </div>

                        <div>
                            <h2 className="mb-2 px-3 text-xs font-semibold tracking-[0.2em] text-muted-foreground uppercase">
                                Modules
                            </h2>
                            <div className="space-y-2">
                                {filteredModuleGroups.map((group) => {
                                    const playbook = findPlaybook(
                                        documentation,
                                        group,
                                    );
                                    const moduleId = getModuleId(group.menu);

                                    return (
                                        <div
                                            key={group.menu}
                                            className="rounded-xl border border-sidebar-border/70 p-2"
                                        >
                                            <SidebarButton
                                                id={moduleId}
                                                label={getSectionLabel(
                                                    group.menu,
                                                )}
                                                icon={FolderTree}
                                                active={isActive(moduleId)}
                                                searchQuery={searchQuery}
                                            />
                                            <ModuleSidebarChildren
                                                group={group}
                                                playbook={playbook}
                                                activeSection={activeSection}
                                                searchQuery={searchQuery}
                                            />
                                        </div>
                                    );
                                })}
                            </div>
                        </div>

                        <div>
                            <h2 className="mb-2 px-3 text-xs font-semibold tracking-[0.2em] text-muted-foreground uppercase">
                                Reference
                            </h2>
                            <div className="space-y-1">
                                {filteredReferenceSections.map((section) => (
                                    <SidebarButton
                                        key={section.id}
                                        id={section.id}
                                        label={section.label}
                                        icon={section.icon}
                                        active={isActive(section.id)}
                                        searchQuery={searchQuery}
                                    />
                                ))}
                            </div>
                        </div>
                    </nav>
                </aside>

                <main className="flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 bg-white shadow-sm dark:bg-black/20">
                    <div
                        ref={contentRef}
                        className="overflow-y-auto p-4 md:h-[calc(100vh-theme(spacing.20))]"
                    >
                        <div className="mx-auto max-w-5xl space-y-3">
                            <IntroductionSection
                                documentation={documentation}
                            />
                            <RolesSection roles={documentation.roleGuide} />
                            <MenuStructureSection
                                documentation={documentation}
                            />

                            {moduleGroups.map((group) => (
                                <ModuleSection
                                    key={group.menu}
                                    documentation={documentation}
                                    group={group}
                                    searchQuery={searchQuery}
                                />
                            ))}

                            <CoreWorkflowsSection
                                workflows={documentation.coreWorkflows}
                            />
                            <HowToGuidesSection
                                guides={documentation.howToGuides}
                            />
                            <StatusGuidanceSection
                                statuses={documentation.commonStatuses}
                            />
                            <OperationalTipsSection tips={documentation.tips} />
                        </div>
                    </div>
                </main>
            </div>

            {showScrollTop ? (
                <button
                    type="button"
                    onClick={scrollToTop}
                    className="fixed right-5 bottom-5 z-50 inline-flex h-11 w-11 items-center justify-center rounded-full border border-sidebar-border/70 bg-white text-foreground shadow-lg transition-transform hover:-translate-y-0.5 hover:bg-muted dark:bg-black/80"
                    aria-label="Scroll to top"
                >
                    <ArrowUp className="h-4 w-4" />
                </button>
            ) : null}
        </AppLayout>
    );
}
