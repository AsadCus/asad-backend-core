import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { index } from '@/routes/general-enquiries';
import { OptionType, type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useCallback } from 'react';
import EnquiryRemarksTimeline from '../enquiries/components/enquiry-remarks-timeline';
import GeneralEnquiryForm, { GeneralEnquiryFormSchema } from './form';

interface EditGeneralEnquiryProps {
    data: GeneralEnquiryFormSchema;
    packageOptions: OptionType[];
    branchOptions?: OptionType[];
    countryOptions?: OptionType[];
    scopeMode?: 'country' | 'branch';
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'General Enquiries',
        href: index().url,
    },
];

export default function EditGeneralEnquiry({
    data,
    packageOptions = [],
    branchOptions = [],
    countryOptions = [],
    scopeMode = 'country',
}: EditGeneralEnquiryProps) {
    const handleCancel = useCallback(() => {
        window.history.back();
    }, []);

    const enquiryId = data.enquiry_id ?? undefined;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit General Enquiry" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">
                        General Enquiry - Edit
                    </h2>
                </div>

                <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                    <GeneralEnquiryForm
                        mode="edit"
                        initialData={data}
                        packageOptions={packageOptions}
                        branchOptions={branchOptions}
                        countryOptions={countryOptions}
                        scopeMode={scopeMode}
                        onCancel={handleCancel}
                    />
                </div>

                {enquiryId && (
                    <Card>
                        <CardHeader className="gap-0">
                            <CardTitle className="text-xl">
                                Enquiry Remarks
                            </CardTitle>
                            <CardDescription>
                                View and manage remarks for this enquiry.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <EnquiryRemarksTimeline
                                isOpen={true}
                                enquiryId={enquiryId}
                            />
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
