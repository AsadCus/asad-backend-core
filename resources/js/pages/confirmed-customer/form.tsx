import { DatePickerField } from '@/components/date-picker';
import { FormField } from '@/components/form-field';
import { ProperInputSelect } from '@/components/proper-input-select';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    formatCurrency,
    formatDateForDisplay,
    parseDisplayDate,
} from '@/lib/utils';
import { update as updateGroup } from '@/routes/customer-confirmations';
import {
    confirm as confirmEnquiry,
    createCustomerConfirmation,
    getForShow,
    listCustomers,
} from '@/routes/enquiries';
import { OptionType } from '@/types';
import { useForm } from '@inertiajs/react';
import {
    AlertCircle,
    CheckCircle,
    ExternalLink,
    Plus,
    Trash2,
} from 'lucide-react';
import {
    FormEvent,
    UIEvent,
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import ConfirmedCustomerFormFields from '../customer/confirmed-customer-form-fields';
import CustomerFormFields from '../customer/form-fields';
import {
    emptyMember,
    packageCategoryOptions,
    type CustomerConfirmationFormData,
    type CustomerConfirmationFormSchema,
    type CustomerMemberFormData,
    type CustomerOption,
} from '../customer/schema';
import { customerConfirmationFormValidationSchema } from '../customer/validation';
import EnquiryViewDialog from '../enquiries/components/enquiry-view-dialog';
import {
    EnquiryDetails,
    enquiryStatusLabels,
    enquiryTypeLabels,
} from '../enquiries/schema';
import PackageForm from '../packages/form';
import PackageInformationSection from '../packages/package-information-section';
import { sharingPlanOptions, type PackageSchema } from '../packages/schema';

type ClientValidationErrors = Record<string, string>;

interface LinkedPackageInfo {
    id: number;
    package_number?: string | null;
    name: string;
    status?: string;
    departure_date?: string | null;
    return_date?: string | null;
    total_seats?: number | null;
    seats_left?: number | null;
    visa_type?: string | null;
    vehicle_type?: string | null;
    ticket_type?: string | null;
    remarks?: string | null;
    price_single?: number | null;
    price_double?: number | null;
    price_triple?: number | null;
    price_quad?: number | null;
    child_with_bed_price?: number | null;
    child_no_bed_price?: number | null;
    infant_price?: number | null;
}

export interface CustomerConfirmationFormProps {
    mode?: 'create' | 'edit' | 'view';
    enquiryId?: number;
    enquiryType?: string;
    enquiryDetails?: EnquiryDetails;
    prefillName?: string;
    prefillEmail?: string;
    prefillContact?: string;
    prefillPackageId?: number | null;
    isPublic?: boolean;
    publicSubmitUrl?: string;
    initialData?: CustomerConfirmationFormSchema;
    packageOptions?: OptionType[];
    packageData?: PackageSchema;
    onSuccess?: () => void;
    onCancel?: () => void;
}

interface CustomerConfirmationPackageOption extends OptionType {
    package_number?: string;
    departure_date?: string | null;
    return_date?: string | null;
    seats_left?: number | null;
    is_private?: boolean;
}

const PUBLIC_TERMS_AND_CONDITIONS_CONTENT = `Terms & Conditions

A. Payment
1. Deposit of S$500 per person is to be paid upon registration for the Umrah / Tour packages and full payment to be made 2 months prior to departure date.

2. Deposit of S$1000 or 50% of package price (whichever is higher) per person is to be paid upon registration for the Umrah / Tour packages during peak period.

3. Payments can be made by Cash, Cheque, Credit Card, NETS, Bank Transfer and PayNow. Official receipts will be issued after every payment.
     - Credit Card payment (service charge of 3% will be imposed)
     - NETS payment (service charge of 0.8% will be imposed)
     - PayNow (UEN 202333370R)

4. Unutilized services such as city tour, transportation, meals etc., will NOT be refunded.

5. If the minimum room size is not achieved for the requested package, Jemaah is required to pay for the package price difference.

6. Departures are subject to full payment, failing which the cancellation fee will be charged according to rules and conditions contained herein.

B. Cancellation
*** to confirm

C. Exclusion of Responsibility
Karva Travel Group Pte. Ltd. shall not be responsible for any accidents / incidents including losses / lateness of transfers, natural disasters including floods, fire, riots, war, storms, typhoons, earthquakes, tsunami or any other tragedies encountered during journey to and fro which involves bodily harm / damage of properties.

Pilgrims / Clients are not allowed to take any legal actions under any circumstances for any claims, damages, losses, injuries or whatsoever reasons Karva Travel Group Pte. Ltd. including:
    - From any matter that may arise.
    - From the cause of the said accidents / incidents in any respect whatsoever.

D. Changes
Karva Travel Group Pte. Ltd. is entitled under any circumstances to change price or schedule without giving prior notice.

E. Umrah Visa Application
Umrah Visa Application is subject to approval by the Saudi Foreign Ministry / Ministry of Home Affairs / Saudi Embassy in Singapore. All unsuccessful visa applications will be charged with cancellation fees. (See B)

F. Insurance
*
(1) As a licensing condition of the Singapore Tourism Board, we, Karva Travel Group Pte. Ltd., are required to inform you, the client (the applicant) to consider purchasing travel insurance - (a) Against any failure or disruption in our provision of the travel product arising out of any insolvency on our part; and (b) In favour of all travelers for whom payment or deposit is to be made for.
(2). You acknowledge the risk if you do not purchase travel insurance against insolvency.
Please acknowledge the accuracy of this form, below (under Section - G Declaration)
I decline to be insured for the journey.
I will arrange my travel insurance on my own accord.

G. Declaration
*
I certify that all information contained in this form is true and correct to the best of my knowledge and realize that false information or omission may lead to dismissal from the offered package agreement. I have understood the above terms and conditions and I agree to sign up for the above package.
I agree to take Emergency & Medical Assistance (EMA) - (Valid for Umrah Only)
I have read and agree to all the terms and conditions above.`;

export default function CustomerConfirmationForm({
    mode = 'create',
    enquiryId,
    enquiryType,
    enquiryDetails,
    prefillName = '',
    prefillEmail = '',
    prefillContact = '',
    prefillPackageId,
    isPublic = false,
    publicSubmitUrl,
    initialData,
    packageOptions = [],
    packageData,
    onSuccess,
    onCancel,
}: CustomerConfirmationFormProps) {
    const isView = mode === 'view';
    const isEdit = mode === 'edit';
    const isCreate = mode === 'create';

    // State
    const [customerOptions, setCustomerOptions] = useState<CustomerOption[]>(
        [],
    );

    const [linkedEnquiryInfo, setLinkedEnquiryInfo] =
        useState<EnquiryDetails | null>(null);
    const [enquiryDialogOpen, setEnquiryDialogOpen] = useState(false);
    const [linkedEnquiryChild, setLinkedEnquiryChild] = useState<Record<
        string,
        unknown
    > | null>(null);
    const [linkedPackageInfo, setLinkedPackageInfo] =
        useState<LinkedPackageInfo | null>(null);
    const [linkedPackageData, setLinkedPackageData] =
        useState<PackageSchema | null>(null);
    const [packageDialogOpen, setPackageDialogOpen] = useState(false);
    const [isLoadingEnquiryChild, setIsLoadingEnquiryChild] = useState(false);
    const [isLoadingLinkedPackage, setIsLoadingLinkedPackage] = useState(false);
    const [activeTab, setActiveTab] = useState('customer-0');
    const [isSubmitted, setIsSubmitted] = useState(false);
    const [termsDialogOpen, setTermsDialogOpen] = useState(false);
    const [hasReachedTermsBottom, setHasReachedTermsBottom] = useState(false);
    const errorAlertRef = useRef<HTMLDivElement | null>(null);
    const termsScrollRef = useRef<HTMLDivElement | null>(null);

    const loadPackageInfo = useCallback(async (packageId: number) => {
        setIsLoadingLinkedPackage(true);

        try {
            const response = await fetch(`/packages-get-for-show/${packageId}`);

            if (!response.ok) {
                return;
            }

            const pkg = await response.json();

            setLinkedPackageInfo({
                id: pkg.id,
                package_number: pkg.package_number ?? null,
                name: pkg.name,
                status: pkg.status,
                departure_date: pkg.departure_date,
                return_date: pkg.return_date,
                total_seats: pkg.total_seats,
                seats_left: pkg.seats_left,
                visa_type: pkg.visa_type,
                vehicle_type: pkg.vehicle_type,
                ticket_type: pkg.ticket_type,
                remarks: pkg.remarks,
                price_single: pkg.price_single,
                price_double: pkg.price_double,
                price_triple: pkg.price_triple,
                price_quad: pkg.price_quad,
                child_with_bed_price: pkg.child_with_bed_price,
                child_no_bed_price: pkg.child_no_bed_price,
                infant_price: pkg.infant_price,
            });
            setLinkedPackageData(pkg);
        } finally {
            setIsLoadingLinkedPackage(false);
        }
    }, []);

    // Bootstrap data
    useEffect(() => {
        if (isPublic) return;

        if (!isView) {
            fetch(listCustomers().url)
                .then((res) => res.json())
                .then((data) => setCustomerOptions(data))
                .catch(() => {});
        }

        const linkedId = initialData?.enquiry_id ?? enquiryId;
        if (linkedId && (!enquiryDetails || !enquiryDetails.created_at)) {
            fetch(getForShow(linkedId).url)
                .then((res) => res.json())
                .then((json) => {
                    const eq = json?.enquiry;
                    if (!eq) return;
                    setLinkedEnquiryInfo({
                        id: eq.id,
                        type: eq.type,
                        name: eq.name,
                        email: eq.email,
                        contact: eq.contact_number ?? eq.contact,
                        status: eq.status_label ?? eq.status,
                        package_name: eq.package_name ?? null,
                        created_at: eq.created_at ?? null,
                    });
                })
                .catch(() => {});
        }
    }, [isPublic, isView, enquiryDetails, initialData?.enquiry_id, enquiryId]);

    useEffect(() => {
        if (!enquiryDialogOpen) return;
        if (linkedEnquiryChild) return;

        const enquiryId = enquiryDetails?.id ?? linkedEnquiryInfo?.id ?? null;
        if (!enquiryId) return;

        setIsLoadingEnquiryChild(true);
        fetch(getForShow(enquiryId).url)
            .then((res) => res.json())
            .then((json) => setLinkedEnquiryChild(json?.child ?? null))
            .catch(() => {})
            .finally(() => setIsLoadingEnquiryChild(false));
    }, [
        enquiryDialogOpen,
        enquiryDetails?.id,
        linkedEnquiryInfo?.id,
        linkedEnquiryChild,
    ]);

    // Form
    const defaultData: CustomerConfirmationFormData = (initialData ?? {
        number: '',
        number_format_id: null,
        enquiry_id: enquiryId ?? null,
        package_id: prefillPackageId ?? null,
        package_room_type: '',
        package_category: '',
        date_of_application: '',
        members: [
            {
                ...emptyMember(true),
                name: prefillName,
                email: prefillEmail,
                contact_number: prefillContact,
            },
        ],
        terms_accepted: isPublic && isEdit ? true : !isPublic,
    }) as CustomerConfirmationFormData;

    const form = useForm<CustomerConfirmationFormData>(defaultData);
    const { data, setData, post, processing, clearErrors, setError } = form;
    const errors: Record<string, string | undefined> = form.errors;
    const normalizedPackageOptions =
        packageOptions as CustomerConfirmationPackageOption[];
    const effectiveLinkedEnquiry = enquiryDetails ?? linkedEnquiryInfo;
    const isPrivateWithLinkedPackage =
        isEdit &&
        (effectiveLinkedEnquiry?.type ?? enquiryType ?? '').toLowerCase() ===
            'private' &&
        !!initialData?.package_id;
    const isPackageAlreadySelected = Boolean(
        initialData?.package_id ?? prefillPackageId,
    );

    const groupedPackageOptions = useMemo(() => {
        const selectedPackageId = Number(data.package_id ?? 0);
        const visiblePackages = normalizedPackageOptions
            .filter((option) => {
                const packageId = Number(option.value ?? 0);
                const isCurrentSelection =
                    selectedPackageId > 0 && packageId === selectedPackageId;
                const seatsLeft = Number(option.seats_left ?? 0);
                const isPrivatePackage =
                    Boolean(option.is_private) ||
                    /^private\s*-/i.test((option.label ?? '').trim());

                if (isCurrentSelection) {
                    return true;
                }

                return seatsLeft > 0 && !isPrivatePackage;
            })
            .sort((left, right) => {
                const leftDate = parseDisplayDate(left.departure_date);
                const rightDate = parseDisplayDate(right.departure_date);

                if (leftDate && rightDate) {
                    return leftDate.getTime() - rightDate.getTime();
                }

                if (leftDate) {
                    return -1;
                }

                if (rightDate) {
                    return 1;
                }

                return String(left.label).localeCompare(String(right.label));
            });

        const options: CustomerConfirmationPackageOption[] = [];
        let previousGroupKey = '';

        visiblePackages.forEach((option) => {
            const departureDate = parseDisplayDate(option.departure_date);

            const groupKey = departureDate
                ? departureDate.toLocaleDateString('en-US', {
                      month: 'long',
                      year: 'numeric',
                  })
                : 'No Departure Date';

            if (groupKey !== previousGroupKey) {
                options.push({
                    value: `__group__:${groupKey}`,
                    label: groupKey,
                });
                previousGroupKey = groupKey;
            }

            const seatsLeft = Number(option.seats_left ?? 0);
            const seatsLeftLabel = Number.isFinite(seatsLeft)
                ? ` (${seatsLeft} Seats Left)`
                : '';

            options.push({
                ...option,
                label: `${option.label}${seatsLeftLabel}`.trim(),
            });
        });

        return options;
    }, [normalizedPackageOptions, data.package_id]);

    useEffect(() => {
        const enquiryCreatedAt = effectiveLinkedEnquiry?.created_at;

        if (!enquiryCreatedAt) {
            return;
        }

        if ((data.date_of_application ?? '').trim().length > 0) {
            return;
        }

        const normalizedDate = formatDateForDisplay(enquiryCreatedAt);
        if (!normalizedDate) {
            return;
        }

        setData('date_of_application', normalizedDate);
    }, [effectiveLinkedEnquiry?.created_at, data.date_of_application, setData]);

    useEffect(() => {
        if (packageData?.id) {
            setLinkedPackageInfo({
                id: packageData.id,
                package_number: packageData.package_number ?? null,
                name: packageData.name ?? '-',
                status: packageData.status,
                departure_date: packageData.departure_date,
                return_date: packageData.return_date,
                total_seats: packageData.total_seats,
                seats_left: packageData.seats_left,
                visa_type: packageData.visa_type,
                vehicle_type: packageData.vehicle_type,
                ticket_type: packageData.ticket_type,
                remarks: packageData.remarks,
            });

            return;
        }

        const packageId = data.package_id;

        if (!packageId) {
            setLinkedPackageInfo(null);

            return;
        }

        const selectedOption = packageOptions.find(
            (option) => Number(option.value) === Number(packageId),
        );

        if (selectedOption) {
            setLinkedPackageInfo((current) => ({
                id: Number(packageId),
                package_number: current?.package_number,
                name: selectedOption.label,
                status: current?.status,
                departure_date: current?.departure_date,
                return_date: current?.return_date,
                total_seats: current?.total_seats,
                seats_left: current?.seats_left,
                visa_type: current?.visa_type,
                vehicle_type: current?.vehicle_type,
                ticket_type: current?.ticket_type,
                remarks: current?.remarks,
                price_single: current?.price_single,
                price_double: current?.price_double,
                price_triple: current?.price_triple,
                price_quad: current?.price_quad,
                child_with_bed_price: current?.child_with_bed_price,
                child_no_bed_price: current?.child_no_bed_price,
                infant_price: current?.infant_price,
            }));
        }

        loadPackageInfo(Number(packageId));
    }, [data.package_id, packageData, packageOptions, loadPackageInfo]);

    const memberSharingPlanOptions = useMemo(() => {
        const packagePriceSource =
            linkedPackageInfo ??
            (linkedPackageData as {
                price_single?: number | null;
                price_double?: number | null;
                price_triple?: number | null;
                price_quad?: number | null;
                child_with_bed_price?: number | null;
                child_no_bed_price?: number | null;
                infant_price?: number | null;
            } | null);

        if (!packagePriceSource) {
            return sharingPlanOptions;
        }

        const dynamic = [
            {
                value: 'single',
                label: 'Single',
                price: Number(packagePriceSource.price_single ?? 0),
            },
            {
                value: 'double',
                label: 'Double',
                price: Number(packagePriceSource.price_double ?? 0),
            },
            {
                value: 'triple',
                label: 'Triple',
                price: Number(packagePriceSource.price_triple ?? 0),
            },
            {
                value: 'quad',
                label: 'Quad',
                price: Number(packagePriceSource.price_quad ?? 0),
            },
            {
                value: 'child_with_bed',
                label: 'Child (7-11 years)',
                price: Number(packagePriceSource.child_with_bed_price ?? 0),
            },
            {
                value: 'child_no_bed',
                label: 'Child (2-6 years)',
                price: Number(packagePriceSource.child_no_bed_price ?? 0),
            },
            {
                value: 'infant',
                label: 'Infant (0-2 years)',
                price: Number(packagePriceSource.infant_price ?? 0),
            },
        ]
            .filter((item) => item.price > 0)
            .map((item) => ({
                value: item.value,
                label: `${item.label} (${formatCurrency(item.price)})`,
            }));

        return dynamic.length > 0 ? dynamic : sharingPlanOptions;
    }, [linkedPackageInfo, linkedPackageData]);

    // Helpers

    const getError = (path: string): string | undefined => {
        return errors[path];
    };

    const hasErrors = Object.keys(errors).length > 0;

    const toFieldLabel = (path: string): string => {
        const fieldName = path.split('.').pop() ?? path;

        return fieldName
            .replace(/[_-]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim()
            .replace(/\b\w/g, (character) => character.toUpperCase());
    };

    const getMemberIndexFromPath = (path: string): number | null => {
        const match = path.match(/^members\.(\d+)\./);
        if (!match) {
            return null;
        }

        return Number(match[1]);
    };

    const scrollToErrorBanner = useCallback(() => {
        setTimeout(() => {
            errorAlertRef.current?.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
        }, 0);
    }, []);

    const focusErrorField = useCallback(
        (path: string) => {
            const memberIndex = getMemberIndexFromPath(path);

            if (memberIndex !== null) {
                setActiveTab(`customer-${memberIndex}`);
            }

            setTimeout(
                () => {
                    const target = document.getElementById(path);

                    if (!target) {
                        return;
                    }

                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center',
                    });

                    if (target instanceof HTMLElement) {
                        target.focus();
                    }
                },
                memberIndex !== null ? 180 : 60,
            );
        },
        [setActiveTab],
    );

    const groupedErrorSummary = useMemo(() => {
        const globalErrors: Array<{ path: string; message: string }> = [];
        const memberErrors = new Map<
            number,
            Array<{ path: string; message: string; fieldLabel: string }>
        >();

        Object.entries(errors).forEach(([path, message]) => {
            if (!message) {
                return;
            }

            const memberIndex = getMemberIndexFromPath(path);

            if (memberIndex === null) {
                globalErrors.push({ path, message });

                return;
            }

            if (!memberErrors.has(memberIndex)) {
                memberErrors.set(memberIndex, []);
            }

            memberErrors.get(memberIndex)?.push({
                path,
                message,
                fieldLabel: toFieldLabel(path),
            });
        });

        const sortedMemberErrors = [...memberErrors.entries()]
            .sort((a, b) => a[0] - b[0])
            .map(([memberIndex, list]) => {
                const memberName =
                    data.members?.[memberIndex]?.name?.trim() ||
                    `Member ${memberIndex + 1}`;

                return {
                    memberIndex,
                    memberName,
                    errors: list,
                };
            });

        return {
            globalErrors,
            memberGroups: sortedMemberErrors,
        };
    }, [errors, data.members]);

    const enquiryTypeKey = (effectiveLinkedEnquiry?.type ?? '').toLowerCase();
    const enquiryTypeLabel = enquiryTypeLabels[enquiryTypeKey] ?? '-';

    const enquiryStatusKey = (effectiveLinkedEnquiry?.status ?? '')
        .toLowerCase()
        .replace(/\s+/g, '_');
    const enquiryStatusLabel = enquiryStatusLabels[enquiryStatusKey] ?? '-';

    const handleOpenPackageDialog = async () => {
        if (!data.package_id) {
            return;
        }

        if (!linkedPackageData || linkedPackageData.id !== data.package_id) {
            await loadPackageInfo(Number(data.package_id));
        }

        setPackageDialogOpen(true);
    };

    // Customer actions

    const addCustomer = () => {
        const next = [...(data.members ?? []), emptyMember(false)];
        setData('members', next);
        setActiveTab(`customer-${next.length - 1}`);
    };

    // Customer selection
    const selectedCustomerValues = customerOptions
        .filter((c) => data.members?.some((m) => m.email === c.email))
        .map((c) => String(c.value));

    const handleMultiSelectChange = (newValues: string[]) => {
        const currentCustomers = data.members ?? [];

        const newSelectedEmails = new Set(
            customerOptions
                .filter((c) => newValues.includes(String(c.value)))
                .map((c) => c.email),
        );

        const nextCustomers = currentCustomers.filter((customer) => {
            if (customer.is_leader) return true;
            const isFromExistingCustomer = customerOptions.some(
                (option) => option.email === customer.email,
            );
            return (
                !isFromExistingCustomer || newSelectedEmails.has(customer.email)
            );
        });

        for (const v of newValues) {
            const customer = customerOptions.find((c) => String(c.value) === v);
            if (!customer) continue;
            if (
                nextCustomers.some(
                    (existing) => existing.email === customer.email,
                )
            )
                continue;
            nextCustomers.push({
                ...emptyMember(false),
                name: customer.name,
                email: customer.email,
                contact_number: customer.contact_number,
                nric_number: customer.nric_number,
                address: customer.address,
                nationality: customer.nationality ?? '',
                passport_number: customer.passport_number ?? '',
                passport_issue_date: customer.passport_issue_date ?? '',
                passport_expiry_date: customer.passport_expiry_date ?? '',
                passport_place_of_issue: customer.passport_place_of_issue ?? '',
                gender: customer.gender ?? '',
                marital_status: customer.marital_status ?? '',
                date_of_birth: customer.date_of_birth ?? '',
                place_of_birth: customer.place_of_birth ?? '',
                first_time_umrah: customer.first_time_umrah ?? null,
                has_chronic_disease: customer.has_chronic_disease ?? false,
                chronic_disease_details: customer.chronic_disease_details ?? '',
                passport_document: customer.passport_document ?? null,
                photo_document: customer.photo_document ?? null,
                passport_file_name:
                    customer.passport_document?.file_name ?? null,
                photo_file_name: customer.photo_document?.file_name ?? null,
                passport_file_removed: false,
                photo_file_removed: false,
            });
        }

        if (
            nextCustomers.length > 0 &&
            !nextCustomers.some((customer) => customer.is_leader)
        ) {
            nextCustomers[0] = { ...nextCustomers[0], is_leader: true };
        }

        setData('members', nextCustomers);
        setActiveTab(`customer-${Math.max(0, nextCustomers.length - 1)}`);
    };

    const removeCustomer = (index: number) => {
        const next = (data.members ?? []).filter((_, i) => i !== index);
        if (next.length > 0 && !next.some((customer) => customer.is_leader)) {
            next[0] = { ...next[0], is_leader: true };
        }
        setData('members', next);
        const newIdx = Math.min(index, next.length - 1);
        setActiveTab(`customer-${Math.max(0, newIdx)}`);
    };

    const applyCustomerUpdate = (
        index: number,
        field: keyof CustomerMemberFormData,
        value: string | boolean | File | null,
    ) => {
        setData((currentData) => {
            const next = [...(currentData.members ?? [])];

            if (field === 'is_leader' && value === true) {
                for (let i = 0; i < next.length; i++) {
                    next[i] = { ...next[i], is_leader: i === index };
                }
            } else {
                next[index] = { ...next[index], [field]: value };
            }

            return {
                ...currentData,
                members: next,
            };
        });
    };

    const updateCustomer = (
        index: number,
        field: keyof CustomerMemberFormData,
        value: string | boolean | File | null,
    ) => {
        if (field === 'is_leader' && value === true) {
            applyCustomerUpdate(index, field, value);

            return;
        }

        applyCustomerUpdate(index, field, value);
    };

    // Submit
    function validateClientSide(): boolean {
        clearErrors();
        const clientErrors: ClientValidationErrors = {};
        const result = customerConfirmationFormValidationSchema.safeParse(data);

        if (isPublic && data.terms_accepted !== true) {
            clientErrors.terms_accepted =
                'You must read and accept the Terms and Conditions before submitting.';
        }

        if (!result.success) {
            result.error.issues.forEach((issue) => {
                const key = issue.path.join('.');
                if (!clientErrors[key]) {
                    clientErrors[key] = issue.message;
                }
            });
        }

        const departureDateValue =
            linkedPackageInfo?.departure_date ?? packageData?.departure_date;
        const departureDate = parseDisplayDate(departureDateValue);

        if (data.package_id && departureDate) {
            (data.members ?? []).forEach((member, index) => {
                const passportExpiryDate = parseDisplayDate(
                    member.passport_expiry_date,
                );

                if (!passportExpiryDate) {
                    return;
                }

                const minimumPassportValidityDate = new Date(departureDate);
                minimumPassportValidityDate.setMonth(
                    minimumPassportValidityDate.getMonth() + 6,
                );

                if (passportExpiryDate <= minimumPassportValidityDate) {
                    const key = `members.${index}.passport_expiry_date`;
                    if (!clientErrors[key]) {
                        clientErrors[key] =
                            'Passport must be valid at least 6 months from departure/travel date.';
                    }
                }
            });
        }

        if (Object.keys(clientErrors).length > 0) {
            setError(clientErrors);

            return false;
        }

        return true;
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        if (isView) return;
        if (!validateClientSide()) {
            if (isPublic && data.terms_accepted !== true) {
                setTermsDialogOpen(true);
            }
            scrollToErrorBanner();

            return;
        }

        const handleSuccess = () => {
            if (isPublic) {
                setIsSubmitted(true);
                window.scrollTo({ top: 0, behavior: 'smooth' });
                setTimeout(() => setIsSubmitted(false), 8000);
            }
            onSuccess?.();
        };

        const handleError = (errs: Record<string, string>) => {
            setError(errs);
            scrollToErrorBanner();
            if (isPublic) {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        };

        if (isEdit && initialData?.id) {
            const editUrl = publicSubmitUrl ?? updateGroup(initialData.id).url;
            const isPublicEdit = !!publicSubmitUrl;

            form.transform((currentData) => {
                if (isPublicEdit) {
                    return currentData;
                }

                return {
                    ...currentData,
                    _method: 'put',
                };
            });

            post(editUrl, {
                forceFormData: true,
                onSuccess: handleSuccess,
                onError: handleError,
                onFinish: () => {
                    form.transform((currentData) => currentData);
                },
            });
        } else {
            const effectiveEnquiryId =
                enquiryId ?? initialData?.enquiry_id ?? null;
            const submitUrl = publicSubmitUrl
                ? publicSubmitUrl
                : enquiryId
                  ? confirmEnquiry(enquiryId).url
                  : createCustomerConfirmation().url;

            form.transform((currentData) => ({
                ...currentData,
                enquiry_id: effectiveEnquiryId,
                ...(packageData && enquiryType === 'private'
                    ? { package_data: packageData }
                    : {}),
            }));

            post(submitUrl, {
                forceFormData: true,
                onSuccess: handleSuccess,
                onError: handleError,
                onFinish: () => {
                    form.transform((currentData) => currentData);
                },
            });
        }
    }

    return (
        <div className="mx-auto w-full">
            <Dialog open={termsDialogOpen} onOpenChange={setTermsDialogOpen}>
                <DialogContent
                    className="max-h-[92vh] max-w-3xl"
                    onInteractOutside={(event) => event.preventDefault()}
                    onPointerDownOutside={(event) => event.preventDefault()}
                    onEscapeKeyDown={(event) => event.preventDefault()}
                >
                    <DialogHeader>
                        <DialogTitle>Terms & Conditions</DialogTitle>
                        <DialogDescription>
                            Please read all terms carefully. Scroll to the
                            bottom to enable acceptance.
                        </DialogDescription>
                    </DialogHeader>

                    <div
                        ref={termsScrollRef}
                        className="max-h-[55vh] overflow-y-auto rounded-md border p-4"
                        onScroll={(event: UIEvent<HTMLDivElement>) => {
                            const target = event.currentTarget;
                            const reachedBottom =
                                target.scrollTop + target.clientHeight >=
                                target.scrollHeight - 8;

                            if (reachedBottom) {
                                setHasReachedTermsBottom(true);
                            }
                        }}
                    >
                        <p className="text-base leading-relaxed whitespace-pre-wrap">
                            {PUBLIC_TERMS_AND_CONDITIONS_CONTENT}
                        </p>
                    </div>

                    <div className="space-y-2">
                        <div className="flex items-start gap-3">
                            <Checkbox
                                id="terms_dialog_accept"
                                checked={data.terms_accepted}
                                disabled={processing || !hasReachedTermsBottom}
                                onCheckedChange={(checked) => {
                                    const accepted = checked === true;
                                    setData('terms_accepted', accepted);

                                    if (accepted) {
                                        clearErrors('terms_accepted');
                                        setTermsDialogOpen(false);
                                    }
                                }}
                            />
                            <Label
                                htmlFor="terms_dialog_accept"
                                className="cursor-pointer text-base"
                            >
                                I have read and accept the Terms & Conditions
                            </Label>
                        </div>

                        {!hasReachedTermsBottom && (
                            <p className="text-sm text-muted-foreground">
                                Scroll to the bottom to enable the acceptance
                                checkbox.
                            </p>
                        )}
                    </div>
                </DialogContent>
            </Dialog>

            <form onSubmit={submit} className="space-y-4">
                {/* Success */}
                {isSubmitted && isPublic && (
                    <Alert className="border-green-600 bg-green-50 shadow-sm">
                        <CheckCircle className="h-5 w-5 text-green-600" />
                        <AlertDescription className="font-medium text-green-900">
                            {isEdit
                                ? 'Your application has been updated successfully.'
                                : 'Your application has been submitted successfully. We will contact you soon.'}
                        </AlertDescription>
                    </Alert>
                )}

                {/* Error */}
                {hasErrors && !isView && (
                    <Alert variant="destructive" ref={errorAlertRef}>
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>
                            <div className="space-y-3">
                                <p>
                                    Please fix the errors below and try again.
                                </p>

                                {groupedErrorSummary.globalErrors.length >
                                    0 && (
                                    <div className="space-y-1">
                                        {groupedErrorSummary.globalErrors.map(
                                            ({ path, message }) => (
                                                <button
                                                    key={path}
                                                    type="button"
                                                    onClick={() =>
                                                        focusErrorField(path)
                                                    }
                                                    className="block w-full rounded-sm text-left text-base underline-offset-2 hover:underline"
                                                >
                                                    {toFieldLabel(path)}:{' '}
                                                    {message}
                                                </button>
                                            ),
                                        )}
                                    </div>
                                )}

                                {groupedErrorSummary.memberGroups.map(
                                    ({ memberIndex, memberName, errors }) => (
                                        <div
                                            key={memberIndex}
                                            className="space-y-1"
                                        >
                                            <p className="font-medium">
                                                {memberName}
                                            </p>
                                            {errors.map(
                                                ({
                                                    path,
                                                    message,
                                                    fieldLabel,
                                                }) => (
                                                    <button
                                                        key={path}
                                                        type="button"
                                                        onClick={() =>
                                                            focusErrorField(
                                                                path,
                                                            )
                                                        }
                                                        className="block w-full rounded-sm text-left text-base underline-offset-2 hover:underline"
                                                    >
                                                        {fieldLabel}: {message}
                                                    </button>
                                                ),
                                            )}
                                        </div>
                                    ),
                                )}
                            </div>
                        </AlertDescription>
                    </Alert>
                )}

                {/* Linked enquiry */}
                {(isView || isEdit) && !isPublic && effectiveLinkedEnquiry && (
                    <Card>
                        <CardHeader className="gap-0 pb-3">
                            <div className="flex items-center justify-between">
                                <CardTitle className="text-xl">
                                    Linked Enquiry
                                </CardTitle>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setEnquiryDialogOpen(true)}
                                >
                                    <ExternalLink className="h-4 w-4 md:mr-1" />
                                    <span className="hidden md:block">
                                        View Details
                                    </span>
                                </Button>
                            </div>
                            <CardDescription>
                                Details of the linked enquiry.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                                <FormField label="Enquiry Number">
                                    <div className="rounded-md border bg-muted/30 px-3 py-1.25 select-text">
                                        #{effectiveLinkedEnquiry.id}
                                    </div>
                                </FormField>
                                <FormField label="Type">
                                    <div className="rounded-md border bg-muted/30 px-3 py-1">
                                        <Badge variant="secondary">
                                            {enquiryTypeLabel}
                                        </Badge>
                                    </div>
                                </FormField>
                                <FormField label="Status">
                                    <div className="rounded-md border bg-muted/30 px-3 py-1.25 select-text">
                                        {enquiryStatusLabel}
                                    </div>
                                </FormField>
                                <FormField label="Name">
                                    <div className="rounded-md border bg-muted/30 px-3 py-1.25 select-text">
                                        {effectiveLinkedEnquiry.name || '-'}
                                    </div>
                                </FormField>
                                <FormField label="Email">
                                    <div className="rounded-md border bg-muted/30 px-3 py-1.25 select-text">
                                        {effectiveLinkedEnquiry.email || '-'}
                                    </div>
                                </FormField>
                                <FormField label="Contact">
                                    <div className="rounded-md border bg-muted/30 px-3 py-1.25 select-text">
                                        {effectiveLinkedEnquiry.contact || '-'}
                                    </div>
                                </FormField>
                                {effectiveLinkedEnquiry.package_name && (
                                    <FormField
                                        label="Package"
                                        className="md:col-span-2 lg:col-span-3"
                                    >
                                        <div className="rounded-md border bg-muted/30 px-3 py-1.25 select-text">
                                            {
                                                effectiveLinkedEnquiry.package_name
                                            }
                                        </div>
                                    </FormField>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Linked package */}
                {!isPublic && data.package_id && (
                    <PackageInformationSection
                        description="Details of the currently selected package."
                        packageInfo={
                            linkedPackageInfo
                                ? linkedPackageInfo
                                : data.package_id
                                  ? {
                                        id: Number(data.package_id),
                                        name: '-',
                                    }
                                  : null
                        }
                        isLoading={isLoadingLinkedPackage}
                        onViewDetails={handleOpenPackageDialog}
                    />
                )}

                {/* Group details */}
                <Card className="py-3 md:py-6">
                    <CardHeader className="gap-0 px-3 md:px-6">
                        <CardTitle className="text-xl">Group Details</CardTitle>
                        <CardDescription>
                            {isView
                                ? 'View the details of this customer group.'
                                : isEdit
                                  ? 'Update the details of this customer group.'
                                  : 'Fill in the details of the customer group.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="px-3 md:px-6">
                        <div className="grid grid-cols-1 items-start gap-6 md:grid-cols-2">
                            <FormField
                                label="Package"
                                fieldRequirementsProps={{
                                    hint:
                                        enquiryType === 'private'
                                            ? 'Package was created in the previous step'
                                            : 'Select the travel package for this group',
                                }}
                                htmlFor="package_id"
                                error={getError('package_id')}
                            >
                                {enquiryType === 'private' && packageData ? (
                                    <div className="flex h-9 items-center rounded-md border bg-muted px-3">
                                        {packageData.name ||
                                            'Package (from step 1)'}
                                    </div>
                                ) : (
                                    <ProperInputSelect
                                        id="package_id"
                                        options={groupedPackageOptions}
                                        value={
                                            data.package_id
                                                ? String(data.package_id)
                                                : ''
                                        }
                                        onValueChange={(nextValue) => {
                                            if (Array.isArray(nextValue)) {
                                                return;
                                            }

                                            if (
                                                String(nextValue).startsWith(
                                                    '__group__:',
                                                )
                                            ) {
                                                return;
                                            }

                                            setData(
                                                'package_id',
                                                nextValue
                                                    ? Number(nextValue)
                                                    : null,
                                            );
                                        }}
                                        placeholder="Select package..."
                                        disabled={
                                            isView ||
                                            processing ||
                                            isPackageAlreadySelected ||
                                            isPrivateWithLinkedPackage ||
                                            (isPublic &&
                                                Boolean(data.package_id))
                                        }
                                        truncate={100}
                                    />
                                )}
                            </FormField>

                            <FormField
                                label="Category"
                                fieldRequirementsProps={{
                                    hint: 'Select the package category based on services',
                                    example: 'Economy, Standard, Premium, VIP',
                                }}
                                htmlFor="package_category"
                                error={getError('package_category')}
                            >
                                <ProperInputSelect
                                    id="package_category"
                                    mode="classic"
                                    options={packageCategoryOptions}
                                    value={data.package_category ?? ''}
                                    onValueChange={(nextValue) => {
                                        if (Array.isArray(nextValue)) {
                                            return;
                                        }

                                        setData(
                                            'package_category',
                                            nextValue
                                                ? String(nextValue)
                                                : null,
                                        );
                                    }}
                                    placeholder="Select category..."
                                    disabled={isView || processing}
                                />
                            </FormField>

                            <FormField
                                label="Date of Application"
                                fieldRequirementsProps={{
                                    required: true,
                                    hint: 'Date when this group application is being submitted',
                                }}
                                htmlFor="date_of_application"
                                error={getError('date_of_application')}
                            >
                                <DatePickerField
                                    id="date_of_application"
                                    value={data.date_of_application}
                                    disabled={isView || processing}
                                    onChange={(v) =>
                                        setData('date_of_application', v)
                                    }
                                />
                            </FormField>
                        </div>
                    </CardContent>
                </Card>

                {/* Members */}
                <Card className="py-3 md:py-6">
                    <CardHeader className="grid grid-cols-1 px-3 md:grid-cols-2 md:px-6">
                        <div className="grid grid-cols-1 gap-0">
                            <CardTitle className="text-xl">
                                Group Customers ({data.members?.length ?? 0})
                            </CardTitle>
                            <CardDescription>
                                {isView
                                    ? 'View the details of the group customers.'
                                    : isEdit
                                      ? 'Update the details of the group customers.'
                                      : 'Fill in the details of the group customers.'}
                            </CardDescription>
                        </div>
                        {!isView && (
                            <div className="flex flex-col justify-end gap-2 md:flex-row">
                                {!isPublic && customerOptions.length > 0 && (
                                    <ProperInputSelect
                                        mode="multi"
                                        options={customerOptions.map((c) => ({
                                            label: c.label,
                                            value: String(c.value),
                                        }))}
                                        value={selectedCustomerValues}
                                        onValueChange={(nextValue) => {
                                            if (!Array.isArray(nextValue)) {
                                                return;
                                            }

                                            handleMultiSelectChange(nextValue);
                                        }}
                                        placeholder="Search & select customers..."
                                        maxWidth="300px"
                                        responsive={true}
                                        disabled={processing}
                                        maxCount={0}
                                    />
                                )}
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={addCustomer}
                                >
                                    <Plus className="mr-1 h-4 w-4" />
                                    Add Customer
                                </Button>
                            </div>
                        )}
                    </CardHeader>
                    <CardContent className="px-3 md:px-6">
                        {getError('members') && (
                            <p className="mb-3 text-base font-medium text-red-600">
                                {getError('members')}
                            </p>
                        )}

                        {(data.members?.length ?? 0) === 0 ? (
                            <p className="text-base text-muted-foreground">
                                No customers added. Click &quot;Add
                                Customer&quot; to start building the group.
                            </p>
                        ) : (
                            <Tabs
                                value={activeTab}
                                onValueChange={setActiveTab}
                            >
                                <ScrollArea className="w-full whitespace-nowrap">
                                    <TabsList>
                                        {data.members?.map((customer, idx) => (
                                            <TabsTrigger
                                                key={idx}
                                                value={`customer-${idx}`}
                                                className="relative"
                                            >
                                                <span className="mr-1">
                                                    {customer.name ||
                                                        `Customer ${idx + 1}`}
                                                </span>
                                                {customer.is_leader && (
                                                    <Badge
                                                        variant="default"
                                                        className="ml-1 text-xs"
                                                    >
                                                        Main
                                                    </Badge>
                                                )}
                                            </TabsTrigger>
                                        ))}
                                    </TabsList>
                                    <ScrollBar orientation="horizontal" />
                                </ScrollArea>

                                {data.members?.map((customer, idx) => (
                                    <TabsContent
                                        key={idx}
                                        value={`customer-${idx}`}
                                        className="space-y-2"
                                    >
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-4">
                                                <div className="flex items-center gap-2">
                                                    <input
                                                        type="radio"
                                                        name="leader"
                                                        checked={
                                                            customer.is_leader
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onChange={() =>
                                                            updateCustomer(
                                                                idx,
                                                                'is_leader',
                                                                true,
                                                            )
                                                        }
                                                        className="h-4 w-4 accent-primary"
                                                    />
                                                    <Label className="text-base">
                                                        Main
                                                    </Label>
                                                </div>
                                            </div>
                                            {!isView &&
                                                (data.members?.length ?? 0) >
                                                    1 && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            removeCustomer(idx)
                                                        }
                                                        className="text-red-500 hover:text-red-700"
                                                    >
                                                        <Trash2 className="mr-1 h-4 w-4" />
                                                        Remove
                                                    </Button>
                                                )}
                                        </div>

                                        <div className="space-y-6">
                                            <Card className="py-3 md:py-6">
                                                <CardHeader className="gap-0">
                                                    <CardTitle className="text-xl">
                                                        Customer Confirmation
                                                        Information
                                                    </CardTitle>
                                                    <CardDescription>
                                                        Customer confirmation
                                                        details related to this
                                                        member.
                                                    </CardDescription>
                                                </CardHeader>
                                                <CardContent className="px-3 md:px-6">
                                                    <ConfirmedCustomerFormFields
                                                        customer={customer}
                                                        index={idx}
                                                        isView={isView}
                                                        processing={processing}
                                                        showStatusField={true}
                                                        forceStatusDisabled={
                                                            isPublic ||
                                                            isEdit ||
                                                            isCreate
                                                        }
                                                        getError={getError}
                                                        sharingPlanSelectOptions={
                                                            memberSharingPlanOptions
                                                        }
                                                        onUpdateCustomer={(
                                                            field,
                                                            value,
                                                        ) =>
                                                            updateCustomer(
                                                                idx,
                                                                field,
                                                                value,
                                                            )
                                                        }
                                                    />
                                                </CardContent>
                                            </Card>

                                            <Card className="py-3 md:py-6">
                                                <CardHeader className="gap-0">
                                                    <CardTitle className="text-xl">
                                                        Customer Information
                                                    </CardTitle>
                                                    <CardDescription>
                                                        Personal details of the
                                                        customer member.
                                                    </CardDescription>
                                                </CardHeader>
                                                <CardContent className="px-3 md:px-6">
                                                    <CustomerFormFields
                                                        customer={customer}
                                                        index={idx}
                                                        useGeneratedDocumentName
                                                        isView={isView}
                                                        processing={processing}
                                                        getError={getError}
                                                        onUpdateCustomer={(
                                                            field,
                                                            value,
                                                        ) =>
                                                            updateCustomer(
                                                                idx,
                                                                field,
                                                                value,
                                                            )
                                                        }
                                                    />
                                                </CardContent>
                                            </Card>
                                        </div>
                                    </TabsContent>
                                ))}
                            </Tabs>
                        )}
                    </CardContent>
                </Card>

                {/* Terms */}
                {isPublic && (
                    <Card className="py-3 md:py-6">
                        <CardContent className="px-3 md:px-6">
                            <div className="flex flex-col items-start gap-3">
                                <div className="flex items-center gap-3">
                                    <Checkbox
                                        id="terms_accepted"
                                        checked={data.terms_accepted}
                                        disabled
                                        className="h-4.5 w-4.5"
                                    />
                                    <Label
                                        htmlFor="terms_accepted"
                                        className="text-base"
                                    >
                                        <span>
                                            I agree to the Terms and Conditions{' '}
                                            <span className="text-red-500">
                                                *
                                            </span>
                                        </span>
                                    </Label>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    By checking this box, you confirm that all
                                    the information provided is accurate and you
                                    agree to our terms of service.
                                </p>
                                <Button
                                    type="button"
                                    variant="link"
                                    className="h-auto px-0"
                                    onClick={() => setTermsDialogOpen(true)}
                                >
                                    Read and Accept Terms & Conditions
                                </Button>
                            </div>
                            {getError('terms_accepted') && (
                                <p className="mt-1 text-base font-medium text-red-600">
                                    {getError('terms_accepted')}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Actions */}
                <div className="flex items-center justify-end gap-3">
                    {onCancel && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onCancel}
                            disabled={processing}
                        >
                            {isView ? 'Close' : 'Cancel'}
                        </Button>
                    )}
                    {!isView && (
                        <Button
                            type="submit"
                            className="min-w-[140px]"
                            disabled={
                                processing || (isPublic && !data.terms_accepted)
                            }
                        >
                            {processing
                                ? 'Submitting...'
                                : isEdit
                                  ? 'Update'
                                  : enquiryId
                                    ? 'Confirm Enquiry'
                                    : 'Create'}
                        </Button>
                    )}
                </div>
                {/* Enquiry dialog */}
                <EnquiryViewDialog
                    open={enquiryDialogOpen}
                    onOpenChange={setEnquiryDialogOpen}
                    enquiryId={effectiveLinkedEnquiry?.id}
                    enquiryType={effectiveLinkedEnquiry?.type}
                    statusLabel={effectiveLinkedEnquiry?.status}
                    childData={
                        linkedEnquiryChild as Record<string, unknown> | null
                    }
                    isLoadingChild={isLoadingEnquiryChild}
                    showStatusActions={false}
                />

                {/* Package dialog */}
                <Dialog
                    open={packageDialogOpen}
                    onOpenChange={setPackageDialogOpen}
                >
                    <DialogContent className="flex max-h-[95%] max-w-[95%] min-w-[95%] flex-col">
                        <DialogHeader>
                            <DialogTitle>Package Details</DialogTitle>
                            <DialogDescription className="sr-only">
                                View package details
                            </DialogDescription>
                        </DialogHeader>

                        <div className="h-full w-full flex-1 overflow-y-auto pb-2">
                            {linkedPackageData ? (
                                <PackageForm
                                    mode="view"
                                    initialData={linkedPackageData}
                                    onCancel={() => setPackageDialogOpen(false)}
                                />
                            ) : (
                                <div className="text-sm text-muted-foreground">
                                    Loading package details...
                                </div>
                            )}
                        </div>
                    </DialogContent>
                </Dialog>
            </form>
        </div>
    );
}
