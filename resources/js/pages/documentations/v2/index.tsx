import { Callout } from '@/components/documentation/callout';
import { CopyLinkButton } from '@/components/documentation/copy-link-button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type {
    DocumentationPageProps,
    HowToStep,
    MenuGroup,
    ModulePlaybook,
    PlaybookStep,
    RoleGuideItem,
    Workflow,
    WorkflowStep,
} from '@/types/documentation';
import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    Check,
    CheckCircle,
    ChevronRight,
    ExternalLink,
    FileText,
    Info,
    LayoutGrid,
    Lightbulb,
    Search,
    Sparkles,
    Users,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

// ---------------------------------------------------------------------------
// Constants & helpers
// ---------------------------------------------------------------------------

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Documentation V2', href: '/documentations/v2' },
];

const slugify = (text: string) =>
    text.toLowerCase().replace(/\s+/g, '-');

/** Safely extract text & path from a step that may be a plain string or object */
function parseStep(step: WorkflowStep | HowToStep | PlaybookStep | string): {
    text: string;
    path?: string;
} {
    if (typeof step === 'string') return { text: step };
    return { text: step.text, path: step.path };
}

// ---------------------------------------------------------------------------
// Shared sub-components
// ---------------------------------------------------------------------------

interface SectionHeaderProps {
    title: string;
    sectionId: string;
    description?: string;
}

function SectionHeader({ title, sectionId, description }: SectionHeaderProps) {
    return (
        <div className="group mb-8">
            <h1 className="inline-flex items-center text-3xl font-bold tracking-tight text-foreground">
                {title}
                <CopyLinkButton sectionId={sectionId} />
            </h1>
            {description && (
                <p className="mt-3 text-lg leading-relaxed text-muted-foreground">
                    {description}
                </p>
            )}
        </div>
    );
}

interface StepListProps {
    steps: (WorkflowStep | HowToStep | PlaybookStep | string)[];
    numbered?: boolean;
    /** Called when a step is toggled (playbook checklist mode). Key = stepId. */
    onToggle?: (stepId: string) => void;
    completedSteps?: string[];
    stepIdPrefix?: string;
}

function StepList({
    steps,
    numbered = false,
    onToggle,
    completedSteps = [],
    stepIdPrefix = '',
}: StepListProps) {
    const isChecklist = !!onToggle;

    return (
        <ol className={numbered ? 'relative ml-2 space-y-6 border-l border-muted/50 pl-5' : 'space-y-4'}>
            {steps.map((step, idx) => {
                const { text, path } = parseStep(step);
                const stepId = `${stepIdPrefix}-${idx}`;
                const isCompleted = completedSteps.includes(stepId);

                if (isChecklist) {
                    return (
                        <li
                            key={idx}
                            className="group/step flex items-start gap-3 rounded-lg bg-muted/30 p-3 text-sm text-foreground transition-colors hover:bg-muted/50"
                        >
                            <button
                                onClick={() => onToggle(stepId)}
                                className={`mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 transition-colors ${isCompleted
                                        ? 'border-primary bg-primary text-white'
                                        : 'border-muted-foreground/40'
                                    }`}
                                aria-label={isCompleted ? 'Mark incomplete' : 'Mark complete'}
                            >
                                {isCompleted && <Check className="h-3 w-3" />}
                            </button>
                            <div className="flex-1 pt-0.5">
                                <span
                                    className={`leading-relaxed ${isCompleted ? 'text-muted-foreground line-through' : ''
                                        }`}
                                >
                                    {text}
                                </span>
                                {path && (
                                    <div className="mt-2 flex items-center gap-1 opacity-0 transition-opacity group-hover/step:opacity-100">
                                        <PracticeLink href={path} />
                                    </div>
                                )}
                            </div>
                        </li>
                    );
                }

                if (numbered) {
                    return (
                        <li key={idx} className="group/step relative">
                            <span className="absolute -left-[30px] flex h-6 w-6 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary ring-4 ring-card">
                                {idx + 1}
                            </span>
                            <div className="flex flex-col gap-1">
                                <p className="pt-0.5 text-base text-foreground">{text}</p>
                                {path && (
                                    <span className="opacity-0 transition-opacity group-hover/step:opacity-100">
                                        <PracticeLink href={path} />
                                    </span>
                                )}
                            </div>
                        </li>
                    );
                }

                // plain numbered list (how-to style)
                return (
                    <li key={idx} className="group/step flex flex-col gap-2">
                        <div className="flex items-start gap-3 text-sm">
                            <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-sidebar-accent/50 text-xs font-medium">
                                {idx + 1}
                            </span>
                            <span className="pt-0.5 leading-relaxed text-foreground">{text}</span>
                        </div>
                        {path && (
                            <span className="ml-8 opacity-0 transition-opacity group-hover/step:opacity-100">
                                <PracticeLink href={path} />
                            </span>
                        )}
                    </li>
                );
            })}
        </ol>
    );
}

function PracticeLink({ href }: { href: string }) {
    return (
        <Link
            href={href}
            className="flex w-fit items-center gap-1 text-[11px] font-medium text-primary hover:underline"
        >
            Practice Now <ExternalLink className="h-3 w-3" />
        </Link>
    );
}

// ---------------------------------------------------------------------------
// Section components
// ---------------------------------------------------------------------------

function IntroductionSection({
    documentation,
}: Pick<DocumentationPageProps, 'documentation'>) {
    return (
        <div className="duration-300 animate-in fade-in">
            <SectionHeader
                title={documentation.manual.title}
                sectionId="introduction"
                description={documentation.introduction}
            />

            <Callout type="info">
                <strong>Welcome to KTMS Knowledge Hub!</strong> This
                documentation is designed as an interactive guide to help you
                understand and operate the system effectively.
            </Callout>

            <div className="mb-8 grid grid-cols-1 gap-4 md:grid-cols-3">
                {[
                    { label: 'Version', value: documentation.manual.version },
                    { label: 'Last Updated', value: documentation.manual.date },
                    { label: 'Author', value: documentation.manual.author },
                ].map(({ label, value }) => (
                    <div
                        key={label}
                        className="rounded-xl border border-sidebar-border bg-sidebar-accent/30 p-4"
                    >
                        <div className="text-sm font-medium text-muted-foreground">
                            {label}
                        </div>
                        <div className="mt-1 text-xl font-semibold text-foreground">
                            {value}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

function RolesSection({ roles }: { roles: RoleGuideItem[] }) {
    return (
        <div className="duration-300 animate-in fade-in">
            <SectionHeader
                title="Roles and Access"
                sectionId="roles-access"
                description="Roles control which menus and workflows each user can execute within the system."
            />

            <Callout type="tip">
                <strong>Role-Based Journey:</strong> Each role has specific
                responsibilities. Understand your scope to work more
                efficiently.
            </Callout>

            <div className="grid grid-cols-1 gap-6">
                {roles.map((role) => (
                    <div
                        key={role.role}
                        className="group relative overflow-hidden rounded-xl border bg-card transition-all hover:shadow-md"
                    >
                        <div className="flex flex-col md:flex-row">
                            <div className="bg-muted/50 p-6 md:w-1/3">
                                <div className="mb-3 inline-flex rounded-lg bg-primary/10 p-3 text-primary">
                                    <Users className="h-6 w-6" />
                                </div>
                                <h3 className="text-xl font-bold">{role.role}</h3>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    {role.scope}
                                </p>
                            </div>
                            <div className="p-6 md:w-2/3">
                                <h4 className="mb-4 text-xs font-bold tracking-widest text-primary uppercase">
                                    Responsibility Matrix
                                </h4>
                                <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    {role.primary_actions.map((action, i) => (
                                        <div
                                            key={i}
                                            className="flex items-center gap-2 text-sm text-foreground/80"
                                        >
                                            <div className="h-1.5 w-1.5 rounded-full bg-primary" />
                                            {action}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

function CoreWorkflowsSection({ workflows }: { workflows: Workflow[] }) {
    return (
        <div className="duration-300 animate-in fade-in">
            <SectionHeader
                title="Core Workflows"
                sectionId="core-workflows"
                description="End-to-end operational life cycles across multiple modules."
            />

            <Callout type="info">
                These workflows illustrate complete business processes from
                start to finish. Follow each step sequentially for optimal
                results.
            </Callout>

            <div className="space-y-8">
                {workflows.map((workflow, idx) => (
                    <article
                        key={idx}
                        className="overflow-hidden rounded-xl border border-sidebar-border bg-card shadow-sm"
                    >
                        <div className="border-b border-sidebar-border bg-sidebar-accent/50 px-6 py-4">
                            <h3 className="text-lg font-semibold text-foreground">
                                {workflow.name}
                            </h3>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {workflow.goal}
                            </p>
                        </div>
                        <div className="p-6">
                            <StepList steps={workflow.steps} numbered />
                        </div>
                    </article>
                ))}
            </div>
        </div>
    );
}

function HowToGuidesSection({
    guides,
}: {
    guides: DocumentationPageProps['documentation']['howToGuides'];
}) {
    return (
        <div className="duration-300 animate-in fade-in">
            <SectionHeader
                title="How-To Guides"
                sectionId="how-to-guides"
                description="Step-by-step instructions for specific operational tasks."
            />

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {guides.map((guide, idx) => (
                    <article
                        key={idx}
                        className="rounded-xl border border-sidebar-border bg-card p-6 shadow-sm"
                    >
                        <h3 className="mb-4 flex items-center gap-2 text-lg font-semibold text-foreground">
                            <FileText className="h-5 w-5 text-primary" />
                            {guide.task}
                        </h3>
                        <StepList steps={guide.steps} />
                    </article>
                ))}
            </div>
        </div>
    );
}

function StatusGuidanceSection({
    statuses,
}: {
    statuses: DocumentationPageProps['documentation']['commonStatuses'];
}) {
    return (
        <div className="duration-300 animate-in fade-in">
            <SectionHeader
                title="Status Guidance"
                sectionId="status-guidance"
                description="Learn what each status means and when to use it."
            />

            <Callout type="warning">
                <strong>Important:</strong> Proper status usage ensures data
                accuracy and smooth processes. Understand the context before
                changing a status.
            </Callout>

            <div className="grid gap-6">
                {statuses.map((status, idx) => (
                    <article
                        key={idx}
                        className="rounded-xl border border-sidebar-border bg-card p-6 shadow-sm"
                    >
                        <h3 className="mb-4 flex items-center gap-2 text-lg font-semibold text-foreground">
                            <Activity className="h-5 w-5 text-primary" />
                            {status.topic}
                        </h3>
                        <ul className="space-y-3">
                            {status.notes.map((note, i) => (
                                <li
                                    key={i}
                                    className="flex items-start gap-2 text-sm text-foreground"
                                >
                                    <CheckCircle className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                                    <span className="leading-relaxed">{note}</span>
                                </li>
                            ))}
                        </ul>
                    </article>
                ))}
            </div>
        </div>
    );
}

function OperationalTipsSection({ tips }: { tips: string[] }) {
    return (
        <div className="duration-300 animate-in fade-in">
            <SectionHeader
                title="Operational Tips"
                sectionId="operational-tips"
                description="Best practices to keep your system data clean and error-free."
            />

            <div className="rounded-xl border border-sidebar-border bg-card p-6 shadow-sm">
                <ul className="space-y-4">
                    {tips.map((tip, idx) => (
                        <li
                            key={idx}
                            className="flex items-start gap-3 rounded-lg bg-yellow-500/10 p-4"
                        >
                            <Lightbulb className="mt-0.5 h-5 w-5 shrink-0 text-yellow-600" />
                            <span className="leading-relaxed text-foreground">{tip}</span>
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}

interface ModuleSectionProps {
    activeGroup: MenuGroup;
    playbook?: ModulePlaybook;
    completedSteps: string[];
    onToggleStep: (id: string) => void;
}

function ModuleSection({
    activeGroup,
    playbook,
    completedSteps,
    onToggleStep,
}: ModuleSectionProps) {
    const sectionId = `module-${slugify(activeGroup.menu)}`;

    return (
        <div className="duration-300 animate-in fade-in">
            {/* Header */}
            <div className="group mb-8 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div className="flex-1">
                    <div className="flex items-center gap-3">
                        <div className="rounded-xl bg-primary/10 p-3 text-primary">
                            <LayoutGrid className="h-6 w-6" />
                        </div>
                        <h1 className="inline-flex items-center text-3xl font-bold tracking-tight text-foreground">
                            {activeGroup.menu} Module
                            <CopyLinkButton sectionId={sectionId} />
                        </h1>
                    </div>
                    <p className="mt-4 text-lg leading-relaxed text-muted-foreground">
                        {activeGroup.purpose}
                    </p>
                </div>

                {activeGroup.route_path && (
                    <div className="shrink-0">
                        <Link
                            href={activeGroup.route_path}
                            className="group flex items-center gap-3 rounded-2xl bg-primary p-1 pr-5 text-sm font-semibold text-primary-foreground shadow-lg transition-all hover:scale-105 active:scale-95"
                        >
                            <div className="rounded-xl bg-white/20 p-2">
                                <ExternalLink className="h-5 w-5" />
                            </div>
                            <span>Start Practice: {activeGroup.menu}</span>
                        </Link>
                    </div>
                )}
            </div>

            <Callout type="tip">
                <strong>Learning Guide:</strong> Read <b>Key Features</b> to
                understand the functionality, then follow{' '}
                <b>Operational Playbooks</b> below while trying them in the
                system menu.
            </Callout>

            {/* Key Features + How-To Summary */}
            <div className="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <article className="rounded-xl border border-sidebar-border bg-card p-6 shadow-sm">
                    <h3 className="mb-4 text-lg font-semibold text-foreground">
                        Key Features
                    </h3>
                    <ul className="space-y-3">
                        {activeGroup.features.map((feature, i) => (
                            <li
                                key={i}
                                className="flex items-start gap-2 text-sm text-foreground"
                            >
                                <ChevronRight className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                                <span className="leading-relaxed">{feature}</span>
                            </li>
                        ))}
                    </ul>
                </article>

                <article className="rounded-xl border border-sidebar-border bg-card p-6 shadow-sm">
                    <h3 className="mb-4 text-lg font-semibold text-foreground">
                        How-To Summary
                    </h3>
                    <ul className="space-y-3">
                        {activeGroup.how_to.map((howto, i) => (
                            <li
                                key={i}
                                className="flex items-start gap-2 text-sm text-foreground"
                            >
                                <ChevronRight className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                                <span className="leading-relaxed">{howto}</span>
                            </li>
                        ))}
                    </ul>
                </article>
            </div>

            {/* Operational Playbooks */}
            {playbook && (
                <div className="mt-10 border-t border-sidebar-border pt-8">
                    <div className="mb-6 flex items-center justify-between rounded-lg border border-primary/20 bg-primary/5 px-4 py-2">
                        <div className="flex items-center gap-2 text-sm font-medium text-primary">
                            <Sparkles className="h-4 w-4" />
                            <span>Practice Mode Active</span>
                        </div>
                        <div className="text-xs text-muted-foreground">
                            Complete all steps to master this module.
                        </div>
                    </div>

                    <div className="mb-6">
                        <h2 className="text-2xl font-bold text-foreground">
                            Operational Playbooks
                        </h2>
                        <p className="mt-2 text-muted-foreground">
                            {playbook.overview}
                        </p>
                    </div>

                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                        {playbook.procedures.map((proc, idx) => (
                            <article
                                key={idx}
                                className="overflow-hidden rounded-xl border border-sidebar-border bg-card shadow-sm"
                            >
                                <div className="border-b border-sidebar-border bg-sidebar-accent/50 px-5 py-4">
                                    <h4 className="font-semibold text-foreground">
                                        {proc.name}
                                    </h4>
                                </div>
                                <div className="p-5">
                                    <StepList
                                        steps={proc.steps}
                                        onToggle={onToggleStep}
                                        completedSteps={completedSteps}
                                        stepIdPrefix={proc.name}
                                    />
                                </div>
                            </article>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

// ---------------------------------------------------------------------------
// Main page
// ---------------------------------------------------------------------------

export default function DocumentationIndex({
    documentation,
}: DocumentationPageProps) {
    const [activeSection, setActiveSection] = useState('introduction');
    const [searchQuery, setSearchQuery] = useState('');
    const [readingProgress, setReadingProgress] = useState(0);
    const [completedSteps, setCompletedSteps] = useState<string[]>([]);
    const contentRef = useRef<HTMLDivElement>(null);

    // Sync hash → activeSection
    useEffect(() => {
        const handleHashChange = () => {
            const hash = window.location.hash.replace('#', '');
            if (hash) setActiveSection(hash);
        };
        handleHashChange();
        window.addEventListener('hashchange', handleHashChange);
        return () => window.removeEventListener('hashchange', handleHashChange);
    }, []);

    // Reading progress bar
    useEffect(() => {
        const element = contentRef.current;
        if (!element) return;
        const handleScroll = () => {
            const { scrollTop, scrollHeight, clientHeight } = element;
            const progress = (scrollTop / (scrollHeight - clientHeight)) * 100;
            setReadingProgress(Math.min(progress, 100));
        };
        element.addEventListener('scroll', handleScroll);
        return () => element.removeEventListener('scroll', handleScroll);
    }, []);

    // Reset completed steps when switching modules
    useEffect(() => {
        setCompletedSteps([]);
    }, [activeSection]);

    const navigate = useCallback((id: string) => {
        setActiveSection(id);
        window.location.hash = id;
    }, []);

    const toggleStep = useCallback((id: string) => {
        setCompletedSteps((prev) =>
            prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id],
        );
    }, []);

    // Filter modules AND check if static sections match search
    const filteredModules = useMemo(() => {
        if (!documentation?.menuGroups) return [];
        if (!searchQuery) return documentation.menuGroups;
        return documentation.menuGroups.filter((g) =>
            g.menu.toLowerCase().includes(searchQuery.toLowerCase()),
        );
    }, [documentation, searchQuery]);

    const showStaticSections = !searchQuery ||
        ['introduction', 'roles', 'workflows', 'getting started'].some((kw) =>
            kw.includes(searchQuery.toLowerCase()),
        );

    const showReferenceSections = !searchQuery ||
        ['guides', 'tutorials', 'status', 'tips', 'how-to'].some((kw) =>
            kw.includes(searchQuery.toLowerCase()),
        );

    // Resolve active module (memoized to avoid re-computation on each render)
    const { activeGroup, activePlaybook } = useMemo(() => {
        if (!activeSection.startsWith('module-')) return {};
        const moduleName = activeSection.replace('module-', '');
        const group = documentation.menuGroups.find(
            (g) => slugify(g.menu) === moduleName,
        );
        const pb = documentation.modulePlaybooks.find(
            (p) =>
                slugify(p.title) === group?.menu.toLowerCase() + '-module' ||
                p.id === `${moduleName}-module`,
        );
        return { activeGroup: group, activePlaybook: pb };
    }, [activeSection, documentation]);

    // ---------------------------------------------------------------------------
    // Render active section content
    // ---------------------------------------------------------------------------
    const renderContent = () => {
        switch (activeSection) {
            case 'introduction':
                return <IntroductionSection documentation={documentation} />;

            case 'roles-access':
                return <RolesSection roles={documentation.roleGuide} />;

            case 'core-workflows':
                return (
                    <CoreWorkflowsSection
                        workflows={documentation.coreWorkflows}
                    />
                );

            case 'how-to-guides':
                return (
                    <HowToGuidesSection guides={documentation.howToGuides} />
                );

            case 'status-guidance':
                return (
                    <StatusGuidanceSection
                        statuses={documentation.commonStatuses}
                    />
                );

            case 'operational-tips':
                return <OperationalTipsSection tips={documentation.tips} />;

            default:
                if (activeSection.startsWith('module-')) {
                    if (!activeGroup) {
                        return (
                            <div className="flex h-40 items-center justify-center text-muted-foreground">
                                Module not found.
                            </div>
                        );
                    }
                    return (
                        <ModuleSection
                            activeGroup={activeGroup}
                            playbook={activePlaybook}
                            completedSteps={completedSteps}
                            onToggleStep={toggleStep}
                        />
                    );
                }
                return null;
        }
    };

    // ---------------------------------------------------------------------------
    // Sidebar nav helper
    // ---------------------------------------------------------------------------
    const navItem = (
        id: string,
        label: string,
        Icon: React.ElementType,
    ) => (
        <button
            key={id}
            onClick={() => navigate(id)}
            className={`flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm transition-colors ${activeSection === id
                    ? 'bg-primary/10 font-medium text-primary'
                    : 'text-foreground hover:bg-muted'
                }`}
        >
            <Icon className="h-4 w-4" />
            {label}
        </button>
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Documentation V2" />

            <div className="flex h-[calc(100vh-theme(spacing.24))] w-full flex-col gap-4 overflow-hidden p-4 pb-0 md:flex-row md:p-0">
                {/* ── Left Sidebar ── */}
                <div className="flex w-full shrink-0 flex-col overflow-hidden rounded-xl border border-sidebar-border bg-white md:w-72 lg:w-80 dark:bg-black/20">
                    {/* Search */}
                    <div className="border-b border-sidebar-border p-4">
                        <div className="relative">
                            <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <input
                                type="text"
                                placeholder="Search modules..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="w-full rounded-md border border-input bg-transparent py-2 pr-4 pl-9 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                            />
                        </div>
                    </div>

                    <nav className="custom-scrollbar flex-1 space-y-6 overflow-y-auto p-4">
                        {/* Getting Started */}
                        {showStaticSections && (
                            <div>
                                <h3 className="mb-2 px-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    Getting Started
                                </h3>
                                <div className="space-y-1">
                                    {navItem('introduction', 'Introduction', Info)}
                                    {navItem('roles-access', 'Roles & Access', Users)}
                                    {navItem('core-workflows', 'Core Workflows', Activity)}
                                </div>
                            </div>
                        )}

                        {/* Modules */}
                        {filteredModules.length > 0 && (
                            <div>
                                <h3 className="mb-2 px-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    Modules
                                </h3>
                                <div className="space-y-1">
                                    {filteredModules.map((group) => {
                                        const id = `module-${slugify(group.menu)}`;
                                        return (
                                            <button
                                                key={id}
                                                onClick={() => navigate(id)}
                                                className={`flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm transition-colors ${activeSection === id
                                                        ? 'bg-primary/10 font-medium text-primary'
                                                        : 'text-foreground hover:bg-muted'
                                                    }`}
                                            >
                                                <LayoutGrid
                                                    className={`h-4 w-4 ${activeSection === id ? 'text-primary' : 'text-muted-foreground'}`}
                                                />
                                                {group.menu}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        )}

                        {/* Reference Guides */}
                        {showReferenceSections && (
                            <div>
                                <h3 className="mb-2 px-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    Reference Guides
                                </h3>
                                <div className="space-y-1">
                                    {navItem('how-to-guides', 'How-To Guides', FileText)}
                                    {navItem('status-guidance', 'Status Guidance', CheckCircle)}
                                    {navItem('operational-tips', 'Operational Tips', Lightbulb)}
                                </div>
                            </div>
                        )}
                    </nav>
                </div>

                {/* ── Main Content ── */}
                <div className="mb-4 flex-1 overflow-hidden rounded-xl border border-sidebar-border bg-white shadow-sm md:mb-0 dark:bg-black/20">
                    {/* Reading progress */}
                    <div className="h-1 bg-muted">
                        <div
                            className="h-full bg-primary transition-all duration-150"
                            style={{ width: `${readingProgress}%` }}
                        />
                    </div>
                    <div
                        ref={contentRef}
                        className="custom-scrollbar h-full overflow-y-auto p-6 lg:p-10"
                    >
                        <div className="mx-auto max-w-4xl">{renderContent()}</div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}