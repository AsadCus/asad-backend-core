import AppLayout from '@/layouts/app-layout';
import { index } from '@/routes/maid';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useCallback } from 'react';
import { MaidForm, MaidSchema } from './refactor-form';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Maid',
        href: index().url,
    },
];

interface EditMaidProps {
    data: MaidSchema;
    dataNationality: [];
    dataReligion: [];
    dataEducationLevel: [];
    dataSupplier: [];
}

export default function EditMaid({
    data,
    dataNationality,
    dataReligion,
    dataEducationLevel,
    dataSupplier,
}: EditMaidProps) {
    const handleCancel = useCallback(() => {
        window.history.back();
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Maid" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">Maid - Edit</h2>
                </div>
                <div className="relative min-h-screen flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 md:min-h-min dark:border-sidebar-border">
                    <MaidForm
                        mode="edit"
                        initialData={data}
                        nationalities={dataNationality}
                        religions={dataReligion}
                        educationLevels={dataEducationLevel}
                        suppliers={dataSupplier}
                        onCancel={handleCancel}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
