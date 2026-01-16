import AppLayout from '@/layouts/app-layout';
import { index } from '@/routes/maid';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useCallback } from 'react';
import { MaidForm } from './form';
import { MaidSchema } from './schema';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Maid',
        href: index().url,
    },
];

interface CreateMaidProps {
    dataNationality: [];
    dataReligion: [];
    dataEducationLevel: [];
    dataSupplier: [];
    prefilledSupplierId?: number;
}

export default function CreateMaid({
    dataNationality,
    dataReligion,
    dataEducationLevel,
    dataSupplier,
    prefilledSupplierId,
}: CreateMaidProps) {
    const handleCancel = useCallback(() => {
        window.history.back();
    }, []);

    const initialData = prefilledSupplierId
        ? ({ supplier_id: String(prefilledSupplierId) } as MaidSchema)
        : undefined;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Maid" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">Maid - Create</h2>
                </div>
                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 md:min-h-min dark:border-sidebar-border">
                    <MaidForm
                        mode="create"
                        nationalities={dataNationality}
                        religions={dataReligion}
                        educationLevels={dataEducationLevel}
                        suppliers={dataSupplier}
                        onCancel={handleCancel}
                        initialData={initialData}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
