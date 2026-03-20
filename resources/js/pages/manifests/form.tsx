import { DocumentField } from '@/components/document-field';
import { FormField } from '@/components/form-field';
import { FormProgressHeader } from '@/components/form-progress-header';
import { FormSection } from '@/components/form-section';
import { ProperInput } from '@/components/proper-input';
import { ProperInputSelect } from '@/components/proper-input-select';
import { Accordion } from '@/components/ui/accordion';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    convertNameToArabic,
    normalizeArabicNameInput,
} from '@/lib/arabic-name';
import { navigateToSection } from '@/lib/navigation-helper';
import { store } from '@/routes/manifests';
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
    type ManifestDocumentFieldKey,
    type ManifestDocumentItem,
    type ManifestDocumentsByField,
    type ManifestFormData,
    type ManifestFormProps,
    type PackageAccommodationOption,
    type PackageForManifestOption,
    type TravelerWithUI,
} from './types';
import { manifestValidationSchema } from './validation';

export type { ManifestFormData } from './types';

const MANIFEST_DOCUMENT_TABS: Array<{
    key: ManifestDocumentFieldKey;
    label: string;
    hint: string;
    accept: string;
}> = [
    {
        key: 'flight_tickets',
        label: 'Flight Tickets',
        hint: 'Upload flight ticket files for this manifest.',
        accept: '.pdf,.png,.jpg,.jpeg,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv',
    },
    {
        key: 'visa',
        label: 'Visa',
        hint: 'Upload visa files for this manifest.',
        accept: '.pdf,.png,.jpg,.jpeg,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv',
    },
    {
        key: 'hotel',
        label: 'Hotel',
        hint: 'Upload hotel-related documents.',
        accept: '.pdf,.png,.jpg,.jpeg,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv',
    },
    {
        key: 'passport',
        label: 'Passport',
        hint: 'Upload passport supporting files.',
        accept: '.pdf,.png,.jpg,.jpeg,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv',
    },
    {
        key: 'photo',
        label: 'Photo',
        hint: 'Upload traveler photo files.',
        accept: '.pdf,.png,.jpg,.jpeg,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv',
    },
];

function createEmptyDocumentEntry(): ManifestDocumentItem {
    return {
        file: null,
        file_name: null,
        file_path: null,
        removed: false,
    };
}

function removeDocumentEntryAtIndex(
    rows: ManifestDocumentItem[],
    index: number,
): ManifestDocumentItem[] {
    if (index < 0 || index >= rows.length) {
        return rows;
    }

    const nextRows = [...rows];
    const currentRow = nextRows[index];

    if (!currentRow) {
        return nextRows;
    }

    if (currentRow.id || currentRow.file_path) {
        nextRows[index] = {
            ...currentRow,
            file: null,
            file_name: null,
            file_path: null,
            removed: true,
        };

        return nextRows;
    }

    nextRows.splice(index, 1);

    return nextRows;
}

function buildManifestDocumentFileName(
    fieldLabel: string,
    iteration: number,
    manifestNumber?: string | null,
): string {
    const normalizedManifestNumber = String(manifestNumber ?? '').trim();
    const safeManifestNumber =
        normalizedManifestNumber.length > 0
            ? normalizedManifestNumber
            : 'Draft';

    return `Manifest ${fieldLabel} #${iteration} - ${safeManifestNumber}`;
}

function buildEmptyManifestDocuments(): ManifestDocumentsByField {
    return {
        flight_tickets: [createEmptyDocumentEntry()],
        visa: [createEmptyDocumentEntry()],
        hotel: [createEmptyDocumentEntry()],
        passport: [createEmptyDocumentEntry()],
        photo: [createEmptyDocumentEntry()],
    };
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

    const memberId = toPositiveIntegerOrNull(
        row.customer_confirmation_member_id,
    );
    const customerId = toPositiveIntegerOrNull(row.customer_id);
    const packageOfficialId = toPositiveIntegerOrNull(row.package_official_id);
    const travelerId = toPositiveIntegerOrNull(row.id);
    const sharingGroupId = toPositiveIntegerOrNull(
        row.manifest_sharing_group_id ?? row.sharing_group_id,
    );

    return {
        ...(row as TravelerWithUI),
        role: String(row.role ?? ''),
        relationship: String(row.relationship ?? ''),
        arabic_name: String(
            row.arabic_name ??
                convertNameToArabic(String(row.name_as_per_passport ?? '')),
        ),
        receipt_documents: Array.isArray(row.receipt_documents)
            ? (row.receipt_documents as ManifestDocumentItem[])
            : [createEmptyDocumentEntry()],
        row_key:
            typeof row.row_key === 'string' && row.row_key.trim().length > 0
                ? row.row_key
                : memberId !== null
                  ? `traveler-member-${memberId}`
                  : customerId !== null
                    ? `traveler-customer-${customerId}`
                    : packageOfficialId !== null
                      ? `traveler-official-${packageOfficialId}`
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

    if (
        traveler.package_official_id !== undefined &&
        traveler.package_official_id !== null
    ) {
        return `official-${traveler.package_official_id}`;
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

    const sourceDocuments = initialData?.documents;
    const fallbackDocuments = buildEmptyManifestDocuments();
    const documents: ManifestDocumentsByField = {
        flight_tickets: sourceDocuments?.flight_tickets?.length
            ? sourceDocuments.flight_tickets.map((doc) => ({
                  ...doc,
                  removed: doc.removed ?? false,
              }))
            : fallbackDocuments.flight_tickets,
        visa: sourceDocuments?.visa?.length
            ? sourceDocuments.visa.map((doc) => ({
                  ...doc,
                  removed: doc.removed ?? false,
              }))
            : fallbackDocuments.visa,
        hotel: sourceDocuments?.hotel?.length
            ? sourceDocuments.hotel.map((doc) => ({
                  ...doc,
                  removed: doc.removed ?? false,
              }))
            : fallbackDocuments.hotel,
        passport: sourceDocuments?.passport?.length
            ? sourceDocuments.passport.map((doc) => ({
                  ...doc,
                  removed: doc.removed ?? false,
              }))
            : fallbackDocuments.passport,
        photo: sourceDocuments?.photo?.length
            ? sourceDocuments.photo.map((doc) => ({
                  ...doc,
                  removed: doc.removed ?? false,
              }))
            : fallbackDocuments.photo,
    };

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
        in_charge_official_id: initialData?.in_charge_official_id ?? null,
        manifest_number: initialData?.manifest_number ?? '',
        status: initialData?.status ?? 'open',
        notes: initialData?.notes ?? '',
        travelers,
        roomLists: normalizedRoomLists,
        airlineList,
        documents,
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
            room_relationship: existing?.room_relationship ?? '',
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

            const rowRoomRelationship = String(
                row.room_relationship ?? '',
            ).trim();

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
                room_number: row.room_number ?? traveler.room_number ?? '',
                room_relationship:
                    rowRoomRelationship !== '' ? row.room_relationship : '',
                room_label: row.room_label ?? traveler.room_label ?? '',
                room_type: row.room_type ?? traveler.room_type ?? '',
                bed_type: row.bed_type ?? traveler.bed_type ?? '',
                number_of_beds_checked:
                    row.number_of_beds_checked ??
                    traveler.number_of_beds_checked ??
                    false,
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

    const seenIdentities = new Set<string>();
    const dedupedRows = keptRows.filter((row, index) => {
        const identity = travelerIdentityKey(row, index);

        if (seenIdentities.has(identity)) {
            return false;
        }

        seenIdentities.add(identity);

        return true;
    });

    const missingTravelers = activeTravelers.filter((traveler, index) => {
        const identity = travelerIdentityKey(traveler, index);

        return !seenIdentities.has(identity);
    });

    const appendedRows =
        missingTravelers.length > 0
            ? buildRoomRowsFromTravelers(
                  missingTravelers,
                  dedupedRows,
                  accommodationKey,
                  defaultMealPlan,
              )
            : [];

    const mergedRows = [...dedupedRows, ...appendedRows];

    return mergedRows.map((row, index) => ({
        ...row,
        sn: index + 1,
        sort_order: index + 1,
    }));
}

function normalizeDocumentEntriesForSubmit(
    entries: ManifestDocumentItem[] | undefined,
): ManifestDocumentItem[] {
    if (!Array.isArray(entries)) {
        return [];
    }

    return entries
        .map((entry) => {
            const filePath = String(entry.file_path ?? '').trim();
            const fileName = String(entry.file_name ?? '').trim();
            const isRemoved = Boolean(entry.removed);

            return {
                id: entry.id,
                file: entry.file instanceof File ? entry.file : null,
                file_name: fileName === '' ? null : fileName,
                file_path: filePath === '' ? null : filePath,
                removed: isRemoved,
            };
        })
        .filter((entry) => {
            if (entry.removed) {
                return true;
            }

            if (entry.id) {
                return true;
            }

            if (entry.file instanceof File) {
                return true;
            }

            return Boolean(entry.file_path);
        });
}

function areDocumentEntriesEquivalent(
    currentEntries: ManifestDocumentItem[],
    baselineEntries: ManifestDocumentItem[],
): boolean {
    if (currentEntries.length !== baselineEntries.length) {
        return false;
    }

    return currentEntries.every((entry, index) => {
        const baselineEntry = baselineEntries[index];

        if (!baselineEntry) {
            return false;
        }

        return (
            (entry.id ?? null) === (baselineEntry.id ?? null) &&
            (entry.file_name ?? null) === (baselineEntry.file_name ?? null) &&
            (entry.file_path ?? null) === (baselineEntry.file_path ?? null) &&
            Boolean(entry.removed) === Boolean(baselineEntry.removed) &&
            entry.file instanceof File === baselineEntry.file instanceof File
        );
    });
}

function shouldIncludeTravelerField(
    currentValue: unknown,
    baselineValue: unknown,
): boolean {
    return currentValue !== baselineValue;
}

function buildTravelerBaselineMap(
    travelers: TravelerWithUI[],
): Map<string, TravelerWithUI> {
    const baselineMap = new Map<string, TravelerWithUI>();

    travelers.forEach((traveler, index) => {
        baselineMap.set(travelerIdentityKey(traveler, index), traveler);
    });

    return baselineMap;
}

function toTravelerSubmitRow(
    traveler: TravelerWithUI,
    index: number,
    baselineTraveler?: TravelerWithUI,
): Record<string, unknown> {
    const payload: Record<string, unknown> = {
        id: traveler.id ?? null,
        customer_confirmation_member_id:
            traveler.customer_confirmation_member_id ?? null,
        customer_id: traveler.customer_id ?? null,
        package_official_id: traveler.package_official_id ?? null,
        role: traveler.role ?? null,
        relationship: traveler.relationship ?? null,
        group_remarks: traveler.group_remarks ?? null,
        sharing_plan: traveler.sharing_plan ?? null,
        sharing_group_key:
            traveler.sharing_group_key ??
            `solo-${traveler.customer_confirmation_member_id ?? traveler.customer_id ?? index + 1}`,
        group_sort_order: traveler.group_sort_order ?? null,
        sort_order: traveler.sort_order ?? index + 1,
        name_as_per_passport: traveler.name_as_per_passport ?? null,
        remarks: traveler.remarks ?? null,
        status: traveler.status ?? null,
    };

    const compactOptionalFields: Array<keyof TravelerWithUI> = [
        'arabic_name',
        'contact_no',
        'passport_number',
        'nationality',
        'gender',
        'date_of_birth',
        'date_of_issue',
        'date_of_expiry',
        'issue_place',
        'birth_place',
        'address',
        'first_time_umrah',
        'has_chronic_disease',
        'chronic_disease_details',
        'passport_path',
        'photo_path',
        'course_1',
        'course_2',
        'lanyard',
        'luggage_tag',
        'cabin_tag',
        'passport_cover',
        'umrah_guidebook',
        'sling_bag',
        'cabin_size_luggage',
        'umrah_essentials',
    ];

    compactOptionalFields.forEach((field) => {
        const currentValue = traveler[field] ?? null;
        const baselineValue = baselineTraveler?.[field] ?? null;

        if (shouldIncludeTravelerField(currentValue, baselineValue)) {
            payload[field] = currentValue;
        }
    });

    const normalizedReceipts = normalizeDocumentEntriesForSubmit(
        traveler.receipt_documents,
    );
    const baselineReceipts = normalizeDocumentEntriesForSubmit(
        baselineTraveler?.receipt_documents,
    );

    if (!areDocumentEntriesEquivalent(normalizedReceipts, baselineReceipts)) {
        payload.receipt_documents = normalizedReceipts;
    }

    return payload;
}

function toRoomListSubmitRow(
    row: TravelerWithUI,
    index: number,
    accommodationKey: string,
): Record<string, unknown> {
    const roomNumber = String(row.room_number ?? '').trim();

    return {
        manifest_traveler_id: row.manifest_traveler_id ?? row.id ?? null,
        id: row.id ?? null,
        customer_confirmation_member_id:
            row.customer_confirmation_member_id ?? null,
        package_official_id: row.package_official_id ?? null,
        accommodation_key: accommodationKey,
        sort_order: index + 1,
        sharing_group_key:
            row.sharing_group_key ??
            `solo-${row.customer_confirmation_member_id ?? row.customer_id ?? index + 1}`,
        sharing_plan: row.sharing_plan ?? null,
        room_relationship: String(row.room_relationship ?? '').trim(),
        room_label: String(row.room_label ?? '').trim(),
        room_number: roomNumber === '' ? null : roomNumber,
        room_type: String(row.room_type ?? '').trim(),
        bed_type: String(row.bed_type ?? '').trim(),
        number_of_beds_checked: !!row.number_of_beds_checked,
        meal: String(row.meal ?? '').trim(),
        room_remarks: row.room_remarks ?? null,
        remarks: row.remarks ?? null,
    };
}

function buildSubmitPayload(
    data: ManifestFormData,
    baselineTravelers: TravelerWithUI[] = [],
): ManifestFormData {
    const baselineTravelerMap = buildTravelerBaselineMap(baselineTravelers);

    const roomLists = Object.fromEntries(
        Object.entries(data.roomLists ?? {}).map(([key, rows]) => {
            return [
                key,
                ((rows ?? []) as TravelerWithUI[]).map((row, index) =>
                    toRoomListSubmitRow(row, index, key),
                ),
            ];
        }),
    );

    return {
        ...data,
        airlineList: [],
        roomLists,
        documents: {
            flight_tickets: normalizeDocumentEntriesForSubmit(
                data.documents?.flight_tickets,
            ),
            visa: normalizeDocumentEntriesForSubmit(data.documents?.visa),
            hotel: normalizeDocumentEntriesForSubmit(data.documents?.hotel),
            passport: normalizeDocumentEntriesForSubmit(
                data.documents?.passport,
            ),
            photo: normalizeDocumentEntriesForSubmit(data.documents?.photo),
        },
        travelers: ((data.travelers ?? []) as TravelerWithUI[]).map(
            (traveler, index) =>
                toTravelerSubmitRow(
                    traveler,
                    index,
                    baselineTravelerMap.get(
                        travelerIdentityKey(traveler, index),
                    ),
                ),
        ) as TravelerWithUI[],
    };
}

export default function ManifestForm({
    mode,
    initialData,
    dataPackage = [],
    onCancel,
}: ManifestFormProps) {
    const isView = mode === 'view';
    const isEdit = mode === 'edit';

    const packageOptions = dataPackage as PackageForManifestOption[];
    const defaults = buildDefaultData(initialData);

    const form = useForm<ManifestFormData>(defaults);
    const { data, setData, post, processing, hasErrors } = form;
    const rawErrors = (form as unknown as { errors?: Record<string, string> })
        .errors;
    const formErrorActions = form as unknown as {
        clearErrors: () => void;
        setError: (field: string, message: string) => void;
    };
    const formResetAction = (form as unknown as { reset: () => void }).reset;
    const formErrors = useMemo<Record<string, string>>(() => {
        return rawErrors ?? {};
    }, [rawErrors]);
    const setFormData = useCallback(
        (key: string, value: unknown) => {
            (
                setData as unknown as (
                    field: string,
                    fieldValue: unknown,
                ) => void
            )(key, value);
        },
        [setData],
    );
    const clearFormErrors = useCallback(() => {
        formErrorActions.clearErrors();
    }, [formErrorActions]);
    const setFormError = useCallback(
        (field: string, message: string) => {
            formErrorActions.setError(field, message);
        },
        [formErrorActions],
    );
    const errorAlertRef = useRef<HTMLDivElement | null>(null);
    const previousNameByIdentityRef = useRef<Record<string, string>>({});
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

    const selectedPackageOfficials = useMemo(() => {
        return selectedPackage?.officials ?? [];
    }, [selectedPackage]);

    const selectedInChargeOfficial = useMemo(() => {
        const inChargeOfficialId = Number(data.in_charge_official_id ?? 0);

        if (inChargeOfficialId < 1) {
            return null;
        }

        return (
            selectedPackageOfficials.find(
                (official) => Number(official.id) === inChargeOfficialId,
            ) ?? null
        );
    }, [data.in_charge_official_id, selectedPackageOfficials]);

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
            setFormData('roomLists', syncedRoomLists);
        }
    }, [
        roomTabs,
        data.travelers,
        data.roomLists,
        isCancelledTraveler,
        setFormData,
    ]);

    useEffect(() => {
        if (isView) {
            return;
        }

        if (hasErrors) {
            scrollToErrorBanner();
        }
    }, [hasErrors, isView, scrollToErrorBanner]);

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
                                        row.room_relationship ?? '',
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
                        (row: unknown, existingIndex: number) =>
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

            setFormData('travelers', travelersWithSn);
            setFormData('roomLists', nextRoomLists);
            setFormData('airlineList', nextAirline);
        },
        [
            data.airlineList,
            data.roomLists,
            data.travelers,
            isCancelledTraveler,
            roomTabs,
            setFormData,
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

            setFormData('roomLists', {
                ...(data.roomLists ?? {}),
                [tabKey]: baseRows,
            });
        },
        [
            data.roomLists,
            data.travelers,
            isCancelledTraveler,
            roomTabs,
            setFormData,
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

            setFormData('roomLists', nextRoomLists);
        },
        [data.roomLists, roomTabs, setFormData],
    );

    const updateManifestDocuments = useCallback(
        (field: ManifestDocumentFieldKey, rows: ManifestDocumentItem[]) => {
            const currentDocuments =
                data.documents ?? buildEmptyManifestDocuments();
            const nextDocuments: ManifestDocumentsByField = {
                ...currentDocuments,
                [field]: rows,
            };

            setFormData('documents', nextDocuments);
        },
        [data.documents, setFormData],
    );

    const updateTravelerArabicName = useCallback(
        (traveler: TravelerWithUI, arabicName: string) => {
            const sanitizedArabicName = normalizeArabicNameInput(arabicName);

            const nextTravelers = (
                (data.travelers ?? []) as TravelerWithUI[]
            ).map((row, index) => {
                if (
                    travelerIdentityKey(row, index) !==
                    travelerIdentityKey(traveler, 0)
                ) {
                    return row;
                }

                return {
                    ...row,
                    arabic_name: sanitizedArabicName,
                };
            });

            setFormData('travelers', nextTravelers);
        },
        [data.travelers, setFormData],
    );

    const updateTravelerReceiptDocuments = useCallback(
        (traveler: TravelerWithUI, rows: ManifestDocumentItem[]) => {
            const nextTravelers = (
                (data.travelers ?? []) as TravelerWithUI[]
            ).map((row, index) => {
                if (
                    travelerIdentityKey(row, index) !==
                    travelerIdentityKey(traveler, 0)
                ) {
                    return row;
                }

                return {
                    ...row,
                    receipt_documents: rows,
                };
            });

            setFormData('travelers', nextTravelers);
        },
        [data.travelers, setFormData],
    );

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        const validationResult = manifestValidationSchema.safeParse(data);

        clearFormErrors();

        if (!validationResult.success) {
            validationResult.error.issues.forEach((issue) => {
                setFormError(issue.path.join('.'), issue.message);
            });

            scrollToErrorBanner();

            return;
        }

        const submitPayload = buildSubmitPayload(
            data,
            (defaults.travelers ?? []) as TravelerWithUI[],
        );

        const handleError = (errors: Record<string, string>) => {
            Object.entries(errors).forEach(([field, message]) => {
                setFormError(field, message);
            });

            scrollToErrorBanner();
        };

        if (isEdit && submitPayload.id) {
            form.transform(() => submitPayload);

            post(store().url, {
                preserveScroll: 'errors',
                forceFormData: true,
                onError: handleError,
                onFinish: () => {
                    form.transform(
                        (currentData: ManifestFormData) => currentData,
                    );
                },
            });
        }
    };

    const renderError = (path: string) => {
        const message = formErrors[path];

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

        Object.entries(formErrors).forEach(([path, message]) => {
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
    }, [formErrors, data.travelers]);

    const sectionStatuses = useMemo(() => {
        const errorEntries = Object.entries(formErrors).filter(([, message]) =>
            Boolean(message),
        );

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
        formErrors,
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

    useEffect(() => {
        const travelers = (data.travelers ?? []) as TravelerWithUI[];
        const previousMap = previousNameByIdentityRef.current;
        let hasChanges = false;

        const nextTravelers = travelers.map((traveler, index) => {
            if (traveler.package_official_id) {
                return traveler;
            }

            const identity = travelerIdentityKey(traveler, index);
            const currentName = String(
                traveler.name_as_per_passport ?? '',
            ).trim();
            const previousName = previousMap[identity] ?? null;
            previousMap[identity] = currentName;

            if (currentName === '') {
                return traveler;
            }

            const shouldAutoFill =
                !traveler.arabic_name ||
                traveler.arabic_name.trim() === '' ||
                previousName !== null;

            if (!shouldAutoFill || previousName === currentName) {
                return traveler;
            }

            const autoArabicName = convertNameToArabic(currentName);

            if (autoArabicName === traveler.arabic_name) {
                return traveler;
            }

            hasChanges = true;

            return {
                ...traveler,
                arabic_name: autoArabicName,
            };
        });

        if (hasChanges) {
            setFormData('travelers', nextTravelers);
        }
    }, [data.travelers, setFormData]);

    useEffect(() => {
        const inChargeOfficialId = Number(data.in_charge_official_id ?? 0);

        if (inChargeOfficialId < 1) {
            return;
        }

        const stillExists = selectedPackageOfficials.some(
            (official) => Number(official.id) === inChargeOfficialId,
        );

        if (!stillExists) {
            setFormData('in_charge_official_id', null);
        }
    }, [data.in_charge_official_id, selectedPackageOfficials, setFormData]);

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

    console.log(data);
    console.log(formErrors);

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
                {Object.keys(formErrors).length > 0 && !isView && (
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
                            setData={setFormData}
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

                        <ScrollArea className="w-full whitespace-nowrap">
                            <TabsList className="w-fit group-data-[orientation=horizontal]/tabs:h-11">
                                <TabsTrigger
                                    value="document-flight-tickets"
                                    className="text-lg"
                                >
                                    Flight Tickets
                                </TabsTrigger>
                                <TabsTrigger
                                    value="document-visa"
                                    className="text-lg"
                                >
                                    Visa
                                </TabsTrigger>
                                <TabsTrigger
                                    value="document-hotel"
                                    className="text-lg"
                                >
                                    Hotel
                                </TabsTrigger>
                                <TabsTrigger
                                    value="document-passport"
                                    className="text-lg"
                                >
                                    Passport
                                </TabsTrigger>
                                <TabsTrigger
                                    value="document-photo"
                                    className="text-lg"
                                >
                                    Photo
                                </TabsTrigger>
                                <TabsTrigger
                                    value="arabic-names"
                                    className="text-lg"
                                >
                                    Arabic Names
                                </TabsTrigger>
                                <TabsTrigger
                                    value="receipt"
                                    className="text-lg"
                                >
                                    Receipt
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
                            errors={formErrors}
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
                                    errors={formErrors}
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

                                        setFormData('roomLists', {
                                            ...(data.roomLists ?? {}),
                                            [tab.key]: normalizedRows,
                                        });
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
                                    errors={formErrors}
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

                                        setFormData('roomLists', {
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
                            rows={(
                                (data.airlineList ?? []) as TravelerWithUI[]
                            ).filter((row) => !isCancelledTraveler(row))}
                            disabled={isView}
                            allowReorder
                            errorPrefix="airlineList"
                            errors={formErrors}
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

                                setFormData('travelers', nextTravelers);
                                setFormData('airlineList', normalizedAirline);
                                setFormData('roomLists', nextRoomLists);
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
                            errors={formErrors}
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

                                setFormData('travelers', nextTravelers);
                            }}
                        />
                    </TabsContent>

                    {MANIFEST_DOCUMENT_TABS.map((tab) => {
                        const allRows =
                            (data.documents?.[tab.key] as
                                | ManifestDocumentItem[]
                                | undefined) ?? [];
                        const visibleRowIndexes = allRows
                            .map((row, rowIndex) =>
                                row.removed ? null : rowIndex,
                            )
                            .filter(
                                (rowIndex): rowIndex is number =>
                                    rowIndex !== null,
                            );
                        const rowsToRender =
                            visibleRowIndexes.length > 0
                                ? visibleRowIndexes.map(
                                      (actualIndex, visibleIndex) => ({
                                          row: allRows[actualIndex],
                                          actualIndex,
                                          visibleIndex,
                                      }),
                                  )
                                : [
                                      {
                                          row: createEmptyDocumentEntry(),
                                          actualIndex: -1,
                                          visibleIndex: 0,
                                      },
                                  ];

                        return (
                            <TabsContent
                                key={tab.key}
                                value={`document-${tab.key.replaceAll('_', '-')}`}
                                className="space-y-4"
                            >
                                <div className="rounded-xl border border-border/70 p-4">
                                    <div className="mb-4 flex items-center justify-between">
                                        <div>
                                            <h3 className="text-lg font-semibold">
                                                {tab.label} Documents
                                            </h3>
                                            <p className="text-sm text-muted-foreground">
                                                {tab.hint}
                                            </p>
                                        </div>
                                        {!isView && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() => {
                                                    updateManifestDocuments(
                                                        tab.key,
                                                        [
                                                            ...allRows,
                                                            createEmptyDocumentEntry(),
                                                        ],
                                                    );
                                                }}
                                            >
                                                Add Document
                                            </Button>
                                        )}
                                    </div>

                                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                        {rowsToRender.map((renderRow) => {
                                            const {
                                                row,
                                                actualIndex,
                                                visibleIndex,
                                            } = renderRow;

                                            return (
                                                <div
                                                    key={`${tab.key}-${row.id ?? `new-${visibleIndex}`}`}
                                                    className="rounded-lg border p-3"
                                                >
                                                    {!isView &&
                                                        actualIndex >= 0 && (
                                                            <div className="mb-3 flex justify-end">
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="h-8 px-2 text-destructive hover:text-destructive"
                                                                    onClick={() => {
                                                                        updateManifestDocuments(
                                                                            tab.key,
                                                                            removeDocumentEntryAtIndex(
                                                                                allRows,
                                                                                actualIndex,
                                                                            ),
                                                                        );
                                                                    }}
                                                                >
                                                                    Remove
                                                                </Button>
                                                            </div>
                                                        )}

                                                    <DocumentField
                                                        label={`${tab.label} #${visibleIndex + 1}`}
                                                        hint={tab.hint}
                                                        accept={tab.accept}
                                                        fileValue={
                                                            row.file ??
                                                            undefined
                                                        }
                                                        existingPath={
                                                            row.file_path ??
                                                            undefined
                                                        }
                                                        existingFileName={
                                                            row.file_name ??
                                                            undefined
                                                        }
                                                        useFileNameInput
                                                        fileNameValue={
                                                            row.file_name ??
                                                            null
                                                        }
                                                        isView={isView}
                                                        disabled={isView}
                                                        onSelect={(file) => {
                                                            const nextRows =
                                                                allRows.length >
                                                                0
                                                                    ? [
                                                                          ...allRows,
                                                                      ]
                                                                    : [
                                                                          createEmptyDocumentEntry(),
                                                                      ];
                                                            const targetIndex =
                                                                actualIndex >= 0
                                                                    ? actualIndex
                                                                    : 0;
                                                            nextRows[
                                                                targetIndex
                                                            ] = {
                                                                ...nextRows[
                                                                    targetIndex
                                                                ],
                                                                file,
                                                                removed: false,
                                                                file_name:
                                                                    nextRows[
                                                                        targetIndex
                                                                    ]
                                                                        ?.file_name ??
                                                                    buildManifestDocumentFileName(
                                                                        tab.label,
                                                                        visibleIndex +
                                                                            1,
                                                                        data.manifest_number,
                                                                    ),
                                                            };
                                                            updateManifestDocuments(
                                                                tab.key,
                                                                nextRows,
                                                            );
                                                        }}
                                                        onFileNameChange={(
                                                            fileName,
                                                        ) => {
                                                            const nextRows =
                                                                allRows.length >
                                                                0
                                                                    ? [
                                                                          ...allRows,
                                                                      ]
                                                                    : [
                                                                          createEmptyDocumentEntry(),
                                                                      ];
                                                            const targetIndex =
                                                                actualIndex >= 0
                                                                    ? actualIndex
                                                                    : 0;
                                                            nextRows[
                                                                targetIndex
                                                            ] = {
                                                                ...nextRows[
                                                                    targetIndex
                                                                ],
                                                                file_name:
                                                                    fileName,
                                                            };
                                                            updateManifestDocuments(
                                                                tab.key,
                                                                nextRows,
                                                            );
                                                        }}
                                                        onClear={() => {
                                                            updateManifestDocuments(
                                                                tab.key,
                                                                removeDocumentEntryAtIndex(
                                                                    allRows,
                                                                    actualIndex,
                                                                ),
                                                            );
                                                        }}
                                                    />
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            </TabsContent>
                        );
                    })}

                    <TabsContent value="arabic-names" className="space-y-4">
                        <div className="rounded-xl border border-border/70 p-4">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <FormField
                                    label="Official In Charge"
                                    htmlFor="in_charge_official_id"
                                    error={formErrors.in_charge_official_id}
                                >
                                    <ProperInputSelect
                                        id="in_charge_official_id"
                                        options={[
                                            {
                                                label: 'Select official in charge',
                                                value: '',
                                            },
                                            ...selectedPackageOfficials.map(
                                                (official) => ({
                                                    label:
                                                        official.name ??
                                                        `Official #${official.id}`,
                                                    value: String(official.id),
                                                }),
                                            ),
                                        ]}
                                        value={String(
                                            data.in_charge_official_id ?? '',
                                        )}
                                        onValueChange={(value) => {
                                            const nextValue = String(
                                                value ?? '',
                                            ).trim();
                                            setFormData(
                                                'in_charge_official_id',
                                                nextValue === ''
                                                    ? null
                                                    : Number(nextValue),
                                            );
                                        }}
                                        disabled={isView}
                                        searchable
                                        placeholder="Select official in charge"
                                    />
                                </FormField>
                                <FormField label="Contact Number">
                                    <ProperInput
                                        value={
                                            selectedInChargeOfficial?.contact_number ??
                                            ''
                                        }
                                        onCommit={(value) => {
                                            void value;
                                        }}
                                        disabled
                                        placeholder="Auto-filled from package official"
                                    />
                                </FormField>
                                <div className="flex items-end justify-start md:justify-end">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={!data.id}
                                        onClick={() => {
                                            if (!data.id) {
                                                return;
                                            }

                                            exportPdfWithSnapshot(
                                                `/manifests/${data.id}/arabic-names-pdf`,
                                                {
                                                    travelers:
                                                        nonCancelledNonOfficialTravelers,
                                                    manifest_number:
                                                        data.manifest_number,
                                                    package_name:
                                                        selectedPackage?.label,
                                                    departure_date:
                                                        selectedPackage?.departure_date,
                                                    in_charge_official_name:
                                                        selectedInChargeOfficial?.name ??
                                                        '',
                                                    in_charge_official_contact_number:
                                                        selectedInChargeOfficial?.contact_number ??
                                                        '',
                                                },
                                            );
                                        }}
                                    >
                                        <Download className="mr-2 h-4 w-4" />
                                        Export PDF
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <div className="overflow-hidden rounded-xl border border-border/70">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/40 text-left">
                                    <tr>
                                        <th className="px-4 py-3 font-semibold">
                                            No
                                        </th>
                                        <th className="px-4 py-3 font-semibold">
                                            Name
                                        </th>
                                        <th className="px-4 py-3 font-semibold">
                                            Arabic Name
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {nonCancelledNonOfficialTravelers.map(
                                        (traveler, index) => (
                                            <tr
                                                key={`${travelerIdentityKey(traveler, index)}-arabic`}
                                                className="border-t"
                                            >
                                                <td className="px-4 py-3 align-top">
                                                    {index + 1}
                                                </td>
                                                <td className="px-4 py-3 align-top">
                                                    <ProperInput
                                                        value={
                                                            traveler.name_as_per_passport ??
                                                            ''
                                                        }
                                                        onCommit={(value) => {
                                                            void value;
                                                        }}
                                                        disabled
                                                    />
                                                </td>
                                                <td className="px-4 py-3 align-top">
                                                    <ProperInput
                                                        value={
                                                            traveler.arabic_name ??
                                                            ''
                                                        }
                                                        onCommit={(value) => {
                                                            updateTravelerArabicName(
                                                                traveler,
                                                                value,
                                                            );
                                                        }}
                                                        disabled={isView}
                                                        inputProps={{
                                                            dir: 'rtl',
                                                        }}
                                                        className="text-right"
                                                    />
                                                </td>
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </TabsContent>

                    <TabsContent value="receipt" className="space-y-4">
                        <div className="overflow-hidden rounded-xl border border-border/70">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/40 text-left">
                                    <tr>
                                        <th className="px-4 py-3 font-semibold">
                                            No
                                        </th>
                                        <th className="px-4 py-3 font-semibold">
                                            Name
                                        </th>
                                        <th className="px-4 py-3 font-semibold">
                                            Receipt
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {nonCancelledNonOfficialTravelers.map(
                                        (traveler, index) => {
                                            const sourceRows =
                                                traveler.receipt_documents ??
                                                [];
                                            const rows = sourceRows.filter(
                                                (row) => !row.removed,
                                            );
                                            const visibleRowIndexes = sourceRows
                                                .map((row, rowIndex) =>
                                                    row.removed
                                                        ? null
                                                        : rowIndex,
                                                )
                                                .filter(
                                                    (
                                                        rowIndex,
                                                    ): rowIndex is number =>
                                                        rowIndex !== null,
                                                );
                                            const rowsToRender =
                                                visibleRowIndexes.length > 0
                                                    ? visibleRowIndexes.map(
                                                          (
                                                              actualIndex,
                                                              visibleIndex,
                                                          ) => ({
                                                              row: sourceRows[
                                                                  actualIndex
                                                              ],
                                                              actualIndex,
                                                              visibleIndex,
                                                          }),
                                                      )
                                                    : [
                                                          {
                                                              row: createEmptyDocumentEntry(),
                                                              actualIndex: -1,
                                                              visibleIndex: 0,
                                                          },
                                                      ];

                                            return (
                                                <tr
                                                    key={`${travelerIdentityKey(traveler, index)}-receipt`}
                                                    className="border-t"
                                                >
                                                    <td className="px-4 py-3 align-top">
                                                        {index + 1}
                                                    </td>
                                                    <td className="px-4 py-3 align-top">
                                                        <input
                                                            type="text"
                                                            value={
                                                                traveler.name_as_per_passport ??
                                                                ''
                                                            }
                                                            disabled
                                                            className="w-full rounded-md border bg-muted/40 px-3 py-2 text-sm text-muted-foreground"
                                                        />
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div className="space-y-3">
                                                            {rowsToRender.map(
                                                                (renderRow) => {
                                                                    const {
                                                                        row,
                                                                        actualIndex,
                                                                        visibleIndex,
                                                                    } =
                                                                        renderRow;

                                                                    return (
                                                                        <div
                                                                            key={`${travelerIdentityKey(traveler, index)}-receipt-doc-${row.id ?? visibleIndex}`}
                                                                            className="rounded-lg border p-3"
                                                                        >
                                                                            {!isView &&
                                                                                actualIndex >=
                                                                                    0 && (
                                                                                    <div className="mb-3 flex justify-end">
                                                                                        <Button
                                                                                            type="button"
                                                                                            variant="ghost"
                                                                                            size="sm"
                                                                                            className="h-8 px-2 text-destructive hover:text-destructive"
                                                                                            onClick={() => {
                                                                                                updateTravelerReceiptDocuments(
                                                                                                    traveler,
                                                                                                    removeDocumentEntryAtIndex(
                                                                                                        sourceRows,
                                                                                                        actualIndex,
                                                                                                    ),
                                                                                                );
                                                                                            }}
                                                                                        >
                                                                                            Remove
                                                                                        </Button>
                                                                                    </div>
                                                                                )}

                                                                            <DocumentField
                                                                                label={`Receipt #${visibleIndex + 1}`}
                                                                                hint="Upload receipt evidence for this traveler."
                                                                                accept=".pdf,.png,.jpg,.jpeg,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv"
                                                                                fileValue={
                                                                                    row.file ??
                                                                                    undefined
                                                                                }
                                                                                existingPath={
                                                                                    row.file_path ??
                                                                                    undefined
                                                                                }
                                                                                existingFileName={
                                                                                    row.file_name ??
                                                                                    undefined
                                                                                }
                                                                                useFileNameInput
                                                                                fileNameValue={
                                                                                    row.file_name ??
                                                                                    null
                                                                                }
                                                                                isView={
                                                                                    isView
                                                                                }
                                                                                disabled={
                                                                                    isView
                                                                                }
                                                                                onSelect={(
                                                                                    file,
                                                                                ) => {
                                                                                    const nextRows =
                                                                                        sourceRows.length >
                                                                                        0
                                                                                            ? [
                                                                                                  ...sourceRows,
                                                                                              ]
                                                                                            : [
                                                                                                  createEmptyDocumentEntry(),
                                                                                              ];
                                                                                    const targetIndex =
                                                                                        actualIndex >=
                                                                                        0
                                                                                            ? actualIndex
                                                                                            : 0;
                                                                                    nextRows[
                                                                                        targetIndex
                                                                                    ] =
                                                                                        {
                                                                                            ...nextRows[
                                                                                                targetIndex
                                                                                            ],
                                                                                            file,
                                                                                            removed: false,
                                                                                            file_name:
                                                                                                nextRows[
                                                                                                    targetIndex
                                                                                                ]
                                                                                                    ?.file_name ??
                                                                                                buildManifestDocumentFileName(
                                                                                                    'Receipt',
                                                                                                    visibleIndex +
                                                                                                        1,
                                                                                                    data.manifest_number,
                                                                                                ),
                                                                                        };
                                                                                    updateTravelerReceiptDocuments(
                                                                                        traveler,
                                                                                        nextRows,
                                                                                    );
                                                                                }}
                                                                                onFileNameChange={(
                                                                                    fileName,
                                                                                ) => {
                                                                                    const nextRows =
                                                                                        sourceRows.length >
                                                                                        0
                                                                                            ? [
                                                                                                  ...sourceRows,
                                                                                              ]
                                                                                            : [
                                                                                                  createEmptyDocumentEntry(),
                                                                                              ];
                                                                                    const targetIndex =
                                                                                        actualIndex >=
                                                                                        0
                                                                                            ? actualIndex
                                                                                            : 0;
                                                                                    nextRows[
                                                                                        targetIndex
                                                                                    ] =
                                                                                        {
                                                                                            ...nextRows[
                                                                                                targetIndex
                                                                                            ],
                                                                                            file_name:
                                                                                                fileName,
                                                                                        };
                                                                                    updateTravelerReceiptDocuments(
                                                                                        traveler,
                                                                                        nextRows,
                                                                                    );
                                                                                }}
                                                                                onClear={() => {
                                                                                    updateTravelerReceiptDocuments(
                                                                                        traveler,
                                                                                        removeDocumentEntryAtIndex(
                                                                                            sourceRows,
                                                                                            actualIndex,
                                                                                        ),
                                                                                    );
                                                                                }}
                                                                            />
                                                                        </div>
                                                                    );
                                                                },
                                                            )}

                                                            {!isView && (
                                                                <Button
                                                                    type="button"
                                                                    variant="outline"
                                                                    onClick={() => {
                                                                        const baseRows =
                                                                            sourceRows.length >
                                                                            0
                                                                                ? sourceRows
                                                                                : [
                                                                                      createEmptyDocumentEntry(),
                                                                                  ];

                                                                        updateTravelerReceiptDocuments(
                                                                            traveler,
                                                                            [
                                                                                ...baseRows,
                                                                                createEmptyDocumentEntry(),
                                                                            ],
                                                                        );
                                                                    }}
                                                                >
                                                                    Add Receipt
                                                                </Button>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        },
                                    )}
                                </tbody>
                            </table>
                        </div>
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
                                onClick={() => formResetAction()}
                            >
                                <RotateCcw className="mr-1 h-4 w-4" />
                                Reset
                            </Button>
                            <Button type="submit" disabled={processing}>
                                Update Manifest
                            </Button>
                        </>
                    )}
                </div>
            </form>
        </div>
    );
}
