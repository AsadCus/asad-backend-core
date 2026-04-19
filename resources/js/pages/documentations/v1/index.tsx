import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

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
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Documentation" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="rounded-xl border border-sidebar-border/70 bg-white p-4 dark:border-sidebar-border dark:bg-black/20">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {documentation.manual.title}
                    </h1>
                    <p className="mt-2 text-base leading-6 text-muted-foreground">
                        {documentation.introduction}
                    </p>

                    <div className="mt-4 grid grid-cols-1 gap-2 text-base text-muted-foreground md:grid-cols-3">
                        <div>
                            <span className="font-medium text-foreground">
                                Version:
                            </span>{' '}
                            {documentation.manual.version}
                        </div>
                        <div>
                            <span className="font-medium text-foreground">
                                Date:
                            </span>{' '}
                            {documentation.manual.date}
                        </div>
                        <div>
                            <span className="font-medium text-foreground">
                                By:
                            </span>{' '}
                            {documentation.manual.author}
                        </div>
                    </div>
                </div>

                <section className="rounded-xl border border-sidebar-border/70 bg-white p-4 dark:border-sidebar-border dark:bg-black/20">
                    <h2 className="text-xl font-semibold">Bibliography</h2>
                    <p className="mt-1 text-base text-muted-foreground">
                        Quick navigation. Click any topic to jump to that
                        section.
                    </p>

                    <div className="mt-4 grid grid-cols-1 gap-2 md:grid-cols-2">
                        {documentation.bibliography.map((item) => (
                            <a
                                key={item.id}
                                href={`#${item.id}`}
                                className="rounded-md border border-sidebar-border/70 px-3 py-2 text-base text-primary hover:bg-muted"
                            >
                                {item.title}
                            </a>
                        ))}
                    </div>
                </section>

                <section
                    id="roles-access"
                    className="rounded-xl border border-sidebar-border/70 bg-white p-4 dark:border-sidebar-border dark:bg-black/20"
                >
                    <h2 className="text-xl font-semibold">Roles and Access</h2>
                    <p className="mt-1 text-base text-muted-foreground">
                        Roles control which menus and workflows each user can
                        execute.
                    </p>

                    <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                        {documentation.roleGuide.map((role) => (
                            <article
                                key={role.role}
                                className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                            >
                                <h3 className="text-lg font-semibold">
                                    {role.role}
                                </h3>
                                <p className="mt-2 text-base text-muted-foreground">
                                    {role.scope}
                                </p>
                                <ul className="mt-3 list-disc space-y-1 pl-5 text-base text-muted-foreground">
                                    {role.primary_actions.map((action) => (
                                        <li key={action}>{action}</li>
                                    ))}
                                </ul>
                            </article>
                        ))}
                    </div>
                </section>

                <section
                    id="menu-structure"
                    className="rounded-xl border border-sidebar-border/70 bg-white p-4 dark:border-sidebar-border dark:bg-black/20"
                >
                    <h2 className="text-xl font-semibold">Menu Structure</h2>
                    <p className="mt-1 text-base text-muted-foreground">
                        Summary of major menus and submodules available in this
                        system.
                    </p>

                    <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                        {documentation.menuStructure.map((group) => (
                            <article
                                key={group.menu}
                                className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                            >
                                <h3 className="text-lg font-semibold">
                                    {group.menu}
                                </h3>
                                <ul className="mt-3 list-disc space-y-1 pl-5 text-base text-muted-foreground">
                                    {group.children.map((child) => (
                                        <li key={child}>{child}</li>
                                    ))}
                                </ul>
                            </article>
                        ))}
                    </div>
                </section>

                <section className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    {documentation.modulePlaybooks.map((playbook) => (
                        <article
                            key={playbook.id}
                            id={playbook.id}
                            className="rounded-xl border border-sidebar-border/70 bg-white p-4 dark:border-sidebar-border dark:bg-black/20"
                        >
                            <h2 className="text-xl font-semibold">
                                {playbook.title}
                            </h2>
                            <p className="mt-2 text-base leading-6 text-muted-foreground">
                                {playbook.overview}
                            </p>

                            <h3 className="mt-4 text-base font-semibold">
                                Key Focus
                            </h3>
                            <ul className="mt-2 list-disc space-y-1 pl-5 text-base text-muted-foreground">
                                {playbook.highlights.map((highlight) => (
                                    <li key={highlight}>{highlight}</li>
                                ))}
                            </ul>

                            <h3 className="mt-4 text-base font-semibold">
                                Procedures
                            </h3>
                            <div className="mt-2 grid grid-cols-1 gap-3">
                                {playbook.procedures.map((procedure) => (
                                    <div
                                        key={procedure.name}
                                        className="rounded-md border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                                    >
                                        <h4 className="text-base font-medium">
                                            {procedure.name}
                                        </h4>
                                        <ol className="mt-2 list-decimal space-y-1 pl-5 text-base text-muted-foreground">
                                            {procedure.steps.map((step) => (
                                                <li key={step}>{step}</li>
                                            ))}
                                        </ol>
                                    </div>
                                ))}
                            </div>
                        </article>
                    ))}
                </section>

                <section
                    id="menus-modules"
                    className="rounded-xl border border-sidebar-border/70 bg-white p-4 dark:border-sidebar-border dark:bg-black/20"
                >
                    <h2 className="text-xl font-semibold">Menus and Modules</h2>
                    <p className="mt-1 text-base text-muted-foreground">
                        Use this section to understand what each menu is for,
                        what features it contains, and how to operate it.
                    </p>

                    <div className="mt-4 grid grid-cols-1 gap-3">
                        {documentation.menuGroups.map((group) => (
                            <article
                                key={group.menu}
                                className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                            >
                                <h3 className="text-lg font-semibold">
                                    {group.menu}
                                </h3>
                                <p className="mt-1 text-base text-muted-foreground">
                                    Module: {group.module}
                                </p>
                                <p className="mt-2 text-base leading-6">
                                    {group.purpose}
                                </p>

                                <div className="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <div>
                                        <h4 className="text-base font-medium">
                                            Key Features
                                        </h4>
                                        <ul className="mt-2 list-disc space-y-1 pl-5 text-base text-muted-foreground">
                                            {group.features.map((feature) => (
                                                <li key={feature}>{feature}</li>
                                            ))}
                                        </ul>
                                    </div>

                                    <div>
                                        <h4 className="text-base font-medium">
                                            How To
                                        </h4>
                                        <ul className="mt-2 list-disc space-y-1 pl-5 text-base text-muted-foreground">
                                            {group.how_to.map((item) => (
                                                <li key={item}>{item}</li>
                                            ))}
                                        </ul>
                                    </div>
                                </div>
                            </article>
                        ))}
                    </div>
                </section>

                <section
                    id="core-workflows"
                    className="rounded-xl border border-sidebar-border/70 bg-white p-4 dark:border-sidebar-border dark:bg-black/20"
                >
                    <h2 className="text-xl font-semibold">Core Workflows</h2>
                    <p className="mt-1 text-base text-muted-foreground">
                        These are the main end-to-end flows used daily in this
                        system.
                    </p>

                    <div className="mt-4 grid grid-cols-1 gap-3">
                        {documentation.coreWorkflows.map((workflow) => (
                            <article
                                key={workflow.name}
                                className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                            >
                                <h3 className="text-lg font-semibold">
                                    {workflow.name}
                                </h3>
                                <p className="mt-1 text-base text-muted-foreground">
                                    Goal: {workflow.goal}
                                </p>

                                <ol className="mt-3 list-decimal space-y-1 pl-5 text-base leading-6">
                                    {workflow.steps.map((step) => (
                                        <li key={step}>{step}</li>
                                    ))}
                                </ol>
                            </article>
                        ))}
                    </div>
                </section>

                <section
                    id="how-to-guides"
                    className="rounded-xl border border-sidebar-border/70 bg-white p-4 dark:border-sidebar-border dark:bg-black/20"
                >
                    <h2 className="text-xl font-semibold">How-To Guides</h2>
                    <p className="mt-1 text-base text-muted-foreground">
                        Practical task-focused guides for common activities.
                    </p>

                    <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                        {documentation.howToGuides.map((guide) => (
                            <article
                                key={guide.task}
                                className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                            >
                                <h3 className="text-lg font-semibold">
                                    {guide.task}
                                </h3>
                                <ol className="mt-3 list-decimal space-y-1 pl-5 text-base leading-6 text-muted-foreground">
                                    {guide.steps.map((step) => (
                                        <li key={step}>{step}</li>
                                    ))}
                                </ol>
                            </article>
                        ))}
                    </div>
                </section>

                <section className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div
                        id="status-guidance"
                        className="rounded-xl border border-sidebar-border/70 bg-white p-4 dark:border-sidebar-border dark:bg-black/20"
                    >
                        <h2 className="text-xl font-semibold">
                            Status Guidance
                        </h2>
                        <div className="mt-4 grid grid-cols-1 gap-3">
                            {documentation.commonStatuses.map((status) => (
                                <article
                                    key={status.topic}
                                    className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                                >
                                    <h3 className="text-lg font-semibold">
                                        {status.topic}
                                    </h3>
                                    <ul className="mt-2 list-disc space-y-1 pl-5 text-base text-muted-foreground">
                                        {status.notes.map((note) => (
                                            <li key={note}>{note}</li>
                                        ))}
                                    </ul>
                                </article>
                            ))}
                        </div>
                    </div>

                    <div
                        id="operational-tips"
                        className="rounded-xl border border-sidebar-border/70 bg-white p-4 dark:border-sidebar-border dark:bg-black/20"
                    >
                        <h2 className="text-xl font-semibold">
                            Operational Tips
                        </h2>
                        <ul className="mt-4 list-disc space-y-1 pl-5 text-base leading-6 text-muted-foreground">
                            {documentation.tips.map((tip) => (
                                <li key={tip}>{tip}</li>
                            ))}
                        </ul>
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}