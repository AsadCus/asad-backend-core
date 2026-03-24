import AppLayout from '@/layouts/app-layout';
import { index as masterIndex } from '@/routes/master';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useCallback } from 'react';
import { CountryForm } from './form';
import { CountrySchema } from './schema';

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

interface ViewCountryProps {
    data: CountrySchema;
}

export default function ViewCountry({ data }: ViewCountryProps) {
    const handleCancel = useCallback(() => {
        window.history.back();
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Country" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">Country - View</h2>
                </div>
                <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                    <CountryForm
                        mode="view"
                        initialData={data}
                        onCancel={handleCancel}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
