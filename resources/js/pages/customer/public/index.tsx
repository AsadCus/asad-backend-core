import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { OptionType } from '@/types';
import { Head } from '@inertiajs/react';
import { CheckCircle } from 'lucide-react';
import CustomerConfirmationForm from '../../confirmed-customer/form';
import { CustomerConfirmationFormSchema } from '../schema';

interface PublicCustomerFormProps {
    mode?: 'create' | 'edit';
    enquiryId?: number;
    groupId?: number;
    prefillName?: string;
    prefillEmail?: string;
    prefillContact?: string;
    packageId?: number | null;
    packageName?: string | null;
    packageOptions?: OptionType[];
    publicSubmitUrl?: string;
    initialData?: CustomerConfirmationFormSchema;
    linkType?: 'continuous' | 'one_time';
    oneTimeCompleted?: boolean;
    successTitle?: string;
    successDescription?: string;
}

export default function PublicCustomerForm({
    mode = 'create',
    enquiryId,
    // groupId,
    prefillName = '',
    prefillEmail = '',
    prefillContact = '',
    packageId = null,
    packageName = null,
    packageOptions = [],
    publicSubmitUrl,
    initialData,
    linkType = 'continuous',
    oneTimeCompleted = false,
    successTitle = 'Update complete',
    successDescription = 'Your update has been submitted successfully. This one-time link is no longer accessible.',
}: PublicCustomerFormProps) {
    const isEdit = mode === 'edit';
    const title = isEdit
        ? 'Edit Customer Confirmation Form'
        : 'Customer Confirmation Form';
    const description = isEdit
        ? 'Update your group details and member information below.'
        : 'Please fill in the group details and member information below. All required fields must be completed before submission.';

    return (
        <div className="flex min-h-screen items-center justify-center bg-orange-50 p-4 dark:bg-gray-600">
            <Head title={title} />

            <Card className="w-full gap-0 border-0 shadow-md md:max-w-[90%]">
                <CardHeader className="pb-6">
                    <CardTitle className="text-4xl font-light">
                        {title}
                    </CardTitle>
                    <CardDescription className="mt-2 text-base">
                        {description}
                        {packageName && (
                            <div className="mt-3 flex items-center gap-2">
                                <span className="text-base font-medium">
                                    Package:
                                </span>
                                <Badge
                                    variant="outline"
                                    className="rounded-full px-3 py-1 text-base"
                                >
                                    {packageName}
                                </Badge>
                            </div>
                        )}
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    {linkType === 'one_time' && oneTimeCompleted ? (
                        <Card className="border-green-600 bg-green-50 shadow-sm">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-green-900">
                                    <CheckCircle className="h-5 w-5 text-green-600" />
                                    {successTitle}
                                </CardTitle>
                                <CardDescription className="text-green-900">
                                    {successDescription}
                                </CardDescription>
                            </CardHeader>
                        </Card>
                    ) : (
                        <CustomerConfirmationForm
                            mode={mode}
                            enquiryId={enquiryId}
                            isPublic={true}
                            publicSubmitUrl={publicSubmitUrl}
                            packageOptions={packageOptions}
                            initialData={
                                initialData
                                    ? { ...initialData, terms_accepted: false }
                                    : enquiryId
                                      ? {
                                            enquiry_id: enquiryId,
                                            package_id: packageId,
                                            package_room_type: '',
                                            date_of_application: '',
                                            members: [
                                                {
                                                    is_leader: true,
                                                    name: prefillName,
                                                    email: prefillEmail,
                                                    contact_number:
                                                        prefillContact,
                                                    nric_number: '',
                                                    address: '',
                                                    nationality: '',
                                                    passport_number: '',
                                                    passport_issue_date: '',
                                                    passport_expiry_date: '',
                                                    passport_place_of_issue: '',
                                                    gender: '',
                                                    marital_status: '',
                                                    date_of_birth: '',
                                                    place_of_birth: '',
                                                    first_time_umrah: false,
                                                    has_chronic_disease: false,
                                                    is_using_wheelchair: false,
                                                    chronic_disease_details: '',
                                                },
                                            ],
                                            terms_accepted: false,
                                        }
                                      : undefined
                            }
                            onSuccess={() => {
                                // Show success state - handled by Laravel flash message
                            }}
                        />
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
