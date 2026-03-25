import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { index } from '@/routes/packages';
import { OptionType, type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { useCallback, useState } from 'react';
import PackageForm from './form';
import PackagePreviewModal from './package-preview-modal';
import { type PackageSchema } from './schema';

interface ShowPackageProps {
    data: PackageSchema;
    dataCountry: OptionType[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Packages',
        href: index().url,
    },
];

export default function ShowPackage({ data, dataCountry }: ShowPackageProps) {
    const handleCancel = useCallback(() => {
        window.history.back();
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="View Package" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">Package - View</h2>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setPreviewModalOpen(true)}
                        className="gap-2"
                    >
                        <Eye className="h-4 w-4" />
                        Preview Report
                    </Button>
                </div>

                <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                    <PackageForm
                        mode="view"
                        initialData={data}
                        countries={dataCountry}
                        onCancel={handleCancel}
                    />
                </div>
            </div>

            <PackagePreviewModal
                data={{ id: data.id, package_number: data.package_number }}
                open={previewModalOpen}
                onOpenChange={setPreviewModalOpen}
            />
        </AppLayout>
    );
}
