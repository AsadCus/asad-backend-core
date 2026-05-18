import { AppHeader } from '@/components/app-header';
import { AppShell } from '@/components/app-shell';
import { Toaster } from '@/components/ui/sonner';
import { type BreadcrumbItem, SharedData } from '@/types';
import { type DocumentationPageProps } from '@/types/documentation';
import { Head, usePage } from '@inertiajs/react';
import { useEffect, type ReactNode } from 'react';
import { toast } from 'sonner';
import { HomeView } from './views/home-view';
import { ModuleDetailView } from './views/module-detail-view';
import { ProcedureDetailView } from './views/procedure-detail-view';
import { useDocNavigation } from './hooks/use-doc-navigation';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Documentation', href: 'support/documentation' },
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

export default function DocumentationV3Index({ documentation }: DocumentationPageProps) {
    const nav = useDocNavigation(documentation);

    return (
        <DocLayout>
            <Head title={documentation.manual.title} />
            <div className="min-h-[calc(100vh-4rem)]">
                {nav.view === 'home' && (
                    <HomeView
                        documentation={documentation}
                        searchQuery={nav.searchQuery}
                        onSearchChange={nav.setSearchQuery}
                        onModuleClick={nav.goToModule}
                    />
                )}
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
        </DocLayout>
    );
}
