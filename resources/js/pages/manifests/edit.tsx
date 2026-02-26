import AppLayout from '@/layouts/app-layout';
import { index } from '@/routes/manifests';
import { type BreadcrumbItem, type ValueNumberOptionType } from '@/types';
import { Head } from '@inertiajs/react';
import { useCallback } from 'react';
import ManifestForm from './form';
import { type ManifestSchema } from './schema';

interface CustomerGroupData {
    id: number;
    enquiry_id: number;
    enquiry_type: string;
    package_room_type: string;
    enquiry_status: string;
    leader_name: string;
    leader_email: string;
    leader_contact: string;
    leader_customer_number: string;
    member_count: number;
    created_at: string;
    members: CustomerMemberData[];
}

interface CustomerMemberData {
    id: number;
    customer_id: number;
    is_leader: boolean;
    name: string;
    email: string;
    contact: string;
    customer_number: string;
    nric_number: string;
    passport_number: string;
    passport_issue_date: string;
    passport_expiry_date: string;
    passport_place_of_issue: string;
    date_of_birth: string;
    age: number;
}

interface EditManifestProps {
    data: ManifestSchema;
    dataPackage: ValueNumberOptionType[];
    customerGroups?: CustomerGroupData[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Manifests',
        href: index().url,
    },
];

export default function EditManifest({
    data,
    dataPackage,
    customerGroups = [],
}: EditManifestProps) {
    const handleCancel = useCallback(() => {
        window.history.back();
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit Manifest" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">Manifest - Edit</h2>
                </div>

                <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                    <ManifestForm
                        mode="edit"
                        initialData={data}
                        dataPackage={dataPackage}
                        customerGroups={customerGroups}
                        onCancel={handleCancel}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
