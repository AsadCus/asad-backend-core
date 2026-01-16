import { ActionType } from '@/components/action-column';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { index, recommendMaidSubmit } from '@/routes/customer';
import { show } from '@/routes/maid';
import { type BreadcrumbItem, SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import { toast } from 'sonner';
import { MaidCardList } from '../maid/card-list';
import { MaidSchema } from '../maid/schema';
import { UserForm } from '../masters/users/form';
import { UserSchema } from '../masters/users/schema';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Customer',
        href: index().url,
    },
];

interface RecommendMaidFormProps {
    data: {
        user: UserSchema;
        selectedMaidIds?: number[];
        maids?: MaidSchema[];
        nationality: [];
        religion: [];
        educationLevel: [];
        roles: [];
        branches: [];
        countries: [];
        sales: [];
    };
}

export default function RecommendMaidForm({ data }: RecommendMaidFormProps) {
    const { auth } = usePage<SharedData>().props;
    const userPermissions = auth.permissions || [];
    const userId = data.user.id;

    const actions: ActionType[] = [];
    if (userPermissions.includes('maid view')) actions.push('view', 'preview');
    if (userPermissions.includes('quotation create'))
        actions.push('quotation-create');

    const [selectedMaids, setSelectedMaids] = useState<number[]>(
        data.selectedMaidIds || [],
    );
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSelect = (maidId: number) => {
        setSelectedMaids((prev) =>
            prev.includes(maidId)
                ? prev.filter((id) => id !== maidId)
                : [...prev, maidId],
        );
    };

    const handleSubmit = () => {
        if (!userId) return;

        if (selectedMaids.length === 0) {
            toast.error('Please select at least one maid');
            return;
        }

        setIsSubmitting(true);

        router.post(
            recommendMaidSubmit(userId).url,
            { maids: selectedMaids },
            {
                onStart: () => {
                    toast.loading('Submitting recommendations...');
                },
                onSuccess: () => {
                    toast.success('Recommendations updated successfully.');
                },
                onError: (errors) => {
                    console.error(errors);
                    toast.error('Failed to submit recommendations.');
                },
                onFinish: () => {
                    toast.dismiss();
                    setIsSubmitting(false);
                },
            },
        );
    };

    const handleCancel = useCallback(() => {
        window.history.back();
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Customer" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">
                        Customer - Recommend Maids
                    </h2>
                    <div className="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleCancel}
                        >
                            Back
                        </Button>
                        <Button
                            onClick={handleSubmit}
                            disabled={isSubmitting}
                            className="w-full md:w-auto"
                        >
                            {isSubmitting
                                ? 'Submitting...'
                                : 'Submit Recommended Maids'}
                        </Button>
                    </div>
                </div>
                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 md:min-h-min dark:border-sidebar-border">
                    <UserForm
                        mode="view"
                        initialData={data.user}
                        branches={data.branches}
                        countries={data.countries}
                        roles={data.roles}
                        salesList={data.sales}
                    />
                    {data.maids && data.maids.length !== 0 ? (
                        <MaidCardList
                            data={data.maids}
                            dataNationality={data.nationality}
                            dataReligion={data.religion}
                            dataEducationLevel={data.educationLevel}
                            actions={actions}
                            onAction={(action, row) => {
                                const maidId = row?.id;
                                const customerId = data.user.customer_id;

                                if (maidId !== undefined) {
                                    if (action === 'view') {
                                        router.get(show(maidId).url);
                                    } else if (action === 'quotation-create') {
                                        if (!customerId) {
                                            toast.error(
                                                'Customer ID not found. Please contact support.',
                                            );
                                            return;
                                        }

                                        toast.loading(
                                            'Redirecting to quotation...',
                                        );

                                        router.visit(
                                            `/quotation/create?customer_id=${customerId}&maid_id=${maidId}`,
                                            {
                                                method: 'get',
                                                preserveState: false,
                                                preserveScroll: false,
                                                onSuccess: () => {
                                                    toast.dismiss();
                                                    toast.success(
                                                        'Ready to create quotation for customer and maid.',
                                                    );
                                                },
                                                onError: (errors) => {
                                                    console.error(errors);
                                                    toast.dismiss();
                                                    toast.error(
                                                        'Failed to navigate to quotation.',
                                                    );
                                                },
                                            },
                                        );
                                    }
                                }
                            }}
                            selectedMaids={selectedMaids}
                            onSelect={handleSelect}
                        />
                    ) : (
                        <p className="text-center text-gray-500">
                            No maids found.
                        </p>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
