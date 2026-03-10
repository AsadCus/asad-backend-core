import { FormField } from '@/components/form-field';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { store, update } from '@/routes/manifests';
import { useForm } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, RotateCcw } from 'lucide-react';
import { nanoid } from 'nanoid';
import { useCallback, useEffect, useMemo, useRef } from 'react';
import ManifestCustomerDatatable from './components/manifest-customer-datatable';
import ManifestInformationCard from './components/manifest-information-card';
import {
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
    const toPositiveIntegerOrNull = (value: unknown): number | null => {
        const parsed =
            typeof value === 'number'
                ? value
                : Number.parseInt(String(value ?? ''), 10);

        if (!Number.isFinite(parsed) || parsed <= 0) {
            return null;
        }

        return parsed;
    };

    const memberId = toPositiveIntegerOrNull(row.customer_confirmation_member_id);
    const customerId = toPositiveIntegerOrNull(row.customer_id);
    const travelerId = toPositiveIntegerOrNull(row.id);

    return {
        ...(row as TravelerWithUI),
        role: String(row.role ?? row.relationship ?? ''),
        row_key:
            typeof row.row_key === 'string' && row.row_key.trim().length > 0
                ? row.row_key
                : memberId !== null
                  ? `traveler-member-${memberId}`
                  : customerId !== null
                    ? `traveler-customer-${customerId}`
                    : travelerId !== null
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

function travelerIdentityKey(traveler: TravelerWithUI, index: number): string {
    if (
        traveler.customer_confirmation_member_id !== undefined &&
        traveler.customer_confirmation_member_id !== null
    ) {
        return `member-${traveler.customer_confirmation_member_id}`;
    }

    if (traveler.customer_id !== undefined && traveler.customer_id !== null) {
        return `customer-${traveler.customer_id}`;
    }

    if (traveler.id !== undefined && traveler.id !== null) {
        return `traveler-${traveler.id}`;
    }

    if (traveler.row_key && traveler.row_key.trim().length > 0) {
        return `row-${traveler.row_key}`;
    }

    return `index-${index}`;
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

    return {
        id: initialData?.id,
        package_id: initialData?.package_id ?? 0,
        manifest_number: initialData?.manifest_number ?? '',
        status: initialData?.status ?? 'draft',
        notes: initialData?.notes ?? '',
        travelers,
        roomLists: normalizedRoomLists,
        airlineList,
    };
}

function buildRoomRowsFromTravelers(
    travelers: TravelerWithUI[],
    existingRows: TravelerWithUI[] = [],
    accommodationKey: string,
    defaultMealPlan: string,
): TravelerWithUI[] {
    const toRoomTypeFromSharingPlan = (
        sharingPlan?: string,
    ): string | undefined => {
        const value = String(sharingPlan ?? '').toLowerCase();

        if (value === 'single') {
            return 'Single';
        }

        if (value === 'double') {
            return 'Double';
        }

        if (value === 'triple') {
            return 'Triple';
        }

        if (value === 'quad') {
            return 'Quad';
        }

        return undefined;
    };

    const toBedTypeFromSharingPlan = (sharingPlan?: string): string | undefined => {
        const value = String(sharingPlan ?? '').toLowerCase();

        if (value === 'single') {
            return 'Single';
        }

        return undefined;
    };

    return travelers.map((traveler, index) => {
        const travelerIdentity = travelerIdentityKey(traveler, index);
        const existing = existingRows.find(
            (row, rowIndex) =>
                travelerIdentityKey(row, rowIndex) === travelerIdentity,
        );

        const sharingPlan = traveler.sharing_plan;
        const fallbackRoomType = toRoomTypeFromSharingPlan(sharingPlan);
        const fallbackBedType = toBedTypeFromSharingPlan(sharingPlan);

        return {
            ...traveler,
            ...existing,
            role: traveler.role ?? traveler.relationship ?? existing?.role,
            relationship:
                traveler.relationship ??
                traveler.role ??
                existing?.relationship ??
                existing?.role,
            row_key:
                existing?.row_key ??
                traveler.row_key ??
                `room-${accommodationKey}-${traveler.customer_confirmation_member_id ?? traveler.customer_id ?? index}`,
            sn: index + 1,
            accommodation_key: accommodationKey,
            room_type:
                existing?.room_type ??
                traveler.room_type ??
                fallbackRoomType ??
                '',
            bed_type:
                existing?.bed_type ??
                traveler.bed_type ??
                fallbackBedType ??
                '',
            meal:
                existing?.meal ??
                traveler.meal ??
                defaultMealPlan ??
                '',
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
                    tab.accommodation.type_of_meal ?? '',
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
                role: row.role ?? row.relationship,
                relationship: row.relationship ?? row.role,
                status:
                    row.status ??
                    (row.customer_confirmation_member_id
                        ? 'pending_payment'
                        : 'confirmed'),
            }));

            const activeTravelersWithSn = travelersWithSn.filter(
                (traveler) => !isCancelledTraveler(traveler),
            );

            const travelerMap = new Map(
                activeTravelersWithSn.map((row, index) => [
                    travelerIdentityKey(row, index),
                    row,
                ]),
            );

            const nextRoomLists = Object.fromEntries(
                roomTabs.map((tab) => {
                    const key = tab.key;
                    const rows = (data.roomLists ?? {})[key] ?? [];

                    return [
                        key,
                        buildRoomRowsFromTravelers(
                            activeTravelersWithSn,
                            (rows as TravelerWithUI[]).map((row, rowIndex) => ({
                                ...row,
                                name_as_per_passport:
                                    travelerMap.get(
                                        travelerIdentityKey(row, rowIndex),
                                    )?.name_as_per_passport ??
                                    row.name_as_per_passport,
                                passport_no:
                                    travelerMap.get(
                                        travelerIdentityKey(row, rowIndex),
                                    )?.passport_no ?? row.passport_no,
                                role:
                                    travelerMap.get(
                                        travelerIdentityKey(row, rowIndex),
                                    )?.role ?? row.role,
                                relationship:
                                    travelerMap.get(
                                        travelerIdentityKey(row, rowIndex),
                                    )?.relationship ?? row.relationship,
                            })),
                            key,
                            tab.accommodation.type_of_meal ?? '',
                        ),
                    ];
                }),
            );

            const nextAirline = travelersWithSn
                .map((traveler, travelerIndex) => {
                    const travelerIdentity = travelerIdentityKey(
                        traveler,
                        travelerIndex,
                    );

                    const existing = (data.airlineList ?? []).find(
                        (row, existingIndex) =>
                            travelerIdentityKey(row as TravelerWithUI, existingIndex) ===
                            travelerIdentity,
                    ) as TravelerWithUI | undefined;

                    return {
                        ...existing,
                        ...traveler,
                        passport_no:
                            traveler.passport_no ?? existing?.passport_no,
                        nationality:
                            traveler.nationality ?? existing?.nationality,
                        gender: traveler.gender ?? existing?.gender,
                        date_of_birth:
                            traveler.date_of_birth ?? existing?.date_of_birth,
                        date_of_issue:
                            traveler.date_of_issue ?? existing?.date_of_issue,
                        date_of_expiry:
                            traveler.date_of_expiry ?? existing?.date_of_expiry,
                        issue_place:
                            traveler.issue_place ?? existing?.issue_place,
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

            updateFromTravelers(nextTravelers);
        } catch (error) {
            console.error(error);
        }
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
                                            value={
                                                tab.accommodation.hotel_name ??
                                                ''
                                            }
                                            disabled
                                        />
                                    </FormField>
                                    <FormField label="Location">
                                        <Input
                                            value={
                                                tab.accommodation.location ?? ''
                                            }
                                            disabled
                                        />
                                    </FormField>
                                    <FormField label="Check In">
                                        <Input
                                            value={
                                                tab.accommodation.check_in ?? ''
                                            }
                                            disabled
                                        />
                                    </FormField>
                                    <FormField label="Check Out">
                                        <Input
                                            value={
                                                tab.accommodation.check_out ??
                                                ''
                                            }
                                            disabled
                                        />
                                    </FormField>
                                    <FormField label="Meal Plan">
                                        <Input
                                            value={
                                                tab.accommodation
                                                    .type_of_meal ?? ''
                                            }
                                            disabled
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
                        allowReorder
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
