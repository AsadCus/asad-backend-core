import AppLayout from '@/layouts/app-layout';
import { index } from '@/routes/packages';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useCallback } from 'react';
import PackageForm from './form';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Packages',
        href: index().url,
    },
];

export default function CreatePackage() {
    const handleCancel = useCallback(() => {
        window.history.back();
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Package" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">Package - Create</h2>
                </div>

                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 md:min-h-min dark:border-sidebar-border">
                    <PackageForm mode="create" onCancel={handleCancel} />
                </div>
            </div>
        </AppLayout>
    );
}
