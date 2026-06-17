import AppLayout from '@/layouts/app-layout';
import { index } from '@/routes/package-proposals';
import { OptionType, type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useCallback } from 'react';
import ProposalForm from './form';
import { type PackageProposalSchema } from './schema';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Package PnL',
        href: index().url,
    },
];

interface ApproverOption {
    id: number;
    name: string;
    email: string;
}

interface EditProposalProps {
    data: PackageProposalSchema;
    dataCountry: OptionType[];
    assignableCountryIds: number[];
    approverOptions: ApproverOption[];
    countryCurrencyMap: Record<string | number, string>;
}

export default function EditProposal({
    data,
    dataCountry,
    assignableCountryIds,
    approverOptions,
    countryCurrencyMap,
}: EditProposalProps) {
    const handleCancel = useCallback(() => {
        window.history.back();
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit Package Proposal" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <ProposalForm
                    mode="edit"
                    initialData={data}
                    countries={dataCountry}
                    assignableCountryIds={assignableCountryIds}
                    countryCurrencyMap={countryCurrencyMap}
                    approverOptions={approverOptions}
                    onCancel={handleCancel}
                />
            </div>
        </AppLayout>
    );
}
