import { FormProgressHeader } from '@/components/form-progress-header';
import { Accordion } from '@/components/ui/accordion';
import { Button } from '@/components/ui/button';
import { navigateToSection } from '@/lib/navigation-helper';
import { formatDateForDisplay } from '@/lib/utils';
import { show as showCustomerConfirmation } from '@/routes/customer-confirmations';
import { OptionType } from '@/types';
import { useForm } from '@inertiajs/react';
import { nanoid } from 'nanoid';
import { useCallback, useEffect, useRef, useState } from 'react';
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
    const [availableMembers, setAvailableMembers] = useState<
        Array<{
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
        }>
    >([]);
    const [selectedMemberIds, setSelectedMemberIds] = useState<number[]>([]);
    const [handlerMemberId, setHandlerMemberId] = useState<number | null>(null);
    const [packagePrices, setPackagePrices] = useState<{
        single: number;
        double: number;
        triple: number;
        quad: number;
    }>({
        single: 0,
        double: 0,
        triple: 0,
        quad: 0,
    });

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

    const getRateFromSharingPlan = useCallback(
        (sharingPlan?: string | null) => {
            if (sharingPlan === 'single') return packagePrices.single;
            if (sharingPlan === 'double') return packagePrices.double;
            if (sharingPlan === 'triple') return packagePrices.triple;
            if (sharingPlan === 'quad') return packagePrices.quad;
            return 0;
        },
        [packagePrices],
    );

    const buildItemsFromMembers = useCallback(
        (memberIds: number[], existingItems: QuotationItemSchema[] = []) => {
            const selectedMembers = availableMembers.filter((member) =>
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
                    rate: getRateFromSharingPlan(member.sharing_plan),
                    amount: getRateFromSharingPlan(member.sharing_plan),
                    sort_order: index + 1,
                };
            });

            // Combine manual items with member items
            return [...memberItems, ...manualItems];
        },
        [availableMembers, getRateFromSharingPlan],
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

            const members = (confirmation.members ?? []) as Array<{
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
            }>;

            const linkedMemberIds = new Set(
                (data.items ?? [])
                    .map((item) =>
                        Number(item.customer_confirmation_member_id ?? 0),
                    )
                    .filter((value) => value > 0),
            );

            const eligible = members.filter((member) => {
                if (linkedMemberIds.has(member.member_id)) {
                    return true;
                }

                return member.status === 'draft' && !member.has_quotation;
            });

            setAvailableMembers(eligible);

            const autoSelectedMemberIds = isCreate
                ? eligible.map((member) => member.member_id)
                : eligible
                      .filter((member) => linkedMemberIds.has(member.member_id))
                      .map((member) => member.member_id);

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

            setPackagePrices({
                single: Number(confirmation.package_price_single ?? 0),
                double: Number(confirmation.package_price_double ?? 0),
                triple: Number(confirmation.package_price_triple ?? 0),
                quad: Number(confirmation.package_price_quad ?? 0),
            });

            setData((prev) => ({
                ...prev,
                customer_confirmation_id: confirmationId,
                package_name:
                    confirmation.package_data?.name ?? prev.package_name,
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
                          items: buildItemsFromMembers(autoSelectedMemberIds),
                      }
                    : {}),
                customer_id: nextHandlerId
                    ? (eligible.find(
                          (member) => member.member_id === nextHandlerId,
                      )?.customer_id ?? prev.customer_id)
                    : prev.customer_id,
                customer_name: nextHandlerId
                    ? (eligible.find(
                          (member) => member.member_id === nextHandlerId,
                      )?.name ?? prev.customer_name)
                    : prev.customer_name,
                customer_contact: nextHandlerId
                    ? (eligible.find(
                          (member) => member.member_id === nextHandlerId,
                      )?.contact_number ?? prev.customer_contact)
                    : prev.customer_contact,
                customer_address: nextHandlerId
                    ? (eligible.find(
                          (member) => member.member_id === nextHandlerId,
                      )?.address ?? prev.customer_address)
                    : prev.customer_address,
                customer_email: nextHandlerId
                    ? (eligible.find(
                          (member) => member.member_id === nextHandlerId,
                      )?.email ?? prev.customer_email)
                    : prev.customer_email,
            }));
        },
        [
            buildItemsFromMembers,
            data.customer_id,
            data.items,
            isCreate,
            setData,
        ],
    );

    const loadedConfirmationRef = useRef<number | null>(null);

    useEffect(() => {
        if (!data.customer_confirmation_id) {
            return;
        }

        if (!isCreate && loadedConfirmationRef.current !== data.customer_confirmation_id) {
            loadedConfirmationRef.current = data.customer_confirmation_id;
            loadCustomerConfirmation(
                Number(data.customer_confirmation_id),
            ).catch(() => {
                setAvailableMembers([]);
            });
        }
    }, [data.customer_confirmation_id, isCreate]);

    useEffect(() => {
        syncHandlerCustomer(handlerMemberId);
    }, [handlerMemberId, syncHandlerCustomer]);

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
                    className="mb-8 space-y-4"
                >
                    <QuotationInformationSection
                        data={data}
                        isView={isView}
                        disableCustomerConfirmation={!isCreate}
                        setData={setData}
                        renderError={renderError}
                        customerConfirmations={customerConfirmations}
                        availableMembers={availableMembers}
                        selectedMemberIds={selectedMemberIds}
                        handlerMemberId={handlerMemberId}
                        onCustomerConfirmationChange={(value) => {
                            const confirmationId = Number(value ?? 0);

                            if (!confirmationId) {
                                setAvailableMembers([]);
                                setSelectedMemberIds([]);
                                setHandlerMemberId(null);
                                setData('customer_confirmation_id', null);

                                return;
                            }

                            loadCustomerConfirmation(confirmationId).catch(
                                () => {
                                    setAvailableMembers([]);
                                    setSelectedMemberIds([]);
                                    setHandlerMemberId(null);
                                },
                            );
                        }}
                        onSelectedMembersChange={(memberIds) => {
                            setSelectedMemberIds(memberIds);

                            if (!isView) {
                                const nextItems = buildItemsFromMembers(
                                    memberIds,
                                    data.items ?? [],
                                );
                                setData('items', nextItems);
                            }

                            if (!memberIds.length) {
                                setHandlerMemberId(null);
                                return;
                            }

                            if (
                                !handlerMemberId ||
                                !memberIds.includes(handlerMemberId)
                            ) {
                                setHandlerMemberId(memberIds[0]);
                            }
                        }}
                        onHandlerChange={(memberId) => {
                            setHandlerMemberId(memberId);
                        }}
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
                        status={getQuotationSectionStatus(
                            'maid_and_quotation_details',
                        )}
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
