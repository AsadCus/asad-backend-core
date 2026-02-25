import AppLayout from '@/layouts/app-layout';
import { index } from '@/routes/manifests';
import { type BreadcrumbItem, type ValueNumberOptionType } from '@/types';
import { Head } from '@inertiajs/react';
import { useCallback } from 'react';
import ManifestForm, { type ManifestFormData } from './form';
import { type TravelerSchema } from './schema';

interface CustomerGroupData {
    id: number;
    package_room_type: string;
    enquiry_id: number;
    enquiry_type: string;
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

interface CreateManifestProps {
    dataPackage: ValueNumberOptionType[];
    customerGroups?: CustomerGroupData[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Manifests',
        href: index().url,
    },
];




// Default room data for Makkah
const DEFAULT_ROOM_DATA_MAKKAH = {
  
};

// Default room data for Madinah
const DEFAULT_ROOM_DATA_MADINAH = {
    
};

// Default room data for Others (Transit & Airlines)
const DEFAULT_ROOM_DATA_OTHERS = {
   
};

const DEFAULT_MANIFEST_TRAVELERS: Record<number, TravelerSchema[]> = {
   
};

const DEFAULT_AIRLINE_LIST = {
   
};


const MANIFEST_DATA = {
    package_id: 0,
    reference_number: '',
    status: '',
    company_address: '',
    company_phone: '',
    departure_date: '',
    return_date: '',
    duration: '',
    makkah_hotel: '',
    makkah_check_in: '',
    makkah_check_out: '',
    madinah_hotel: '',
    madinah_check_in: '',
    madinah_check_out: '',
    groupIds:[],
    first_meal: '',
    last_meal: '',
    notes: '',
    travelers: DEFAULT_MANIFEST_TRAVELERS,
    roomListMakkah: DEFAULT_ROOM_DATA_MAKKAH,
    roomListMadinah: DEFAULT_ROOM_DATA_MADINAH,
    roomListOthers: DEFAULT_ROOM_DATA_OTHERS,
    airlineList: DEFAULT_AIRLINE_LIST,
} as ManifestFormData;



export default function CreateManifest({
    dataPackage,
    customerGroups = [],
}: CreateManifestProps) {
    const handleCancel = useCallback(() => {
        window.history.back();
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Manifest" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">Manifest - Create</h2>
                </div>

                <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                    <ManifestForm
                        mode="create"
                        initialData={MANIFEST_DATA}
                        dataPackage={dataPackage}
                        customerGroups={customerGroups}
                        onCancel={handleCancel}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
