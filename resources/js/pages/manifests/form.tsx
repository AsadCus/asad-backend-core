import { FormField } from '@/components/form-field';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { store, update } from '@/routes/manifests';
import { useForm } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, RotateCcw } from 'lucide-react';
import { nanoid } from 'nanoid';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import ManifestDatatable from './components/datatable';
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

    const memberId = toPositiveIntegerOrNull(
        row.customer_confirmation_member_id,
    );
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

function calculateAgeFromDob(dateValue?: string | null): number | null {
    if (!dateValue) {
        return null;
    }

    const parsedDate = new Date(dateValue);
    if (Number.isNaN(parsedDate.getTime())) {
        return null;
    }

    const now = new Date();
    let age = now.getFullYear() - parsedDate.getFullYear();
    const monthDiff = now.getMonth() - parsedDate.getMonth();

    if (
        monthDiff < 0 ||
        (monthDiff === 0 && now.getDate() < parsedDate.getDate())
    ) {
        age -= 1;
    }

    return age >= 0 ? age : null;
}

function buildDefaultData(initialData?: ManifestFormData): ManifestFormData {
    const travelers = flattenGroupedRows(initialData?.travelers ?? []);

    const roomLists = initialData?.roomLists ?? {};

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
        sharingPlan?: string | null,
    ): string | undefined => {
        const value = String(sharingPlan ?? '').toLowerCase();

        if (value === 'single') {
            return 'single';
        }

        if (value === 'double') {
            return 'double';
        }

        if (value === 'triple') {
            return 'triple';
        }

        if (value === 'quad') {
            return 'quad';
        }

        return undefined;
    };

    const toBedTypeFromSharingPlan = (
        sharingPlan?: string | null,
    ): string | undefined => {
        const value = String(sharingPlan ?? '').toLowerCase();

        if (value === 'double' || value === 'quad') {
            return 'king';
        }

        if (value === 'single' || value === 'triple') {
            return 'single';
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
            passport_number:
                traveler.passport_number ?? existing?.passport_number,
            date_of_birth:
                traveler.date_of_birth ?? existing?.date_of_birth ?? null,
            age:
                calculateAgeFromDob(traveler.date_of_birth) ??
                calculateAgeFromDob(existing?.date_of_birth) ??
                existing?.age ??
                null,
            accommodation_key: accommodationKey,
            sharing_plan:
                existing?.sharing_plan ?? traveler.sharing_plan ?? 'single',
            room_relationship:
                existing?.room_relationship ??
                traveler.relationship ??
                traveler.role ??
                '',
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
            room_label: existing?.room_label ?? traveler.room_label ?? '',
            meal: existing?.meal ?? traveler.meal ?? defaultMealPlan ?? '',
            sharing_group_key:
                traveler.sharing_group_key ??
                existing?.sharing_group_key ??
                `solo-${traveler.customer_confirmation_member_id ?? index}`,
        };
    });
}

function countRoomGroups(rows: TravelerWithUI[]): number {
    const groupKeys = new Set<string>();

    rows.forEach((row, index) => {
        const key =
            row.sharing_group_key ??
            String(
                row.sharing_group_id ??
                    row.customer_confirmation_member_id ??
                    row.customer_id ??
                    `solo-${index}`,
            );

        groupKeys.add(key);
    });

    return groupKeys.size;
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

    const form = useForm<ManifestFormData>(defaults);
    const data = form.data;
    const { setData } = form;
    const errorAlertRef = useRef<HTMLDivElement | null>(null);
    const [activeTab, setActiveTab] = useState('main');

    const scrollToErrorBanner = useCallback(() => {
        setTimeout(() => {
            errorAlertRef.current?.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
        }, 0);
    }, []);

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
                    key: 'mekkah',
                    label: 'Mekkah',
                    accommodation: {
                        location: 'Mekkah',
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

    const roomErrorRanges = useMemo(() => {
        let runningIndex = 0;

        return roomTabs.map((tab) => {
            const tabRows =
                ((data.roomLists ?? {})[tab.key] as TravelerWithUI[]) ?? [];
            const groupCount = countRoomGroups(tabRows);
            const range = {
                tabKey: tab.key,
                start: runningIndex,
                end: runningIndex + Math.max(groupCount - 1, 0),
            };

            runningIndex += groupCount;

            return range;
        });
    }, [data.roomLists, roomTabs]);

    const resolveErrorTab = useCallback(
        (path: string): string => {
            if (path.startsWith('travelers.')) {
                return 'main';
            }

            if (path.startsWith('roomLists.')) {
                const roomMatch = path.match(/^roomLists\.([^.]+)\./);
                if (roomMatch?.[1]) {
                    return `room-${roomMatch[1]}`;
                }
            }

            if (path.startsWith('rooms.')) {
                const roomIndexMatch = path.match(/^rooms\.(\d+)\./);
                const roomIndex = roomIndexMatch?.[1]
                    ? Number(roomIndexMatch[1])
                    : null;

                if (roomIndex !== null && Number.isFinite(roomIndex)) {
                    const matchedRange = roomErrorRanges.find(
                        (range) =>
                            roomIndex >= range.start && roomIndex <= range.end,
                    );

                    if (matchedRange) {
                        return `room-${matchedRange.tabKey}`;
                    }
                }
            }

            if (path.startsWith('airlineList.')) {
                return 'airline';
            }

            return activeTab;
        },
        [activeTab, roomErrorRanges],
    );

    const navigateToErrorField = useCallback(
        (path: string) => {
            setActiveTab(resolveErrorTab(path));

            setTimeout(() => {
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
            }, 120);
        },
        [resolveErrorTab],
    );

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

    useEffect(() => {
        if (isView) {
            return;
        }

        if (Object.keys(form.errors).length > 0) {
            scrollToErrorBanner();
        }
    }, [form.errors, isView, scrollToErrorBanner]);

    const updateFromTravelers = useCallback(
        (nextTravelers: TravelerWithUI[]) => {
            const travelersWithSn = nextTravelers.map((row, index) => ({
                ...row,
                sn: index + 1,
                role: row.role ?? row.relationship ?? undefined,
                relationship: row.relationship ?? row.role ?? undefined,
                passport_number: row.passport_number ?? undefined,
                age: calculateAgeFromDob(row.date_of_birth) ?? row.age ?? null,
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
                                passport_number:
                                    travelerMap.get(
                                        travelerIdentityKey(row, rowIndex),
                                    )?.passport_number ?? row.passport_number,
                                role:
                                    travelerMap.get(
                                        travelerIdentityKey(row, rowIndex),
                                    )?.role ?? row.role,
                                relationship:
                                    travelerMap.get(
                                        travelerIdentityKey(row, rowIndex),
                                    )?.relationship ?? row.relationship,
                                sharing_plan:
                                    travelerMap.get(
                                        travelerIdentityKey(row, rowIndex),
                                    )?.sharing_plan ?? row.sharing_plan,
                                date_of_birth:
                                    travelerMap.get(
                                        travelerIdentityKey(row, rowIndex),
                                    )?.date_of_birth ?? row.date_of_birth,
                                age:
                                    calculateAgeFromDob(
                                        travelerMap.get(
                                            travelerIdentityKey(row, rowIndex),
                                        )?.date_of_birth ?? row.date_of_birth,
                                    ) ?? row.age,
                                nationality:
                                    travelerMap.get(
                                        travelerIdentityKey(row, rowIndex),
                                    )?.nationality ?? row.nationality,
                                gender:
                                    travelerMap.get(
                                        travelerIdentityKey(row, rowIndex),
                                    )?.gender ?? row.gender,
                                date_of_issue:
                                    travelerMap.get(
                                        travelerIdentityKey(row, rowIndex),
                                    )?.date_of_issue ?? row.date_of_issue,
                                date_of_expiry:
                                    travelerMap.get(
                                        travelerIdentityKey(row, rowIndex),
                                    )?.date_of_expiry ?? row.date_of_expiry,
                                issue_place:
                                    travelerMap.get(
                                        travelerIdentityKey(row, rowIndex),
                                    )?.issue_place ?? row.issue_place,
                                room_relationship:
                                    row.room_relationship ??
                                    travelerMap.get(
                                        travelerIdentityKey(row, rowIndex),
                                    )?.relationship ??
                                    travelerMap.get(
                                        travelerIdentityKey(row, rowIndex),
                                    )?.role,
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
                            travelerIdentityKey(
                                row as TravelerWithUI,
                                existingIndex,
                            ) === travelerIdentity,
                    ) as TravelerWithUI | undefined;

                    return {
                        ...existing,
                        ...traveler,
                        passport_number:
                            traveler.passport_number ??
                            existing?.passport_number,
                        nationality:
                            traveler.nationality ?? existing?.nationality,
                        gender: traveler.gender ?? existing?.gender,
                        date_of_birth:
                            traveler.date_of_birth ?? existing?.date_of_birth,
                        age:
                            calculateAgeFromDob(traveler.date_of_birth) ??
                            existing?.age ??
                            traveler.age ??
                            null,
                        date_of_issue:
                            traveler.date_of_issue ?? existing?.date_of_issue,
                        date_of_expiry:
                            traveler.date_of_expiry ?? existing?.date_of_expiry,
                        issue_place:
                            traveler.issue_place ?? existing?.issue_place,
                        role: traveler.role ?? existing?.role,
                        relationship:
                            traveler.relationship ?? existing?.relationship,
                        sharing_plan:
                            traveler.sharing_plan ?? existing?.sharing_plan,
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
        [data.airlineList, data.roomLists, form, isCancelledTraveler, roomTabs],
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

            scrollToErrorBanner();

            return;
        }

        if (isCreate) {
            form.post(store().url, {
                preserveScroll: 'errors',
                onError: () => {
                    scrollToErrorBanner();
                },
            });
        }

        if (isEdit && data.id) {
            form.put(update(data.id).url, {
                preserveScroll: 'errors',
                onError: () => {
                    scrollToErrorBanner();
                },
            });
        }
    };

    const renderError = (path: string) => {
        const message = (form.errors as Record<string, string | undefined>)[
            path
        ];

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

        Object.entries(
            form.errors as Record<string, string | undefined>,
        ).forEach(([path, message]) => {
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
                                    <button
                                        key={path}
                                        type="button"
                                        className="block text-left text-sm underline"
                                        onClick={() =>
                                            navigateToErrorField(path)
                                        }
                                    >
                                        {path}: {message}
                                    </button>
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
                                            <button
                                                key={path}
                                                type="button"
                                                className="block text-left text-sm underline"
                                                onClick={() =>
                                                    navigateToErrorField(path)
                                                }
                                            >
                                                {path.split('.').slice(-1)[0]}:{' '}
                                                {message}
                                            </button>
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

            <Tabs
                value={activeTab}
                onValueChange={setActiveTab}
                className="w-full"
            >
                <TabsList className="flex w-full flex-wrap group-data-[orientation=horizontal]/tabs:h-11">
                    <TabsTrigger value="main" className="text-lg">
                        Main
                    </TabsTrigger>
                    {roomTabs.map((tab) => (
                        <TabsTrigger
                            key={tab.key}
                            value={`room-${tab.key}`}
                            className="text-lg"
                        >
                            Room List - {tab.label}
                        </TabsTrigger>
                    ))}
                    <TabsTrigger value="airline" className="text-lg">
                        Airline Name List
                    </TabsTrigger>
                </TabsList>

                <TabsContent value="main" className="space-y-4">
                    <ManifestDatatable
                        mode="travelers"
                        rows={(data.travelers ?? []) as TravelerWithUI[]}
                        disabled={isView}
                        allowReorder
                        errorPrefix="travelers"
                        errors={form.errors}
                        onMoveToHolding={moveTravelerToHolding}
                        onRowsChange={updateFromTravelers}
                    />
                </TabsContent>

                {roomTabs.map((tab) => {
                    const roomRows =
                        ((data.roomLists ?? {})[tab.key] as
                            | TravelerWithUI[]
                            | undefined) ?? [];
                    const roomRange = roomErrorRanges.find(
                        (range) => range.tabKey === tab.key,
                    );

                    return (
                        <TabsContent
                            key={tab.key}
                            value={`room-${tab.key}`}
                            className="space-y-4"
                        >
                            <Card>
                                <CardHeader className="gap-0">
                                    <CardTitle className="text-xl">
                                        {tab.label} Accommodation Information
                                    </CardTitle>
                                    <CardDescription>
                                        This section is auto-generated based on
                                        the travelers assigned to this
                                        accommodation and the package
                                        information. You can adjust the room
                                        type, bed type, meal plan, and sharing
                                        plan for each traveler in the table
                                        below.
                                    </CardDescription>
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

                            <ManifestDatatable
                                mode="room"
                                rows={roomRows}
                                disabled={isView}
                                allowReorder
                                errorPrefix={`roomLists.${tab.key}`}
                                roomGroupErrorPrefix="rooms"
                                roomGroupStartIndex={roomRange?.start ?? 0}
                                errors={form.errors}
                                onRowsChange={(rows: TravelerWithUI[]) => {
                                    const normalizedRows = rows.map(
                                        (row, index) => ({
                                            ...row,
                                            age:
                                                calculateAgeFromDob(
                                                    row.date_of_birth,
                                                ) ?? row.age,
                                            sort_order: index + 1,
                                            sharing_group_key:
                                                row.sharing_group_key ??
                                                `solo-${row.customer_confirmation_member_id ?? row.customer_id ?? index + 1}`,
                                        }),
                                    );

                                    const roomTravelerMap = new Map(
                                        normalizedRows.map((row, index) => [
                                            travelerIdentityKey(row, index),
                                            row,
                                        ]),
                                    );

                                    const nextTravelers = (
                                        (data.travelers ?? []) as
                                            | TravelerWithUI[]
                                            | undefined
                                    )?.map((traveler, travelerIndex) => {
                                        const updated = roomTravelerMap.get(
                                            travelerIdentityKey(
                                                traveler,
                                                travelerIndex,
                                            ),
                                        );

                                        if (!updated) {
                                            return traveler;
                                        }

                                        return {
                                            ...traveler,
                                            name_as_per_passport:
                                                updated.name_as_per_passport ??
                                                traveler.name_as_per_passport,
                                            passport_number:
                                                updated.passport_number ??
                                                traveler.passport_number,
                                            role: updated.role ?? traveler.role,
                                            relationship:
                                                updated.relationship ??
                                                updated.role ??
                                                traveler.relationship,
                                            sharing_plan:
                                                updated.sharing_plan ??
                                                traveler.sharing_plan,
                                            nationality:
                                                updated.nationality ??
                                                traveler.nationality,
                                            gender:
                                                updated.gender ??
                                                traveler.gender,
                                            date_of_birth:
                                                updated.date_of_birth ??
                                                traveler.date_of_birth,
                                            date_of_issue:
                                                updated.date_of_issue ??
                                                traveler.date_of_issue,
                                            date_of_expiry:
                                                updated.date_of_expiry ??
                                                traveler.date_of_expiry,
                                            issue_place:
                                                updated.issue_place ??
                                                traveler.issue_place,
                                            age:
                                                calculateAgeFromDob(
                                                    updated.date_of_birth ??
                                                        traveler.date_of_birth,
                                                ) ?? traveler.age,
                                        };
                                    });

                                    if (nextTravelers) {
                                        const travelerMap = new Map(
                                            nextTravelers.map(
                                                (traveler, travelerIndex) => [
                                                    travelerIdentityKey(
                                                        traveler,
                                                        travelerIndex,
                                                    ),
                                                    traveler,
                                                ],
                                            ),
                                        );

                                        const nextRoomLists =
                                            Object.fromEntries(
                                                Object.entries(
                                                    data.roomLists ?? {},
                                                ).map(([roomKey, roomRows]) => [
                                                    roomKey,
                                                    (roomKey === tab.key
                                                        ? normalizedRows
                                                        : (roomRows as TravelerWithUI[])
                                                    ).map(
                                                        (
                                                            roomRow,
                                                            roomIndex,
                                                        ) => {
                                                            const travelerUpdate =
                                                                travelerMap.get(
                                                                    travelerIdentityKey(
                                                                        roomRow,
                                                                        roomIndex,
                                                                    ),
                                                                );

                                                            if (
                                                                !travelerUpdate
                                                            ) {
                                                                return roomRow;
                                                            }

                                                            return {
                                                                ...roomRow,
                                                                name_as_per_passport:
                                                                    travelerUpdate.name_as_per_passport ??
                                                                    roomRow.name_as_per_passport,
                                                                passport_number:
                                                                    travelerUpdate.passport_number ??
                                                                    roomRow.passport_number,
                                                                role:
                                                                    travelerUpdate.role ??
                                                                    roomRow.role,
                                                                relationship:
                                                                    travelerUpdate.relationship ??
                                                                    travelerUpdate.role ??
                                                                    roomRow.relationship,
                                                                sharing_plan:
                                                                    travelerUpdate.sharing_plan ??
                                                                    roomRow.sharing_plan,
                                                                nationality:
                                                                    travelerUpdate.nationality ??
                                                                    roomRow.nationality,
                                                                gender:
                                                                    travelerUpdate.gender ??
                                                                    roomRow.gender,
                                                                date_of_birth:
                                                                    travelerUpdate.date_of_birth ??
                                                                    roomRow.date_of_birth,
                                                                date_of_issue:
                                                                    travelerUpdate.date_of_issue ??
                                                                    roomRow.date_of_issue,
                                                                date_of_expiry:
                                                                    travelerUpdate.date_of_expiry ??
                                                                    roomRow.date_of_expiry,
                                                                issue_place:
                                                                    travelerUpdate.issue_place ??
                                                                    roomRow.issue_place,
                                                                age:
                                                                    calculateAgeFromDob(
                                                                        travelerUpdate.date_of_birth ??
                                                                            roomRow.date_of_birth,
                                                                    ) ??
                                                                    roomRow.age,
                                                            };
                                                        },
                                                    ),
                                                ]),
                                            );

                                        const nextAirline = (
                                            (data.airlineList ??
                                                []) as TravelerWithUI[]
                                        ).map((airlineRow, airlineIndex) => {
                                            const travelerUpdate =
                                                travelerMap.get(
                                                    travelerIdentityKey(
                                                        airlineRow,
                                                        airlineIndex,
                                                    ),
                                                );

                                            if (!travelerUpdate) {
                                                return airlineRow;
                                            }

                                            return {
                                                ...airlineRow,
                                                name_as_per_passport:
                                                    travelerUpdate.name_as_per_passport ??
                                                    airlineRow.name_as_per_passport,
                                                passport_number:
                                                    travelerUpdate.passport_number ??
                                                    airlineRow.passport_number,
                                                nationality:
                                                    travelerUpdate.nationality ??
                                                    airlineRow.nationality,
                                                gender:
                                                    travelerUpdate.gender ??
                                                    airlineRow.gender,
                                                date_of_birth:
                                                    travelerUpdate.date_of_birth ??
                                                    airlineRow.date_of_birth,
                                                date_of_issue:
                                                    travelerUpdate.date_of_issue ??
                                                    airlineRow.date_of_issue,
                                                date_of_expiry:
                                                    travelerUpdate.date_of_expiry ??
                                                    airlineRow.date_of_expiry,
                                                issue_place:
                                                    travelerUpdate.issue_place ??
                                                    airlineRow.issue_place,
                                                age:
                                                    calculateAgeFromDob(
                                                        travelerUpdate.date_of_birth ??
                                                            airlineRow.date_of_birth,
                                                    ) ?? airlineRow.age,
                                            };
                                        });

                                        form.setData(
                                            'travelers',
                                            nextTravelers,
                                        );
                                        form.setData(
                                            'roomLists',
                                            nextRoomLists,
                                        );

                                        form.setData(
                                            'airlineList',
                                            nextAirline,
                                        );
                                    }
                                }}
                            />
                        </TabsContent>
                    );
                })}

                <TabsContent value="airline" className="space-y-4">
                    <ManifestDatatable
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
                        errorPrefix="airlineList"
                        errors={form.errors}
                        onRowsChange={(rows: TravelerWithUI[]) => {
                            const normalizedAirline = rows.map((row) => ({
                                ...row,
                                passport_number:
                                    row.passport_number ?? undefined,
                                age:
                                    calculateAgeFromDob(row.date_of_birth) ??
                                    row.age ??
                                    null,
                            }));

                            const airlineMap = new Map(
                                normalizedAirline.map((row, index) => [
                                    travelerIdentityKey(row, index),
                                    row,
                                ]),
                            );

                            const nextTravelers = (
                                (data.travelers ?? []) as TravelerWithUI[]
                            ).map((traveler, travelerIndex) => {
                                const updated = airlineMap.get(
                                    travelerIdentityKey(
                                        traveler,
                                        travelerIndex,
                                    ),
                                );

                                if (!updated) {
                                    return traveler;
                                }

                                return {
                                    ...traveler,
                                    name_as_per_passport:
                                        updated.name_as_per_passport ??
                                        traveler.name_as_per_passport,
                                    passport_number:
                                        updated.passport_number ??
                                        traveler.passport_number,
                                    role: updated.role ?? traveler.role,
                                    relationship:
                                        updated.relationship ??
                                        updated.role ??
                                        traveler.relationship,
                                    sharing_plan:
                                        updated.sharing_plan ??
                                        traveler.sharing_plan,
                                    nationality:
                                        updated.nationality ??
                                        traveler.nationality,
                                    gender: updated.gender ?? traveler.gender,
                                    date_of_birth:
                                        updated.date_of_birth ??
                                        traveler.date_of_birth,
                                    date_of_issue:
                                        updated.date_of_issue ??
                                        traveler.date_of_issue,
                                    date_of_expiry:
                                        updated.date_of_expiry ??
                                        traveler.date_of_expiry,
                                    issue_place:
                                        updated.issue_place ??
                                        traveler.issue_place,
                                    age:
                                        calculateAgeFromDob(
                                            updated.date_of_birth ??
                                                traveler.date_of_birth,
                                        ) ?? traveler.age,
                                };
                            });

                            const travelerMap = new Map(
                                nextTravelers.map((traveler, index) => [
                                    travelerIdentityKey(traveler, index),
                                    traveler,
                                ]),
                            );

                            const nextRoomLists = Object.fromEntries(
                                Object.entries(data.roomLists ?? {}).map(
                                    ([key, roomRows]) => [
                                        key,
                                        (roomRows as TravelerWithUI[]).map(
                                            (roomRow, roomIndex) => {
                                                const travelerUpdate =
                                                    travelerMap.get(
                                                        travelerIdentityKey(
                                                            roomRow,
                                                            roomIndex,
                                                        ),
                                                    );

                                                if (!travelerUpdate) {
                                                    return roomRow;
                                                }

                                                return {
                                                    ...roomRow,
                                                    name_as_per_passport:
                                                        travelerUpdate.name_as_per_passport ??
                                                        roomRow.name_as_per_passport,
                                                    passport_number:
                                                        travelerUpdate.passport_number ??
                                                        roomRow.passport_number,
                                                    role:
                                                        travelerUpdate.role ??
                                                        roomRow.role,
                                                    relationship:
                                                        travelerUpdate.relationship ??
                                                        travelerUpdate.role ??
                                                        roomRow.relationship,
                                                    sharing_plan:
                                                        travelerUpdate.sharing_plan ??
                                                        roomRow.sharing_plan,
                                                    nationality:
                                                        travelerUpdate.nationality ??
                                                        roomRow.nationality,
                                                    gender:
                                                        travelerUpdate.gender ??
                                                        roomRow.gender,
                                                    date_of_birth:
                                                        travelerUpdate.date_of_birth ??
                                                        roomRow.date_of_birth,
                                                    date_of_issue:
                                                        travelerUpdate.date_of_issue ??
                                                        roomRow.date_of_issue,
                                                    date_of_expiry:
                                                        travelerUpdate.date_of_expiry ??
                                                        roomRow.date_of_expiry,
                                                    issue_place:
                                                        travelerUpdate.issue_place ??
                                                        roomRow.issue_place,
                                                    age:
                                                        calculateAgeFromDob(
                                                            travelerUpdate.date_of_birth ??
                                                                roomRow.date_of_birth,
                                                        ) ?? roomRow.age,
                                                };
                                            },
                                        ),
                                    ],
                                ),
                            );

                            form.setData('travelers', nextTravelers);
                            form.setData('airlineList', normalizedAirline);
                            form.setData('roomLists', nextRoomLists);
                        }}
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
