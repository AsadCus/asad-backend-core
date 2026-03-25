import AppLayout from '@/layouts/app-layout';
import { index as masterIndex } from '@/routes/master';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useCallback } from 'react';
import { CountryForm } from './form';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Master',
        href: masterIndex().url,
    },
    {
        title: 'Country',
        href: '/master/country',
    },
];

export default function CreateCountry() {
    const handleCancel = useCallback(() => {
        window.history.back();
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Country" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">Country - Create</h2>
                </div>
                <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                    <CountryForm mode="create" onCancel={handleCancel} />
                </div>
            </div>
        </AppLayout>
    );
}
