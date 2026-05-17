import { type DocumentationPageProps, type MenuGroup } from '@/types/documentation';
import { useState, useCallback } from 'react';

export type DocView = 'home' | 'module' | 'procedure';

export function useDocNavigation(documentation: DocumentationPageProps['documentation']) {
    const [view, setView] = useState<DocView>('home');
    const [selectedModule, setSelectedModule] = useState<MenuGroup | null>(null);
    const [selectedProcedure, setSelectedProcedure] = useState<number | null>(null);
    const [searchQuery, setSearchQuery] = useState('');

    const goHome = useCallback(() => {
        setView('home');
        setSelectedModule(null);
        setSelectedProcedure(null);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }, []);

    const goToModule = useCallback((group: MenuGroup) => {
        setView('module');
        setSelectedModule(group);
        setSelectedProcedure(null);
        setSearchQuery('');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }, []);

    const goToProcedure = useCallback((index: number) => {
        setView('procedure');
        setSelectedProcedure(index);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }, []);

    return {
        view,
        selectedModule,
        selectedProcedure,
        searchQuery,
        setSearchQuery,
        goHome,
        goToModule,
        goToProcedure,
    };
}
