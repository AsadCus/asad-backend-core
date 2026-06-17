import {
    type DocumentationData,
    type MenuGroup,
    type ModulePlaybook,
} from '@/types/documentation';
import { router } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';
import { slugify } from '../lib/doc-utils';

export type DocView = 'home' | 'module' | 'procedure';

/**
 * Find the matching ModulePlaybook for a given MenuGroup.
 */
function findPlaybook(
    documentation: DocumentationData,
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

export function useDocNavigation(
    documentation: DocumentationData,
    moduleSlug?: string | null,
    procedureSlug?: string | null,
) {
    const [searchQuery, setSearchQuery] = useState('');

    // Derive view from URL slugs
    const view: DocView = useMemo(() => {
        if (moduleSlug && procedureSlug) return 'procedure';
        if (moduleSlug) return 'module';
        return 'home';
    }, [moduleSlug, procedureSlug]);

    // Resolve selected module from slug
    const selectedModule: MenuGroup | null = useMemo(() => {
        if (!moduleSlug) return null;
        return (
            documentation.menuGroups.find((g) => {
                const gSlug = slugify(g.menu.replace(/ Modules?$/i, ''));
                return gSlug === moduleSlug;
            }) ?? null
        );
    }, [moduleSlug, documentation.menuGroups]);

    // Resolve selected procedure index from slug
    const selectedProcedure: number | null = useMemo(() => {
        if (!selectedModule || !procedureSlug) return null;
        const playbook = findPlaybook(documentation, selectedModule);
        if (!playbook) return null;
        const idx = playbook.procedures.findIndex(
            (p) => slugify(p.name) === procedureSlug,
        );
        return idx >= 0 ? idx : null;
    }, [selectedModule, procedureSlug, documentation]);

    const goHome = useCallback(() => {
        router.visit('/documentation', {
            preserveState: true,
            preserveScroll: false,
            replace: false,
        });
    }, []);

    const goToModule = useCallback((group: MenuGroup) => {
        const slug = slugify(group.menu.replace(/ Modules?$/i, ''));
        router.visit(`/documentation/${slug}`, {
            preserveState: true,
            preserveScroll: false,
            replace: false,
        });
    }, []);

    const goToProcedure = useCallback(
        (index: number) => {
            if (!selectedModule) return;
            const mSlug = slugify(
                selectedModule.menu.replace(/ Modules?$/i, ''),
            );
            const playbook = findPlaybook(documentation, selectedModule);
            const proc = playbook?.procedures?.[index];
            if (!proc) return;
            const pSlug = slugify(proc.name);
            router.visit(`/documentation/${mSlug}/${pSlug}`, {
                preserveState: true,
                preserveScroll: false,
                replace: false,
            });
        },
        [selectedModule, documentation],
    );

    const goToModuleProcedure = useCallback(
        (group: MenuGroup, index: number) => {
            const mSlug = slugify(group.menu.replace(/ Modules?$/i, ''));
            const playbook = findPlaybook(documentation, group);
            const proc = playbook?.procedures?.[index];
            if (!proc) return;
            const pSlug = slugify(proc.name);
            router.visit(`/documentation/${mSlug}/${pSlug}`, {
                preserveState: true,
                preserveScroll: false,
                replace: false,
            });
        },
        [documentation],
    );

    return {
        view,
        selectedModule,
        selectedProcedure,
        searchQuery,
        setSearchQuery,
        goHome,
        goToModule,
        goToProcedure,
        goToModuleProcedure,
    };
}
