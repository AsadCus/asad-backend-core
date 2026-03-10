import AppLayout from '@/layouts/app-layout';
import { index } from '@/routes/manifests';
import { type BreadcrumbItem, type ValueNumberOptionType } from '@/types';
import { Head } from '@inertiajs/react';
import { useCallback } from 'react';
import ManifestForm, { type ManifestFormData } from './form';

interface ShowManifestProps {
    data: ManifestFormData;
    dataPackage: ValueNumberOptionType[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Manifests',
        href: index().url,
    },
];

export default function ShowManifest({ data, dataPackage }: ShowManifestProps) {
    const handleCancel = useCallback(() => {
        window.history.back();
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="View Manifest" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">Manifest - View</h2>
                </div>

                <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                    <ManifestForm
                        mode="view"
                        initialData={data}
                        dataPackage={dataPackage}
                        onCancel={handleCancel}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
