import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import {
    Activity,
    CheckCircle,
    ChevronRight,
    FileText,
    Info,
    LayoutGrid,
    Lightbulb,
    Search,
    Users,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

interface ManualInfo {
    title: string;
    version: string;
    date: string;
    author: string;
}

interface BibliographyItem {
    id: string;
    title: string;
}

interface RoleGuideItem {
    role: string;
    scope: string;
    primary_actions: string[];
}

interface MenuStructureItem {
    menu: string;
    children: string[];
}

interface PlaybookProcedure {
    name: string;
    steps: string[];
}

interface ModulePlaybook {
    id: string;
    title: string;
    overview: string;
    highlights: string[];
    procedures: PlaybookProcedure[];
}

interface MenuGroup {
    menu: string;
    module: string;
    purpose: string;
    features: string[];
    how_to: string[];
}

interface Workflow {
    name: string;
    goal: string;
    steps: string[];
}

interface HowToGuide {
    task: string;
    steps: string[];
}

interface CommonStatus {
    topic: string;
    notes: string[];
}

interface DocumentationData {
    manual: ManualInfo;
    introduction: string;
    bibliography: BibliographyItem[];
    roleGuide: RoleGuideItem[];
    menuStructure: MenuStructureItem[];
    modulePlaybooks: ModulePlaybook[];
    menuGroups: MenuGroup[];
    coreWorkflows: Workflow[];
    howToGuides: HowToGuide[];
    commonStatuses: CommonStatus[];
    tips: string[];
}

interface DocumentationPageProps {
    documentation: DocumentationData;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Documentation',
        href: '/documentations',
    },
];

export default function DocumentationIndex({
    documentation,
}: DocumentationPageProps) {
    const [activeSection, setActiveSection] = useState('introduction');
    const [searchQuery, setSearchQuery] = useState('');

    // Handle hash routing
    useEffect(() => {
        const handleHashChange = () => {
            const hash = window.location.hash.replace('#', '');
            if (hash) {
                setActiveSection(hash);
            }
        };

        handleHashChange();
        window.addEventListener('hashchange', handleHashChange);
        return () => window.removeEventListener('hashchange', handleHashChange);
    }, []);

    const navigate = (id: string) => {
        setActiveSection(id);
        window.location.hash = id;
    };

    const modules = useMemo(() => {
        if (!documentation?.menuGroups) return [];
        return documentation.menuGroups.filter((g) =>
            g.menu.toLowerCase().includes(searchQuery.toLowerCase()),
        );
    }, [documentation, searchQuery]);

    const slugify = (text: string) => text.toLowerCase().replace(/\s+/g, '-');

    const renderContent = () => {
        if (activeSection === 'introduction') {
            return (
                <div className="duration-300 animate-in fade-in">
                    <div className="mb-8">
                        <h1 className="text-3xl font-bold tracking-tight text-foreground">
                            {documentation.manual.title}
                        </h1>
                        <p className="mt-3 text-lg leading-relaxed text-muted-foreground">
                            {documentation.introduction}
                        </p>
                    </div>

                    <div className="mb-8 grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div className="rounded-xl border border-sidebar-border bg-sidebar-accent/30 p-4">
                            <div className="text-sm font-medium text-muted-foreground">
                                Version
                            </div>
                            <div className="mt-1 text-xl font-semibold text-foreground">
                                {documentation.manual.version}
                            </div>
                        </div>
                        <div className="rounded-xl border border-sidebar-border bg-sidebar-accent/30 p-4">
                            <div className="text-sm font-medium text-muted-foreground">
                                Last Updated
                            </div>
                            <div className="mt-1 text-xl font-semibold text-foreground">
                                {documentation.manual.date}
                            </div>
                        </div>
                        <div className="rounded-xl border border-sidebar-border bg-sidebar-accent/30 p-4">
                            <div className="text-sm font-medium text-muted-foreground">
                                Author
                            </div>
                            <div className="mt-1 text-xl font-semibold text-foreground">
                                {documentation.manual.author}
                            </div>
                        </div>
                    </div>
                </div>
            );
        }

        if (activeSection === 'roles-access') {
            return (
                <div className="duration-300 animate-in fade-in">
                    <div className="mb-8">
                        <h1 className="text-3xl font-bold tracking-tight text-foreground">
                            Roles and Access
                        </h1>
                        <p className="mt-3 text-lg leading-relaxed text-muted-foreground">
                            Roles control which menus and workflows each user
                            can execute within the system.
                        </p>
                    </div>

                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                        {documentation.roleGuide.map((role) => (
                            <article
                                key={role.role}
                                className="rounded-xl border border-sidebar-border bg-card p-6 shadow-sm"
                            >
                                <div className="mb-4 flex items-center gap-3">
                                    <div className="rounded-lg bg-primary/10 p-2 text-primary">
                                        <Users className="h-5 w-5" />
                                    </div>
                                    <h3 className="text-xl font-semibold">
                                        {role.role}
                                    </h3>
                                </div>
                                <p className="mb-6 line-clamp-3 h-[80px] text-muted-foreground">
                                    {role.scope}
                                </p>
                                <h4 className="mb-3 text-sm font-medium tracking-wider text-muted-foreground uppercase">
                                    Primary Actions
                                </h4>
                                <ul className="space-y-2">
                                    {role.primary_actions.map((action, i) => (
                                        <li
                                            key={i}
                                            className="flex items-start gap-2 text-sm text-foreground"
                                        >
                                            <ChevronRight className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                                            <span>{action}</span>
                                        </li>
                                    ))}
                                </ul>
                            </article>
                        ))}
                    </div>
                </div>
            );
        }

        if (activeSection === 'core-workflows') {
            return (
                <div className="duration-300 animate-in fade-in">
                    <div className="mb-8">
                        <h1 className="text-3xl font-bold tracking-tight text-foreground">
                            Core Workflows
                        </h1>
                        <p className="mt-3 text-lg leading-relaxed text-muted-foreground">
                            End-to-end operational life cycles across multiple
                            modules.
                        </p>
                    </div>

                    <div className="space-y-8">
                        {documentation.coreWorkflows.map((workflow, idx) => (
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
                                    <ol className="relative ml-2 space-y-6 border-l border-muted/50 pl-5">
                                        {workflow.steps.map((step, stepIdx) => (
                                            <li
                                                key={stepIdx}
                                                className="relative"
                                            >
                                                <span className="absolute -left-[30px] flex h-6 w-6 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary ring-4 ring-card">
                                                    {stepIdx + 1}
                                                </span>
                                                <p className="pt-0.5 text-base text-foreground">
                                                    {step}
                                                </p>
                                            </li>
                                        ))}
                                    </ol>
                                </div>
                            </article>
                        ))}
                    </div>
                </div>
            );
        }

        if (activeSection === 'how-to-guides') {
            return (
                <div className="duration-300 animate-in fade-in">
                    <div className="mb-8">
                        <h1 className="text-3xl font-bold tracking-tight text-foreground">
                            How-To Guides
                        </h1>
                        <p className="mt-3 text-lg leading-relaxed text-muted-foreground">
                            Step-by-step instructions for specific operational
                            tasks.
                        </p>
                    </div>

                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        {documentation.howToGuides.map((guide, idx) => (
                            <article
                                key={idx}
                                className="rounded-xl border border-sidebar-border bg-card p-6 shadow-sm"
                            >
                                <h3 className="mb-4 flex items-center gap-2 text-lg font-semibold text-foreground">
                                    <FileText className="h-5 w-5 text-primary" />
                                    {guide.task}
                                </h3>
                                <ol className="space-y-3">
                                    {guide.steps.map((step, stepIdx) => (
                                        <li
                                            key={stepIdx}
                                            className="flex items-start gap-3 text-sm text-muted-foreground"
                                        >
                                            <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-sidebar-accent/50 text-xs font-medium">
                                                {stepIdx + 1}
                                            </span>
                                            <span className="pt-0.5 leading-relaxed text-foreground">
                                                {step}
                                            </span>
                                        </li>
                                    ))}
                                </ol>
                            </article>
                        ))}
                    </div>
                </div>
            );
        }

        if (activeSection === 'status-guidance') {
            return (
                <div className="duration-300 animate-in fade-in">
                    <div className="mb-8">
                        <h1 className="text-3xl font-bold tracking-tight text-foreground">
                            Status Guidance
                        </h1>
                        <p className="mt-3 text-lg leading-relaxed text-muted-foreground">
                            Learn what each status means and when to use it.
                        </p>
                    </div>

                    <div className="grid gap-6">
                        {documentation.commonStatuses.map((status, idx) => (
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
                                            <span className="leading-relaxed">
                                                {note}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </article>
                        ))}
                    </div>
                </div>
            );
        }

        if (activeSection === 'operational-tips') {
            return (
                <div className="duration-300 animate-in fade-in">
                    <div className="mb-8">
                        <h1 className="text-3xl font-bold tracking-tight text-foreground">
                            Operational Tips
                        </h1>
                        <p className="mt-3 text-lg leading-relaxed text-muted-foreground">
                            Best practices to keep your system data clean and
                            error-free.
                        </p>
                    </div>

                    <div className="rounded-xl border border-sidebar-border bg-card p-6 shadow-sm">
                        <ul className="space-y-4">
                            {documentation.tips.map((tip, idx) => (
                                <li
                                    key={idx}
                                    className="flex items-start gap-3 rounded-lg bg-yellow-500/10 p-4"
                                >
                                    <Lightbulb className="mt-0.5 h-5 w-5 shrink-0 text-yellow-600" />
                                    <span className="leading-relaxed text-foreground">
                                        {tip}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>
            );
        }

        // Handle dynamically matched modules
        if (activeSection.startsWith('module-')) {
            const moduleName = activeSection.replace('module-', '');
            const activeGroup = documentation.menuGroups.find(
                (g) => slugify(g.menu) === moduleName,
            );
            const playbook = documentation.modulePlaybooks.find(
                (p) =>
                    slugify(p.title) ===
                        activeGroup?.menu.toLowerCase() + '-module' ||
                    p.id === `${moduleName}-module`,
            );

            if (!activeGroup) {
                return (
                    <div className="flex h-40 items-center justify-center text-muted-foreground">
                        Module not found.
                    </div>
                );
            }

            return (
                <div className="duration-300 animate-in fade-in">
                    <div className="mb-8">
                        <div className="flex items-center gap-3">
                            <div className="rounded-xl bg-primary/10 p-3 text-primary">
                                <LayoutGrid className="h-6 w-6" />
                            </div>
                            <h1 className="text-3xl font-bold tracking-tight text-foreground">
                                {activeGroup.menu} Module
                            </h1>
                        </div>
                        <p className="mt-4 text-lg leading-relaxed text-muted-foreground">
                            {activeGroup.purpose}
                        </p>
                    </div>

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
                                        <span className="leading-relaxed">
                                            {feature}
                                        </span>
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
                                        <span className="leading-relaxed">
                                            {howto}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </article>
                    </div>

                    {playbook && (
                        <div className="mt-10 border-t border-sidebar-border pt-8">
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
                                            <ol className="space-y-4">
                                                {proc.steps.map(
                                                    (step, stepIdx) => (
                                                        <li
                                                            key={stepIdx}
                                                            className="flex items-start gap-3 text-sm text-foreground"
                                                        >
                                                            <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">
                                                                {stepIdx + 1}
                                                            </span>
                                                            <span className="pt-0.5 leading-relaxed">
                                                                {step}
                                                            </span>
                                                        </li>
                                                    ),
                                                )}
                                            </ol>
                                        </div>
                                    </article>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            );
        }

        return null;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Documentation" />

            <div className="flex h-[calc(100vh-theme(spacing.24))] w-full flex-col gap-4 overflow-hidden p-4 pb-0 md:flex-row md:p-0">
                {/* Left Sidebar Menu */}
                <div className="flex w-full shrink-0 flex-col overflow-hidden rounded-xl border border-sidebar-border bg-white md:w-72 lg:w-80 dark:bg-black/20">
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
                        {(!searchQuery ||
                            'getting started introduction roles workflows'.includes(
                                searchQuery.toLowerCase(),
                            )) && (
                            <div>
                                <h3 className="mb-2 px-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    Getting Started
                                </h3>
                                <div className="space-y-1">
                                    <button
                                        onClick={() => navigate('introduction')}
                                        className={`flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm transition-colors ${activeSection === 'introduction' ? 'bg-primary/10 font-medium text-primary' : 'text-foreground hover:bg-muted'}`}
                                    >
                                        <Info className="h-4 w-4" />{' '}
                                        Introduction
                                    </button>
                                    <button
                                        onClick={() => navigate('roles-access')}
                                        className={`flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm transition-colors ${activeSection === 'roles-access' ? 'bg-primary/10 font-medium text-primary' : 'text-foreground hover:bg-muted'}`}
                                    >
                                        <Users className="h-4 w-4" /> Roles &
                                        Access
                                    </button>
                                    <button
                                        onClick={() =>
                                            navigate('core-workflows')
                                        }
                                        className={`flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm transition-colors ${activeSection === 'core-workflows' ? 'bg-primary/10 font-medium text-primary' : 'text-foreground hover:bg-muted'}`}
                                    >
                                        <Activity className="h-4 w-4" /> Core
                                        Workflows
                                    </button>
                                </div>
                            </div>
                        )}

                        {/* Modules */}
                        {modules.length > 0 && (
                            <div>
                                <h3 className="mb-2 px-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    Modules
                                </h3>
                                <div className="space-y-1">
                                    {modules.map((group) => {
                                        const slug = `module-${slugify(group.menu)}`;
                                        return (
                                            <button
                                                key={slug}
                                                onClick={() => navigate(slug)}
                                                className={`flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm transition-colors ${activeSection === slug ? 'bg-primary/10 font-medium text-primary' : 'text-foreground hover:bg-muted'}`}
                                            >
                                                <LayoutGrid
                                                    className={`h-4 w-4 ${activeSection === slug ? 'text-primary' : 'text-muted-foreground'}`}
                                                />{' '}
                                                {group.menu}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        )}

                        {/* Guides */}
                        {(!searchQuery ||
                            'guides tutorials status tips'.includes(
                                searchQuery.toLowerCase(),
                            )) && (
                            <div>
                                <h3 className="mb-2 px-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    Reference Guides
                                </h3>
                                <div className="space-y-1">
                                    <button
                                        onClick={() =>
                                            navigate('how-to-guides')
                                        }
                                        className={`flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm transition-colors ${activeSection === 'how-to-guides' ? 'bg-primary/10 font-medium text-primary' : 'text-foreground hover:bg-muted'}`}
                                    >
                                        <FileText className="h-4 w-4" /> How-To
                                        Guides
                                    </button>
                                    <button
                                        onClick={() =>
                                            navigate('status-guidance')
                                        }
                                        className={`flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm transition-colors ${activeSection === 'status-guidance' ? 'bg-primary/10 font-medium text-primary' : 'text-foreground hover:bg-muted'}`}
                                    >
                                        <CheckCircle className="h-4 w-4" />{' '}
                                        Status Guidance
                                    </button>
                                    <button
                                        onClick={() =>
                                            navigate('operational-tips')
                                        }
                                        className={`flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm transition-colors ${activeSection === 'operational-tips' ? 'bg-primary/10 font-medium text-primary' : 'text-foreground hover:bg-muted'}`}
                                    >
                                        <Lightbulb className="h-4 w-4" />{' '}
                                        Operational Tips
                                    </button>
                                </div>
                            </div>
                        )}
                    </nav>
                </div>

                {/* Right Main Content Area */}
                <div className="mb-4 flex-1 overflow-hidden rounded-xl border border-sidebar-border bg-white shadow-sm md:mb-0 dark:bg-black/20">
                    <div className="custom-scrollbar h-full overflow-y-auto p-6 lg:p-10">
                        <div className="mx-auto max-w-4xl">
                            {renderContent()}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

interface ManualInfo {
    title: string;
    version: string;
    date: string;
    author: string;
}

interface BibliographyItem {
    id: string;
    title: string;
}

interface RoleGuideItem {
    role: string;
    scope: string;
    primary_actions: string[];
}

interface MenuStructureItem {
    menu: string;
    children: string[];
}

interface PlaybookProcedure {
    name: string;
    steps: string[];
}

interface ModulePlaybook {
    id: string;
    title: string;
    overview: string;
    highlights: string[];
    procedures: PlaybookProcedure[];
}

interface MenuGroup {
    menu: string;
    module: string;
    purpose: string;
    features: string[];
    how_to: string[];
}

interface Workflow {
    name: string;
    goal: string;
    steps: string[];
}

interface HowToGuide {
    task: string;
    steps: string[];
}

interface CommonStatus {
    topic: string;
    notes: string[];
}

interface DocumentationData {
    manual: ManualInfo;
    introduction: string;
    bibliography: BibliographyItem[];
    roleGuide: RoleGuideItem[];
    menuStructure: MenuStructureItem[];
    modulePlaybooks: ModulePlaybook[];
    menuGroups: MenuGroup[];
    coreWorkflows: Workflow[];
    howToGuides: HowToGuide[];
    commonStatuses: CommonStatus[];
    tips: string[];
}

interface DocumentationPageProps {
    documentation: DocumentationData;
}
