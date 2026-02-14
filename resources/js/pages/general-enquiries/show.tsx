import AppLayout from '@/layouts/app-layout';
import { index } from '@/routes/general-enquiries';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useCallback } from 'react';
import GeneralEnquiryForm, { GeneralEnquiryFormSchema } from './form';

interface ShowGeneralEnquiryProps {
    data: GeneralEnquiryFormSchema;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'General Enquiries',
        href: index().url,
    },
];

export default function ShowGeneralEnquiry({ data }: ShowGeneralEnquiryProps) {
    const handleCancel = useCallback(() => {
        window.history.back();
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="View General Enquiry" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">
                        General Enquiry - View
                    </h2>
                </div>

                <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                    <GeneralEnquiryForm
                        mode="view"
                        initialData={data}
                        onCancel={handleCancel}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
