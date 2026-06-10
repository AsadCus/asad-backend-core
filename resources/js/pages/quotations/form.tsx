import { FormProgressHeader } from '@/components/form-progress-header';
import { Accordion } from '@/components/ui/accordion';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { navigateToSection } from '@/lib/navigation-helper';
import { formatDateForDisplay } from '@/lib/utils';
import { show as showCustomerConfirmation } from '@/routes/customer-confirmations';
import { OptionType, SharedData } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';
import { nanoid } from 'nanoid';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { UserSchema } from '../masters/users/schema';
import { NoteSchema } from '../notes/schema';
import { sharingPlanLabels } from '../packages/schema';
import QuotationDetailSection from './components/quotation-detail-section';
import QuotationInformationSection from './components/quotation-information-section';
import QuotationPreviewModal from './components/quotation-preview-modal';
import StatusSection from './components/status-section';
import { useQuotationSectionStatus } from './hooks/use-quotation-section-status';
import { QuotationItemSchema, quotationItemsSchema } from './items/schema';
import { QuotationSchema } from './schema';
import { quotationFormValidationSchema } from './validation';

interface QuotationFormProps {
    mode: 'create' | 'edit' | 'view';
    initialData?: QuotationSchema;
    paymentPlans?: OptionType[];
    statuses?: OptionType[];
    customerConfirmations?: OptionType[];
    activeCustomers?: Array<{
        value: number;
        label: string;
        name: string;
        contact?: string | null;
        address?: string | null;
        email?: string | null;
    }>;
    quotationItems?: QuotationItemSchema[];
    quotationNotes?: NoteSchema[];
    extensionMasters?: Array<{
        id?: number;
        name: string;
        type: string;
        calculation_mode?: string | null;
        calculation_value?: string | number | null;
        payment_methods?: string[];
        is_active?: boolean;
        sort_order?: number;
    }>;
    defaultExtensions?: QuotationSchema['extensions'];
    prefilledCustomerId?: string;
    prefilledCustomerData?: UserSchema;
    salespersons?: Array<{
        value: number | string;
        label: string;
        country_id?: number | null;
        country_ids?: number[];
        country_name?: string | null;
    }>;
    onCancel?: () => void;
}

type AvailableMember = {
    member_id: number;
    customer_id: number;
    name: string;
    sharing_plan: string | null;
    status: string;
    is_leader?: boolean;
    has_quotation?: boolean;
    contact_number?: string;
    address?: string;
    email?: string;
};

type PackagePrices = {
    single: number;
    double: number;
    triple: number;
    quad: number;
    childWithBed: number;
    childNoBed: number;
    infant: number;
};

type QuotationExtensionInput = NonNullable<
    QuotationSchema['extensions']
>[number];

const EMPTY_PACKAGE_PRICES: PackagePrices = {
    single: 0,
    double: 0,
    triple: 0,
    quad: 0,
    childWithBed: 0,
    childNoBed: 0,
    infant: 0,
};

const UMRAH_PACKAGES_HEADER_LABEL = 'Umrah Packages';

function formatSharingPlanLabel(sharingPlan: string | null): string {
    const normalized = String(sharingPlan ?? '')
        .trim()
        .toLowerCase();

    if (normalized.length === 0) {
        return 'Standard';
    }

    return sharingPlanLabels[normalized] ?? String(sharingPlan);
}

function isUmrahPackagesRootHeader(item: QuotationItemSchema): boolean {
    return (
        Boolean(item.is_header) &&
        item.parent_id == null &&
        item.parent_key == null &&
        String(item.description ?? '')
            .trim()
            .toLowerCase() === UMRAH_PACKAGES_HEADER_LABEL.toLowerCase()
    );
}

function getLinkedMemberIds(items: QuotationItemSchema[] = []): number[] {
    return Array.from(
        new Set(
            items
                .map((item) =>
                    Number(item.customer_confirmation_member_id ?? 0),
                )
                .filter((value) => value > 0),
        ),
    );
}

function getPackagePricesFromConfirmation(confirmation: {
    package_price_single?: number | string | null;
    package_price_double?: number | string | null;
    package_price_triple?: number | string | null;
    package_price_quad?: number | string | null;
    package_price_child_with_bed?: number | string | null;
    package_price_child_no_bed?: number | string | null;
    package_price_infant?: number | string | null;
    child_with_bed_price?: number | string | null;
    child_no_bed_price?: number | string | null;
    infant_price?: number | string | null;
}): PackagePrices {
    return {
        single: Number(confirmation.package_price_single ?? 0),
        double: Number(confirmation.package_price_double ?? 0),
        triple: Number(confirmation.package_price_triple ?? 0),
        quad: Number(confirmation.package_price_quad ?? 0),
        childWithBed: Number(
            confirmation.package_price_child_with_bed ??
                confirmation.child_with_bed_price ??
                0,
        ),
        childNoBed: Number(
            confirmation.package_price_child_no_bed ??
                confirmation.child_no_bed_price ??
                0,
        ),
        infant: Number(
            confirmation.package_price_infant ?? confirmation.infant_price ?? 0,
        ),
    };
}

function resolveAutoSelectedMemberIds(
    isCreate: boolean,
    eligibleMembers: AvailableMember[],
    linkedMemberIds: Set<number>,
    sourceItems: QuotationItemSchema[],
): number[] {
    const selectedFromLinkedIds = isCreate
        ? eligibleMembers.map((member) => member.member_id)
        : Array.from(linkedMemberIds).filter((memberId) =>
              eligibleMembers.some((member) => member.member_id === memberId),
          );

    const selectedFromHasQuotation = !isCreate
        ? eligibleMembers
              .filter((member) => member.has_quotation)
              .map((member) => member.member_id)
        : [];

    const selectedFromLegacyItems =
        !isCreate &&
        sourceItems.length > 0 &&
        selectedFromLinkedIds.length === 0 &&
        selectedFromHasQuotation.length === 0
            ? eligibleMembers.map((member) => member.member_id)
            : [];

    if (selectedFromLinkedIds.length > 0) {
        return selectedFromLinkedIds;
    }

    if (selectedFromHasQuotation.length > 0) {
        return selectedFromHasQuotation;
    }

    return selectedFromLegacyItems;
}

function mergeQuotationExtensionsByNameAndType(
    extensions: QuotationExtensionInput[] = [],
): QuotationExtensionInput[] {
    const grouped = new Map<string, QuotationExtensionInput>();

    extensions.forEach((extension, index) => {
        const name = String(extension.name ?? '').trim() || 'Extension';
        const type = String(extension.type ?? 'discount')
            .trim()
            .toLowerCase();
        const key = `${name.toLowerCase()}|${type}`;
        const value = Number(
            extension.calculation_value ?? extension.amount ?? 0,
        );

        if (!grouped.has(key)) {
            grouped.set(key, {
                ...extension,
                name,
                type,
                quotation_extension_master_id: null,
                calculation_mode:
                    String(extension.calculation_mode ?? 'fixed') ===
                    'percentage'
                        ? 'percentage'
                        : 'fixed',
                calculation_value: value,
                amount: Number(extension.amount ?? value),
                sort_order: Number(extension.sort_order ?? index + 1),
            });

            return;
        }

        const current = grouped.get(key);

        if (!current) {
            return;
        }

        const mergedValue =
            Number(current.calculation_value ?? current.amount ?? 0) + value;

        grouped.set(key, {
            ...current,
            quotation_extension_master_id: null,
            calculation_mode: 'fixed',
            calculation_value: mergedValue,
            amount: mergedValue,
        });
    });

    return Array.from(grouped.values()).map((extension, index) => ({
        ...extension,
        sort_order: index + 1,
    }));
}

export function QuotationForm({
    mode,
    initialData,
    paymentPlans = [],
    statuses = [],
    customerConfirmations = [],
    activeCustomers = [],
    quotationItems = [],
    quotationNotes = [],
    extensionMasters = [],
    prefilledCustomerId,
    prefilledCustomerData,
    salespersons = [],
    onCancel,
}: QuotationFormProps) {
    const isView = mode === 'view';
    const isEdit = mode === 'edit';
    const isCreate = mode === 'create';

    const { auth } = usePage<SharedData>().props;
    const authRoles = auth?.roles ?? [];
    const isSuperadmin = authRoles.includes('superadmin');
    const isSalesOrAdmin =
        authRoles.includes('sales') || authRoles.includes('admin');
    const authUserId = auth?.user?.id != null ? Number(auth.user.id) : null;

    const initialNotes: NoteSchema[] = (
        initialData?.notes?.length ? initialData.notes : quotationNotes
    ).map((note) => ({
        ...note,
        _key: note.id ? `id-${note.id}` : nanoid(),
        model: 'quotation',
    }));

    const today = formatDateForDisplay(new Date());

    const initialSalespersonId =
        isCreate && isSalesOrAdmin && !isSuperadmin && authUserId
            ? authUserId
            : (initialData?.salesperson_id ?? null);

    const initialFormState: QuotationSchema = {
        id: undefined,
        quotation_number: '',
        number_format_id: null,
        quotation_date: today,
        expiry_date: today,
        customer_id: undefined,
        customer_confirmation_id: undefined,
        salesperson_id: initialSalespersonId,
        customer_name: '',
        nric_number: '',
        customer_contact: '',
        customer_address: '',
        customer_email: null,
        description: '',
        payment_plan: 'full',
        status: 'draft',
        reason: '',
        items: [],
        extensions: [],
        invoice_extensions: [],
        order_invoices_total_amount: null,
        model: 'quotation',
        notes: [],
    };

    const normalizedExtensions = mergeQuotationExtensionsByNameAndType(
        (initialData?.extensions ?? []).map((extension) => ({
            ...extension,
            _key:
                extension._key ??
                (extension.id ? `id-${extension.id}` : nanoid()),
            type: extension.type || 'discount',
            calculation_mode: extension.calculation_mode ?? 'fixed',
            calculation_value: extension.calculation_value ?? extension.amount,
            quotation_extension_master_id:
                extension.quotation_extension_master_id ?? null,
            name: extension.name || 'Discount',
            sort_order: extension.sort_order ?? 1,
        })),
    );

    const defaultData: QuotationSchema = {
        ...(initialData ?? initialFormState),
        notes: initialNotes,
        extensions: initialData ? normalizedExtensions : [],
        ...(prefilledCustomerId && prefilledCustomerData
            ? {
                  customer_id: Number.parseInt(prefilledCustomerId, 10),
                  customer_name: prefilledCustomerData.name || '',
                  customer_contact: prefilledCustomerData.contact || '',
                  customer_address: prefilledCustomerData.address || '',
                  customer_email: prefilledCustomerData.email || '',
              }
            : {}),
    };

    const initialLinkedMemberIds = getLinkedMemberIds(defaultData.items ?? []);

    const {
        data,
        setData,
        post,
        put,
        processing,
        errors,
        reset,
        setError,
        clearErrors,
    } = useForm<QuotationSchema>(defaultData);

    const sectionErrors = errors as Partial<
        Record<keyof QuotationSchema, string>
    >;
    const errorAlertRef = useRef<HTMLDivElement | null>(null);
    const [openSections, setOpenSections] = useState<string[]>([
        'customer_and_quotation_information',
    ]);
    const { sections, getQuotationSectionStatus } = useQuotationSectionStatus({
        data,
        errors: sectionErrors,
    });
    const [selectedCustomerData, setSelectedCustomerData] =
        useState<UserSchema | null>(null);
    const [availableMembers, setAvailableMembers] = useState<AvailableMember[]>(
        [],
    );
    const [selectedMemberIds, setSelectedMemberIds] = useState<number[]>(
        initialLinkedMemberIds,
    );
    const [handlerMemberId, setHandlerMemberId] = useState<number | null>(null);

    const [packagePrices, setPackagePrices] =
        useState<PackagePrices>(EMPTY_PACKAGE_PRICES);

    const [confirmationCountryId, setConfirmationCountryId] = useState<
        number | null
    >(null);

    const salespersonOptions = useMemo<OptionType[]>(() => {
        let filtered = salespersons ?? [];
        if (confirmationCountryId) {
            const targetCountryId = Number(confirmationCountryId);
            filtered = filtered.filter((option) => {
                const isSelected =
                    String(option.value) === String(data.salesperson_id);
                if (isSelected) return true;
                if (
                    option.country_id != null &&
                    Number(option.country_id) === targetCountryId
                )
                    return true;
                if (
                    option.country_ids &&
                    Array.isArray(option.country_ids) &&
                    option.country_ids.map(Number).includes(targetCountryId)
                )
                    return true;
                return false;
            });
        }
        return filtered.map((option) => {
            let suffix = '';
            if (option.country_name) {
                suffix = ` (${option.country_name})`;
            }

            const isSelected =
                String(option.value) === String(data.salesperson_id);
            let mismatchSuffix = '';
            if (isSelected && confirmationCountryId) {
                const targetCountryId = Number(confirmationCountryId);
                const matches =
                    (option.country_id != null &&
                        Number(option.country_id) === targetCountryId) ||
                    (Array.isArray(option.country_ids) &&
                        option.country_ids
                            .map(Number)
                            .includes(targetCountryId));
                if (!matches) {
                    mismatchSuffix = ' (Country Mismatch)';
                }
            }

            return {
                label: `${option.label}${suffix}${mismatchSuffix}`,
                value: String(option.value),
            };
        });
    }, [salespersons, confirmationCountryId, data.salesperson_id]);

    const activeExtensionMasters = useMemo(
        () =>
            extensionMasters
                .filter((master) => master.is_active !== false)
                .sort(
                    (left, right) =>
                        Number(left.sort_order ?? 0) -
                        Number(right.sort_order ?? 0),
                ),
        [extensionMasters],
    );

    const mergedFormExtensions = useMemo(
        () => mergeQuotationExtensionsByNameAndType(data.extensions ?? []),
        [data.extensions],
    );

    const computedGrandTotal = useMemo(() => {
        const invoiceBackedAmount = Number(
            data.order_invoices_total_amount ?? data.total_amount,
        );

        if (
            Boolean(data.have_invoices) &&
            Number.isFinite(invoiceBackedAmount)
        ) {
            return invoiceBackedAmount;
        }

        const sourceItems = data.items ?? [];
        const sourceExtensions = mergedFormExtensions;

        const subtotalAmount = sourceItems.reduce((sum, item) => {
            if (item.is_header) {
                return sum;
            }

            return sum + Number(item.quantity ?? 0) * Number(item.rate ?? 0);
        }, 0);

        const itemTaxTotal = sourceItems.reduce((sum, item) => {
            if (item.is_header) {
                return sum;
            }

            const lineAmount =
                Number(item.quantity ?? 0) * Number(item.rate ?? 0);

            const taxTotal = (item.taxes ?? []).reduce((taxSum, tax) => {
                const calculationMode = String(tax.calculation_mode ?? '');
                const calculationValue = Number(tax.calculation_value ?? 0);

                if (
                    !['fixed', 'percentage'].includes(calculationMode) ||
                    calculationValue === 0
                ) {
                    return taxSum;
                }

                return (
                    taxSum +
                    (calculationMode === 'percentage'
                        ? (lineAmount * calculationValue) / 100
                        : calculationValue)
                );
            }, 0);

            return sum + taxTotal;
        }, 0);

        const nonDiscountExtensionTotal = sourceExtensions.reduce(
            (sum, extension) => {
                if (String(extension.type ?? 'discount') === 'discount') {
                    return sum;
                }

                const calculationMode = String(
                    extension.calculation_mode ?? 'fixed',
                );
                const calculationValue = Number(
                    extension.calculation_value ?? extension.amount ?? 0,
                );
                const type = String(extension.type ?? '');

                if (
                    (type === 'tax' || type === 'credit_card') &&
                    calculationMode === 'percentage'
                ) {
                    return sum + (subtotalAmount * calculationValue) / 100;
                }

                if (type === 'tax' || type === 'credit_card') {
                    return sum + calculationValue;
                }

                return sum + Number(extension.amount ?? 0);
            },
            0,
        );

        const discountAmount = sourceExtensions
            .filter(
                (extension) =>
                    String(extension.type ?? 'discount') === 'discount',
            )
            .reduce((sum, discountExtension) => {
                const calculationMode = String(
                    discountExtension.calculation_mode ?? 'fixed',
                );
                const calculationValue = Math.abs(
                    Number(
                        discountExtension.calculation_value ??
                            discountExtension.amount ??
                            0,
                    ),
                );

                const computed =
                    calculationMode === 'percentage'
                        ? (subtotalAmount * calculationValue) / 100
                        : calculationValue;

                return sum - Math.abs(computed);
            }, 0);

        return (
            subtotalAmount +
            itemTaxTotal +
            nonDiscountExtensionTotal +
            discountAmount
        );
    }, [
        data.have_invoices,
        data.items,
        data.order_invoices_total_amount,
        data.total_amount,
        mergedFormExtensions,
    ]);

    useEffect(() => {
        if (activeExtensionMasters.length === 0) {
            return;
        }

        const shouldSyncAutoExtensions = !isEdit;

        if (!shouldSyncAutoExtensions) {
            return;
        }

        setData((prev) => {
            const existingExtensions = prev.extensions ?? [];
            const existingAutoByMasterId = new Map(
                existingExtensions
                    .filter(
                        (extension) =>
                            Number(
                                extension.quotation_extension_master_id ?? 0,
                            ) > 0,
                    )
                    .map((extension) => [
                        Number(extension.quotation_extension_master_id),
                        extension,
                    ]),
            );

            const manualExtensions = existingExtensions.filter(
                (extension) =>
                    Number(extension.quotation_extension_master_id ?? 0) <= 0,
            );

            const applicableMasters = activeExtensionMasters.filter(
                (master) => {
                    if (master.type === 'tax') {
                        return false;
                    }

                    if (isCreate) {
                        return false;
                    }

                    return true;
                },
            );

            const autoExtensions = applicableMasters.map((master, index) => {
                const masterId = Number(master.id ?? 0);
                const existing = existingAutoByMasterId.get(masterId);
                const calculationMode =
                    existing?.calculation_mode ??
                    master.calculation_mode ??
                    'fixed';
                const calculationValue =
                    existing?.calculation_value ??
                    master.calculation_value ??
                    0;
                const fixedAmount =
                    calculationMode === 'fixed'
                        ? Number(calculationValue ?? 0)
                        : Number(existing?.amount ?? 0);

                return {
                    _key:
                        existing?._key ??
                        (existing?.id ? `id-${existing.id}` : nanoid()),
                    id: existing?.id,
                    quotation_extension_master_id: masterId || null,
                    name: existing?.name ?? master.name,
                    type: existing?.type ?? master.type,
                    calculation_mode: calculationMode,
                    calculation_value: calculationValue,
                    amount: fixedAmount,
                    sort_order: index + 1,
                };
            });

            const mergedExtensions = [
                ...autoExtensions,
                ...manualExtensions,
            ].map((extension, index) => ({
                ...extension,
                sort_order: index + 1,
            }));

            return {
                ...prev,
                extensions: mergedExtensions,
            };
        });
    }, [activeExtensionMasters, isCreate, isEdit, setData]);

    // customer
    useEffect(() => {
        if (selectedCustomerData) {
            setData((prev) => ({
                ...prev,
                customer_name: selectedCustomerData.name,
                customer_contact: selectedCustomerData.contact,
                customer_address: selectedCustomerData.address,
                customer_email: selectedCustomerData.email,
            }));
        }
    }, [selectedCustomerData, setData]);

    // Initialize prefilled data
    useEffect(() => {
        if (prefilledCustomerData && prefilledCustomerId && isCreate) {
            setSelectedCustomerData(prefilledCustomerData);
        }
    }, [prefilledCustomerData, prefilledCustomerId, isCreate]);

    const buildItemsFromMembers = useCallback(
        (
            memberIds: number[],
            existingItems: QuotationItemSchema[] = [],
            membersSource = availableMembers,
            packagePricesToUse?: typeof packagePrices,
            packageNameToUse?: string,
        ) => {
            const selectedMembers = membersSource.filter((member) =>
                memberIds.includes(member.member_id),
            );

            const existingItemByMemberId = new Map(
                existingItems
                    .filter(
                        (item) =>
                            Number(item.customer_confirmation_member_id ?? 0) >
                            0,
                    )
                    .map((item) => [
                        Number(item.customer_confirmation_member_id),
                        item,
                    ]),
            );

            // Keep existing items that are not linked to members (manual items)
            const nonMemberItems = existingItems.filter(
                (item) => !item.customer_confirmation_member_id,
            );

            const existingUmrahHeader = nonMemberItems.find((item) =>
                isUmrahPackagesRootHeader(item),
            );

            const getRateForPlan = (sharingPlan?: string | null) => {
                const prices = packagePricesToUse ?? packagePrices;
                if (sharingPlan === 'single') return prices.single;
                if (sharingPlan === 'double') return prices.double;
                if (sharingPlan === 'triple') return prices.triple;
                if (sharingPlan === 'quad') return prices.quad;
                if (sharingPlan === 'child_with_bed') {
                    return prices.childWithBed;
                }
                if (sharingPlan === 'child_no_bed') return prices.childNoBed;
                if (sharingPlan === 'infant') return prices.infant;
                return 0;
            };

            const resolvedPackageName =
                (packageNameToUse ?? data.package_name ?? '').trim() ||
                'Package';

            if (selectedMembers.length === 0) {
                if (!existingUmrahHeader) {
                    return nonMemberItems;
                }

                const hasNonMemberChildren = nonMemberItems.some(
                    (item) =>
                        item._key !== existingUmrahHeader._key &&
                        (item.parent_key === existingUmrahHeader._key ||
                            (existingUmrahHeader.id != null &&
                                item.parent_id === existingUmrahHeader.id)),
                );

                if (hasNonMemberChildren) {
                    return nonMemberItems;
                }

                return nonMemberItems.filter(
                    (item) => item._key !== existingUmrahHeader._key,
                );
            }

            const umrahHeader: QuotationItemSchema = existingUmrahHeader
                ? {
                      ...existingUmrahHeader,
                      _key:
                          existingUmrahHeader._key ||
                          (existingUmrahHeader.id
                              ? `id-${existingUmrahHeader.id}`
                              : nanoid()),
                      description: UMRAH_PACKAGES_HEADER_LABEL,
                      parent_id: null,
                      parent_key: null,
                      is_header: true,
                      quantity: null,
                      rate: null,
                      amount: null,
                  }
                : {
                      _key: nanoid(),
                      id: undefined,
                      quotation_id: undefined,
                      customer_confirmation_member_id: null,
                      sharing_plan: null,
                      parent_id: null,
                      parent_key: null,
                      description: UMRAH_PACKAGES_HEADER_LABEL,
                      is_header: true,
                      is_optional: false,
                      quantity: null,
                      rate: null,
                      amount: null,
                      sort_order: 1,
                  };

            const memberItems = selectedMembers.map((member, index) => {
                const existingItem = existingItemByMemberId.get(
                    member.member_id,
                );

                if (existingItem) {
                    const sharingPlanLabel = formatSharingPlanLabel(
                        member.sharing_plan,
                    );

                    return {
                        ...existingItem,
                        _key:
                            existingItem._key ||
                            (existingItem.id
                                ? `id-${existingItem.id}`
                                : nanoid()),
                        customer_confirmation_member_id: member.member_id,
                        sharing_plan: member.sharing_plan,
                        parent_id: umrahHeader.id ?? null,
                        parent_key: umrahHeader._key,
                        description: `${resolvedPackageName} - ${member.name} - ${sharingPlanLabel} sharing`,
                        sort_order: index + 2,
                    };
                }

                const rate = getRateForPlan(member.sharing_plan);
                const sharingPlanLabel = formatSharingPlanLabel(
                    member.sharing_plan,
                );

                return {
                    _key: nanoid(),
                    id: undefined,
                    quotation_id: undefined,
                    customer_confirmation_member_id: member.member_id,
                    sharing_plan: member.sharing_plan,
                    parent_id: umrahHeader.id ?? null,
                    parent_key: umrahHeader._key,
                    description: `${resolvedPackageName} - ${member.name} - ${sharingPlanLabel} sharing`,
                    is_header: false,
                    is_optional: false,
                    quantity: 1,
                    rate: rate,
                    amount: rate,
                    sort_order: index + 2,
                };
            });

            const remainingNonMemberItems = nonMemberItems.filter(
                (item) => item._key !== umrahHeader._key,
            );

            return [
                umrahHeader,
                ...memberItems,
                ...remainingNonMemberItems,
            ].map((item, index) => ({
                ...item,
                sort_order: index + 1,
            }));
        },
        [availableMembers, data.package_name, packagePrices],
    );

    const syncHandlerCustomer = useCallback(
        (memberId: number | null) => {
            if (!memberId) return;

            const member = availableMembers.find(
                (m) => m.member_id === memberId,
            );
            if (!member) return;

            setData((prev) => ({
                ...prev,
                customer_id: member.customer_id,
                customer_name: member.name,
                customer_contact:
                    member.contact_number ?? prev.customer_contact,
                customer_address: member.address ?? prev.customer_address,
                customer_email: member.email ?? prev.customer_email,
            }));
        },
        [availableMembers, setData],
    );

    const loadCustomerConfirmation = useCallback(
        async (confirmationId: number) => {
            const response = await fetch(
                showCustomerConfirmation(confirmationId).url,
            );
            if (!response.ok) {
                throw new Error('Failed to load customer confirmation');
            }

            const confirmation = await response.json();

            const members = (confirmation.members ?? []) as Array<
                AvailableMember & {
                    id?: number;
                }
            >;

            const normalizedMembers = members
                .map((member) => ({
                    ...member,
                    member_id: Number(member.member_id ?? member.id ?? 0),
                }))
                .filter((member) => member.member_id > 0);

            const sourceItems =
                data.items?.length > 0
                    ? data.items
                    : (initialData?.items ?? []);

            const linkedMemberIds = new Set(
                sourceItems
                    .map((item) =>
                        Number(item.customer_confirmation_member_id ?? 0),
                    )
                    .filter((value) => value > 0),
            );

            const eligible = normalizedMembers.filter((member) => {
                if (linkedMemberIds.has(member.member_id)) {
                    return true;
                }

                return (
                    member.status === 'pending_payment' && !member.has_quotation
                );
            });

            setAvailableMembers(eligible);

            const autoSelectedMemberIds = resolveAutoSelectedMemberIds(
                isCreate,
                eligible,
                linkedMemberIds,
                sourceItems,
            );

            setSelectedMemberIds(autoSelectedMemberIds);

            const currentHandlerMember = eligible.find(
                (member) =>
                    member.customer_id === data.customer_id &&
                    autoSelectedMemberIds.includes(member.member_id),
            );

            const nextHandlerId =
                currentHandlerMember?.member_id ??
                (autoSelectedMemberIds.length === 1
                    ? autoSelectedMemberIds[0]
                    : (autoSelectedMemberIds[0] ?? null));

            setHandlerMemberId(nextHandlerId);

            const extractedPackagePrices =
                getPackagePricesFromConfirmation(confirmation);
            const resolvedConfirmationPackageName =
                confirmation.package_name ??
                confirmation.package_data?.name ??
                '';

            const eligibleMemberById = new Map(
                eligible.map((member) => [member.member_id, member]),
            );
            const selectedHandlerMember = nextHandlerId
                ? eligibleMemberById.get(nextHandlerId)
                : null;

            setPackagePrices(extractedPackagePrices);
            setConfirmationCountryId(confirmation.package_country_id ?? null);

            setData((prev) => ({
                ...prev,
                customer_confirmation_id: confirmationId,
                package_name:
                    confirmation.package_name ??
                    confirmation.package_data?.name ??
                    prev.package_name,
                package_price_single: Number(
                    confirmation.package_price_single ?? 0,
                ),
                package_price_double: Number(
                    confirmation.package_price_double ?? 0,
                ),
                package_price_triple: Number(
                    confirmation.package_price_triple ?? 0,
                ),
                package_price_quad: Number(
                    confirmation.package_price_quad ?? 0,
                ),
                package_price_child_with_bed: Number(
                    confirmation.package_price_child_with_bed ??
                        confirmation.child_with_bed_price ??
                        0,
                ),
                package_price_child_no_bed: Number(
                    confirmation.package_price_child_no_bed ??
                        confirmation.child_no_bed_price ??
                        0,
                ),
                package_price_infant: Number(
                    confirmation.package_price_infant ??
                        confirmation.infant_price ??
                        0,
                ),
                ...(isCreate
                    ? {
                          items: buildItemsFromMembers(
                              autoSelectedMemberIds,
                              prev.items ?? [],
                              eligible,
                              extractedPackagePrices,
                              resolvedConfirmationPackageName,
                          ),
                      }
                    : {}),
                customer_id:
                    selectedHandlerMember?.customer_id ?? prev.customer_id,
                customer_name:
                    selectedHandlerMember?.name ?? prev.customer_name,
                customer_contact:
                    selectedHandlerMember?.contact_number ??
                    prev.customer_contact,
                customer_address:
                    selectedHandlerMember?.address ?? prev.customer_address,
                customer_email:
                    selectedHandlerMember?.email ?? prev.customer_email,
            }));
        },
        [
            buildItemsFromMembers,
            data.customer_id,
            data.items,
            initialData?.items,
            isCreate,
            setData,
        ],
    );

    const loadedConfirmationRef = useRef<number | null>(null);

    const clearCustomerConfirmationSelection = useCallback(() => {
        loadedConfirmationRef.current = null;
        setAvailableMembers([]);
        setSelectedMemberIds([]);
        setHandlerMemberId(null);
        setPackagePrices(EMPTY_PACKAGE_PRICES);
        setConfirmationCountryId(null);
        setSelectedCustomerData(null);

        setData((prev) => ({
            ...prev,
            items: (() => {
                const nonMemberItems = (prev.items ?? []).filter(
                    (item) => !item.customer_confirmation_member_id,
                );

                const umrahHeader = nonMemberItems.find((item) =>
                    isUmrahPackagesRootHeader(item),
                );

                if (!umrahHeader) {
                    return nonMemberItems;
                }

                const hasChildren = nonMemberItems.some(
                    (item) =>
                        item._key !== umrahHeader._key &&
                        (item.parent_key === umrahHeader._key ||
                            (umrahHeader.id != null &&
                                item.parent_id === umrahHeader.id)),
                );

                if (hasChildren) {
                    return nonMemberItems;
                }

                return nonMemberItems.filter(
                    (item) => item._key !== umrahHeader._key,
                );
            })(),
            customer_confirmation_id: null,
            customer_id: null,
            customer_name: '',
            customer_contact: '',
            customer_address: '',
            customer_email: '',
            package_name: '',
            package_price_single: 0,
            package_price_double: 0,
            package_price_triple: 0,
            package_price_quad: 0,
            package_price_child_with_bed: 0,
            package_price_child_no_bed: 0,
            package_price_infant: 0,
        }));
    }, [setData]);

    useEffect(() => {
        if (!data.customer_confirmation_id) {
            loadedConfirmationRef.current = null;
            return;
        }

        if (loadedConfirmationRef.current === data.customer_confirmation_id) {
            return;
        }

        loadedConfirmationRef.current = data.customer_confirmation_id;

        loadCustomerConfirmation(Number(data.customer_confirmation_id)).catch(
            () => {
                setAvailableMembers([]);
            },
        );
    }, [data.customer_confirmation_id, isCreate, loadCustomerConfirmation]);

    useEffect(() => {
        syncHandlerCustomer(handlerMemberId);
    }, [handlerMemberId, syncHandlerCustomer]);

    const linkedMemberIdsFromItems = useMemo(
        () => getLinkedMemberIds(data.items ?? []),
        [data.items],
    );

    const effectiveSelectedMemberIds = useMemo(() => {
        if (selectedMemberIds.length > 0) {
            return selectedMemberIds;
        }

        return linkedMemberIdsFromItems;
    }, [selectedMemberIds, linkedMemberIdsFromItems]);

    const selectedAvailableMembers = useMemo(
        () =>
            availableMembers.filter((member) =>
                effectiveSelectedMemberIds.includes(member.member_id),
            ),
        [availableMembers, effectiveSelectedMemberIds],
    );

    const customerOptions = useMemo(() => {
        if (data.customer_confirmation_id) {
            return selectedAvailableMembers.map((member) => ({
                label: member.name,
                value: String(member.member_id),
            }));
        }

        return activeCustomers.map((customer) => ({
            label: customer.label,
            value: String(customer.value),
        }));
    }, [
        activeCustomers,
        data.customer_confirmation_id,
        selectedAvailableMembers,
    ]);

    const selectedCustomerValue = useMemo(() => {
        if (data.customer_confirmation_id) {
            return handlerMemberId ? String(handlerMemberId) : '';
        }

        return data.customer_id ? String(data.customer_id) : '';
    }, [data.customer_confirmation_id, data.customer_id, handlerMemberId]);

    useEffect(() => {
        if (
            !isEdit ||
            availableMembers.length === 0 ||
            selectedMemberIds.length > 0
        ) {
            return;
        }

        const fallbackMemberIds =
            linkedMemberIdsFromItems.length > 0
                ? linkedMemberIdsFromItems.filter((memberId) =>
                      availableMembers.some(
                          (member) => member.member_id === memberId,
                      ),
                  )
                : availableMembers
                      .filter((member) => member.has_quotation)
                      .map((member) => member.member_id);

        const resolvedFallbackMemberIds =
            fallbackMemberIds.length > 0
                ? fallbackMemberIds
                : linkedMemberIdsFromItems.length === 0 &&
                    (data.items?.length ?? 0) > 0
                  ? availableMembers.map((member) => member.member_id)
                  : [];

        if (resolvedFallbackMemberIds.length === 0) {
            return;
        }

        setSelectedMemberIds(resolvedFallbackMemberIds);

        if (
            !handlerMemberId ||
            !resolvedFallbackMemberIds.includes(handlerMemberId)
        ) {
            setHandlerMemberId(resolvedFallbackMemberIds[0]);
        }
    }, [
        isEdit,
        availableMembers,
        selectedMemberIds.length,
        linkedMemberIdsFromItems,
        handlerMemberId,
        data.items,
    ]);

    // items
    const initializedRef = useRef(false);

    useEffect(() => {
        if (initializedRef.current) return;

        if (data.items?.length) {
            const updateItems = data.items.map((item) => {
                const key = item._key || (item.id ? `id-${item.id}` : nanoid());
                return {
                    ...item,
                    _key: key,
                };
            });
            setData('items', updateItems);
        } else if (quotationItems?.length) {
            const initialItems = quotationItems.map((item) => ({
                ...item,
                _key: item.id ? `id-${item.id}` : nanoid(),
            }));

            setData('items', initialItems);
        }

        initializedRef.current = true;
    }, [data.items, quotationItems, setData]);

    // validation
    function validateClientSide() {
        clearErrors();
        let valid = true;

        const quotationResult = quotationFormValidationSchema.safeParse(data);

        if (!quotationResult.success) {
            quotationResult.error.issues.forEach((issue) => {
                const key = issue.path.join('.') as keyof QuotationSchema;
                setError(key, issue.message);
            });
            valid = false;
        }

        const itemsResult = quotationItemsSchema.safeParse({
            items: data.items ?? [],
        });

        if (!itemsResult.success) {
            itemsResult.error.issues.forEach((issue) => {
                const path = issue.path.join('.');
                setError(path as unknown as keyof typeof errors, issue.message);
            });
            valid = false;
        }

        if (data.customer_confirmation_id && data.package_name?.trim()) {
            const missingSharingPlanMembers = selectedAvailableMembers
                .filter(
                    (member) =>
                        String(member.sharing_plan ?? '').trim().length === 0,
                )
                .map((member) => member.name);

            if (missingSharingPlanMembers.length > 0) {
                setError(
                    'customer_confirmation_id',
                    `Customer confirmation member(s) missing sharing plan: ${missingSharingPlanMembers.join(', ')}. Please set sharing plan before creating quotation.`,
                );
                valid = false;
            }
        }

        return valid;
    }

    // action
    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (!validateClientSide()) {
            scrollToErrorBanner();

            return;
        }

        const url = '/quotation';

        if (isCreate) {
            post(url, {
                onError: (nextErrors) => {
                    setError(nextErrors);
                    scrollToErrorBanner();
                },
            });
        } else if (isEdit) {
            put(`${url}/${data.id}`, {
                onError: (nextErrors) => {
                    setError(nextErrors);
                    scrollToErrorBanner();
                },
            });
        }
    }

    // err
    function formatError(path: string, message: string) {
        const parts = path.split('.');

        if (parts[0] === 'items' && parts.length >= 3) {
            const index = Number(parts[1]) + 1;
            const field = parts[2];
            const fieldLabelMap: Record<string, string> = {
                description: 'Description',
                rate: 'Cost',
                sort_order: 'Sort Order',
            };

            return `Item #${index} ${fieldLabelMap[field] ?? field} ${message.replace(/^The\s.+?\s/, '')}`;
        }

        return message;
    }

    const errorMap = errors as Record<string, string | undefined>;

    const renderError = (path: string) => {
        const message = errorMap[path];

        if (!message) return null;

        return (
            <p className="mt-1 text-sm text-red-500">
                {formatError(path, message)}
            </p>
        );
    };

    const hasErrors = Object.keys(errorMap).length > 0;
    const toFieldLabel = (path: string): string => {
        const fieldName = path.split('.').pop() ?? path;

        return fieldName
            .replace(/[_-]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim()
            .replace(/\b\w/g, (character) => character.toUpperCase());
    };

    const scrollToErrorBanner = useCallback(() => {
        setTimeout(() => {
            errorAlertRef.current?.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
        }, 0);
    }, []);

    const resolveErrorTarget = useCallback((path: string) => {
        if (path === 'customer_id') {
            return {
                section: 'customer_and_quotation_information',
                targetId: 'customer_id',
            };
        }

        if (path === 'quotation_date' || path === 'expiry_date') {
            return {
                section: 'customer_and_quotation_information',
                targetId: path,
            };
        }

        if (
            path === 'description' ||
            path === 'payment_plan' ||
            path.startsWith('items.') ||
            path.startsWith('extensions.') ||
            path.startsWith('notes.')
        ) {
            return {
                section: 'quotation_details',
                targetId:
                    path === 'description' || path === 'payment_plan'
                        ? path
                        : 'section-quotation-items',
            };
        }

        if (path === 'status' || path === 'reason') {
            return {
                section: 'status',
                targetId: 'section-status',
            };
        }

        return {
            section: 'customer_and_quotation_information',
            targetId: 'section-quotation-information',
        };
    }, []);

    const focusErrorField = useCallback(
        (path: string) => {
            const target = resolveErrorTarget(path);

            setOpenSections((prev) => {
                if (prev.includes(target.section)) {
                    return prev;
                }

                return [...prev, target.section];
            });

            setTimeout(() => {
                const targetElement = document.getElementById(target.targetId);

                if (!targetElement) {
                    return;
                }

                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center',
                });

                if (targetElement instanceof HTMLElement) {
                    targetElement.focus({ preventScroll: true });
                }
            }, 180);
        },
        [resolveErrorTarget],
    );

    const errorSummary = useMemo(
        () =>
            Object.entries(errorMap)
                .filter(([, message]) => Boolean(message))
                .map(([path, message]) => ({
                    path,
                    message: String(message),
                })),
        [errorMap],
    );

    const noteErrors = Object.entries(errorMap)
        .filter(([key, message]) => key.startsWith('notes') && Boolean(message))
        .map(([, message]) => String(message));

    // misc
    const handleSectionClick = useCallback(
        (sectionId: string) => {
            navigateToSection(sectionId, setOpenSections);
        },
        [setOpenSections],
    );

    const handleReset = () => {
        reset();
    };

    const handleCustomerConfirmationChange = useCallback(
        (value: string | number) => {
            const confirmationId = Number(value ?? 0);

            if (!confirmationId) {
                clearCustomerConfirmationSelection();

                return;
            }

            loadedConfirmationRef.current = confirmationId;

            loadCustomerConfirmation(confirmationId).catch(() => {
                clearCustomerConfirmationSelection();
            });
        },
        [clearCustomerConfirmationSelection, loadCustomerConfirmation],
    );

    const handleSelectedMembersChange = useCallback(
        (memberIds: number[]) => {
            setSelectedMemberIds(memberIds);

            if (!isView) {
                const nextItems = buildItemsFromMembers(
                    memberIds,
                    data.items ?? [],
                    availableMembers,
                    packagePrices,
                );
                setData('items', nextItems);
            }

            if (!memberIds.length) {
                setHandlerMemberId(null);

                return;
            }

            if (!handlerMemberId || !memberIds.includes(handlerMemberId)) {
                setHandlerMemberId(memberIds[0]);
            }
        },
        [
            availableMembers,
            buildItemsFromMembers,
            data.items,
            handlerMemberId,
            isView,
            packagePrices,
            setData,
        ],
    );

    const handleCustomerChange = useCallback(
        (value: string | number) => {
            if (data.customer_confirmation_id) {
                const memberId = Number(value);

                if (Number.isNaN(memberId) || memberId <= 0) {
                    setHandlerMemberId(null);
                    setData((prev) => ({
                        ...prev,
                        customer_id: null,
                        customer_name: '',
                        customer_contact: '',
                        customer_address: '',
                        customer_email: '',
                    }));

                    return;
                }

                setHandlerMemberId(memberId);

                return;
            }

            const customerId = Number(value);

            if (Number.isNaN(customerId) || customerId <= 0) {
                setSelectedCustomerData(null);
                setData((prev) => ({
                    ...prev,
                    customer_id: null,
                    customer_name: '',
                    customer_contact: '',
                    customer_address: '',
                    customer_email: '',
                }));

                return;
            }

            const customer = activeCustomers.find(
                (option) => Number(option.value) === customerId,
            );

            setData((prev) => ({
                ...prev,
                customer_id: customerId,
                customer_name: customer?.name ?? prev.customer_name,
                customer_contact: customer?.contact ?? prev.customer_contact,
                customer_address: customer?.address ?? prev.customer_address,
                customer_email: customer?.email ?? prev.customer_email,
            }));
        },
        [activeCustomers, data.customer_confirmation_id, setData],
    );

    useEffect(() => {
        if (!isEdit) {
            return;
        }

        const queryParams = new URLSearchParams(window.location.search);
        const focusField = queryParams.get('focus_field');

        if (!focusField) {
            return;
        }

        const focusConfigByField: Record<
            string,
            { section: string; targetId: string }
        > = {
            customer_id: {
                section: 'customer_and_quotation_information',
                targetId: 'section-quotation-information',
            },
            quotation_date: {
                section: 'customer_and_quotation_information',
                targetId: 'quotation_date',
            },
            expiry_date: {
                section: 'customer_and_quotation_information',
                targetId: 'expiry_date',
            },
            description: {
                section: 'quotation_details',
                targetId: 'description',
            },
            payment_plan: {
                section: 'quotation_details',
                targetId: 'payment_plan',
            },
            items: {
                section: 'quotation_details',
                targetId: 'section-quotation-items',
            },
        };

        const focusConfig = focusConfigByField[focusField];

        if (!focusConfig) {
            return;
        }

        setOpenSections((prev) => {
            if (prev.includes(focusConfig.section)) {
                return prev;
            }

            return [...prev, focusConfig.section];
        });

        const timeoutId = window.setTimeout(() => {
            const targetElement = document.getElementById(focusConfig.targetId);

            if (!targetElement) {
                return;
            }

            targetElement.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
            });

            if (
                targetElement instanceof HTMLInputElement ||
                targetElement instanceof HTMLTextAreaElement ||
                targetElement instanceof HTMLButtonElement
            ) {
                targetElement.focus({ preventScroll: true });
            }
        }, 180);

        return () => {
            window.clearTimeout(timeoutId);
        };
    }, [isEdit]);

    // console.log(data);

    return (
        <div className="mx-auto w-full">
            {/* Progress Header */}
            {mode !== 'view' && (
                <FormProgressHeader
                    title="Quotation"
                    sections={sections}
                    onSectionClick={handleSectionClick}
                />
            )}

            {/* Quotation Number Box */}
            {isView && data.quotation_number && (
                <div className="mb-2 rounded-lg border border-primary/20 bg-primary/5 p-4">
                    <p className="text-base text-muted-foreground">
                        Quotation No.
                    </p>
                    <p className="text-2xl font-bold text-primary">
                        {data.quotation_number}
                    </p>
                </div>
            )}

            <form onSubmit={submit} className="space-y-6 py-2">
                {hasErrors && !isView && (
                    <Alert variant="destructive" ref={errorAlertRef}>
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>
                            <div className="space-y-2">
                                <p>
                                    Please fix the errors below and try again.
                                </p>
                                <div className="space-y-1">
                                    {errorSummary.map(({ path, message }) => (
                                        <button
                                            key={path}
                                            type="button"
                                            onClick={() =>
                                                focusErrorField(path)
                                            }
                                            className="block w-full rounded-sm text-left text-sm underline-offset-2 hover:underline"
                                        >
                                            {toFieldLabel(path)}: {message}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        </AlertDescription>
                    </Alert>
                )}

                <Accordion
                    type="multiple"
                    value={openSections}
                    onValueChange={setOpenSections}
                    className="space-y-4"
                >
                    <QuotationInformationSection
                        data={data}
                        isView={isView}
                        disableCustomerConfirmation={isView}
                        setData={setData}
                        grandTotalAmount={computedGrandTotal}
                        renderError={renderError}
                        customerConfirmations={customerConfirmations}
                        availableMembers={availableMembers}
                        selectedMemberIds={effectiveSelectedMemberIds}
                        customerOptions={customerOptions}
                        selectedCustomerValue={selectedCustomerValue}
                        salespersonOptions={salespersonOptions}
                        salespersonDisabled={isSalesOrAdmin && !isSuperadmin}
                        salespersonRequired={isSuperadmin}
                        quotationNumberError={
                            errorMap.quotation_number ??
                            errorMap.number_format_id
                        }
                        onCustomerConfirmationChange={
                            handleCustomerConfirmationChange
                        }
                        onSelectedMembersChange={handleSelectedMembersChange}
                        onCustomerChange={handleCustomerChange}
                        status={getQuotationSectionStatus(
                            'customer_and_quotation_information',
                        )}
                    />

                    <QuotationDetailSection
                        data={data}
                        isView={isView}
                        setData={setData}
                        renderError={renderError}
                        onChange={(nextItems) => setData('items', nextItems)}
                        items={data.items ?? []}
                        quotationNotes={data.notes}
                        noteErrors={noteErrors}
                        paymentPlans={paymentPlans}
                        extensionMasters={activeExtensionMasters}
                        availableMembers={availableMembers}
                        status={getQuotationSectionStatus('quotation_details')}
                    />

                    <StatusSection
                        data={data}
                        mode={mode}
                        initialStatus={initialData?.status ?? null}
                        isView={isView}
                        setData={setData}
                        renderError={renderError}
                        statuses={statuses}
                        status={getQuotationSectionStatus('status')}
                    />
                </Accordion>

                {/* Buttons */}
                <div className="flex justify-end gap-2">
                    <div className="flex justify-end gap-2">
                        <QuotationPreviewModal
                            data={data}
                            items={data.items ?? []}
                        />
                    </div>

                    {onCancel && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onCancel}
                        >
                            Back
                        </Button>
                    )}
                    {!isView && (
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={handleReset}
                                disabled={processing}
                            >
                                Reset
                            </Button>
                            <Button
                                type="submit"
                                className="min-w-[140px]"
                                disabled={processing}
                            >
                                {isEdit ? 'Update' : 'Create'}
                            </Button>
                        </>
                    )}
                </div>
            </form>
        </div>
    );
}
