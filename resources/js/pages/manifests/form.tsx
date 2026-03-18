import { FormProgressHeader } from '@/components/form-progress-header';
import { FormSection } from '@/components/form-section';
import { Accordion } from '@/components/ui/accordion';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { navigateToSection } from '@/lib/navigation-helper';
import { store, update } from '@/routes/manifests';
import { useForm } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, Download, RotateCcw } from 'lucide-react';
import { nanoid } from 'nanoid';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import AccommodationInformationCard from './components/accommodation-information-card';
import ManifestDatatable from './components/datatable';
import FlightDetailInformationCard from './components/flight-detail-information-card';
import ManifestInformationCard from './components/manifest-information-card';
import ManifestMemberInformationCard from './components/manifest-member-information-card';
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
    const sharingGroupId = toPositiveIntegerOrNull(
        row.manifest_sharing_group_id ?? row.sharing_group_id,
    );

    return {
        ...(row as TravelerWithUI),
        role: String(row.role ?? ''),
        relationship: String(row.relationship ?? ''),
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
                (sharingGroupId !== null ? `group-${sharingGroupId}` : null) ??
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
    const travelers = normalizeMainTabOrdering(
        flattenGroupedRows(initialData?.travelers ?? []),
    );

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
        status: initialData?.status ?? 'open',
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
            is_official: traveler.package_official_id !== null,
            role: traveler.role ?? existing?.role,
            relationship: traveler.relationship ?? existing?.relationship,
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
                existing?.room_relationship ?? traveler.relationship ?? '',
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
            number_of_beds_checked:
                existing?.number_of_beds_checked ??
                traveler.number_of_beds_checked ??
                false,
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

function capacityFromSharingPlan(sharingPlan?: string | null): number {
    const plan = String(sharingPlan ?? '')
        .toLowerCase()
        .trim();

    if (plan === 'quad') {
        return 4;
    }

    if (plan === 'triple') {
        return 3;
    }

    if (plan === 'double') {
        return 2;
    }

    return 1;
}

function normalizeMainTabOrdering(rows: TravelerWithUI[]): TravelerWithUI[] {
    const grouped = new Map<
        string,
        {
            groupSortOrder: number;
            firstGlobalOrder: number;
            isOfficial: boolean;
            members: Array<TravelerWithUI & { _globalOrder: number }>;
        }
    >();

    rows.forEach((row, index) => {
        const key =
            row.sharing_group_key ??
            `solo-${row.customer_confirmation_member_id ?? row.customer_id ?? index + 1}`;
        const groupSortOrder = Number(
            row.group_sort_order ??
                row.manifest_sharing_group_id ??
                Number.MAX_SAFE_INTEGER,
        );
        const memberOrder = Number(row.sort_order ?? row.sn ?? index + 1);

        if (!grouped.has(key)) {
            grouped.set(key, {
                groupSortOrder,
                firstGlobalOrder: memberOrder,
                isOfficial: !!row.package_official_id,
                members: [],
            });
        }

        const group = grouped.get(key);

        if (!group) {
            return;
        }

        group.groupSortOrder = Math.min(group.groupSortOrder, groupSortOrder);
        group.firstGlobalOrder = Math.min(group.firstGlobalOrder, memberOrder);
        group.isOfficial = group.isOfficial && !!row.package_official_id;
        group.members.push({ ...row, _globalOrder: memberOrder });
    });

    const orderedGroups = Array.from(grouped.entries()).sort((a, b) => {
        const [aKey, aData] = a;
        const [bKey, bData] = b;

        if (aData.isOfficial !== bData.isOfficial) {
            return aData.isOfficial ? 1 : -1;
        }

        if (aData.groupSortOrder !== bData.groupSortOrder) {
            return aData.groupSortOrder - bData.groupSortOrder;
        }

        if (aData.firstGlobalOrder !== bData.firstGlobalOrder) {
            return aData.firstGlobalOrder - bData.firstGlobalOrder;
        }

        return aKey.localeCompare(bKey);
    });

    let globalSn = 1;

    return orderedGroups.flatMap(([groupKey, groupData], groupIndex) => {
        const orderedMembers = [...groupData.members].sort((left, right) => {
            if (left._globalOrder !== right._globalOrder) {
                return left._globalOrder - right._globalOrder;
            }

            return (
                (left.id ?? Number.MAX_SAFE_INTEGER) -
                (right.id ?? Number.MAX_SAFE_INTEGER)
            );
        });

        return orderedMembers.map((member, memberIndex) => ({
            ...member,
            sharing_group_key: groupKey,
            group_sort_order: groupIndex + 1,
            sn: globalSn++,
            sort_order: memberIndex + 1,
        }));
    });
}

function syncTravelerSharingGroups(
    nextTravelers: TravelerWithUI[],
    previousTravelers: TravelerWithUI[],
): TravelerWithUI[] {
    const previousTravelerMap = new Map(
        previousTravelers.map((traveler, index) => [
            travelerIdentityKey(traveler, index),
            traveler,
        ]),
    );

    const bucketGroups = new Map<string, string[]>();
    const groupCounts = new Map<string, number>();
    const bucketCounters = new Map<string, number>();

    const assigned: TravelerWithUI[] = [];

    nextTravelers.forEach((traveler, index) => {
        const identity = travelerIdentityKey(traveler, index);
        const previousTraveler = previousTravelerMap.get(identity);

        const customerConfirmationId = Number(
            traveler.customer_confirmation_id ??
                previousTraveler?.customer_confirmation_id ??
                0,
        );
        const sharingPlan = String(
            traveler.sharing_plan ?? previousTraveler?.sharing_plan ?? '',
        )
            .toLowerCase()
            .trim();
        const capacity = capacityFromSharingPlan(sharingPlan);

        let groupKey =
            traveler.sharing_group_key ??
            previousTraveler?.sharing_group_key ??
            '';

        const isNewTraveler = !previousTraveler;
        const canAutoAssign =
            isNewTraveler && customerConfirmationId > 0 && sharingPlan !== '';

        if (canAutoAssign) {
            const bucketKey = `${customerConfirmationId}|${sharingPlan}`;
            const candidates = bucketGroups.get(bucketKey) ?? [];
            const target = candidates.find(
                (candidate) => (groupCounts.get(candidate) ?? 0) < capacity,
            );

            if (target) {
                groupKey = target;
            } else {
                const nextCounter = (bucketCounters.get(bucketKey) ?? 0) + 1;
                bucketCounters.set(bucketKey, nextCounter);
                groupKey = `auto-${bucketKey}-${nextCounter}`;
                bucketGroups.set(bucketKey, [...candidates, groupKey]);
            }
        }

        if (!groupKey || groupKey.trim().length === 0) {
            groupKey = `solo-${
                traveler.customer_confirmation_member_id ??
                traveler.customer_id ??
                index + 1
            }`;
        }

        if (customerConfirmationId > 0 && sharingPlan !== '') {
            const bucketKey = `${customerConfirmationId}|${sharingPlan}`;
            const existingBucket = bucketGroups.get(bucketKey) ?? [];

            if (!existingBucket.includes(groupKey)) {
                bucketGroups.set(bucketKey, [...existingBucket, groupKey]);
            }
        }

        groupCounts.set(groupKey, (groupCounts.get(groupKey) ?? 0) + 1);

        assigned.push({
            ...traveler,
            sharing_group_key: groupKey,
        });
    });

    return assigned;
}

function syncRoomRowsWithTravelers(
    activeTravelers: TravelerWithUI[],
    existingRows: TravelerWithUI[],
    accommodationKey: string,
    defaultMealPlan: string,
): TravelerWithUI[] {
    const travelerMap = new Map(
        activeTravelers.map((traveler, index) => [
            travelerIdentityKey(traveler, index),
            traveler,
        ]),
    );

    const keptRows = existingRows
        .map((row, index) => {
            const identity = travelerIdentityKey(row, index);
            const traveler = travelerMap.get(identity);

            if (!traveler) {
                return null;
            }

            return {
                ...row,
                ...traveler,
                row_key:
                    row.row_key ??
                    traveler.row_key ??
                    `room-${accommodationKey}-${traveler.customer_confirmation_member_id ?? traveler.customer_id ?? index}`,
                sharing_group_key:
                    row.sharing_group_key ??
                    traveler.sharing_group_key ??
                    `solo-${traveler.customer_confirmation_member_id ?? traveler.customer_id ?? index + 1}`,
                sharing_plan:
                    row.sharing_plan ?? traveler.sharing_plan ?? 'single',
                room_relationship:
                    row.room_relationship ?? traveler.relationship ?? '',
                room_label: row.room_label ?? traveler.room_label ?? '',
                room_type: row.room_type ?? traveler.room_type ?? '',
                bed_type: row.bed_type ?? traveler.bed_type ?? '',
                remarks: row.remarks ?? traveler.remarks,
                room_remarks: row.room_remarks ?? traveler.room_remarks,
                meal: row.meal ?? traveler.meal ?? defaultMealPlan ?? '',
                age:
                    calculateAgeFromDob(
                        traveler.date_of_birth ?? row.date_of_birth,
                    ) ?? row.age,
            } as TravelerWithUI;
        })
        .filter((row): row is TravelerWithUI => row !== null);

    return keptRows.map((row, index) => ({
        ...row,
        sn: index + 1,
        sort_order: index + 1,
    }));
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
    const [openSections, setOpenSections] = useState<string[]>([
        'manifest_information',
    ]);

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

    const officialCheckTabs = useMemo(() => {
        return roomTabs.map((tab) => ({
            ...tab,
            key: `official-check-${tab.key}`,
            sourceRoomKey: tab.key,
        }));
    }, [roomTabs]);

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
                syncRoomRowsWithTravelers(
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

        const membershipChanged = roomTabs.some((tab) => {
            const currentRows =
                ((currentRoomLists[tab.key] ?? []) as TravelerWithUI[]) ?? [];
            const syncedRows =
                ((syncedRoomLists[tab.key] ?? []) as TravelerWithUI[]) ?? [];

            if (currentRows.length !== syncedRows.length) {
                return true;
            }

            const currentIdentitySet = new Set(
                currentRows.map((traveler, index) =>
                    travelerIdentityKey(traveler, index),
                ),
            );
            const syncedIdentitySet = new Set(
                syncedRows.map((traveler, index) =>
                    travelerIdentityKey(traveler, index),
                ),
            );

            if (currentIdentitySet.size !== syncedIdentitySet.size) {
                return true;
            }

            for (const identity of currentIdentitySet) {
                if (!syncedIdentitySet.has(identity)) {
                    return true;
                }
            }

            return false;
        });

        if (keysChanged || membershipChanged) {
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
            const groupedTravelers = syncTravelerSharingGroups(
                nextTravelers,
                ((data.travelers ?? []) as TravelerWithUI[]) ?? [],
            );

            const orderedTravelers = normalizeMainTabOrdering(groupedTravelers);

            const travelersWithSn = orderedTravelers.map((row, index) => ({
                ...row,
                sn: index + 1,
                role: row.role ?? undefined,
                relationship: row.relationship ?? undefined,
                passport_number: row.passport_number ?? undefined,
                age: calculateAgeFromDob(row.date_of_birth) ?? row.age ?? null,
                status:
                    row.status ??
                    (row.customer_confirmation_member_id
                        ? 'pending_payment'
                        : 'confirmed'),
                sharing_group_key:
                    row.sharing_group_key ??
                    `solo-${row.customer_confirmation_member_id ?? row.customer_id ?? index + 1}`,
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
                        syncRoomRowsWithTravelers(
                            activeTravelersWithSn,
                            (rows as TravelerWithUI[]).map((row, rowIndex) => {
                                const travelerUpdate = travelerMap.get(
                                    travelerIdentityKey(row, rowIndex),
                                );

                                if (!travelerUpdate) {
                                    return row;
                                }

                                return {
                                    ...row,
                                    name_as_per_passport:
                                        travelerUpdate.name_as_per_passport ??
                                        row.name_as_per_passport,
                                    passport_number:
                                        travelerUpdate.passport_number ??
                                        row.passport_number,
                                    role: travelerUpdate.role ?? row.role,
                                    nationality:
                                        travelerUpdate.nationality ??
                                        row.nationality,
                                    gender: travelerUpdate.gender ?? row.gender,
                                    date_of_birth:
                                        travelerUpdate.date_of_birth ??
                                        row.date_of_birth,
                                    date_of_issue:
                                        travelerUpdate.date_of_issue ??
                                        row.date_of_issue,
                                    date_of_expiry:
                                        travelerUpdate.date_of_expiry ??
                                        row.date_of_expiry,
                                    issue_place:
                                        travelerUpdate.issue_place ??
                                        row.issue_place,
                                    birth_place:
                                        travelerUpdate.birth_place ??
                                        row.birth_place,
                                    contact_no:
                                        travelerUpdate.contact_no ??
                                        row.contact_no,
                                    package_price:
                                        travelerUpdate.package_price ??
                                        row.package_price,
                                    room_relationship:
                                        row.room_relationship ??
                                        travelerUpdate.relationship,
                                };
                            }),
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
                        birth_place:
                            traveler.birth_place ?? existing?.birth_place,
                        contact_no: traveler.contact_no ?? existing?.contact_no,
                        package_price:
                            traveler.package_price ?? existing?.package_price,
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
        [
            data.airlineList,
            data.roomLists,
            data.travelers,
            form,
            isCancelledTraveler,
            roomTabs,
        ],
    );

    const resetRoomListTab = useCallback(
        (tabKey: string) => {
            const targetTab = roomTabs.find((tab) => tab.key === tabKey);

            if (!targetTab) {
                return;
            }

            const currentNonCancelledTravelers = (
                (data.travelers ?? []) as TravelerWithUI[]
            ).filter((traveler) => !isCancelledTraveler(traveler));

            const baseRows = buildRoomRowsFromTravelers(
                currentNonCancelledTravelers,
                [],
                targetTab.key,
                targetTab.accommodation.type_of_meal ?? '',
            ).map((row, index) => ({
                ...row,
                sn: index + 1,
                sort_order: index + 1,
            }));

            setData('roomLists', {
                ...(data.roomLists ?? {}),
                [tabKey]: baseRows,
            });
        },
        [
            data.roomLists,
            data.travelers,
            isCancelledTraveler,
            roomTabs,
            setData,
        ],
    );

    const getTravelerAssignedLocations = useCallback(
        (traveler: TravelerWithUI): string[] => {
            const membership = new Set<string>();

            roomTabs.forEach((tab) => {
                const rows = ((data.roomLists ?? {})[tab.key] ?? []) as
                    | TravelerWithUI[]
                    | undefined;

                if (!rows || rows.length === 0) {
                    return;
                }

                const exists = rows.some((row, index) => {
                    return (
                        travelerIdentityKey(row, index) ===
                        travelerIdentityKey(traveler, 0)
                    );
                });

                if (exists) {
                    membership.add(tab.key.trim().toLowerCase());
                }
            });

            return Array.from(membership);
        },
        [data.roomLists, roomTabs],
    );

    const handleRoomAssignmentChange = useCallback(
        (
            traveler: TravelerWithUI,
            action: 'assign' | 'unassign',
            scope: string,
        ) => {
            const normalizeLocationKey = (value: string): string => {
                return value.trim().toLowerCase();
            };

            const allLocationKeys = roomTabs
                .map((tab) => normalizeLocationKey(tab.key))
                .filter((value) => value.length > 0);
            const scopeKey = normalizeLocationKey(scope);
            const targetLocations =
                scopeKey === 'all'
                    ? allLocationKeys
                    : scopeKey.length > 0
                      ? [scopeKey]
                      : [];

            if (targetLocations.length === 0) {
                return;
            }

            const currentRoomLists =
                (data.roomLists as Record<string, TravelerWithUI[]>) ?? {};
            const nextRoomLists: Record<string, TravelerWithUI[]> = {
                ...currentRoomLists,
            };

            targetLocations.forEach((locationKey) => {
                const tab = roomTabs.find(
                    (roomTab) =>
                        normalizeLocationKey(roomTab.key) === locationKey,
                );

                if (!tab) {
                    return;
                }

                const existingRows = (nextRoomLists[tab.key] ??
                    []) as TravelerWithUI[];
                const travelerExists = existingRows.some((row, rowIndex) => {
                    return (
                        travelerIdentityKey(row, rowIndex) ===
                        travelerIdentityKey(traveler, 0)
                    );
                });

                if (action === 'unassign') {
                    nextRoomLists[tab.key] = existingRows
                        .filter((row, rowIndex) => {
                            return (
                                travelerIdentityKey(row, rowIndex) !==
                                travelerIdentityKey(traveler, 0)
                            );
                        })
                        .map((row, index) => ({
                            ...row,
                            sn: index + 1,
                            sort_order: index + 1,
                        }));

                    return;
                }

                if (!travelerExists) {
                    const appended = buildRoomRowsFromTravelers(
                        [traveler],
                        existingRows,
                        tab.key,
                        tab.accommodation.type_of_meal ?? '',
                    );

                    nextRoomLists[tab.key] = [...existingRows, ...appended].map(
                        (row, index) => ({
                            ...row,
                            sn: index + 1,
                            sort_order: index + 1,
                        }),
                    );
                }
            });

            form.setData('roomLists', nextRoomLists);
        },
        [data.roomLists, form, roomTabs],
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

    const sectionStatuses = useMemo(() => {
        const errorEntries = Object.entries(
            form.errors as Record<string, string | undefined>,
        ).filter(([, message]) => Boolean(message));

        const hasErrorsForPrefix = (prefixes: string[]): boolean => {
            return errorEntries.some(([path]) =>
                prefixes.some(
                    (prefix) =>
                        path === prefix || path.startsWith(`${prefix}.`),
                ),
            );
        };

        const selectedFlights = selectedPackage?.flights ?? [];
        const activeTravelerCount = (
            ((data.travelers ?? []) as TravelerWithUI[]).filter(
                (traveler) => !isCancelledTraveler(traveler),
            ) ?? []
        ).length;

        const manifestInformationStatus = hasErrorsForPrefix([
            'package_id',
            'status',
            'notes',
        ])
            ? 'error'
            : data.package_id && data.package_id > 0 && data.status
              ? 'complete'
              : 'incomplete';

        const manifestMemberInformationStatus = hasErrorsForPrefix([
            'travelers',
        ])
            ? 'error'
            : activeTravelerCount > 0
              ? 'complete'
              : 'incomplete';

        const accommodationInformationStatus =
            hotelAccommodations.length > 0 ? 'complete' : 'incomplete';

        const flightDetailInformationStatus =
            selectedFlights.length > 0 ? 'complete' : 'incomplete';

        return {
            manifest_information: manifestInformationStatus,
            manifest_member_information: manifestMemberInformationStatus,
            accommodation_information: accommodationInformationStatus,
            flight_detail_information: flightDetailInformationStatus,
        } as const;
    }, [
        form.errors,
        data.package_id,
        data.status,
        data.travelers,
        hotelAccommodations.length,
        isCancelledTraveler,
        selectedPackage,
    ]);

    const sections = useMemo(() => {
        return [
            {
                id: 'manifest_information',
                title: 'Manifest Information',
                status: sectionStatuses.manifest_information,
            },
            {
                id: 'manifest_member_information',
                title: 'Manifest Member Information',
                status: sectionStatuses.manifest_member_information,
            },
            {
                id: 'accommodation_information',
                title: 'Accommodation Information',
                status: sectionStatuses.accommodation_information,
            },
            {
                id: 'flight_detail_information',
                title: 'Flight Detail Information',
                status: sectionStatuses.flight_detail_information,
            },
        ];
    }, [sectionStatuses]);

    const handleSectionClick = useCallback(
        (sectionId: string) => {
            navigateToSection(sectionId, setOpenSections);
        },
        [setOpenSections],
    );

    const nonCancelledTravelers = useMemo(() => {
        return ((data.travelers ?? []) as TravelerWithUI[]).filter(
            (traveler) => !isCancelledTraveler(traveler),
        );
    }, [data.travelers, isCancelledTraveler]);

    const nonCancelledNonOfficialTravelers = useMemo(() => {
        return nonCancelledTravelers.filter(
            (traveler) => !traveler.package_official_id,
        );
    }, [nonCancelledTravelers]);

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

    const exportPdfWithSnapshot = useCallback(
        (url: string, snapshot: Record<string, unknown>) => {
            const csrfToken = document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content');

            if (!csrfToken) {
                return;
            }

            const exportForm = document.createElement('form');
            exportForm.method = 'POST';
            exportForm.action = url;
            exportForm.target = '_blank';

            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = csrfToken;
            exportForm.appendChild(csrfInput);

            const snapshotInput = document.createElement('input');
            snapshotInput.type = 'hidden';
            snapshotInput.name = 'snapshot';
            snapshotInput.value = JSON.stringify(snapshot);
            exportForm.appendChild(snapshotInput);

            document.body.appendChild(exportForm);
            exportForm.submit();
            document.body.removeChild(exportForm);
        },
        [],
    );

    return (
        <div className="mx-auto w-full">
            {mode !== 'view' && (
                <FormProgressHeader
                    title="Manifest"
                    sections={sections}
                    onSectionClick={handleSectionClick}
                />
            )}

            <form onSubmit={submit} className="space-y-6 py-2">
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
                                    ({
                                        travelerIndex,
                                        travelerName,
                                        issues,
                                    }) => (
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
                                                        navigateToErrorField(
                                                            path,
                                                        )
                                                    }
                                                >
                                                    {
                                                        path
                                                            .split('.')
                                                            .slice(-1)[0]
                                                    }
                                                    : {message}
                                                </button>
                                            ))}
                                        </div>
                                    ),
                                )}
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
                    <FormSection
                        value="manifest_information"
                        title="Manifest Information"
                        description="Package selection, status, and notes for this manifest."
                        status={sectionStatuses.manifest_information}
                    >
                        <ManifestInformationCard
                            isView={isView}
                            data={data}
                            dataPackage={packageOptions}
                            setData={form.setData}
                            renderError={renderError}
                        />
                    </FormSection>

                    <FormSection
                        value="manifest_member_information"
                        title="Manifest Member Information"
                        description="Auto-calculated summary from active travelers."
                        status={sectionStatuses.manifest_member_information}
                        required={false}
                    >
                        <ManifestMemberInformationCard
                            travelers={
                                (data.travelers ?? []) as TravelerWithUI[]
                            }
                        />
                    </FormSection>

                    <FormSection
                        value="accommodation_information"
                        title="Accommodation Information"
                        description="Read-only accommodation details from selected package."
                        status={sectionStatuses.accommodation_information}
                        required={false}
                    >
                        <div className="space-y-4">
                            {roomTabs.map((tab) => (
                                <AccommodationInformationCard
                                    key={tab.key}
                                    title={`${tab.label} Accommodation Information`}
                                    description="This section is auto-generated based on the selected package accommodation data."
                                    accommodation={tab.accommodation}
                                />
                            ))}
                        </div>
                    </FormSection>

                    <FormSection
                        value="flight_detail_information"
                        title="Flight Detail Information"
                        description="Read-only flight details from selected package."
                        status={sectionStatuses.flight_detail_information}
                        required={false}
                    >
                        <FlightDetailInformationCard
                            flights={selectedPackage?.flights ?? []}
                        />
                    </FormSection>
                </Accordion>

                <Tabs
                    value={activeTab}
                    onValueChange={setActiveTab}
                    className="w-full"
                >
                    <div className="space-y-3">
                        <TabsList className="w-fit group-data-[orientation=horizontal]/tabs:h-11">
                            <TabsTrigger value="main" className="text-lg">
                                Main
                            </TabsTrigger>
                        </TabsList>

                        <ScrollArea className="w-full whitespace-nowrap">
                            <TabsList className="w-fit group-data-[orientation=horizontal]/tabs:h-11">
                                <TabsTrigger
                                    value="airline"
                                    className="text-lg"
                                >
                                    Airline Name List
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
                                {officialCheckTabs.map((tab) => (
                                    <TabsTrigger
                                        key={tab.key}
                                        value={`room-check-${tab.sourceRoomKey}`}
                                        className="text-lg"
                                    >
                                        Room List for Official to Check -{' '}
                                        {tab.label}
                                    </TabsTrigger>
                                ))}
                                <TabsTrigger
                                    value="course-collection"
                                    className="text-lg"
                                >
                                    Namelist Course & Collection Items
                                </TabsTrigger>
                            </TabsList>
                            <ScrollBar orientation="horizontal" />
                        </ScrollArea>
                    </div>

                    <TabsContent value="main" className="space-y-4">
                        <ManifestDatatable
                            mode="travelers"
                            rows={(data.travelers ?? []) as TravelerWithUI[]}
                            disabled={isView}
                            allowReorder
                            errorPrefix="travelers"
                            errors={form.errors}
                            roomAssignmentOptions={roomTabs.map((tab) => ({
                                key: tab.key,
                                label: tab.label,
                            }))}
                            getTravelerAssignedLocations={
                                getTravelerAssignedLocations
                            }
                            onRoomAssignmentChange={handleRoomAssignmentChange}
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
                                <ManifestDatatable
                                    mode="room"
                                    rows={roomRows}
                                    currentRoomLocationKey={tab.key}
                                    disabled={isView}
                                    allowReorder
                                    errorPrefix={`roomLists.${tab.key}`}
                                    roomGroupErrorPrefix="rooms"
                                    roomGroupStartIndex={roomRange?.start ?? 0}
                                    errors={form.errors}
                                    roomAssignmentOptions={roomTabs.map(
                                        (roomTab) => ({
                                            key: roomTab.key,
                                            label: roomTab.label,
                                        }),
                                    )}
                                    onRowsChange={(rows: TravelerWithUI[]) => {
                                        const normalizedRows = rows.map(
                                            (row, index) => ({
                                                ...row,
                                                accommodation_key: tab.key,
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
                                                role:
                                                    updated.role ??
                                                    traveler.role,
                                                relationship:
                                                    updated.relationship ??
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
                                                birth_place:
                                                    updated.birth_place ??
                                                    traveler.birth_place,
                                                contact_no:
                                                    updated.contact_no ??
                                                    traveler.contact_no,
                                                package_price:
                                                    updated.package_price ??
                                                    traveler.package_price,
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
                                                    (
                                                        traveler,
                                                        travelerIndex,
                                                    ) => [
                                                        travelerIdentityKey(
                                                            traveler,
                                                            travelerIndex,
                                                        ),
                                                        traveler,
                                                    ],
                                                ),
                                            );

                                            const nextActiveTravelers =
                                                nextTravelers.filter(
                                                    (traveler) =>
                                                        !isCancelledTraveler(
                                                            traveler,
                                                        ),
                                                );

                                            const nextRoomLists =
                                                Object.fromEntries(
                                                    roomTabs.map((roomTab) => {
                                                        const roomKey =
                                                            roomTab.key;
                                                        const sourceRows =
                                                            roomKey === tab.key
                                                                ? normalizedRows
                                                                : (
                                                                      ((data.roomLists ??
                                                                          {})[
                                                                          roomKey
                                                                      ] ??
                                                                          []) as TravelerWithUI[]
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
                                                                              birth_place:
                                                                                  travelerUpdate.birth_place ??
                                                                                  roomRow.birth_place,
                                                                              contact_no:
                                                                                  travelerUpdate.contact_no ??
                                                                                  roomRow.contact_no,
                                                                              package_price:
                                                                                  travelerUpdate.package_price ??
                                                                                  roomRow.package_price,
                                                                              age:
                                                                                  calculateAgeFromDob(
                                                                                      travelerUpdate.date_of_birth ??
                                                                                          roomRow.date_of_birth,
                                                                                  ) ??
                                                                                  roomRow.age,
                                                                          };
                                                                      },
                                                                  );

                                                        return [
                                                            roomKey,
                                                            syncRoomRowsWithTravelers(
                                                                nextActiveTravelers,
                                                                sourceRows,
                                                                roomKey,
                                                                roomTab
                                                                    .accommodation
                                                                    .type_of_meal ??
                                                                    '',
                                                            ),
                                                        ];
                                                    }),
                                                );

                                            const nextAirline = (
                                                (data.airlineList ??
                                                    []) as TravelerWithUI[]
                                            ).map(
                                                (airlineRow, airlineIndex) => {
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
                                                        birth_place:
                                                            travelerUpdate.birth_place ??
                                                            airlineRow.birth_place,
                                                        contact_no:
                                                            travelerUpdate.contact_no ??
                                                            airlineRow.contact_no,
                                                        package_price:
                                                            travelerUpdate.package_price ??
                                                            airlineRow.package_price,
                                                        age:
                                                            calculateAgeFromDob(
                                                                travelerUpdate.date_of_birth ??
                                                                    airlineRow.date_of_birth,
                                                            ) ?? airlineRow.age,
                                                    };
                                                },
                                            );

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

                                {!isView && (
                                    <div className="flex justify-end">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                resetRoomListTab(tab.key)
                                            }
                                        >
                                            Reset This Room List to Main
                                            Structure
                                        </Button>
                                    </div>
                                )}
                            </TabsContent>
                        );
                    })}

                    {officialCheckTabs.map((tab) => {
                        const sourceRoomRows =
                            ((data.roomLists ?? {})[
                                tab.sourceRoomKey
                            ] as TravelerWithUI[]) ?? [];

                        return (
                            <TabsContent
                                key={tab.key}
                                value={`room-check-${tab.sourceRoomKey}`}
                                className="space-y-4"
                            >
                                <div className="flex items-center justify-end">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={!data.id}
                                        onClick={() => {
                                            if (!data.id) {
                                                return;
                                            }

                                            exportPdfWithSnapshot(
                                                `/manifests/${data.id}/room-check-pdf?location=${encodeURIComponent(tab.sourceRoomKey)}`,
                                                {
                                                    room_check_rows:
                                                        sourceRoomRows,
                                                    room_check_location_label:
                                                        tab.label,
                                                },
                                            );
                                        }}
                                    >
                                        <Download className="mr-2 h-4 w-4" />
                                        Export PDF
                                    </Button>
                                </div>

                                <ManifestDatatable
                                    mode="room_check"
                                    rows={sourceRoomRows}
                                    currentRoomLocationKey={tab.sourceRoomKey}
                                    disabled={isView}
                                    allowReorder
                                    errorPrefix={`roomLists.${tab.sourceRoomKey}`}
                                    roomGroupErrorPrefix="rooms"
                                    errors={form.errors}
                                    roomAssignmentOptions={roomTabs.map(
                                        (roomTab) => ({
                                            key: roomTab.key,
                                            label: roomTab.label,
                                        }),
                                    )}
                                    onRowsChange={(updatedRows) => {
                                        const normalizedRows = updatedRows.map(
                                            (row, index) => ({
                                                ...row,
                                                sort_order: index + 1,
                                                sharing_group_key:
                                                    row.sharing_group_key ??
                                                    `solo-${row.customer_confirmation_member_id ?? row.customer_id ?? index + 1}`,
                                            }),
                                        );

                                        setData('roomLists', {
                                            ...(data.roomLists ?? {}),
                                            [tab.sourceRoomKey]: normalizedRows,
                                        });
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
                            roomAssignmentOptions={roomTabs.map((tab) => ({
                                key: tab.key,
                                label: tab.label,
                            }))}
                            onRowsChange={(rows: TravelerWithUI[]) => {
                                const normalizedAirline = rows.map((row) => ({
                                    ...row,
                                    passport_number:
                                        row.passport_number ?? undefined,
                                    age:
                                        calculateAgeFromDob(
                                            row.date_of_birth,
                                        ) ??
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
                                            traveler.relationship,
                                        sharing_plan:
                                            updated.sharing_plan ??
                                            traveler.sharing_plan,
                                        nationality:
                                            updated.nationality ??
                                            traveler.nationality,
                                        gender:
                                            updated.gender ?? traveler.gender,
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
                                        birth_place:
                                            updated.birth_place ??
                                            traveler.birth_place,
                                        contact_no:
                                            updated.contact_no ??
                                            traveler.contact_no,
                                        package_price:
                                            updated.package_price ??
                                            traveler.package_price,
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
                                                        birth_place:
                                                            travelerUpdate.birth_place ??
                                                            roomRow.birth_place,
                                                        contact_no:
                                                            travelerUpdate.contact_no ??
                                                            roomRow.contact_no,
                                                        package_price:
                                                            travelerUpdate.package_price ??
                                                            roomRow.package_price,
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

                    <TabsContent
                        value="course-collection"
                        className="space-y-4"
                    >
                        <div className="flex items-center justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={!data.id}
                                onClick={() => {
                                    if (!data.id) {
                                        return;
                                    }

                                    exportPdfWithSnapshot(
                                        `/manifests/${data.id}/collection-items-pdf`,
                                        {
                                            travelers:
                                                nonCancelledNonOfficialTravelers,
                                        },
                                    );
                                }}
                            >
                                <Download className="mr-2 h-4 w-4" />
                                Export PDF
                            </Button>
                        </div>

                        <ManifestDatatable
                            mode="course_collection"
                            rows={
                                nonCancelledNonOfficialTravelers as TravelerWithUI[]
                            }
                            disabled={isView}
                            allowReorder={false}
                            errors={form.errors}
                            onRowsChange={(rows: TravelerWithUI[]) => {
                                const checklistMap = new Map(
                                    rows.map((row, index) => [
                                        travelerIdentityKey(row, index),
                                        row,
                                    ]),
                                );

                                const nextTravelers = (
                                    (data.travelers ?? []) as TravelerWithUI[]
                                ).map((traveler, travelerIndex) => {
                                    const updatedChecklist = checklistMap.get(
                                        travelerIdentityKey(
                                            traveler,
                                            travelerIndex,
                                        ),
                                    );

                                    if (!updatedChecklist) {
                                        return traveler;
                                    }

                                    return {
                                        ...traveler,
                                        course_1: !!updatedChecklist.course_1,
                                        course_2: !!updatedChecklist.course_2,
                                        lanyard: !!updatedChecklist.lanyard,
                                        luggage_tag:
                                            !!updatedChecklist.luggage_tag,
                                        cabin_tag: !!updatedChecklist.cabin_tag,
                                        passport_cover:
                                            !!updatedChecklist.passport_cover,
                                        umrah_guidebook:
                                            !!updatedChecklist.umrah_guidebook,
                                        sling_bag: !!updatedChecklist.sling_bag,
                                        cabin_size_luggage:
                                            !!updatedChecklist.cabin_size_luggage,
                                        umrah_essentials:
                                            !!updatedChecklist.umrah_essentials,
                                    };
                                });

                                form.setData('travelers', nextTravelers);
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
                                {isCreate
                                    ? 'Create Manifest'
                                    : 'Update Manifest'}
                            </Button>
                        </>
                    )}
                </div>
            </form>
        </div>
    );
}
