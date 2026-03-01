import { DatePickerField } from '@/components/date-picker';
import { FormField } from '@/components/form-field';
import { ProperInputSelect } from '@/components/proper-input-select';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { store, update } from '@/routes/manifests';
import { type OptionType } from '@/types';
import { useForm } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, RotateCcw } from 'lucide-react';
import { nanoid } from 'nanoid';
import { useCallback, useEffect, useMemo, useRef } from 'react';
import ManifestCustomerDatatable from './components/manifest-customer-datatable';
import ManifestInformationCard from './components/manifest-information-card';
import {
    type CustomerConfirmationData,
    type ManifestFormData,
    type ManifestFormProps,
    type PackageAccommodationOption,
    type PackageForManifestOption,
    type TravelerWithUI,
} from './types';
import { manifestValidationSchema } from './validation';

export type { ManifestFormData } from './types';

interface ManifestFormStore {
    data: ManifestFormData;
    errors: Record<string, string>;
    processing: boolean;
    setData: (key: string, value: unknown) => void;
    setError: (field: string, value: string) => void;
    clearErrors: () => void;
    reset: () => void;
    post: (url: string) => void;
    put: (url: string) => void;
}

function slugifyTab(value: string): string {
    return value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)/g, '');
}

function toTravelerWithUI(
    row: Record<string, unknown>,
    index: number,
): TravelerWithUI {
    const memberId = Number(row.customer_confirmation_member_id);
    const customerId = Number(row.customer_id);
    const travelerId = Number(row.id);

    return {
        ...(row as TravelerWithUI),
        row_key:
            typeof row.row_key === 'string' && row.row_key.trim().length > 0
                ? row.row_key
                : Number.isFinite(memberId)
                  ? `traveler-member-${memberId}`
                  : Number.isFinite(customerId)
                    ? `traveler-customer-${customerId}`
                    : Number.isFinite(travelerId)
                      ? `traveler-id-${travelerId}`
                      : `traveler-temp-${nanoid()}`,
        sn: Number(row.sn ?? index + 1),
        sharing_group_key: String(
            row.sharing_group_key ??
                row.sharing_group_id ??
                row.customer_confirmation_member_id ??
                `solo-${index}`,
        ),
    };
}

function flattenGroupedRows(rows: unknown): TravelerWithUI[] {
    if (!Array.isArray(rows)) {
        if (rows && typeof rows === 'object') {
            return Object.values(rows as Record<string, unknown[]>)
                .flat()
                .filter(
                    (item): item is Record<string, unknown> =>
                        !!item && typeof item === 'object',
                )
                .map((item, index) => toTravelerWithUI(item, index));
        }

        return [];
    }

    return rows
        .filter(
            (item): item is Record<string, unknown> =>
                !!item && typeof item === 'object',
        )
        .map((item, index) => toTravelerWithUI(item, index));
}

const sharingPlanCapacity: Record<string, number> = {
    single: 1,
    double: 2,
    triple: 3,
    quad: 4,
};

function buildSharingPlanGroupKeys(
    confirmation: CustomerConfirmationData,
    members: Array<{ id?: number; sharing_plan?: string | null }>,
): Map<number, string> {
    const assignments = new Map<number, string>();
    const membersByPlan = new Map<string, number[]>();

    members.forEach((member) => {
        const memberId = Number(member.id);
        if (!Number.isFinite(memberId)) {
            return;
        }

        const sharingPlan = (member.sharing_plan ?? '').toString().trim();
        if (!sharingPlan) {
            assignments.set(memberId, `solo-${memberId}`);

            return;
        }

        if (!membersByPlan.has(sharingPlan)) {
            membersByPlan.set(sharingPlan, []);
        }

        membersByPlan.get(sharingPlan)?.push(memberId);
    });

    membersByPlan.forEach((memberIds, sharingPlan) => {
        const normalizedPlan = sharingPlan.toLowerCase();
        const capacity = sharingPlanCapacity[normalizedPlan] ?? 1;

        memberIds.forEach((memberId, index) => {
            const chunkNumber = Math.floor(index / capacity) + 1;
            assignments.set(
                memberId,
                `plan-${normalizedPlan}-${confirmation.id}-${chunkNumber}`,
            );
        });
    });

    return assignments;
}

function buildDefaultData(initialData?: ManifestFormData): ManifestFormData {
    const travelers = flattenGroupedRows(initialData?.travelers ?? []);

    const roomLists =
        initialData?.roomLists ??
        ({
            makkah: flattenGroupedRows(initialData?.roomListMakkah ?? []),
            madinah: flattenGroupedRows(initialData?.roomListMadinah ?? []),
            others: flattenGroupedRows(initialData?.roomListOthers ?? []),
        } as Record<string, TravelerWithUI[]>);

    const normalizedRoomLists = Object.fromEntries(
        Object.entries(roomLists)
            .map(([key, rows]) => [key, flattenGroupedRows(rows)])
            .filter(([, rows]) => rows.length > 0),
    );

    const airlineList = flattenGroupedRows(
        initialData?.airlineList ?? travelers,
    );

    const selectedConfirmationIds =
        initialData?.selected_confirmation_ids ??
        Array.from(
            new Set(
                travelers
                    .map((row) => row.customer_confirmation_id)
                    .filter((value): value is number => Number.isFinite(value)),
            ),
        );

    return {
        id: initialData?.id,
        package_id: initialData?.package_id ?? 0,
        reference_number: initialData?.reference_number ?? '',
        status: initialData?.status ?? 'draft',
        company_address: initialData?.company_address ?? '',
        company_phone: initialData?.company_phone ?? '',
        departure_date: initialData?.departure_date ?? '',
        return_date: initialData?.return_date ?? '',
        duration: initialData?.duration ?? '',
        first_meal: initialData?.first_meal ?? '',
        last_meal: initialData?.last_meal ?? '',
        notes: initialData?.notes ?? '',
        flight_details: initialData?.flight_details ?? {},
        travelers,
        roomLists: normalizedRoomLists,
        airlineList,
        selected_confirmation_ids: selectedConfirmationIds,
    };
}

function buildRoomRowsFromTravelers(
    travelers: TravelerWithUI[],
    existingRows: TravelerWithUI[] = [],
    accommodationKey: string,
): TravelerWithUI[] {
    return travelers.map((traveler, index) => {
        const existing = existingRows.find(
            (row) =>
                row.customer_confirmation_member_id ===
                traveler.customer_confirmation_member_id,
        );

        return {
            ...traveler,
            ...existing,
            row_key:
                existing?.row_key ??
                traveler.row_key ??
                `room-${accommodationKey}-${traveler.customer_confirmation_member_id ?? traveler.customer_id ?? index}`,
            sn: index + 1,
            accommodation_key: accommodationKey,
            sharing_group_key:
                traveler.sharing_group_key ??
                existing?.sharing_group_key ??
                `solo-${traveler.customer_confirmation_member_id ?? index}`,
        };
    });
}

export default function ManifestForm({
    mode,
    initialData,
    dataPackage = [],
    customerConfirmations = [],
    onCancel,
}: ManifestFormProps) {
    const isView = mode === 'view';
    const isEdit = mode === 'edit';
    const isCreate = mode === 'create';

    const packageOptions = dataPackage as PackageForManifestOption[];
    const defaults = buildDefaultData(initialData);

    const form = useForm(defaults) as unknown as ManifestFormStore;
    const data = form.data;
    const { setData } = form;
    const errorAlertRef = useRef<HTMLDivElement | null>(null);

    const isCancelledTraveler = useCallback((traveler: TravelerWithUI) => {
        return traveler.status === 'cancelled';
    }, []);

    const selectedPackage = useMemo(
        () => packageOptions.find((item) => item.value === data.package_id),
        [packageOptions, data.package_id],
    );

    const hotelAccommodations = useMemo(() => {
        return (selectedPackage?.accommodations ?? []).filter(
            (accommodation) => !!accommodation.hotel_name,
        );
    }, [selectedPackage]);

    const roomTabs = useMemo(() => {
        if (hotelAccommodations.length === 0) {
            return [
                {
                    key: 'makkah',
                    label: 'Makkah',
                    accommodation: {
                        location: 'Makkah',
                        hotel_name: '',
                        check_in: '',
                        check_out: '',
                        type_of_meal: '',
                    } as PackageAccommodationOption,
                },
            ];
        }

        return hotelAccommodations.map((accommodation) => ({
            key: slugifyTab(
                accommodation.location || accommodation.hotel_name || 'hotel',
            ),
            label:
                accommodation.location || accommodation.hotel_name || 'Hotel',
            accommodation,
        }));
    }, [hotelAccommodations]);

    useEffect(() => {
        const currentTravelers = (
            (data.travelers ?? []) as TravelerWithUI[]
        ).filter((traveler) => !isCancelledTraveler(traveler));
        const currentRoomLists = data.roomLists ?? {};

        const syncedRoomLists = Object.fromEntries(
            roomTabs.map((tab) => [
                tab.key,
                buildRoomRowsFromTravelers(
                    currentTravelers,
                    (currentRoomLists[tab.key] ?? []) as TravelerWithUI[],
                    tab.key,
                ),
            ]),
        );

        const currentKeys = Object.keys(currentRoomLists);
        const syncedKeys = Object.keys(syncedRoomLists);
        const keysChanged =
            currentKeys.length !== syncedKeys.length ||
            currentKeys.some((key) => !syncedKeys.includes(key));

        if (keysChanged) {
            setData('roomLists', syncedRoomLists);
        }
    }, [
        roomTabs,
        data.travelers,
        data.roomLists,
        isCancelledTraveler,
        setData,
    ]);

    const updateFromTravelers = useCallback(
        (nextTravelers: TravelerWithUI[]) => {
            const travelersWithSn = nextTravelers.map((row, index) => ({
                ...row,
                sn: index + 1,
                status: row.status ?? 'assigned',
            }));

            const activeTravelersWithSn = travelersWithSn.filter(
                (traveler) => !isCancelledTraveler(traveler),
            );

            const travelerMap = new Map(
                activeTravelersWithSn.map((row) => [
                    row.customer_confirmation_member_id,
                    row,
                ]),
            );

            const nextRoomLists = Object.fromEntries(
                Object.entries(data.roomLists ?? {}).map(([key, rows]) => [
                    key,
                    buildRoomRowsFromTravelers(
                        activeTravelersWithSn,
                        (rows as TravelerWithUI[]).map((row) => ({
                            ...row,
                            name_as_per_passport:
                                travelerMap.get(
                                    row.customer_confirmation_member_id,
                                )?.name_as_per_passport ??
                                row.name_as_per_passport,
                            passport_no:
                                travelerMap.get(
                                    row.customer_confirmation_member_id,
                                )?.passport_no ?? row.passport_no,
                        })),
                        key,
                    ),
                ]),
            );

            const nextAirline = travelersWithSn
                .map((traveler) => {
                    const existing = (data.airlineList ?? []).find(
                        (row) =>
                            row.customer_confirmation_member_id ===
                            traveler.customer_confirmation_member_id,
                    ) as TravelerWithUI | undefined;

                    return {
                        ...traveler,
                        ...existing,
                        row_key:
                            existing?.row_key ??
                            traveler.row_key ??
                            `airline-${traveler.customer_confirmation_member_id ?? traveler.customer_id ?? traveler.sn}`,
                        sn: traveler.sn,
                    };
                })
                .filter((traveler) => !isCancelledTraveler(traveler));

            form.setData('travelers', travelersWithSn);
            form.setData('roomLists', nextRoomLists);
            form.setData('airlineList', nextAirline);
        },
        [data.airlineList, data.roomLists, form, isCancelledTraveler],
    );

    const selectedConfirmationIds = useMemo(
        () => data.selected_confirmation_ids ?? [],
        [data.selected_confirmation_ids],
    );

    const packageMatchedConfirmations = useMemo(() => {
        if (!data.package_id || data.package_id < 1) {
            return [] as CustomerConfirmationData[];
        }

        return customerConfirmations.filter(
            (confirmation) => confirmation.package_id === data.package_id,
        );
    }, [customerConfirmations, data.package_id]);

    const availableConfirmations = packageMatchedConfirmations.filter(
        (confirmation) => !selectedConfirmationIds.includes(confirmation.id),
    );

    useEffect(() => {
        if (!data.package_id || data.package_id < 1) {
            if (selectedConfirmationIds.length === 0) {
                return;
            }

            form.setData('selected_confirmation_ids', []);
            updateFromTravelers(
                ((data.travelers ?? []) as TravelerWithUI[]).filter(
                    (row) => !row.customer_confirmation_id,
                ),
            );

            return;
        }

        const allowedConfirmationIds = new Set(
            packageMatchedConfirmations.map((confirmation) => confirmation.id),
        );

        const nextSelectedIds = selectedConfirmationIds.filter((id) =>
            allowedConfirmationIds.has(id),
        );

        const hasSelectionMismatch =
            nextSelectedIds.length !== selectedConfirmationIds.length;

        const currentTravelers = (data.travelers ?? []) as TravelerWithUI[];
        const nextTravelers = currentTravelers.filter((row) => {
            if (!row.customer_confirmation_id) {
                return true;
            }

            return allowedConfirmationIds.has(row.customer_confirmation_id);
        });

        const hasTravelerMismatch =
            nextTravelers.length !== currentTravelers.length;

        if (!hasSelectionMismatch && !hasTravelerMismatch) {
            return;
        }

        form.setData('selected_confirmation_ids', nextSelectedIds);
        updateFromTravelers(nextTravelers);
    }, [
        form,
        data.package_id,
        data.travelers,
        packageMatchedConfirmations,
        selectedConfirmationIds,
        updateFromTravelers,
    ]);

    const addCustomerConfirmation = (confirmationId: number) => {
        const confirmation = packageMatchedConfirmations.find(
            (item) => item.id === confirmationId,
        );

        if (!confirmation) {
            return;
        }

        const currentTravelers = (data.travelers ?? []) as TravelerWithUI[];
        const existingMemberIds = new Set(
            currentTravelers.map(
                (traveler) => traveler.customer_confirmation_member_id,
            ),
        );

        const newTravelers = (confirmation.members ?? [])
            .filter((member) => member.status !== 'cancelled')
            .filter((member) => !existingMemberIds.has(member.id));

        const sharingPlanGroups = buildSharingPlanGroupKeys(
            confirmation,
            newTravelers,
        );

        const mappedTravelers = newTravelers.map((member, index) => ({
            customer_id: member.customer_id,
            customer_confirmation_member_id: member.id,
            customer_confirmation_id: confirmation.id,
            customer_name: member.name,
            name_as_per_passport: member.name,
            relationship: member.is_leader ? 'Self' : '',
            passport_no: member.passport_number ?? '',
            ppt_no: member.passport_number ?? '',
            date_of_birth: member.date_of_birth ?? '',
            age: member.age ?? undefined,
            date_of_issue: member.passport_issue_date ?? '',
            date_of_expiry: member.passport_expiry_date ?? '',
            issue_place: member.passport_place_of_issue ?? '',
            sharing_group_key: Number.isFinite(Number(member.id))
                ? sharingPlanGroups.get(Number(member.id))
                : undefined,
            row_key: `traveler-member-${member.id}`,
            sn: currentTravelers.length + index + 1,
            status: 'assigned' as const,
        }));

        const mergedTravelers = [...currentTravelers, ...mappedTravelers];

        form.setData('selected_confirmation_ids', [
            ...(data.selected_confirmation_ids ?? []),
            confirmation.id,
        ]);

        updateFromTravelers(mergedTravelers);
    };

    const removeCustomerConfirmation = (confirmationId: number) => {
        const filteredTravelers = (
            (data.travelers ?? []) as TravelerWithUI[]
        ).filter((row) => row.customer_confirmation_id !== confirmationId);

        form.setData(
            'selected_confirmation_ids',
            (data.selected_confirmation_ids ?? []).filter(
                (id: number) => id !== confirmationId,
            ),
        );

        updateFromTravelers(filteredTravelers);
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        const validationResult = manifestValidationSchema.safeParse(data);
        form.clearErrors();

        if (!validationResult.success) {
            validationResult.error.issues.forEach((issue) => {
                form.setError(
                    issue.path.join('.') as keyof ManifestFormData,
                    issue.message,
                );
            });

            setTimeout(() => {
                errorAlertRef.current?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start',
                });
            }, 0);

            return;
        }

        if (isCreate) {
            form.post(store().url);
        }

        if (isEdit && data.id) {
            form.put(update(data.id).url);
        }
    };

    const renderError = (path: string) => {
        const message = form.errors[path];

        if (typeof message !== 'string' || message.length === 0) {
            return null;
        }

        return <p className="mt-1 text-sm text-red-500">{message}</p>;
    };

    const groupedErrorSummary = useMemo(() => {
        const globalErrors: Array<{ path: string; message: string }> = [];
        const travelerErrors = new Map<
            number,
            Array<{ path: string; message: string }>
        >();

        Object.entries(form.errors).forEach(([path, message]) => {
            if (!message) {
                return;
            }

            const travelerMatch = path.match(/^travelers\.(\d+)\./);

            if (!travelerMatch) {
                globalErrors.push({ path, message });

                return;
            }

            const travelerIndex = Number(travelerMatch[1]);
            if (!travelerErrors.has(travelerIndex)) {
                travelerErrors.set(travelerIndex, []);
            }

            travelerErrors.get(travelerIndex)?.push({ path, message });
        });

        return {
            globalErrors,
            travelerGroups: [...travelerErrors.entries()].map(
                ([travelerIndex, issues]) => ({
                    travelerIndex,
                    travelerName:
                        ((data.travelers ?? []) as TravelerWithUI[])[
                            travelerIndex
                        ]?.name_as_per_passport ||
                        `Traveler ${travelerIndex + 1}`,
                    issues,
                }),
            ),
        };
    }, [form.errors, data.travelers]);

    const nonCancelledTravelers = useMemo(() => {
        return ((data.travelers ?? []) as TravelerWithUI[]).filter(
            (traveler) => !isCancelledTraveler(traveler),
        );
    }, [data.travelers, isCancelledTraveler]);

    const moveTravelerToHolding = async (traveler: TravelerWithUI) => {
        const memberId = traveler.customer_confirmation_member_id;
        const travelerId = traveler.id;

        if (!memberId || !travelerId || !data.id) {
            return;
        }

        try {
            const csrfToken = document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content');

            const response = await fetch(
                `/manifests/${data.id}/travelers/${travelerId}/move-holding`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                    },
                    body: JSON.stringify({
                        target_package_id: null,
                    }),
                },
            );

            if (!response.ok) {
                throw new Error('Failed to move traveler to holding.');
            }

            const nextTravelers = (
                (data.travelers ?? []) as TravelerWithUI[]
            ).map((row) => {
                if (
                    row.customer_confirmation_member_id ===
                    traveler.customer_confirmation_member_id
                ) {
                    return {
                        ...row,
                        status: 'cancelled' as const,
                    };
                }

                return row;
            });

            const activeConfirmationIds = new Set(
                nextTravelers
                    .filter((row) => row.status !== 'cancelled')
                    .map((row) => row.customer_confirmation_id)
                    .filter((value): value is number => Number.isFinite(value)),
            );

            form.setData(
                'selected_confirmation_ids',
                (data.selected_confirmation_ids ?? []).filter((id) =>
                    activeConfirmationIds.has(id),
                ),
            );
            updateFromTravelers(nextTravelers);
        } catch (error) {
            console.error(error);
        }
    };

    const setAccommodationInfo = (
        tabKey: string,
        field:
            | 'hotel_name'
            | 'location'
            | 'check_in'
            | 'check_out'
            | 'type_of_meal',
        value: string,
    ) => {
        const flightDetails = (data.flight_details ?? {}) as Record<
            string,
            unknown
        >;
        const currentInfo =
            (flightDetails.ui_accommodation_info as
                | Record<string, Record<string, string>>
                | undefined) ?? {};

        const nextFlightDetails: Record<string, unknown> = {
            ...flightDetails,
            ui_accommodation_info: {
                ...currentInfo,
                [tabKey]: {
                    ...(currentInfo[tabKey] ?? {}),
                    [field]: value,
                },
            },
        };

        form.setData('flight_details', nextFlightDetails);
    };

    const getAccommodationInfo = (
        tabKey: string,
        fallback: PackageAccommodationOption,
    ): Record<string, string> => {
        const flightDetails = (data.flight_details ?? {}) as Record<
            string,
            unknown
        >;
        const currentInfo =
            (flightDetails.ui_accommodation_info as
                | Record<string, Record<string, string>>
                | undefined) ?? {};

        return {
            hotel_name:
                currentInfo[tabKey]?.hotel_name ?? fallback.hotel_name ?? '',
            location: currentInfo[tabKey]?.location ?? fallback.location ?? '',
            check_in: currentInfo[tabKey]?.check_in ?? fallback.check_in ?? '',
            check_out:
                currentInfo[tabKey]?.check_out ?? fallback.check_out ?? '',
            type_of_meal:
                currentInfo[tabKey]?.type_of_meal ??
                fallback.type_of_meal ??
                '',
        };
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            {Object.keys(form.errors).length > 0 && !isView && (
                <Alert variant="destructive" ref={errorAlertRef}>
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>
                        <div className="space-y-2">
                            <p>
                                Please fix the validation errors before
                                submitting.
                            </p>
                            {groupedErrorSummary.globalErrors.map(
                                ({ path, message }) => (
                                    <p key={path} className="text-sm">
                                        {path}: {message}
                                    </p>
                                ),
                            )}
                            {groupedErrorSummary.travelerGroups.map(
                                ({ travelerIndex, travelerName, issues }) => (
                                    <div
                                        key={travelerIndex}
                                        className="space-y-1"
                                    >
                                        <p className="font-medium">
                                            {travelerName}
                                        </p>
                                        {issues.map(({ path, message }) => (
                                            <p key={path} className="text-sm">
                                                {path.split('.').slice(-1)[0]}:{' '}
                                                {message}
                                            </p>
                                        ))}
                                    </div>
                                ),
                            )}
                        </div>
                    </AlertDescription>
                </Alert>
            )}

            <ManifestInformationCard
                isView={isView}
                data={data}
                dataPackage={packageOptions}
                setData={form.setData}
                renderError={renderError}
            />

            <Card>
                <CardHeader>
                    <CardTitle>Customer Confirmation Selection</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="flex flex-wrap items-end gap-3">
                        <FormField
                            label="Add Customer Confirmation"
                            className="min-w-[280px] flex-1"
                        >
                            <ProperInputSelect
                                options={
                                    availableConfirmations.map((item) => ({
                                        value: String(item.id),
                                        label: `#${item.id} · ${item.leader_name ?? '-'} (${item.member_count ?? 0} pax)`,
                                    })) as OptionType[]
                                }
                                onValueChange={(value) =>
                                    addCustomerConfirmation(Number(value))
                                }
                                value=""
                                placeholder={
                                    data.package_id && data.package_id > 0
                                        ? 'Select confirmation'
                                        : 'Select package first'
                                }
                                disabled={
                                    isView ||
                                    !data.package_id ||
                                    data.package_id < 1 ||
                                    availableConfirmations.length === 0
                                }
                            />
                        </FormField>
                    </div>

                    {selectedConfirmationIds.length > 0 && (
                        <div className="flex flex-wrap gap-2">
                            {selectedConfirmationIds.map((confirmationId) => (
                                <Button
                                    key={confirmationId}
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={isView}
                                    onClick={() =>
                                        removeCustomerConfirmation(
                                            confirmationId,
                                        )
                                    }
                                >
                                    Remove #{confirmationId}
                                </Button>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>

            <Tabs defaultValue="travelers" className="w-full">
                <TabsList className="flex w-full flex-wrap">
                    <TabsTrigger value="travelers">Travelers</TabsTrigger>
                    {roomTabs.map((tab) => (
                        <TabsTrigger key={tab.key} value={`room-${tab.key}`}>
                            Room List - {tab.label}
                        </TabsTrigger>
                    ))}
                    <TabsTrigger value="airline">Airline Name List</TabsTrigger>
                </TabsList>

                <TabsContent value="travelers" className="space-y-4">
                    <ManifestCustomerDatatable
                        mode="travelers"
                        rows={(data.travelers ?? []) as TravelerWithUI[]}
                        disabled={isView}
                        allowReorder
                        onMoveToHolding={moveTravelerToHolding}
                        onRowsChange={updateFromTravelers}
                    />
                </TabsContent>

                {roomTabs.map((tab) => {
                    const roomRows =
                        ((data.roomLists ?? {})[tab.key] as
                            | TravelerWithUI[]
                            | undefined) ?? [];
                    const visibleRoomRows = roomRows.filter(
                        (row) => !isCancelledTraveler(row),
                    );
                    const accommodationInfo = getAccommodationInfo(
                        tab.key,
                        tab.accommodation,
                    );

                    return (
                        <TabsContent
                            key={tab.key}
                            value={`room-${tab.key}`}
                            className="space-y-4"
                        >
                            <Card>
                                <CardHeader>
                                    <CardTitle>
                                        {tab.label} Accommodation Information
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-5">
                                    <FormField label="Hotel">
                                        <Input
                                            value={accommodationInfo.hotel_name}
                                            disabled={isView}
                                            onChange={(event) =>
                                                setAccommodationInfo(
                                                    tab.key,
                                                    'hotel_name',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </FormField>
                                    <FormField label="Location">
                                        <Input
                                            value={accommodationInfo.location}
                                            disabled={isView}
                                            onChange={(event) =>
                                                setAccommodationInfo(
                                                    tab.key,
                                                    'location',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </FormField>
                                    <FormField label="Check In">
                                        <DatePickerField
                                            id={`${tab.key}-check-in`}
                                            value={accommodationInfo.check_in}
                                            disabled={isView}
                                            onChange={(value) =>
                                                setAccommodationInfo(
                                                    tab.key,
                                                    'check_in',
                                                    value,
                                                )
                                            }
                                        />
                                    </FormField>
                                    <FormField label="Check Out">
                                        <DatePickerField
                                            id={`${tab.key}-check-out`}
                                            value={accommodationInfo.check_out}
                                            disabled={isView}
                                            onChange={(value) =>
                                                setAccommodationInfo(
                                                    tab.key,
                                                    'check_out',
                                                    value,
                                                )
                                            }
                                        />
                                    </FormField>
                                    <FormField label="Meal Plan">
                                        <Input
                                            value={
                                                accommodationInfo.type_of_meal
                                            }
                                            disabled={isView}
                                            onChange={(event) =>
                                                setAccommodationInfo(
                                                    tab.key,
                                                    'type_of_meal',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </FormField>
                                </CardContent>
                            </Card>

                            <ManifestCustomerDatatable
                                mode="room"
                                rows={visibleRoomRows}
                                disabled={isView}
                                allowReorder
                                onRowsChange={(rows: TravelerWithUI[]) =>
                                    form.setData('roomLists', {
                                        ...(data.roomLists ?? {}),
                                        [tab.key]: rows.map((row, index) => ({
                                            ...row,
                                            sort_order: index + 1,
                                        })),
                                    })
                                }
                            />
                        </TabsContent>
                    );
                })}

                <TabsContent value="airline" className="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Airline Information</CardTitle>
                        </CardHeader>
                        <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    Airline
                                </p>
                                <p className="font-medium">
                                    {selectedPackage?.airline || '-'}
                                </p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    PNR
                                </p>
                                <p className="font-medium">
                                    {selectedPackage?.pnr || '-'}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <ManifestCustomerDatatable
                        mode="airline"
                        rows={((data.airlineList ?? []) as TravelerWithUI[])
                            .filter((row) => !isCancelledTraveler(row))
                            .map((row) => {
                                const activeTraveler =
                                    nonCancelledTravelers.find(
                                        (traveler) =>
                                            traveler.customer_confirmation_member_id ===
                                            row.customer_confirmation_member_id,
                                    );

                                return {
                                    ...row,
                                    name_as_per_passport:
                                        activeTraveler?.name_as_per_passport ??
                                        row.name_as_per_passport,
                                };
                            })}
                        disabled={isView}
                        onRowsChange={(rows: TravelerWithUI[]) =>
                            form.setData('airlineList', rows)
                        }
                    />
                </TabsContent>
            </Tabs>

            <div className="flex items-center justify-end gap-2">
                <Button type="button" variant="outline" onClick={onCancel}>
                    <ArrowLeft className="mr-1 h-4 w-4" />
                    Back
                </Button>
                {!isView && (
                    <>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => form.reset()}
                        >
                            <RotateCcw className="mr-1 h-4 w-4" />
                            Reset
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {isCreate ? 'Create Manifest' : 'Update Manifest'}
                        </Button>
                    </>
                )}
            </div>
        </form>
    );
}
