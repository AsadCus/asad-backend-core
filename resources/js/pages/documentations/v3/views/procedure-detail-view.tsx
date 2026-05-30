import {
    type DocumentationPageProps,
    type MenuGroup,
    type ModulePlaybook,
    type PlaybookContentBlock,
    type PlaybookStep,
} from '@/types/documentation';
import { Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, ChevronRight, Home } from 'lucide-react';
import { getModuleIcon, slugify } from '../lib/doc-utils';

function findPlaybook(
    documentation: DocumentationPageProps['documentation'],
    group: MenuGroup,
): ModulePlaybook | undefined {
    const menuSlug = slugify(group.menu.replace(/ Modules?$/i, ''));
    return documentation.modulePlaybooks.find((p) => {
        const playbookSlug = slugify(p.title.replace(/ Modules?$/i, ''));
        return (
            playbookSlug === menuSlug ||
            p.id === `${menuSlug}-module` ||
            p.id === menuSlug
        );
    });
}

function Breadcrumb({
    items,
}: {
    items: { label: string; onClick?: () => void }[];
}) {
    return (
        <nav className="flex flex-wrap items-center gap-1.5 text-sm text-muted-foreground">
            {items.map((item, i) => (
                <span key={item.label} className="flex items-center gap-1.5">
                    {i > 0 && <ChevronRight className="h-4 w-4 shrink-0" />}
                    {item.onClick ? (
                        <button
                            type="button"
                            onClick={item.onClick}
                            className="font-medium hover:text-primary dark:hover:text-primary-foreground"
                        >
                            {i === 0 ? (
                                <span className="flex items-center gap-1.5">
                                    <Home className="h-4 w-4" />
                                    <span>{item.label}</span>
                                </span>
                            ) : (
                                item.label
                            )}
                        </button>
                    ) : (
                        <span className="font-semibold text-foreground">
                            {item.label}
                        </span>
                    )}
                </span>
            ))}
        </nav>
    );
}

function resolveStepBlocks(
    step: string | PlaybookStep,
): PlaybookContentBlock[] {
    if (typeof step === 'string') {
        return [{ type: 'text', text: step }];
    }

    if (step.content_blocks && step.content_blocks.length > 0) {
        return step.content_blocks;
    }

    const blocks: PlaybookContentBlock[] = [];
    if (step.text) {
        blocks.push({ type: 'text', text: step.text });
    }
    if (step.screenshot) {
        blocks.push({
            type: 'image',
            src: step.screenshot,
            alt: step.text ? `Visual guide: ${step.text}` : 'Step visual guide',
        });
    }

    return blocks;
}

export function ProcedureDetailView({
    documentation,
    moduleGroup,
    procedureIndex,
    onBackToModule,
    onBackToHome,
}: {
    documentation: DocumentationPageProps['documentation'];
    moduleGroup: MenuGroup;
    procedureIndex: number;
    onBackToModule: () => void;
    onBackToHome: () => void;
}) {
    const Icon = getModuleIcon(moduleGroup.menu);
    const playbook = findPlaybook(documentation, moduleGroup);
    const moduleName = moduleGroup.menu.replace(/ Module$/i, '');
    const procedure = playbook?.procedures?.[procedureIndex];
    // Cross-module navigation
    const menuGroups = documentation.menuGroups;
    const currentModuleIndex = menuGroups.findIndex(
        (g) => g.menu === moduleGroup.menu,
    );

    let prevModuleGroup: MenuGroup | null = null;
    for (let i = currentModuleIndex - 1; i >= 0; i--) {
        const pb = findPlaybook(documentation, menuGroups[i]);
        if (pb && pb.procedures.length > 0) {
            prevModuleGroup = menuGroups[i];
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
    const canGoNextInModule = Boolean(
        playbook && procedureIndex < playbook.procedures.length - 1,
    );
    const prevPlaybook = prevModuleGroup
        ? findPlaybook(documentation, prevModuleGroup)
        : null;
    const nextPlaybook = nextModuleGroup
        ? findPlaybook(documentation, nextModuleGroup)
        : null;
    const previousProcedure = canGoPrevInModule
        ? playbook?.procedures?.[procedureIndex - 1]
        : (prevPlaybook?.procedures?.[prevPlaybook.procedures.length - 1] ??
          null);
    const nextProcedure = canGoNextInModule
        ? playbook?.procedures?.[procedureIndex + 1]
        : (nextPlaybook?.procedures?.[0] ?? null);

    const previousHref = canGoPrevInModule
        ? `/documentation/${slugify(moduleGroup.menu.replace(/ Modules?$/i, ''))}/${slugify(playbook!.procedures[procedureIndex - 1].name)}`
        : prevModuleGroup && previousProcedure
          ? `/documentation/${slugify(prevModuleGroup.menu.replace(/ Modules?$/i, ''))}/${slugify(previousProcedure.name)}`
          : null;

    const nextHref = canGoNextInModule
        ? `/documentation/${slugify(moduleGroup.menu.replace(/ Modules?$/i, ''))}/${slugify(playbook!.procedures[procedureIndex + 1].name)}`
        : nextModuleGroup && nextProcedure
          ? `/documentation/${slugify(nextModuleGroup.menu.replace(/ Modules?$/i, ''))}/${slugify(nextProcedure.name)}`
          : null;

    const previousLabel = previousProcedure?.name ?? 'No previous procedure';
    const nextLabel = nextProcedure?.name ?? 'No next procedure';

    if (!procedure) {
        return (
            <div className="mx-auto max-w-5xl px-8 py-8">
                <Breadcrumb
                    items={[
                        { label: 'Documentation', onClick: onBackToHome },
                        { label: moduleName, onClick: onBackToModule },
                        { label: 'Not Found' },
                    ]}
                />
                <div className="mt-8 rounded-2xl border border-dashed border-sidebar-border/70 p-12 text-center">
                    <p className="text-muted-foreground">
                        Procedure not found.
                    </p>
                    <button
                        type="button"
                        onClick={onBackToModule}
                        className="mt-4 text-sm text-orange-600 hover:underline"
                    >
                        ← Back to {moduleName}
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div className="resource-content-wrap mx-auto max-w-5xl px-8 py-8">
            {/* Breadcrumb */}
            <Breadcrumb
                items={[
                    { label: 'Documentation', onClick: onBackToHome },
                    { label: moduleName, onClick: onBackToModule },
                    { label: procedure.name },
                ]}
            />

            {/* Procedure Header */}
            <div className="mt-6 rounded-2xl border border-sidebar-border/70 bg-white p-6 shadow-sm dark:bg-slate-900/60">
                <div className="flex items-start gap-4">
                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/5 text-primary dark:bg-primary/80 dark:text-primary-foreground">
                        <Icon className="h-6 w-6" />
                    </div>
                    <div className="flex-1">
                        <div className="flex flex-wrap items-start justify-between gap-2">
                            <h1 className="text-2xl font-bold tracking-tight text-foreground">
                                {procedure.name}
                            </h1>
                            <span className="rounded-full bg-primary/5 px-3 py-1 text-xs font-semibold text-primary dark:bg-primary/40 dark:text-primary-foreground">
                                {procedure.steps.length} steps
                            </span>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {moduleName} — operational procedure
                        </p>
                    </div>
                </div>

                {playbook && (
                    <div className="mt-6 rounded-2xl border border-sidebar-border/70 bg-white p-4 shadow-sm dark:bg-slate-900/60">
                        <div className="flex flex-wrap items-center gap-2 border-b border-sidebar-border/60 pb-3">
                            <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                Step guide {procedureIndex + 1} of{' '}
                                {playbook.procedures.length}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                Navigate within this playbook or jump to the
                                adjacent module.
                            </span>
                        </div>

                        <div className="mt-3 flex items-center justify-between gap-2">
                            {previousHref ? (
                                <Link
                                    href={previousHref}
                                    preserveScroll={false}
                                    replace={false}
                                    className="group inline-flex items-center gap-1.5 rounded-lg border border-sidebar-border/70 bg-slate-50 px-3 py-1.5 text-xs font-medium text-muted-foreground hover:border-primary/20 hover:bg-primary/5 hover:text-primary dark:border-slate-800 dark:bg-slate-950/40 dark:hover:border-primary/60 dark:hover:text-primary-foreground"
                                >
                                    <ArrowLeft className="h-3.5 w-3.5" />
                                    <span className="max-w-[160px] truncate">
                                        {previousLabel}
                                    </span>
                                </Link>
                            ) : (
                                <span className="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-sidebar-border/70 px-3 py-1.5 text-xs text-muted-foreground/40 dark:border-slate-800">
                                    <ArrowLeft className="h-3.5 w-3.5" />
                                    No previous
                                </span>
                            )}

                            {nextHref ? (
                                <Link
                                    href={nextHref}
                                    preserveScroll={false}
                                    replace={false}
                                    className="group inline-flex items-center gap-1.5 rounded-lg border border-sidebar-border/70 bg-slate-50 px-3 py-1.5 text-xs font-medium text-muted-foreground hover:border-primary/20 hover:bg-primary/5 hover:text-primary dark:border-slate-800 dark:bg-slate-950/40 dark:hover:border-primary/60 dark:hover:text-primary-foreground"
                                >
                                    <span className="max-w-[160px] truncate">
                                        {nextLabel}
                                    </span>
                                    <ArrowRight className="h-3.5 w-3.5" />
                                </Link>
                            ) : (
                                <span className="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-sidebar-border/70 px-3 py-1.5 text-xs text-muted-foreground/40 dark:border-slate-800">
                                    No next
                                    <ArrowRight className="h-3.5 w-3.5" />
                                </span>
                            )}
                        </div>
                    </div>
                )}
            </div>

            {/* Purpose & Features */}
            {(procedure.purpose ||
                (procedure.features && procedure.features.length > 0)) && (
                <div className="mt-8 rounded-2xl border border-sidebar-border/70 bg-white p-6 shadow-sm dark:bg-slate-900/60">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-400">
                            <Icon className="h-5 w-5" />
                        </div>
                        <h2 className="text-lg font-semibold text-foreground">
                            Overview & Features
                        </h2>
                    </div>
                    {procedure.purpose && (
                        <div className="prose prose-sm dark:prose-invert prose-p:my-0 mt-4 max-w-none leading-7 text-muted-foreground">
                            <p>{procedure.purpose}</p>
                        </div>
                    )}
                    {procedure.features && procedure.features.length > 0 && (
                        <ul className="mt-5 space-y-2">
                            {procedure.features.map((feature, i) => (
                                <li key={i} className="flex items-start gap-3">
                                    <span className="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-bold text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-300">
                                        ✓
                                    </span>
                                    <span className="text-sm text-foreground">
                                        {feature}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}

            {/* Steps */}
            <div className="mt-8">
                <h2 className="text-lg font-semibold text-foreground">
                    Step-by-Step Instructions
                </h2>

                <div className="relative mt-5">
                    {/* Vertical line */}
                    <div className="absolute top-0 bottom-0 left-[1.125rem] w-px bg-primary/20 dark:bg-primary/60" />

                    <div className="space-y-4">
                        {procedure.steps.map((step, i) => {
                            // path is provided on some steps but not used in this view
                            const blocks = resolveStepBlocks(step);

                            // Helper for rendering step text with Note/Insight/Prerequisite styles
                            const renderStepText = (content: string) => {
                                const match = content.match(
                                    /^(Note|Insight|Prerequisite):\s*(.*)/i,
                                );
                                if (match) {
                                    const type = match[1].toLowerCase();
                                    const textBody = match[2];

                                    if (type === 'note') {
                                        return (
                                            <div className="tips-note mt-1 mb-2 rounded-lg border border-orange-200 bg-orange-50 p-3 text-sm text-orange-800 dark:border-orange-900/50 dark:bg-orange-950/30 dark:text-orange-300">
                                                <strong className="font-semibold">
                                                    Note:
                                                </strong>{' '}
                                                {textBody}
                                            </div>
                                        );
                                    } else if (type === 'insight') {
                                        return (
                                            <div className="tips-insight mt-1 mb-2 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800 dark:border-blue-900/50 dark:bg-blue-950/30 dark:text-blue-300">
                                                <strong className="font-semibold">
                                                    Insight:
                                                </strong>{' '}
                                                {textBody}
                                            </div>
                                        );
                                    } else if (type === 'prerequisite') {
                                        return (
                                            <div className="tips-prerequisites mt-1 mb-2 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-300">
                                                <strong className="font-semibold">
                                                    Prerequisite:
                                                </strong>{' '}
                                                {textBody}
                                            </div>
                                        );
                                    }
                                }
                                return (
                                    <p className="leading-7 text-foreground">
                                        {content}
                                    </p>
                                );
                            };

                            return (
                                <div
                                    key={i}
                                    className="relative flex items-start gap-4 pl-0"
                                >
                                    {/* Step number */}
                                    <div className="relative z-10 flex h-9 w-9 shrink-0 items-center justify-center rounded-full border-2 border-primary/20 bg-white text-sm font-bold text-primary dark:border-primary/60 dark:bg-slate-900 dark:text-primary-foreground">
                                        {i + 1}
                                    </div>

                                    {/* Step content */}
                                    <div className="flex-1 rounded-xl border border-sidebar-border/70 bg-white p-4 shadow-sm dark:bg-slate-900/60">
                                        <div className="space-y-3">
                                            {blocks.map((block, blockIndex) => {
                                                if (
                                                    block.type === 'image' &&
                                                    block.src
                                                ) {
                                                    return (
                                                        <figure
                                                            key={`image-${i}-${blockIndex}`}
                                                            className="mx-auto my-1 w-fit overflow-hidden rounded-lg border border-slate-200 bg-slate-50 shadow-sm dark:border-slate-700 dark:bg-slate-900/60"
                                                        >
                                                            <img
                                                                src={block.src}
                                                                alt={
                                                                    block.alt ??
                                                                    `Visual guide for step ${i + 1}`
                                                                }
                                                                className="block w-auto h-auto max-w-[720px] max-h-[420px] [max-width:min(720px,100%)]"
                                                                loading="lazy"
                                                            />
                                                        </figure>
                                                    );
                                                }

                                                if (
                                                    block.type === 'gif' &&
                                                    block.src
                                                ) {
                                                    return (
                                                        <figure
                                                            key={`gif-${i}-${blockIndex}`}
                                                            className="mx-auto my-1 w-fit overflow-hidden rounded-lg border border-indigo-200 bg-indigo-50/40 shadow-sm dark:border-indigo-900/50 dark:bg-indigo-950/20"
                                                        >
                                                            <div className="flex items-center gap-1.5 border-b border-indigo-200 px-3 py-1.5 dark:border-indigo-900/50">
                                                                <span className="rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-bold tracking-widest text-indigo-600 uppercase dark:bg-indigo-900/60 dark:text-indigo-300">
                                                                    GIF
                                                                </span>
                                                                <span className="text-xs text-muted-foreground">
                                                                    {block.alt ??
                                                                        'Demo'}
                                                                </span>
                                                            </div>
                                                            <img
                                                                src={block.src}
                                                                alt={
                                                                    block.alt ??
                                                                    `Demo for step ${i + 1}`
                                                                }
                                                                className="block w-auto h-auto max-w-[720px] max-h-[420px] [max-width:min(720px,100%)]"
                                                            />
                                                        </figure>
                                                    );
                                                }

                                                if (
                                                    block.type === 'text' &&
                                                    block.text
                                                ) {
                                                    return (
                                                        <div
                                                            key={`text-${i}-${blockIndex}`}
                                                            className="prose prose-sm dark:prose-invert prose-p:my-2 prose-p:leading-7 max-w-none text-[15px]"
                                                        >
                                                            {renderStepText(
                                                                block.text,
                                                            )}
                                                        </div>
                                                    );
                                                }

                                                return null;
                                            })}
                                        </div>
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
                    className="inline-flex items-center gap-2 rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium text-muted-foreground shadow-sm hover:bg-slate-50 hover:text-foreground dark:hover:bg-slate-900"
                >
                    ← Back to {moduleName}
                </button>
                <button
                    type="button"
                    onClick={() => {
                        const contentArea =
                            document.getElementById('doc-content-area');
                        if (contentArea) {
                            contentArea.scrollTo({
                                top: 0,
                                behavior: 'smooth',
                            });
                        } else {
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                        }
                    }}
                    className="rounded-lg px-4 py-2 text-sm font-medium text-orange-600 hover:bg-orange-50 dark:text-orange-400 dark:hover:bg-orange-950/30"
                >
                    Back to top
                </button>
            </div>
        </div>
    );
}
