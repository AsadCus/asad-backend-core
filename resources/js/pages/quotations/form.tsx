import { FormProgressHeader } from '@/components/form-progress-header';
import { Accordion } from '@/components/ui/accordion';
import { Button } from '@/components/ui/button';
import { navigateToSection } from '@/lib/navigation-helper';
import { formatDateForDisplay } from '@/lib/utils';
import { show as showCustomerConfirmation } from '@/routes/customer-confirmations';
import { OptionType } from '@/types';
import { useForm } from '@inertiajs/react';
import { nanoid } from 'nanoid';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { UserSchema } from '../masters/users/schema';
import { NoteSchema } from '../notes/schema';
import QuotationDetailSection from './components/quotation-detail-section';
import QuotationInformationSection from './components/quotation-information-section';
import QuotationPreviewModal from './components/quotation-preview-modal';
import StatusSection from './components/status-section';
import { useQuotationSectionStatus } from './hooks/use-quotation-section-status';
import { QuotationItemSchema, quotationItemsSchema } from './items/schema';
import { QuotationSchema, quotationSchema } from './schema';

interface QuotationFormProps {
    mode: 'create' | 'edit' | 'view';
    initialData?: QuotationSchema;
    paymentPlans?: OptionType[];
    paymentMethods?: OptionType[];
    statuses?: OptionType[];
    customerConfirmations?: OptionType[];
    quotationItems?: QuotationItemSchema[];
    quotationNotes?: NoteSchema[];
    prefilledCustomerId?: string;
    prefilledCustomerData?: UserSchema;
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
};

const EMPTY_PACKAGE_PRICES: PackagePrices = {
    single: 0,
    double: 0,
    triple: 0,
    quad: 0,
};

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
}): PackagePrices {
    return {
        single: Number(confirmation.package_price_single ?? 0),
        double: Number(confirmation.package_price_double ?? 0),
        triple: Number(confirmation.package_price_triple ?? 0),
        quad: Number(confirmation.package_price_quad ?? 0),
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

export function QuotationForm({
    mode,
    initialData,
    paymentPlans = [],
    paymentMethods = [],
    statuses = [],
    customerConfirmations = [],
    quotationItems = [],
    quotationNotes = [],
    prefilledCustomerId,
    prefilledCustomerData,
    onCancel,
}: QuotationFormProps) {
    const isView = mode === 'view';
    const isEdit = mode === 'edit';
    const isCreate = mode === 'create';

    const initialNotes: NoteSchema[] = (
        initialData?.notes?.length ? initialData.notes : quotationNotes
    ).map((note) => ({
        ...note,
        _key: note.id ? `id-${note.id}` : nanoid(),
        model: 'quotation',
    }));

    const today = formatDateForDisplay(new Date());

    const initialFormState: QuotationSchema = {
        id: undefined,
        quotation_number: '',
        quotation_date: today,
        expiry_date: today,
        customer_id: undefined,
        customer_confirmation_id: undefined,
        customer_name: '',
        nric_number: '',
        customer_contact: '',
        customer_address: '',
        customer_email: null,
        description: '',
        payment_plan: 'full',
        payment_method: 'transfer',
        status: 'draft',
        reason: '',
        items: [],
        model: 'quotation',
        notes: [],
    };

    const defaultData: QuotationSchema = {
        ...(initialData ?? initialFormState),
        notes: initialNotes,
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
            const manualItems = existingItems.filter(
                (item) => !item.customer_confirmation_member_id,
            );

            const getRateForPlan = (sharingPlan?: string | null) => {
                const prices = packagePricesToUse ?? packagePrices;
                if (sharingPlan === 'single') return prices.single;
                if (sharingPlan === 'double') return prices.double;
                if (sharingPlan === 'triple') return prices.triple;
                if (sharingPlan === 'quad') return prices.quad;
                return 0;
            };

            const memberItems = selectedMembers.map((member, index) => {
                const existingItem = existingItemByMemberId.get(
                    member.member_id,
                );

                if (existingItem) {
                    return {
                        ...existingItem,
                        _key:
                            existingItem._key ||
                            (existingItem.id
                                ? `id-${existingItem.id}`
                                : nanoid()),
                        customer_confirmation_member_id: member.member_id,
                        sharing_plan: member.sharing_plan,
                        sort_order: index + 1,
                    };
                }

                const rate = getRateForPlan(member.sharing_plan);

                return {
                    _key: nanoid(),
                    id: undefined,
                    quotation_id: undefined,
                    customer_confirmation_member_id: member.member_id,
                    sharing_plan: member.sharing_plan,
                    parent_id: null,
                    parent_key: null,
                    description: `${member.name} - ${member.sharing_plan ? `${member.sharing_plan.charAt(0).toUpperCase()}${member.sharing_plan.slice(1)}` : 'Standard'} sharing`,
                    is_header: false,
                    is_optional: false,
                    quantity: 1,
                    rate: rate,
                    amount: rate,
                    sort_order: index + 1,
                };
            });

            // Combine manual items with member items
            return [...memberItems, ...manualItems];
        },
        [availableMembers, packagePrices],
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

                return member.status === 'draft' && !member.has_quotation;
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

            const eligibleMemberById = new Map(
                eligible.map((member) => [member.member_id, member]),
            );
            const selectedHandlerMember = nextHandlerId
                ? eligibleMemberById.get(nextHandlerId)
                : null;

            setPackagePrices(extractedPackagePrices);

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
                ...(isCreate
                    ? {
                          items: buildItemsFromMembers(
                              autoSelectedMemberIds,
                              prev.items ?? [],
                              eligible,
                              extractedPackagePrices,
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

        setData((prev) => ({
            ...prev,
            customer_confirmation_id: null,
            package_name: '',
            package_price_single: 0,
            package_price_double: 0,
            package_price_triple: 0,
            package_price_quad: 0,
            items: (prev.items ?? []).filter(
                (item) => !item.customer_confirmation_member_id,
            ),
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

        const quotationResult = quotationSchema.safeParse(data);

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

        return valid;
    }

    // action
    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (!validateClientSide()) return;

        const url = '/quotation';

        if (isCreate) {
            post(url, {
                onError: (errors) => setError(errors),
            });
        } else if (isEdit) {
            put(`${url}/${data.id}`, {
                onError: (errors) => setError(errors),
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

    const renderError = (path: string) => {
        const errorMap = errors as Record<string, string | undefined>;
        const message = errorMap[path];

        if (!message) return null;

        return (
            <p className="mt-1 text-sm text-red-500">
                {formatError(path, message)}
            </p>
        );
    };

    const noteErrors = Object.entries(
        errors as Record<string, string | undefined>,
    )
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

    const handleHandlerChange = useCallback((memberId: number) => {
        setHandlerMemberId(memberId);
    }, []);

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
            payment_method: {
                section: 'quotation_details',
                targetId: 'payment_method',
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
            {data.quotation_number && (
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
                <Accordion
                    type="multiple"
                    value={openSections}
                    onValueChange={setOpenSections}
                    className="space-y-4"
                >
                    <QuotationInformationSection
                        data={data}
                        isView={isView}
                        disableCustomerConfirmation={!isCreate}
                        setData={setData}
                        renderError={renderError}
                        customerConfirmations={customerConfirmations}
                        availableMembers={availableMembers}
                        selectedMemberIds={effectiveSelectedMemberIds}
                        handlerMemberId={handlerMemberId}
                        onCustomerConfirmationChange={
                            handleCustomerConfirmationChange
                        }
                        onSelectedMembersChange={handleSelectedMembersChange}
                        onHandlerChange={handleHandlerChange}
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
                        paymentMethods={paymentMethods}
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
