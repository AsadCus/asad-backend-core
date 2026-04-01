import { DocumentField } from '@/components/document-field';
import { FormField } from '@/components/form-field';
import { FormProgressHeader } from '@/components/form-progress-header';
import { FormSection } from '@/components/form-section';
import { ProperInput } from '@/components/proper-input';
import { ProperInputSelect } from '@/components/proper-input-select';
import { Accordion } from '@/components/ui/accordion';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    convertNameToArabic,
    normalizeArabicNameInput,
} from '@/lib/arabic-name';
import { navigateToSection } from '@/lib/navigation-helper';
import { store } from '@/routes/manifests';
import manifestSections from '@/routes/manifests/sections';
import { useForm } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    ChevronDown,
    Download,
    Loader2,
    RotateCcw,
} from 'lucide-react';
import { nanoid } from 'nanoid';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import ConfirmedCustomerFormFields from '../customer/confirmed-customer-form-fields';
import CustomerFormFields from '../customer/form-fields';
import { type CustomerSchema } from '../customer/schema';
import AccommodationInformationCard from './components/accommodation-information-card';
import ManifestDatatable from './components/datatable';
import FlightDetailInformationCard from './components/flight-detail-information-card';
import ManifestInformationCard from './components/manifest-information-card';
import ManifestMemberInformationCard from './components/manifest-member-information-card';
import {
    type CanonicalManifestRoom,
    type CanonicalManifestSharingGroup,
    type ManifestDocumentFieldKey,
    type ManifestDocumentItem,
    type ManifestDocumentsByField,
    type ManifestFormData,
    type ManifestFormProps,
    type MemberWithUI,
    type PackageAccommodationOption,
    type PackageForManifestOption,
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
        key: 'train_tickets',
        label: 'Train Tickets',
        hint: 'Upload train ticket files for this manifest.',
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
        hint: 'Upload member photo files.',
        accept: '.pdf,.png,.jpg,.jpeg,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv',
    },
];

const PRICING_PLAN_SINGLE_EQUIVALENT_VALUES = new Set([
    'child_with_bed',
    'child_no_bed',
    'infant',
]);

function toManifestRoomTypeSharingPlan(
    sharingPlan?: string | null,
): string | null {
    const value = String(sharingPlan ?? '')
        .toLowerCase()
        .trim();

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

    if (PRICING_PLAN_SINGLE_EQUIVALENT_VALUES.has(value)) {
        return 'single';
    }

    return null;
}

function buildMemberIdentityDocuments(
    members: MemberWithUI[],
    field: 'passport' | 'photo',
): ManifestDocumentItem[] {
    const rows: ManifestDocumentItem[] = [];

    members.forEach((member, index) => {
        const filePath = String(
            field === 'passport'
                ? (member.passport_path ?? '')
                : (member.photo_path ?? ''),
        ).trim();

        if (filePath.length === 0) {
            return;
        }

        const memberName = String(
            member.name_as_per_passport ?? member.customer_name ?? '',
        ).trim();
        const fallbackName =
            memberName.length > 0 ? memberName : `Member ${index + 1}`;
        const preferredStoredFileName = String(
            field === 'passport'
                ? (member.passport_file_name ?? '')
                : (member.photo_file_name ?? ''),
        ).trim();
        const fallbackFileName =
            field === 'passport'
                ? `Passport ${fallbackName}`
                : `Photo ${fallbackName}`;
        const fileName =
            preferredStoredFileName.length > 0
                ? preferredStoredFileName
                : fallbackFileName;

        rows.push({
            file: null,
            file_name: fileName,
            file_path: filePath,
            removed: false,
        });
    });

    return rows;
}

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
        train_tickets: [createEmptyDocumentEntry()],
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

function toPositiveIntegerOrNull(value: unknown): number | null {
    const parsed =
        typeof value === 'number'
            ? value
            : Number.parseInt(String(value ?? ''), 10);

    if (!Number.isFinite(parsed) || parsed <= 0) {
        return null;
    }

    return parsed;
}

function toMemberWithUI(
    row: Record<string, unknown>,
    index: number,
): MemberWithUI {
    const manifestMemberId = toPositiveIntegerOrNull(
        row.manifest_member_id ?? row.id,
    );
    const confirmationMemberId = toPositiveIntegerOrNull(
        row.customer_confirmation_member_id,
    );
    const customerId = toPositiveIntegerOrNull(row.customer_id);
    const packageOfficialId = toPositiveIntegerOrNull(row.package_official_id);
    const sharingGroupId = toPositiveIntegerOrNull(
        row.manifest_sharing_group_id ?? row.sharing_group_id,
    );

    return {
        ...(row as MemberWithUI),
        relationship: String(row.relationship ?? row.role ?? ''),
        group_relationship: String(
            row.group_relationship ?? row.relation ?? '',
        ),
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
                : manifestMemberId !== null
                  ? `manifest_member-${manifestMemberId}`
                  : confirmationMemberId !== null
                    ? `confirmation_member-${confirmationMemberId}`
                    : customerId !== null
                      ? `customer-${customerId}`
                      : packageOfficialId !== null
                        ? `package_official-${packageOfficialId}`
                        : `manifest_member-temp-${nanoid()}`,
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

function flattenGroupedRows(rows: unknown): MemberWithUI[] {
    if (!Array.isArray(rows)) {
        if (rows && typeof rows === 'object') {
            return Object.values(rows as Record<string, unknown[]>)
                .flat()
                .filter(
                    (item): item is Record<string, unknown> =>
                        !!item && typeof item === 'object',
                )
                .map((item, index) => toMemberWithUI(item, index));
        }

        return [];
    }

    return rows
        .filter(
            (item): item is Record<string, unknown> =>
                !!item && typeof item === 'object',
        )
        .map((item, index) => toMemberWithUI(item, index));
}

function buildMemberLookupMap(
    members: MemberWithUI[],
): Map<string, MemberWithUI> {
    const map = new Map<string, MemberWithUI>();

    members.forEach((member, index) => {
        const manifestMemberId = toPositiveIntegerOrNull(
            member.manifest_member_id ?? member.id,
        );
        const confirmationMemberId = toPositiveIntegerOrNull(
            member.customer_confirmation_member_id,
        );
        const officialId = toPositiveIntegerOrNull(member.package_official_id);

        if (manifestMemberId !== null) {
            map.set(`manifest_member:${manifestMemberId}`, member);
        }

        if (confirmationMemberId !== null) {
            map.set(`confirmation_member:${confirmationMemberId}`, member);
        }

        if (officialId !== null) {
            map.set(`package_official:${officialId}`, member);
        }

        map.set(`member_identity:${memberIdentityKey(member, index)}`, member);
    });

    return map;
}

function getMemberFromLookup(
    memberLookup: Map<string, MemberWithUI>,
    ids: {
        manifestMemberId: number | null;
        confirmationMemberId: number | null;
        officialId: number | null;
    },
): MemberWithUI | undefined {
    const { manifestMemberId, confirmationMemberId, officialId } = ids;

    return (
        (manifestMemberId !== null
            ? memberLookup.get(`manifest_member:${manifestMemberId}`)
            : undefined) ??
        (confirmationMemberId !== null
            ? memberLookup.get(`confirmation_member:${confirmationMemberId}`)
            : undefined) ??
        (officialId !== null
            ? memberLookup.get(`package_official:${officialId}`)
            : undefined)
    );
}

function buildMembersFromCanonicalGroups(
    canonicalGroups: CanonicalManifestSharingGroup[] | undefined,
    fallbackMembers: MemberWithUI[],
): MemberWithUI[] {
    if (!Array.isArray(canonicalGroups) || canonicalGroups.length === 0) {
        return fallbackMembers;
    }

    const fallbackLookup = buildMemberLookupMap(fallbackMembers);
    const nextMembers: MemberWithUI[] = [];

    canonicalGroups.forEach((group, groupIndex) => {
        const groupId = toPositiveIntegerOrNull(group.id);
        const groupKey =
            groupId !== null ? `group-${groupId}` : `group-${groupIndex + 1}`;
        const groupSortOrder =
            toPositiveIntegerOrNull(group.sort_order) ?? groupIndex + 1;

        (group.members ?? []).forEach((member, memberIndex) => {
            const manifestMemberId = toPositiveIntegerOrNull(member.id);
            const confirmationMemberId = toPositiveIntegerOrNull(
                member.customer_confirmation_member_id,
            );
            const officialId = toPositiveIntegerOrNull(
                member.package_official_id,
            );

            const fallbackMember = getMemberFromLookup(fallbackLookup, {
                manifestMemberId,
                confirmationMemberId,
                officialId,
            });

            const patch =
                member.patch && typeof member.patch === 'object'
                    ? member.patch
                    : {};

            nextMembers.push(
                toMemberWithUI(
                    {
                        ...(fallbackMember ?? {}),
                        ...(patch as MemberWithUI),
                        id: manifestMemberId ?? fallbackMember?.id ?? null,
                        customer_confirmation_member_id:
                            confirmationMemberId ??
                            fallbackMember?.customer_confirmation_member_id ??
                            null,
                        package_official_id:
                            officialId ??
                            fallbackMember?.package_official_id ??
                            null,
                        relationship:
                            member.relationship ??
                            fallbackMember?.relationship ??
                            null,
                        sharing_plan:
                            member.sharing_plan ??
                            fallbackMember?.sharing_plan ??
                            null,
                        sort_order:
                            toPositiveIntegerOrNull(member.sort_order) ??
                            fallbackMember?.sort_order ??
                            memberIndex + 1,
                        group_sort_order: groupSortOrder,
                        sharing_group_key:
                            fallbackMember?.sharing_group_key ?? groupKey,
                        manifest_sharing_group_id:
                            groupId ??
                            fallbackMember?.manifest_sharing_group_id ??
                            null,
                        sharing_group_id:
                            groupId ?? fallbackMember?.sharing_group_id ?? null,
                        group_relationship:
                            group.group_relationship ??
                            fallbackMember?.group_relationship ??
                            null,
                        group_remarks:
                            group.remarks ??
                            fallbackMember?.group_remarks ??
                            null,
                        remarks: member.remarks ?? null,
                        status: member.status ?? fallbackMember?.status ?? null,
                    },
                    nextMembers.length,
                ),
            );
        });
    });

    if (nextMembers.length === 0) {
        return fallbackMembers;
    }

    return nextMembers;
}

function buildRoomListsFromCanonicalRooms(
    canonicalRooms: CanonicalManifestRoom[] | undefined,
    members: MemberWithUI[],
): Record<string, MemberWithUI[]> {
    if (!Array.isArray(canonicalRooms) || canonicalRooms.length === 0) {
        return {};
    }

    const memberLookup = buildMemberLookupMap(members);
    const roomLists: Record<string, MemberWithUI[]> = {};

    canonicalRooms.forEach((room, roomIndex) => {
        const roomId = toPositiveIntegerOrNull(room.id);
        const groupKey =
            roomId !== null ? `room-${roomId}` : `room-${roomIndex + 1}`;
        const locationKey = String(room.location ?? '')
            .trim()
            .toLowerCase();

        if (locationKey.length === 0) {
            return;
        }

        const members = [...(room.members ?? [])].sort((left, right) => {
            const leftOrder = toPositiveIntegerOrNull(left.sort_order) ?? 0;
            const rightOrder = toPositiveIntegerOrNull(right.sort_order) ?? 0;

            return leftOrder - rightOrder;
        });

        members.forEach((member, memberIndex) => {
            const manifestMemberId = toPositiveIntegerOrNull(
                member.manifest_member_id ?? member.id,
            );
            const confirmationMemberId = toPositiveIntegerOrNull(
                member.customer_confirmation_member_id,
            );
            const officialId = toPositiveIntegerOrNull(
                member.package_official_id,
            );
            const fallbackMember = getMemberFromLookup(memberLookup, {
                manifestMemberId,
                confirmationMemberId,
                officialId,
            });

            const row = toMemberWithUI(
                {
                    ...(fallbackMember ?? {}),
                    id: manifestMemberId ?? fallbackMember?.id ?? null,
                    manifest_member_id:
                        manifestMemberId ??
                        fallbackMember?.manifest_member_id ??
                        fallbackMember?.id ??
                        null,
                    customer_confirmation_member_id:
                        confirmationMemberId ??
                        fallbackMember?.customer_confirmation_member_id ??
                        null,
                    package_official_id:
                        officialId ??
                        fallbackMember?.package_official_id ??
                        null,
                    manifest_room_id: roomId,
                    room_member_id: toPositiveIntegerOrNull(member.id),
                    sort_order:
                        toPositiveIntegerOrNull(member.sort_order) ??
                        memberIndex + 1,
                    sharing_group_key: groupKey,
                    sharing_plan:
                        room.sharing_plan ??
                        fallbackMember?.sharing_plan ??
                        null,
                    room_relationship:
                        room.group_relationship ?? room.relationship ?? null,
                    room_label: room.room_label ?? null,
                    room_number: room.room_number ?? null,
                    room_type: room.room_type ?? null,
                    bed_type: room.bed_type ?? null,
                    number_of_beds_checked: !!room.number_of_beds_checked,
                    meal: room.meal ?? null,
                    room_remarks: room.remarks ?? null,
                    remarks: member.remarks ?? null,
                },
                memberIndex,
            );

            if (!roomLists[locationKey]) {
                roomLists[locationKey] = [];
            }

            roomLists[locationKey].push({
                ...row,
                sn: memberIndex + 1,
                sort_order: memberIndex + 1,
            });
        });
    });

    return roomLists;
}

function memberIdentityKey(member: MemberWithUI, index: number): string {
    if (
        member.customer_confirmation_member_id !== undefined &&
        member.customer_confirmation_member_id !== null
    ) {
        return `confirmation_member-${member.customer_confirmation_member_id}`;
    }

    if (member.customer_id !== undefined && member.customer_id !== null) {
        return `customer-${member.customer_id}`;
    }

    if (
        member.package_official_id !== undefined &&
        member.package_official_id !== null
    ) {
        return `package_official-${member.package_official_id}`;
    }

    if (member.id !== undefined && member.id !== null) {
        return `manifest_member-${member.id}`;
    }

    if (member.row_key && member.row_key.trim().length > 0) {
        return `row-${member.row_key}`;
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
    const legacyMembers = normalizeMainTabOrdering(
        flattenGroupedRows(
            ((initialData as Record<string, unknown> | undefined)?.[
                'members'
            ] as unknown[]) ??
                initialData?.manifest_members ??
                [],
        ),
    );
    const members = normalizeMainTabOrdering(
        buildMembersFromCanonicalGroups(
            initialData?.manifest_sharing_groups,
            legacyMembers,
        ),
    );

    const sourceDocuments = initialData?.documents;
    const fallbackDocuments = buildEmptyManifestDocuments();
    const documents: ManifestDocumentsByField = {
        train_tickets: sourceDocuments?.train_tickets?.length
            ? sourceDocuments.train_tickets.map((doc) => ({
                  ...doc,
                  removed: doc.removed ?? false,
              }))
            : fallbackDocuments.train_tickets,
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
        passport: buildMemberIdentityDocuments(members, 'passport'),
        photo: buildMemberIdentityDocuments(members, 'photo'),
    };

    const canonicalRoomLists = buildRoomListsFromCanonicalRooms(
        initialData?.manifest_rooms,
        members,
    );

    const canonicalSharingGroups =
        initialData?.manifest_sharing_groups &&
        initialData.manifest_sharing_groups.length > 0
            ? initialData.manifest_sharing_groups
            : buildCanonicalSharingGroupsFromMembers(members);

    const canonicalRooms =
        initialData?.manifest_rooms && initialData.manifest_rooms.length > 0
            ? initialData.manifest_rooms
            : buildCanonicalRoomsFromRoomLists(
                  Object.fromEntries(
                      Object.entries(canonicalRoomLists).map(([key, rows]) => [
                          key,
                          rows as Array<Record<string, unknown>>,
                      ]),
                  ),
              );

    return {
        id: initialData?.id,
        package_id: initialData?.package_id ?? 0,
        in_charge_official_id: initialData?.in_charge_official_id ?? null,
        manifest_number: initialData?.manifest_number ?? '',
        number_format_id: initialData?.number_format_id ?? null,
        status: initialData?.status ?? 'open',
        notes: initialData?.notes ?? '',
        manifest_members: members,
        documents,
        manifest_sharing_groups: canonicalSharingGroups,
        manifest_rooms: canonicalRooms,
    };
}

function buildRoomRowsFromMembers(
    members: MemberWithUI[],
    existingRows: MemberWithUI[] = [],
    accommodationKey: string,
    defaultMealPlan: string,
): MemberWithUI[] {
    const toBedTypeFromSharingPlan = (
        sharingPlan?: string | null,
    ): string | undefined => {
        const value = toManifestRoomTypeSharingPlan(sharingPlan) ?? '';

        if (value === 'double' || value === 'quad') {
            return 'king';
        }

        if (value === 'single' || value === 'triple') {
            return 'single';
        }

        return undefined;
    };

    return members.map((member, index) => {
        const memberIdentity = memberIdentityKey(member, index);
        const existing = existingRows.find(
            (row, rowIndex) =>
                memberIdentityKey(row, rowIndex) === memberIdentity,
        );

        const sharingPlan = member.sharing_plan;
        const fallbackRoomType =
            toManifestRoomTypeSharingPlan(sharingPlan) ?? undefined;
        const fallbackBedType = toBedTypeFromSharingPlan(sharingPlan);

        return {
            ...member,
            ...existing,
            is_official: member.package_official_id !== null,
            relationship: member.relationship ?? existing?.relationship,
            group_relationship:
                member.group_relationship ?? existing?.group_relationship,
            row_key:
                existing?.row_key ??
                member.row_key ??
                `room-${accommodationKey}-${member.customer_confirmation_member_id ?? member.customer_id ?? index}`,
            sn: index + 1,
            passport_number:
                member.passport_number ?? existing?.passport_number,
            date_of_birth:
                member.date_of_birth ?? existing?.date_of_birth ?? null,
            age:
                calculateAgeFromDob(member.date_of_birth) ??
                calculateAgeFromDob(existing?.date_of_birth) ??
                existing?.age ??
                null,
            accommodation_key: accommodationKey,
            sharing_plan:
                existing?.sharing_plan ?? member.sharing_plan ?? 'single',
            room_relationship:
                existing?.room_relationship ??
                member.group_relationship ??
                member.relationship ??
                '',
            room_type:
                existing?.room_type ??
                member.room_type ??
                fallbackRoomType ??
                '',
            bed_type:
                existing?.bed_type ?? member.bed_type ?? fallbackBedType ?? '',
            number_of_beds_checked:
                existing?.number_of_beds_checked ??
                member.number_of_beds_checked ??
                false,
            room_label: existing?.room_label ?? member.room_label ?? '',
            meal: existing?.meal ?? member.meal ?? defaultMealPlan ?? '',
            sharing_group_key:
                member.sharing_group_key ??
                existing?.sharing_group_key ??
                `solo-${member.customer_confirmation_member_id ?? index}`,
        };
    });
}

function countRoomGroups(rows: MemberWithUI[]): number {
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

function normalizeSharingPlanValue(sharingPlan?: string | null): string {
    return String(sharingPlan ?? '')
        .toLowerCase()
        .trim();
}

function isNoBedCapacitySharingPlan(sharingPlan?: string | null): boolean {
    const normalized = normalizeSharingPlanValue(sharingPlan);

    return normalized === 'child_no_bed' || normalized === 'infant';
}

function isExtraBedSharingPlan(sharingPlan?: string | null): boolean {
    return normalizeSharingPlanValue(sharingPlan) === 'child_with_bed';
}

function normalizeExtraBedRemark(
    remarks: string | null | undefined,
    sharingPlan?: string | null,
): string | null {
    const normalizedRemarks = String(remarks ?? '').trim();

    if (!isExtraBedSharingPlan(sharingPlan)) {
        return normalizedRemarks === '' ? null : normalizedRemarks;
    }

    if (normalizedRemarks === '') {
        return 'Extra bed';
    }

    if (/\bextra\s*bed\b/i.test(normalizedRemarks)) {
        return normalizedRemarks;
    }

    return `${normalizedRemarks}; Extra bed`;
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

function capacityFromRoomType(roomType?: string | null): number {
    const normalizedRoomType = String(roomType ?? '')
        .toLowerCase()
        .trim();

    if (normalizedRoomType === 'quad') {
        return 4;
    }

    if (normalizedRoomType === 'triple') {
        return 3;
    }

    if (normalizedRoomType === 'double' || normalizedRoomType === 'twin') {
        return 2;
    }

    return 1;
}

function normalizeMainTabOrdering(rows: MemberWithUI[]): MemberWithUI[] {
    const grouped = new Map<
        string,
        {
            groupSortOrder: number;
            firstGlobalOrder: number;
            isOfficial: boolean;
            members: Array<MemberWithUI & { _globalOrder: number }>;
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

function syncMemberSharingGroups(
    nextMembers: MemberWithUI[],
    previousMembers: MemberWithUI[],
): MemberWithUI[] {
    const previousMemberMap = new Map(
        previousMembers.map((member, index) => [
            memberIdentityKey(member, index),
            member,
        ]),
    );

    const bucketGroups = new Map<string, string[]>();
    const groupCounts = new Map<string, number>();
    const bucketCounters = new Map<string, number>();

    const assigned: MemberWithUI[] = [];

    nextMembers.forEach((member, index) => {
        const identity = memberIdentityKey(member, index);
        const previousMember = previousMemberMap.get(identity);

        const customerConfirmationId = Number(
            member.customer_confirmation_id ??
                previousMember?.customer_confirmation_id ??
                0,
        );
        const sharingPlan = String(
            member.sharing_plan ?? previousMember?.sharing_plan ?? '',
        )
            .toLowerCase()
            .trim();
        const capacity = capacityFromSharingPlan(sharingPlan);

        let groupKey =
            member.sharing_group_key ?? previousMember?.sharing_group_key ?? '';

        const isNewMember = !previousMember;
        const canAutoAssign =
            isNewMember && customerConfirmationId > 0 && sharingPlan !== '';

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
                member.customer_confirmation_member_id ??
                member.customer_id ??
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
            ...member,
            sharing_group_key: groupKey,
        });
    });

    return assigned;
}

function syncRoomRowsWithMembers(
    activeMembers: MemberWithUI[],
    existingRows: MemberWithUI[],
    accommodationKey: string,
    defaultMealPlan: string,
): MemberWithUI[] {
    const memberMap = new Map(
        activeMembers.map((member, index) => [
            memberIdentityKey(member, index),
            member,
        ]),
    );

    const keptRows = existingRows
        .map((row, index) => {
            const identity = memberIdentityKey(row, index);
            const member = memberMap.get(identity);

            if (!member) {
                return null;
            }

            const rowRoomRelationship = String(
                row.room_relationship ?? '',
            ).trim();

            return {
                ...row,
                ...member,
                row_key:
                    row.row_key ??
                    member.row_key ??
                    `room-${accommodationKey}-${member.customer_confirmation_member_id ?? member.customer_id ?? index}`,
                sharing_group_key:
                    row.sharing_group_key ??
                    member.sharing_group_key ??
                    `solo-${member.customer_confirmation_member_id ?? member.customer_id ?? index + 1}`,
                sharing_plan:
                    row.sharing_plan ?? member.sharing_plan ?? 'single',
                room_number: row.room_number ?? member.room_number ?? '',
                room_relationship:
                    rowRoomRelationship !== '' ? row.room_relationship : '',
                room_label: row.room_label ?? member.room_label ?? '',
                room_type: row.room_type ?? member.room_type ?? '',
                bed_type: row.bed_type ?? member.bed_type ?? '',
                number_of_beds_checked:
                    row.number_of_beds_checked ??
                    member.number_of_beds_checked ??
                    false,
                remarks: row.remarks,
                room_remarks: row.room_remarks ?? member.room_remarks,
                meal: row.meal ?? member.meal ?? defaultMealPlan ?? '',
                age:
                    calculateAgeFromDob(
                        member.date_of_birth ?? row.date_of_birth,
                    ) ?? row.age,
            } as MemberWithUI;
        })
        .filter((row): row is MemberWithUI => row !== null);

    const seenIdentities = new Set<string>();
    const dedupedRows = keptRows.filter((row, index) => {
        const identity = memberIdentityKey(row, index);

        if (seenIdentities.has(identity)) {
            return false;
        }

        seenIdentities.add(identity);

        return true;
    });

    const missingMembers = activeMembers.filter((member, index) => {
        const identity = memberIdentityKey(member, index);

        if (seenIdentities.has(identity)) {
            return false;
        }

        // Officials are assigned to rooms explicitly from the Main tab actions.
        // Do not auto-append them here, otherwise an unassigned official gets re-added.
        return !member.package_official_id;
    });

    const appendedRows =
        missingMembers.length > 0
            ? buildRoomRowsFromMembers(
                  missingMembers,
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
                // Document IDs are not required by backend sync; omit them to
                // prevent stale-id validation failures after tab reordering or copying.
                id: null,
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

function shouldIncludeMemberField(
    currentValue: unknown,
    baselineValue: unknown,
): boolean {
    return currentValue !== baselineValue;
}

function buildMemberBaselineMap(
    members: MemberWithUI[],
): Map<string, MemberWithUI> {
    const baselineMap = new Map<string, MemberWithUI>();

    members.forEach((member, index) => {
        baselineMap.set(memberIdentityKey(member, index), member);
    });

    return baselineMap;
}

function toMemberSubmitRow(
    member: MemberWithUI,
    index: number,
    baselineMember?: MemberWithUI,
): Record<string, unknown> {
    const payload: Record<string, unknown> = {
        id: member.id ?? null,
        customer_confirmation_member_id:
            member.customer_confirmation_member_id ?? null,
        customer_id: member.customer_id ?? null,
        package_official_id: member.package_official_id ?? null,
        relationship: member.relationship ?? null,
        group_relationship: member.group_relationship ?? null,
        group_remarks: member.group_remarks ?? null,
        sharing_plan: member.sharing_plan ?? null,
        sharing_group_key:
            member.sharing_group_key ??
            `solo-${member.customer_confirmation_member_id ?? member.customer_id ?? index + 1}`,
        group_sort_order: member.group_sort_order ?? null,
        sort_order: member.sort_order ?? index + 1,
        name_as_per_passport: member.name_as_per_passport ?? null,
        remarks: member.remarks ?? null,
        status: member.status ?? null,
    };

    const compactOptionalFields: Array<keyof MemberWithUI> = [
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
        'is_using_wheelchair',
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
        const currentValue = member[field] ?? null;
        const baselineValue = baselineMember?.[field] ?? null;

        if (shouldIncludeMemberField(currentValue, baselineValue)) {
            payload[field] = currentValue;
        }
    });

    const normalizedReceipts = normalizeDocumentEntriesForSubmit(
        member.receipt_documents,
    );
    const baselineReceipts = normalizeDocumentEntriesForSubmit(
        baselineMember?.receipt_documents,
    );

    if (!areDocumentEntriesEquivalent(normalizedReceipts, baselineReceipts)) {
        payload.receipt_documents = normalizedReceipts;
    }

    return payload;
}

function toRoomListSubmitRow(
    row: MemberWithUI,
    index: number,
    accommodationKey: string,
): Record<string, unknown> {
    const roomNumber = String(row.room_number ?? '').trim();
    const manifestMemberId = toPositiveIntegerOrNull(
        row.manifest_member_id ?? row.id,
    );

    return {
        manifest_member_id: manifestMemberId,
        id: manifestMemberId,
        manifest_room_id: toPositiveIntegerOrNull(row.manifest_room_id),
        room_member_id: toPositiveIntegerOrNull(row.room_member_id),
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

function buildCanonicalSharingGroupsFromMembers(
    members: MemberWithUI[],
): CanonicalManifestSharingGroup[] {
    const groupMap = new Map<string, CanonicalManifestSharingGroup>();

    members.forEach((member, index) => {
        const groupSortOrder = Number(member.group_sort_order ?? index + 1);
        const groupId = toPositiveIntegerOrNull(
            member.manifest_sharing_group_id ?? member.sharing_group_id,
        );
        const groupKey =
            String(member.sharing_group_key ?? '').trim() ||
            (groupId !== null ? `group-${groupId}` : `group-${groupSortOrder}`);
        const memberSortOrder = Number(
            member.sort_order ?? member.sn ?? index + 1,
        );

        if (!groupMap.has(groupKey)) {
            groupMap.set(groupKey, {
                id: groupId,
                customer_confirmation_id:
                    toPositiveIntegerOrNull(member.customer_confirmation_id) ??
                    null,
                sort_order: groupSortOrder,
                group_relationship: member.group_relationship ?? null,
                remarks: member.group_remarks ?? null,
                members: [],
            });
        }

        const group = groupMap.get(groupKey);

        if (!group || !group.members) {
            return;
        }

        const memberPatch: Record<string, string | boolean | null> = {};
        const patchFields: Array<keyof MemberWithUI> = [
            'name_as_per_passport',
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
            'is_using_wheelchair',
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
            'status',
        ];

        patchFields.forEach((field) => {
            const value = member[field];

            if (
                value === null ||
                typeof value === 'string' ||
                typeof value === 'boolean'
            ) {
                memberPatch[field] = value;
            }
        });

        group.members.push({
            id: toPositiveIntegerOrNull(member.id),
            customer_confirmation_member_id: toPositiveIntegerOrNull(
                member.customer_confirmation_member_id,
            ),
            package_official_id: toPositiveIntegerOrNull(
                member.package_official_id,
            ),
            relationship: member.relationship ?? null,
            sharing_plan: member.sharing_plan ?? null,
            sort_order: memberSortOrder,
            remarks: member.remarks ?? null,
            status: member.status ?? null,
            patch: memberPatch,
        });
    });

    return Array.from(groupMap.values()).map((group) => {
        return {
            ...group,
            members: [...(group.members ?? [])].sort((left, right) => {
                return (
                    Number(left.sort_order ?? 0) - Number(right.sort_order ?? 0)
                );
            }),
        };
    });
}

function buildCanonicalRoomsFromRoomLists(
    roomLists: Record<string, Array<Record<string, unknown>>>,
): CanonicalManifestRoom[] {
    const rooms: CanonicalManifestRoom[] = [];

    Object.entries(roomLists).forEach(([location, rows]) => {
        const groupedRows = new Map<string, Array<Record<string, unknown>>>();

        rows.forEach((row, index) => {
            const rowGroupKey = String(row.sharing_group_key ?? '').trim();
            const fallbackGroupKey = `room-${location}-${index + 1}`;
            const groupKey =
                rowGroupKey.length > 0 ? rowGroupKey : fallbackGroupKey;

            if (!groupedRows.has(groupKey)) {
                groupedRows.set(groupKey, []);
            }

            groupedRows.get(groupKey)?.push(row);
        });

        Array.from(groupedRows.entries()).forEach(
            ([groupKey, members], roomIndex) => {
                const base = members[0] ?? {};
                const groupIdMatch = groupKey.match(/^(?:room-)?(\d+)$/);
                const payloadRoomId = toPositiveIntegerOrNull(
                    base.manifest_room_id,
                );
                const canonicalRoomId = groupIdMatch
                    ? Number.parseInt(groupIdMatch[1], 10)
                    : payloadRoomId;

                rooms.push({
                    id: Number.isFinite(canonicalRoomId)
                        ? canonicalRoomId
                        : null,
                    location,
                    sort_order: roomIndex + 1,
                    group_relationship:
                        String(base.room_relationship ?? '').trim() || null,
                    room_label: String(base.room_label ?? '').trim() || null,
                    room_number: String(base.room_number ?? '').trim() || null,
                    room_type: String(base.room_type ?? '').trim() || null,
                    bed_type: String(base.bed_type ?? '').trim() || null,
                    sharing_plan:
                        String(base.sharing_plan ?? '').trim() || null,
                    meal: String(base.meal ?? '').trim() || null,
                    number_of_beds_checked: !!base.number_of_beds_checked,
                    remarks:
                        (base.room_remarks as string | null | undefined) ??
                        null,
                    members: members.map((member, memberIndex) => ({
                        id: toPositiveIntegerOrNull(member.room_member_id),
                        room_member_id: toPositiveIntegerOrNull(
                            member.room_member_id,
                        ),
                        manifest_member_id: toPositiveIntegerOrNull(
                            member.manifest_member_id ?? member.id,
                        ),
                        customer_confirmation_member_id:
                            toPositiveIntegerOrNull(
                                member.customer_confirmation_member_id,
                            ),
                        package_official_id: toPositiveIntegerOrNull(
                            member.package_official_id,
                        ),
                        sort_order: Number(
                            member.sort_order ?? memberIndex + 1,
                        ),
                        sharing_plan:
                            String(member.sharing_plan ?? '').trim() || null,
                        remarks: normalizeExtraBedRemark(
                            (member.remarks as string | null | undefined) ??
                                null,
                            String(member.sharing_plan ?? ''),
                        ),
                    })),
                });
            },
        );
    });

    return rooms;
}

function buildSubmitPayload(
    data: ManifestFormData,
    roomLists: Record<string, MemberWithUI[]>,
    baselineMembers: MemberWithUI[] = [],
): ManifestFormData {
    const baselineMemberMap = buildMemberBaselineMap(baselineMembers);
    const memberRows = ((data.manifest_members ?? []) as MemberWithUI[]).map(
        (member, index) =>
            toMemberSubmitRow(
                member,
                index,
                baselineMemberMap.get(memberIdentityKey(member, index)),
            ),
    );

    const normalizedRoomLists = Object.fromEntries(
        Object.entries(roomLists).map(([key, rows]) => {
            return [
                key,
                ((rows ?? []) as MemberWithUI[]).map((row, index) =>
                    toRoomListSubmitRow(row, index, key),
                ),
            ];
        }),
    );

    const canonicalSharingGroups = buildCanonicalSharingGroupsFromMembers(
        (data.manifest_members ?? []) as MemberWithUI[],
    );
    const canonicalRooms = buildCanonicalRoomsFromRoomLists(
        Object.fromEntries(
            Object.entries(normalizedRoomLists).map(([key, rows]) => [
                key,
                rows as Array<Record<string, unknown>>,
            ]),
        ),
    );

    return {
        ...data,
        id: data.id,
        package_id: data.package_id,
        in_charge_official_id: data.in_charge_official_id,
        manifest_number: data.manifest_number ?? '',
        number_format_id: data.number_format_id ?? null,
        status: data.status,
        notes: data.notes,
        documents: {
            train_tickets: normalizeDocumentEntriesForSubmit(
                data.documents?.train_tickets,
            ),
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
        manifest_members: memberRows as MemberWithUI[],
        manifest_sharing_groups: canonicalSharingGroups,
        manifest_rooms: canonicalRooms,
    };
}

function validateRoomCapacityOnSubmit(
    rooms: CanonicalManifestRoom[] | undefined,
): Record<string, string> {
    if (!Array.isArray(rooms) || rooms.length === 0) {
        return {};
    }

    const errors: Record<string, string> = {};

    rooms.forEach((room, roomIndex) => {
        const roomMembers = Array.isArray(room.members) ? room.members : [];
        const membersCount = roomMembers.filter((member) => {
            return !isNoBedCapacitySharingPlan(member.sharing_plan ?? null);
        }).length;
        const extraBedCount = roomMembers.filter((member) => {
            return isExtraBedSharingPlan(member.sharing_plan ?? null);
        }).length;
        const maxCapacity = capacityFromRoomType(room.room_type ?? null);
        const allowedCapacity = maxCapacity + extraBedCount;

        if (membersCount > allowedCapacity) {
            const roomTypeLabel = String(
                room.room_type ?? 'single',
            ).toUpperCase();
            errors[`rooms.${roomIndex}.remarks`] =
                `Room is over capacity for ${roomTypeLabel}: ${membersCount}/${allowedCapacity} pax.`;
        }
    });

    return errors;
}

type SectionRequestResult = {
    ok: boolean;
    errors?: Record<string, string>;
    isValidationError?: boolean;
};

type ResolvedCsrfToken = {
    value: string;
    source: 'meta' | 'cookie';
};

function normalizeSectionErrors(
    errors: Record<string, unknown>,
): Record<string, string> {
    if (!errors || typeof errors !== 'object') {
        return {};
    }

    return Object.fromEntries(
        Object.entries(errors).map(([field, message]) => {
            if (Array.isArray(message)) {
                return [field, String(message[0] ?? 'Invalid value.')];
            }

            return [field, String(message ?? 'Invalid value.')];
        }),
    );
}

function resolveCsrfToken(): ResolvedCsrfToken | null {
    const cookieEntry = document.cookie
        .split(';')
        .map((segment) => segment.trim())
        .find((segment) => segment.startsWith('XSRF-TOKEN='));

    if (cookieEntry) {
        const encodedValue = cookieEntry.slice('XSRF-TOKEN='.length);

        if (encodedValue) {
            try {
                return {
                    value: decodeURIComponent(encodedValue),
                    source: 'cookie',
                };
            } catch {
                return {
                    value: encodedValue,
                    source: 'cookie',
                };
            }
        }
    }

    const metaToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content')
        ?.trim();

    if (metaToken && metaToken.length > 0) {
        return {
            value: metaToken,
            source: 'meta',
        };
    }

    return null;
}

function mapMemberToCustomerSchema(member: MemberWithUI): CustomerSchema {
    const passportPath = String(member.passport_path ?? '').trim();
    const photoPath = String(member.photo_path ?? '').trim();

    return {
        customer_number: String(member.customer_number ?? ''),
        member_id: member.customer_confirmation_member_id ?? undefined,
        customer_id: member.customer_id ?? undefined,
        is_leader: false,
        name: String(member.name_as_per_passport ?? member.customer_name ?? ''),
        email: String((member as { email?: string }).email ?? ''),
        contact_number: String(member.contact_no ?? ''),
        nric_number: String(
            (member as { nric_number?: string }).nric_number ?? '',
        ),
        address: String(member.address ?? ''),
        nationality: String(member.nationality ?? ''),
        passport_number: String(member.passport_number ?? ''),
        passport_issue_date: String(member.date_of_issue ?? ''),
        passport_expiry_date: String(member.date_of_expiry ?? ''),
        passport_place_of_issue: String(member.issue_place ?? ''),
        gender: String(member.gender ?? ''),
        marital_status: String(
            (member as { marital_status?: string }).marital_status ?? '',
        ),
        date_of_birth: String(member.date_of_birth ?? ''),
        place_of_birth: String(member.birth_place ?? ''),
        first_time_umrah:
            typeof member.first_time_umrah === 'boolean'
                ? member.first_time_umrah
                : null,
        has_chronic_disease:
            typeof member.has_chronic_disease === 'boolean'
                ? member.has_chronic_disease
                : null,
        is_using_wheelchair:
            typeof member.is_using_wheelchair === 'boolean'
                ? member.is_using_wheelchair
                : null,
        chronic_disease_details: String(member.chronic_disease_details ?? ''),
        status: String(member.status ?? 'pending_payment'),
        sharing_plan: member.sharing_plan ? String(member.sharing_plan) : null,
        relationship: member.relationship ? String(member.relationship) : null,
        passport_file: undefined,
        photo_file: undefined,
        passport_file_name: null,
        photo_file_name: null,
        passport_file_removed: false,
        photo_file_removed: false,
        passport_document:
            passportPath.length > 0
                ? {
                      field: 'passport',
                      file_name: `${String(member.name_as_per_passport ?? member.customer_name ?? 'Member').trim() || 'Member'} Passport`,
                      file_path: passportPath,
                  }
                : null,
        photo_document:
            photoPath.length > 0
                ? {
                      field: 'photo',
                      file_name: `${String(member.name_as_per_passport ?? member.customer_name ?? 'Member').trim() || 'Member'} Photo`,
                      file_path: photoPath,
                  }
                : null,
    };
}

async function submitSectionPayload(
    url: string,
    payload: Record<string, unknown>,
    options: { forceFormData?: boolean; validateOnly?: boolean } = {},
): Promise<SectionRequestResult> {
    const csrfToken = resolveCsrfToken();
    const csrfTokenValue = csrfToken?.value ?? null;
    const csrfTokenSource = csrfToken?.source ?? null;
    const useFormData = options.forceFormData ?? false;
    const validateOnly = options.validateOnly ?? false;

    const appendFormValue = (
        formData: FormData,
        key: string,
        value: unknown,
    ): void => {
        if (value === undefined) {
            return;
        }

        if (value === null) {
            formData.append(key, '');

            return;
        }

        if (value instanceof File) {
            formData.append(key, value);

            return;
        }

        if (value instanceof Blob) {
            formData.append(key, value);

            return;
        }

        if (Array.isArray(value)) {
            value.forEach((item, index) => {
                appendFormValue(formData, `${key}[${index}]`, item);
            });

            return;
        }

        if (typeof value === 'object') {
            Object.entries(value as Record<string, unknown>).forEach(
                ([childKey, childValue]) => {
                    appendFormValue(
                        formData,
                        `${key}[${childKey}]`,
                        childValue,
                    );
                },
            );

            return;
        }

        if (typeof value === 'boolean') {
            formData.append(key, value ? '1' : '0');

            return;
        }

        formData.append(key, String(value));
    };

    const buildRequestBody = (): BodyInit => {
        if (!useFormData) {
            return JSON.stringify({
                ...payload,
                ...(validateOnly ? { validate_only: true } : {}),
                ...(csrfTokenSource === 'meta' && csrfTokenValue
                    ? { _token: csrfTokenValue }
                    : {}),
            });
        }

        const formData = new FormData();
        formData.append('_method', 'patch');

        if (csrfTokenSource === 'meta' && csrfTokenValue) {
            formData.append('_token', csrfTokenValue);
        }

        if (validateOnly) {
            formData.append('validate_only', '1');
        }

        Object.entries(payload).forEach(([key, value]) => {
            appendFormValue(formData, key, value);
        });

        return formData;
    };

    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(csrfTokenSource === 'meta' && csrfTokenValue
            ? { 'X-CSRF-TOKEN': csrfTokenValue }
            : {}),
        ...(csrfTokenSource === 'cookie' && csrfTokenValue
            ? { 'X-XSRF-TOKEN': csrfTokenValue }
            : {}),
    };

    if (!useFormData) {
        headers['Content-Type'] = 'application/json';
    }

    let response: Response;

    try {
        response = await fetch(url, {
            method: useFormData ? 'POST' : 'PATCH',
            headers,
            body: buildRequestBody(),
            credentials: 'same-origin',
        });
    } catch {
        return {
            ok: false,
            errors: {
                section_save:
                    'Unable to reach server while saving manifest sections.',
            },
        };
    }

    if (response.ok) {
        return { ok: true };
    }

    let errorPayload: Record<string, unknown> = {};

    try {
        errorPayload = (await response.json()) as Record<string, unknown>;
    } catch {
        errorPayload = {};
    }

    if (response.status === 422) {
        return {
            ok: false,
            isValidationError: true,
            errors: normalizeSectionErrors(
                (errorPayload.errors as Record<string, unknown>) ?? {},
            ),
        };
    }

    return {
        ok: false,
        errors: {
            section_save:
                typeof errorPayload.message === 'string'
                    ? errorPayload.message
                    : 'Unable to save one or more manifest sections.',
        },
    };
}

function buildManifestMemberReceiptsPayload(members: MemberWithUI[]): Array<{
    manifest_member_id: number | null;
    customer_confirmation_member_id: number | null;
    receipt_documents: ManifestDocumentItem[];
}> {
    return members.reduce<
        Array<{
            manifest_member_id: number | null;
            customer_confirmation_member_id: number | null;
            receipt_documents: ManifestDocumentItem[];
        }>
    >((payload, member) => {
        const manifestMemberId = toPositiveIntegerOrNull(
            member.manifest_member_id,
        );
        const confirmationMemberId = toPositiveIntegerOrNull(
            member.customer_confirmation_member_id,
        );
        const receiptDocuments = normalizeDocumentEntriesForSubmit(
            member.receipt_documents,
        );

        if (receiptDocuments.length === 0) {
            return payload;
        }

        if (manifestMemberId === null && confirmationMemberId === null) {
            return payload;
        }

        payload.push({
            manifest_member_id: manifestMemberId,
            customer_confirmation_member_id: confirmationMemberId,
            receipt_documents: receiptDocuments,
        });

        return payload;
    }, []);
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
    const [isSectionSaving, setIsSectionSaving] = useState(false);

    const [roomListsState, setRoomListsState] = useState<
        Record<string, MemberWithUI[]>
    >(() => {
        return buildRoomListsFromCanonicalRooms(
            defaults.manifest_rooms,
            ((defaults.manifest_members ?? []) as MemberWithUI[]) ?? [],
        );
    });
    const [airlineRowsState, setAirlineRowsState] = useState<MemberWithUI[]>(
        () => ((defaults.manifest_members ?? []) as MemberWithUI[]) ?? [],
    );
    const [isMemberDetailDialogOpen, setIsMemberDetailDialogOpen] =
        useState(false);
    const [selectedMemberForDetail, setSelectedMemberForDetail] =
        useState<MemberWithUI | null>(null);

    useEffect(() => {
        const canonicalRooms = buildCanonicalRoomsFromRoomLists(
            Object.fromEntries(
                Object.entries(roomListsState ?? {}).map(([key, rows]) => [
                    key,
                    rows as Array<Record<string, unknown>>,
                ]),
            ),
        );

        setFormData('manifest_rooms', canonicalRooms);
    }, [roomListsState, setFormData]);

    useEffect(() => {
        const members = ((data.manifest_members ?? []) as MemberWithUI[]) ?? [];
        const currentDocuments =
            data.documents ?? buildEmptyManifestDocuments();

        const mirroredPassportRows = buildMemberIdentityDocuments(
            members,
            'passport',
        );
        const mirroredPhotoRows = buildMemberIdentityDocuments(
            members,
            'photo',
        );

        const currentPassportRows =
            (currentDocuments.passport as ManifestDocumentItem[]) ?? [];
        const currentPhotoRows =
            (currentDocuments.photo as ManifestDocumentItem[]) ?? [];

        const isPassportSynced = areDocumentEntriesEquivalent(
            currentPassportRows,
            mirroredPassportRows,
        );
        const isPhotoSynced = areDocumentEntriesEquivalent(
            currentPhotoRows,
            mirroredPhotoRows,
        );

        if (isPassportSynced && isPhotoSynced) {
            return;
        }

        setFormData('documents', {
            ...currentDocuments,
            passport: mirroredPassportRows,
            photo: mirroredPhotoRows,
        });
    }, [data.documents, data.manifest_members, setFormData]);

    const scrollToErrorBanner = useCallback(() => {
        setTimeout(() => {
            errorAlertRef.current?.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
        }, 0);
    }, []);

    const isCancelledMember = useCallback((member: MemberWithUI) => {
        return member.status === 'cancelled';
    }, []);

    const selectedPackage = useMemo(() => {
        const selectedPackageId = Number(data.package_id ?? 0);

        if (!Number.isFinite(selectedPackageId) || selectedPackageId < 1) {
            return undefined;
        }

        return packageOptions.find((item) => {
            return Number(item.value) === selectedPackageId;
        });
    }, [packageOptions, data.package_id]);

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
                ((roomListsState ?? {})[tab.key] as MemberWithUI[]) ?? [];
            const groupCount = countRoomGroups(tabRows);
            const range = {
                tabKey: tab.key,
                start: runningIndex,
                end: runningIndex + Math.max(groupCount - 1, 0),
            };

            runningIndex += groupCount;

            return range;
        });
    }, [roomListsState, roomTabs]);

    const resolveErrorTab = useCallback(
        (path: string): string => {
            if (path.startsWith('members.')) {
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
        const currentMembers = (
            (data.manifest_members ?? []) as MemberWithUI[]
        ).filter((member) => !isCancelledMember(member));
        const currentRoomLists = roomListsState ?? {};

        const syncedRoomLists = Object.fromEntries(
            roomTabs.map((tab) => [
                tab.key,
                syncRoomRowsWithMembers(
                    currentMembers,
                    (currentRoomLists[tab.key] ?? []) as MemberWithUI[],
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
                ((currentRoomLists[tab.key] ?? []) as MemberWithUI[]) ?? [];
            const syncedRows =
                ((syncedRoomLists[tab.key] ?? []) as MemberWithUI[]) ?? [];

            if (currentRows.length !== syncedRows.length) {
                return true;
            }

            const currentIdentitySet = new Set(
                currentRows.map((member, index) =>
                    memberIdentityKey(member, index),
                ),
            );
            const syncedIdentitySet = new Set(
                syncedRows.map((member, index) =>
                    memberIdentityKey(member, index),
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
            setRoomListsState(syncedRoomLists);
        }
    }, [
        roomTabs,
        data.manifest_members,
        roomListsState,
        isCancelledMember,
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

    const updateFromMembers = useCallback(
        (nextMembers: MemberWithUI[]) => {
            const groupedMembers = syncMemberSharingGroups(
                nextMembers,
                ((data.manifest_members ?? []) as MemberWithUI[]) ?? [],
            );

            const orderedMembers = normalizeMainTabOrdering(groupedMembers);

            const membersWithSn = orderedMembers.map((row, index) => ({
                ...row,
                sn: index + 1,
                relationship: row.relationship ?? undefined,
                passport_number: row.passport_number ?? undefined,
                age: calculateAgeFromDob(row.date_of_birth) ?? row.age ?? null,
                status:
                    row.status ??
                    (row.customer_confirmation_member_id
                        ? 'pending_payment'
                        : 'fully_paid'),
                sharing_group_key:
                    row.sharing_group_key ??
                    `solo-${row.customer_confirmation_member_id ?? row.customer_id ?? index + 1}`,
            }));

            const activeMembersWithSn = membersWithSn.filter(
                (member) => !isCancelledMember(member),
            );

            const memberMap = new Map(
                activeMembersWithSn.map((row, index) => [
                    memberIdentityKey(row, index),
                    row,
                ]),
            );

            const nextRoomLists = Object.fromEntries(
                roomTabs.map((tab) => {
                    const key = tab.key;
                    const rows = (roomListsState ?? {})[key] ?? [];

                    return [
                        key,
                        syncRoomRowsWithMembers(
                            activeMembersWithSn,
                            (rows as MemberWithUI[]).map((row, rowIndex) => {
                                const memberUpdate = memberMap.get(
                                    memberIdentityKey(row, rowIndex),
                                );

                                if (!memberUpdate) {
                                    return row;
                                }

                                return {
                                    ...row,
                                    name_as_per_passport:
                                        memberUpdate.name_as_per_passport ??
                                        row.name_as_per_passport,
                                    passport_number:
                                        memberUpdate.passport_number ??
                                        row.passport_number,
                                    nationality:
                                        memberUpdate.nationality ??
                                        row.nationality,
                                    gender: memberUpdate.gender ?? row.gender,
                                    date_of_birth:
                                        memberUpdate.date_of_birth ??
                                        row.date_of_birth,
                                    date_of_issue:
                                        memberUpdate.date_of_issue ??
                                        row.date_of_issue,
                                    date_of_expiry:
                                        memberUpdate.date_of_expiry ??
                                        row.date_of_expiry,
                                    issue_place:
                                        memberUpdate.issue_place ??
                                        row.issue_place,
                                    birth_place:
                                        memberUpdate.birth_place ??
                                        row.birth_place,
                                    contact_no:
                                        memberUpdate.contact_no ??
                                        row.contact_no,
                                    package_price:
                                        memberUpdate.package_price ??
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

            const nextAirline = membersWithSn
                .map((member, memberIndex) => {
                    const memberIdentity = memberIdentityKey(
                        member,
                        memberIndex,
                    );

                    const existing = (airlineRowsState ?? []).find(
                        (row: unknown, existingIndex: number) =>
                            memberIdentityKey(
                                row as MemberWithUI,
                                existingIndex,
                            ) === memberIdentity,
                    ) as MemberWithUI | undefined;

                    return {
                        ...existing,
                        ...member,
                        passport_number:
                            member.passport_number ?? existing?.passport_number,
                        nationality:
                            member.nationality ?? existing?.nationality,
                        gender: member.gender ?? existing?.gender,
                        date_of_birth:
                            member.date_of_birth ?? existing?.date_of_birth,
                        age:
                            calculateAgeFromDob(member.date_of_birth) ??
                            existing?.age ??
                            member.age ??
                            null,
                        date_of_issue:
                            member.date_of_issue ?? existing?.date_of_issue,
                        date_of_expiry:
                            member.date_of_expiry ?? existing?.date_of_expiry,
                        issue_place:
                            member.issue_place ?? existing?.issue_place,
                        birth_place:
                            member.birth_place ?? existing?.birth_place,
                        contact_no: member.contact_no ?? existing?.contact_no,
                        package_price:
                            member.package_price ?? existing?.package_price,
                        relationship:
                            member.relationship ?? existing?.relationship,
                        group_relationship:
                            member.group_relationship ??
                            existing?.group_relationship,
                        sharing_plan:
                            member.sharing_plan ?? existing?.sharing_plan,
                        row_key:
                            existing?.row_key ??
                            member.row_key ??
                            `airline-${member.customer_confirmation_member_id ?? member.customer_id ?? member.sn}`,
                        sn: member.sn,
                    };
                })
                .filter((member) => !isCancelledMember(member));

            setFormData('manifest_members', membersWithSn);
            setRoomListsState(nextRoomLists);
            setAirlineRowsState(nextAirline);
        },
        [
            airlineRowsState,
            roomListsState,
            data.manifest_members,
            isCancelledMember,
            roomTabs,
            setFormData,
        ],
    );

    const resetRoomListTab = useCallback(
        (tabKey: string, sourceTabKey: 'main' | string = 'main') => {
            const targetTab = roomTabs.find((tab) => tab.key === tabKey);

            if (!targetTab) {
                return;
            }

            let baseRows: MemberWithUI[] = [];

            if (sourceTabKey === 'main') {
                const currentNonCancelledMembers = (
                    (data.manifest_members ?? []) as MemberWithUI[]
                ).filter((member) => !isCancelledMember(member));

                baseRows = buildRoomRowsFromMembers(
                    currentNonCancelledMembers,
                    [],
                    targetTab.key,
                    targetTab.accommodation.type_of_meal ?? '',
                ).map((row, index) => ({
                    ...row,
                    sn: index + 1,
                    sort_order: index + 1,
                }));
            } else {
                const sourceRows =
                    ((roomListsState ?? {})[sourceTabKey] as
                        | MemberWithUI[]
                        | undefined) ?? [];

                baseRows = sourceRows
                    .filter((member) => !isCancelledMember(member))
                    .map((row, index) => ({
                        ...row,
                        accommodation_key: targetTab.key,
                        sort_order: index + 1,
                        sn: index + 1,
                    }));
            }

            setRoomListsState({
                ...(roomListsState ?? {}),
                [tabKey]: baseRows,
            });
        },
        [roomListsState, data.manifest_members, isCancelledMember, roomTabs],
    );

    const getMemberAssignedLocations = useCallback(
        (member: MemberWithUI): string[] => {
            const membership = new Set<string>();

            roomTabs.forEach((tab) => {
                const rows = ((roomListsState ?? {})[tab.key] ?? []) as
                    | MemberWithUI[]
                    | undefined;

                if (!rows || rows.length === 0) {
                    return;
                }

                const exists = rows.some((row, index) => {
                    return (
                        memberIdentityKey(row, index) ===
                        memberIdentityKey(member, 0)
                    );
                });

                if (exists) {
                    membership.add(tab.key.trim().toLowerCase());
                }
            });

            return Array.from(membership);
        },
        [roomListsState, roomTabs],
    );

    const handleRoomAssignmentChange = useCallback(
        (
            member: MemberWithUI,
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
                (roomListsState as Record<string, MemberWithUI[]>) ?? {};
            const nextRoomLists: Record<string, MemberWithUI[]> = {
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
                    []) as MemberWithUI[];
                const memberExists = existingRows.some((row, rowIndex) => {
                    return (
                        memberIdentityKey(row, rowIndex) ===
                        memberIdentityKey(member, 0)
                    );
                });

                if (action === 'unassign') {
                    nextRoomLists[tab.key] = existingRows
                        .filter((row, rowIndex) => {
                            return (
                                memberIdentityKey(row, rowIndex) !==
                                memberIdentityKey(member, 0)
                            );
                        })
                        .map((row, index) => ({
                            ...row,
                            sn: index + 1,
                            sort_order: index + 1,
                        }));

                    return;
                }

                if (!memberExists) {
                    const appended = buildRoomRowsFromMembers(
                        [member],
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

            setRoomListsState(nextRoomLists);
        },
        [roomListsState, roomTabs],
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

    const updateMemberArabicName = useCallback(
        (member: MemberWithUI, arabicName: string) => {
            const sanitizedArabicName = normalizeArabicNameInput(
                arabicName.trim().length === 0
                    ? arabicName
                    : convertNameToArabic(arabicName),
            );

            const nextMembers = (
                (data.manifest_members ?? []) as MemberWithUI[]
            ).map((row, index) => {
                if (
                    memberIdentityKey(row, index) !==
                    memberIdentityKey(member, 0)
                ) {
                    return row;
                }

                return {
                    ...row,
                    arabic_name: sanitizedArabicName,
                };
            });

            setFormData('manifest_members', nextMembers);
        },
        [data.manifest_members, setFormData],
    );

    const updateMemberReceiptDocuments = useCallback(
        (member: MemberWithUI, rows: ManifestDocumentItem[]) => {
            const nextMembers = (
                (data.manifest_members ?? []) as MemberWithUI[]
            ).map((row, index) => {
                if (
                    memberIdentityKey(row, index) !==
                    memberIdentityKey(member, 0)
                ) {
                    return row;
                }

                return {
                    ...row,
                    receipt_documents: rows,
                };
            });

            setFormData('manifest_members', nextMembers);
        },
        [data.manifest_members, setFormData],
    );

    const submit = async (event: React.FormEvent) => {
        event.preventDefault();

        const validationResult = manifestValidationSchema.safeParse({
            ...data,
            members: data.manifest_members,
        });

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
            roomListsState,
            (defaults.manifest_members ?? []) as MemberWithUI[],
        );
        const roomCapacityErrors = validateRoomCapacityOnSubmit(
            submitPayload.manifest_rooms,
        );

        if (Object.keys(roomCapacityErrors).length > 0) {
            Object.entries(roomCapacityErrors).forEach(([field, message]) => {
                setFormError(field, message);
            });

            const firstRoomErrorPath = Object.keys(roomCapacityErrors)[0];

            if (firstRoomErrorPath) {
                navigateToErrorField(firstRoomErrorPath);
            }

            scrollToErrorBanner();

            return;
        }

        const manifestMemberReceiptsPayload =
            buildManifestMemberReceiptsPayload(
                (data.manifest_members ?? []) as MemberWithUI[],
            );

        submitPayload.manifest_member_receipts = manifestMemberReceiptsPayload;

        const handleError = (errors: Record<string, string>) => {
            Object.entries(errors).forEach(([field, message]) => {
                setFormError(field, message);
            });

            scrollToErrorBanner();
        };

        const submitWithFullPayload = () => {
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
        };

        if (isEdit && submitPayload.id) {
            const sectionRequests = [
                {
                    url: manifestSections.core.update.url(submitPayload.id),
                    payload: {
                        package_id: submitPayload.package_id ?? null,
                        in_charge_official_id:
                            submitPayload.in_charge_official_id ?? null,
                        notes: submitPayload.notes ?? null,
                        status: submitPayload.status ?? null,
                    },
                    forceFormData: false,
                },
                {
                    url: manifestSections.sharingGroups.update.url(
                        submitPayload.id,
                    ),
                    payload: {
                        manifest_sharing_groups:
                            submitPayload.manifest_sharing_groups ?? [],
                    },
                    forceFormData: false,
                },
                {
                    url: manifestSections.rooms.update.url(submitPayload.id),
                    payload: {
                        manifest_rooms: submitPayload.manifest_rooms ?? [],
                    },
                    forceFormData: false,
                },
                {
                    url: manifestSections.documents.update.url(
                        submitPayload.id,
                    ),
                    payload: {
                        documents:
                            submitPayload.documents ??
                            buildEmptyManifestDocuments(),
                    },
                    forceFormData: true,
                },
                {
                    url: manifestSections.receiptDocuments.update.url(
                        submitPayload.id,
                    ),
                    payload: {
                        manifest_member_receipts: manifestMemberReceiptsPayload,
                    },
                    forceFormData: true,
                },
            ];

            setIsSectionSaving(true);

            try {
                for (const sectionRequest of sectionRequests) {
                    const preflightResult = await submitSectionPayload(
                        sectionRequest.url,
                        sectionRequest.payload,
                        {
                            forceFormData: sectionRequest.forceFormData,
                            validateOnly: true,
                        },
                    );

                    if (!preflightResult.ok) {
                        handleError(
                            preflightResult.errors ?? {
                                section_save:
                                    'Unable to validate one or more manifest sections.',
                            },
                        );

                        return;
                    }
                }

                for (const sectionRequest of sectionRequests) {
                    const result = await submitSectionPayload(
                        sectionRequest.url,
                        sectionRequest.payload,
                        {
                            forceFormData: sectionRequest.forceFormData,
                        },
                    );

                    if (!result.ok) {
                        handleError(
                            result.errors ?? {
                                section_save:
                                    'Unable to save one or more manifest sections.',
                            },
                        );

                        return;
                    }
                }

                toast.success('Manifest updated successfully.');
            } finally {
                setIsSectionSaving(false);
            }

            return;
        }

        submitWithFullPayload();
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
        const memberErrors = new Map<
            number,
            Array<{ path: string; message: string }>
        >();

        Object.entries(formErrors).forEach(([path, message]) => {
            if (!message) {
                return;
            }

            const memberMatch = path.match(/^members\.(\d+)\./);

            if (!memberMatch) {
                globalErrors.push({ path, message });

                return;
            }

            const memberIndex = Number(memberMatch[1]);
            if (!memberErrors.has(memberIndex)) {
                memberErrors.set(memberIndex, []);
            }

            memberErrors.get(memberIndex)?.push({ path, message });
        });

        return {
            globalErrors,
            memberGroups: [...memberErrors.entries()].map(
                ([memberIndex, issues]) => ({
                    memberIndex,
                    memberName:
                        ((data.manifest_members ?? []) as MemberWithUI[])[
                            memberIndex
                        ]?.name_as_per_passport || `Member ${memberIndex + 1}`,
                    issues,
                }),
            ),
        };
    }, [formErrors, data.manifest_members]);

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
        const activeMemberCount = (
            ((data.manifest_members ?? []) as MemberWithUI[]).filter(
                (member) => !isCancelledMember(member),
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

        const manifestMemberInformationStatus = hasErrorsForPrefix(['members'])
            ? 'error'
            : activeMemberCount > 0
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
        data.manifest_members,
        hotelAccommodations.length,
        isCancelledMember,
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

    const nonCancelledMembers = useMemo(() => {
        return ((data.manifest_members ?? []) as MemberWithUI[]).filter(
            (member) => !isCancelledMember(member),
        );
    }, [data.manifest_members, isCancelledMember]);

    const nonCancelledNonOfficialMembers = useMemo(() => {
        return nonCancelledMembers.filter(
            (member) => !member.package_official_id,
        );
    }, [nonCancelledMembers]);

    const nonCancelledNonOfficialMembersForArabicExport = useMemo(() => {
        return nonCancelledNonOfficialMembers.map((member) => {
            const memberName = String(member.name_as_per_passport ?? '').trim();
            const arabicName = String(member.arabic_name ?? '').trim();

            if (arabicName.length > 0) {
                return {
                    ...member,
                    arabic_name: normalizeArabicNameInput(arabicName),
                };
            }

            if (memberName.length > 0) {
                return {
                    ...member,
                    arabic_name: convertNameToArabic(memberName),
                };
            }

            return {
                ...member,
                arabic_name: '',
            };
        });
    }, [nonCancelledNonOfficialMembers]);

    useEffect(() => {
        const members = (data.manifest_members ?? []) as MemberWithUI[];
        const previousMap = previousNameByIdentityRef.current;
        let hasChanges = false;

        const nextMembers = members.map((member, index) => {
            if (member.package_official_id) {
                return member;
            }

            const identity = memberIdentityKey(member, index);
            const currentName = String(
                member.name_as_per_passport ?? '',
            ).trim();
            const previousName = previousMap[identity] ?? null;
            previousMap[identity] = currentName;

            if (currentName === '') {
                return member;
            }

            const currentArabicName = String(member.arabic_name ?? '');
            const arabicNameContainsLatin = /[a-z]/i.test(currentArabicName);

            if (arabicNameContainsLatin) {
                const normalizedArabicName =
                    normalizeArabicNameInput(currentArabicName);

                if (normalizedArabicName !== currentArabicName) {
                    hasChanges = true;

                    return {
                        ...member,
                        arabic_name: normalizedArabicName,
                    };
                }
            }

            const shouldAutoFill =
                !member.arabic_name ||
                member.arabic_name.trim() === '' ||
                previousName !== null;

            if (!shouldAutoFill || previousName === currentName) {
                return member;
            }

            const autoArabicName = convertNameToArabic(currentName);

            if (autoArabicName === member.arabic_name) {
                return member;
            }

            hasChanges = true;

            return {
                ...member,
                arabic_name: autoArabicName,
            };
        });

        if (hasChanges) {
            setFormData('manifest_members', nextMembers);
        }
    }, [data.manifest_members, setFormData]);

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

    const moveMemberToHolding = async (member: MemberWithUI) => {
        const confirmationMemberId = member.customer_confirmation_member_id;
        const manifestMemberId = member.id;

        if (!confirmationMemberId || !manifestMemberId || !data.id) {
            return;
        }

        try {
            const csrfToken = document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content');

            const response = await fetch(
                `/manifests/${data.id}/members/${manifestMemberId}/move-holding`,
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
                throw new Error('Failed to move member to holding.');
            }

            const nextMembers = (
                (data.manifest_members ?? []) as MemberWithUI[]
            ).map((row) => {
                if (
                    row.customer_confirmation_member_id ===
                    member.customer_confirmation_member_id
                ) {
                    return {
                        ...row,
                        status: 'cancelled' as const,
                    };
                }

                return row;
            });

            updateFromMembers(nextMembers);
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

    const selectedMemberCustomerFormData = useMemo(() => {
        if (!selectedMemberForDetail) {
            return null;
        }

        return mapMemberToCustomerSchema(selectedMemberForDetail);
    }, [selectedMemberForDetail]);

    const memberDetailSharingPlanOptions = useMemo(() => {
        return [
            { value: 'single', label: 'Single' },
            { value: 'double', label: 'Double' },
            { value: 'triple', label: 'Triple' },
            { value: 'quad', label: 'Quad' },
            { value: 'child_with_bed', label: 'Child (7-11 years)' },
            { value: 'child_no_bed', label: 'Child (2-6 years)' },
            { value: 'infant', label: 'Infant (0-2 years)' },
        ];
    }, []);

    const viewOnlyCustomerUpdate = useCallback(
        (
            field: keyof CustomerSchema,
            value: string | boolean | File | null,
        ) => {
            void field;
            void value;
        },
        [],
    );

    return (
        <div className="mx-auto w-full">
            <Dialog
                open={isMemberDetailDialogOpen}
                onOpenChange={setIsMemberDetailDialogOpen}
            >
                <DialogContent className="flex max-h-[95vh] max-w-[95vw] min-w-[95vw] flex-col overflow-y-auto">
                    <DialogHeader className="gap-0">
                        <DialogTitle className="text-xl">
                            View Customer Detail
                        </DialogTitle>
                        <DialogDescription>
                            Customer and customer confirmation member detail.
                        </DialogDescription>
                    </DialogHeader>

                    {selectedMemberCustomerFormData && (
                        <div className="space-y-4 pb-2">
                            <Card>
                                <CardHeader className="gap-0">
                                    <CardTitle className="text-xl">
                                        Customer Confirmation Information
                                    </CardTitle>
                                    <CardDescription>
                                        Payment status, pricing plan, and
                                        relationship data.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <ConfirmedCustomerFormFields
                                        customer={
                                            selectedMemberCustomerFormData
                                        }
                                        isView
                                        processing={false}
                                        sharingPlanSelectOptions={
                                            memberDetailSharingPlanOptions
                                        }
                                        getError={() => undefined}
                                        onUpdateCustomer={
                                            viewOnlyCustomerUpdate
                                        }
                                    />
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="gap-0">
                                    <CardTitle className="text-xl">
                                        Customer Information
                                    </CardTitle>
                                    <CardDescription>
                                        Personal and document details.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <CustomerFormFields
                                        customer={
                                            selectedMemberCustomerFormData
                                        }
                                        isView
                                        processing={false}
                                        getError={() => undefined}
                                        onUpdateCustomer={
                                            viewOnlyCustomerUpdate
                                        }
                                    />
                                </CardContent>
                            </Card>

                            <div className="flex justify-end">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        setIsMemberDetailDialogOpen(false)
                                    }
                                >
                                    Close
                                </Button>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

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
                                {groupedErrorSummary.memberGroups.map(
                                    ({ memberIndex, memberName, issues }) => (
                                        <div
                                            key={memberIndex}
                                            className="space-y-1"
                                        >
                                            <p className="font-medium">
                                                {memberName}
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
                            errors={formErrors}
                        />
                    </FormSection>

                    <FormSection
                        value="manifest_member_information"
                        title="Manifest Member Information"
                        description="Auto-calculated summary from active members."
                        status={sectionStatuses.manifest_member_information}
                        required={false}
                    >
                        <ManifestMemberInformationCard
                            members={
                                (data.manifest_members ?? []) as MemberWithUI[]
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
                                    value="document-train-tickets"
                                    className="text-lg"
                                >
                                    Train Tickets
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
                            mode="members"
                            rows={
                                (data.manifest_members ?? []) as MemberWithUI[]
                            }
                            disabled={isView}
                            allowReorder
                            errorPrefix="members"
                            errors={formErrors}
                            roomAssignmentOptions={roomTabs.map((tab) => ({
                                key: tab.key,
                                label: tab.label,
                            }))}
                            getMemberAssignedLocations={
                                getMemberAssignedLocations
                            }
                            onRoomAssignmentChange={handleRoomAssignmentChange}
                            onMoveToHolding={moveMemberToHolding}
                            onViewMember={(member) => {
                                setSelectedMemberForDetail(member);
                                setIsMemberDetailDialogOpen(true);
                            }}
                            onRowsChange={updateFromMembers}
                        />
                    </TabsContent>

                    {roomTabs.map((tab) => {
                        const roomRows =
                            ((roomListsState ?? {})[tab.key] as
                                | MemberWithUI[]
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
                                    onRowsChange={(rows: MemberWithUI[]) => {
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

                                        const roomRowsByTab: Record<
                                            string,
                                            MemberWithUI[]
                                        > = {
                                            ...((roomListsState as Record<
                                                string,
                                                MemberWithUI[]
                                            >) ?? {}),
                                            [tab.key]: normalizedRows,
                                        };

                                        const updatedFromRoomMap = new Map(
                                            normalizedRows.map(
                                                (row, rowIndex) => [
                                                    memberIdentityKey(
                                                        row,
                                                        rowIndex,
                                                    ),
                                                    row,
                                                ],
                                            ),
                                        );

                                        const nextMembers = (
                                            (data.manifest_members ??
                                                []) as MemberWithUI[]
                                        ).map((member, memberIndex) => {
                                            const updated =
                                                updatedFromRoomMap.get(
                                                    memberIdentityKey(
                                                        member,
                                                        memberIndex,
                                                    ),
                                                );

                                            if (!updated) {
                                                return member;
                                            }

                                            return {
                                                ...member,
                                                name_as_per_passport:
                                                    updated.name_as_per_passport ??
                                                    member.name_as_per_passport,
                                                passport_number:
                                                    updated.passport_number ??
                                                    member.passport_number,
                                                relationship:
                                                    updated.relationship ??
                                                    member.relationship,
                                                group_relationship:
                                                    updated.group_relationship ??
                                                    member.group_relationship,
                                                sharing_plan:
                                                    updated.sharing_plan ??
                                                    member.sharing_plan,
                                                nationality:
                                                    updated.nationality ??
                                                    member.nationality,
                                                gender:
                                                    updated.gender ??
                                                    member.gender,
                                                date_of_birth:
                                                    updated.date_of_birth ??
                                                    member.date_of_birth,
                                                date_of_issue:
                                                    updated.date_of_issue ??
                                                    member.date_of_issue,
                                                date_of_expiry:
                                                    updated.date_of_expiry ??
                                                    member.date_of_expiry,
                                                issue_place:
                                                    updated.issue_place ??
                                                    member.issue_place,
                                                birth_place:
                                                    updated.birth_place ??
                                                    member.birth_place,
                                                contact_no:
                                                    updated.contact_no ??
                                                    member.contact_no,
                                                package_price:
                                                    updated.package_price ??
                                                    member.package_price,
                                                status:
                                                    updated.status ??
                                                    member.status,
                                                age:
                                                    calculateAgeFromDob(
                                                        updated.date_of_birth ??
                                                            member.date_of_birth,
                                                    ) ?? member.age,
                                            };
                                        });

                                        const memberMap = new Map(
                                            nextMembers.map(
                                                (member, memberIndex) => [
                                                    memberIdentityKey(
                                                        member,
                                                        memberIndex,
                                                    ),
                                                    member,
                                                ],
                                            ),
                                        );

                                        const nextRoomLists =
                                            Object.fromEntries(
                                                Object.entries(
                                                    roomRowsByTab,
                                                ).map(([roomKey, roomRows]) => [
                                                    roomKey,
                                                    roomRows.map(
                                                        (
                                                            roomRow,
                                                            roomIndex,
                                                        ) => {
                                                            const memberUpdate =
                                                                memberMap.get(
                                                                    memberIdentityKey(
                                                                        roomRow,
                                                                        roomIndex,
                                                                    ),
                                                                );

                                                            if (!memberUpdate) {
                                                                return roomRow;
                                                            }

                                                            return {
                                                                ...roomRow,
                                                                name_as_per_passport:
                                                                    memberUpdate.name_as_per_passport ??
                                                                    roomRow.name_as_per_passport,
                                                                passport_number:
                                                                    memberUpdate.passport_number ??
                                                                    roomRow.passport_number,
                                                                relationship:
                                                                    memberUpdate.relationship ??
                                                                    roomRow.relationship,
                                                                group_relationship:
                                                                    memberUpdate.group_relationship ??
                                                                    roomRow.group_relationship,
                                                                sharing_plan:
                                                                    memberUpdate.sharing_plan ??
                                                                    roomRow.sharing_plan,
                                                                nationality:
                                                                    memberUpdate.nationality ??
                                                                    roomRow.nationality,
                                                                gender:
                                                                    memberUpdate.gender ??
                                                                    roomRow.gender,
                                                                date_of_birth:
                                                                    memberUpdate.date_of_birth ??
                                                                    roomRow.date_of_birth,
                                                                date_of_issue:
                                                                    memberUpdate.date_of_issue ??
                                                                    roomRow.date_of_issue,
                                                                date_of_expiry:
                                                                    memberUpdate.date_of_expiry ??
                                                                    roomRow.date_of_expiry,
                                                                issue_place:
                                                                    memberUpdate.issue_place ??
                                                                    roomRow.issue_place,
                                                                birth_place:
                                                                    memberUpdate.birth_place ??
                                                                    roomRow.birth_place,
                                                                contact_no:
                                                                    memberUpdate.contact_no ??
                                                                    roomRow.contact_no,
                                                                package_price:
                                                                    memberUpdate.package_price ??
                                                                    roomRow.package_price,
                                                                status:
                                                                    memberUpdate.status ??
                                                                    roomRow.status,
                                                                age:
                                                                    calculateAgeFromDob(
                                                                        memberUpdate.date_of_birth ??
                                                                            roomRow.date_of_birth,
                                                                    ) ??
                                                                    roomRow.age,
                                                            };
                                                        },
                                                    ),
                                                ]),
                                            );

                                        setFormData(
                                            'manifest_members',
                                            nextMembers,
                                        );
                                        setRoomListsState(nextRoomLists);
                                    }}
                                />

                                {!isView && (
                                    <div className="flex justify-end">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                >
                                                    Reset This Room List
                                                    Structure
                                                    <ChevronDown className="ml-2 h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem
                                                    onClick={() =>
                                                        resetRoomListTab(
                                                            tab.key,
                                                            'main',
                                                        )
                                                    }
                                                >
                                                    Copy From Main Tab
                                                </DropdownMenuItem>
                                                {roomTabs
                                                    .filter(
                                                        (roomTab) =>
                                                            roomTab.key !==
                                                            tab.key,
                                                    )
                                                    .map((roomTab) => (
                                                        <DropdownMenuItem
                                                            key={`reset-source-${tab.key}-${roomTab.key}`}
                                                            onClick={() =>
                                                                resetRoomListTab(
                                                                    tab.key,
                                                                    roomTab.key,
                                                                )
                                                            }
                                                        >
                                                            Copy From Room List:{' '}
                                                            {roomTab.label}
                                                        </DropdownMenuItem>
                                                    ))}
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                )}
                            </TabsContent>
                        );
                    })}

                    {officialCheckTabs.map((tab) => {
                        const sourceRoomRows =
                            ((roomListsState ?? {})[
                                tab.sourceRoomKey
                            ] as MemberWithUI[]) ?? [];

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

                                        setRoomListsState({
                                            ...(roomListsState ?? {}),
                                            [tab.sourceRoomKey]: normalizedRows,
                                        });
                                    }}
                                />
                            </TabsContent>
                        );
                    })}

                    <TabsContent value="airline" className="space-y-4">
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
                                        `/manifests/${data.id}/airline-names-pdf`,
                                        {
                                            members:
                                                nonCancelledNonOfficialMembers,
                                            manifest_number:
                                                data.manifest_number,
                                            package_name:
                                                selectedPackage?.label,
                                            departure_date:
                                                selectedPackage?.departure_date,
                                            return_date:
                                                selectedPackage?.return_date,
                                        },
                                    );
                                }}
                            >
                                <Download className="mr-2 h-4 w-4" />
                                Export PDF
                            </Button>
                        </div>

                        <ManifestDatatable
                            mode="airline"
                            rows={(
                                (airlineRowsState ?? []) as MemberWithUI[]
                            ).filter((row) => !isCancelledMember(row))}
                            disabled={isView}
                            allowReorder
                            errorPrefix="airlineList"
                            errors={formErrors}
                            roomAssignmentOptions={roomTabs.map((tab) => ({
                                key: tab.key,
                                label: tab.label,
                            }))}
                            onRowsChange={(rows: MemberWithUI[]) => {
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
                                        memberIdentityKey(row, index),
                                        row,
                                    ]),
                                );

                                const nextMembers = (
                                    (data.manifest_members ??
                                        []) as MemberWithUI[]
                                ).map((member, memberIndex) => {
                                    const updated = airlineMap.get(
                                        memberIdentityKey(member, memberIndex),
                                    );

                                    if (!updated) {
                                        return member;
                                    }

                                    return {
                                        ...member,
                                        name_as_per_passport:
                                            updated.name_as_per_passport ??
                                            member.name_as_per_passport,
                                        passport_number:
                                            updated.passport_number ??
                                            member.passport_number,
                                        relationship:
                                            updated.relationship ??
                                            member.relationship,
                                        group_relationship:
                                            updated.group_relationship ??
                                            member.group_relationship,
                                        sharing_plan:
                                            updated.sharing_plan ??
                                            member.sharing_plan,
                                        nationality:
                                            updated.nationality ??
                                            member.nationality,
                                        gender: updated.gender ?? member.gender,
                                        date_of_birth:
                                            updated.date_of_birth ??
                                            member.date_of_birth,
                                        date_of_issue:
                                            updated.date_of_issue ??
                                            member.date_of_issue,
                                        date_of_expiry:
                                            updated.date_of_expiry ??
                                            member.date_of_expiry,
                                        issue_place:
                                            updated.issue_place ??
                                            member.issue_place,
                                        birth_place:
                                            updated.birth_place ??
                                            member.birth_place,
                                        contact_no:
                                            updated.contact_no ??
                                            member.contact_no,
                                        package_price:
                                            updated.package_price ??
                                            member.package_price,
                                        age:
                                            calculateAgeFromDob(
                                                updated.date_of_birth ??
                                                    member.date_of_birth,
                                            ) ?? member.age,
                                    };
                                });

                                const memberMap = new Map(
                                    nextMembers.map((member, index) => [
                                        memberIdentityKey(member, index),
                                        member,
                                    ]),
                                );

                                const nextRoomLists = Object.fromEntries(
                                    Object.entries(roomListsState ?? {}).map(
                                        ([key, roomRows]) => [
                                            key,
                                            (roomRows as MemberWithUI[]).map(
                                                (roomRow, roomIndex) => {
                                                    const memberUpdate =
                                                        memberMap.get(
                                                            memberIdentityKey(
                                                                roomRow,
                                                                roomIndex,
                                                            ),
                                                        );

                                                    if (!memberUpdate) {
                                                        return roomRow;
                                                    }

                                                    return {
                                                        ...roomRow,
                                                        name_as_per_passport:
                                                            memberUpdate.name_as_per_passport ??
                                                            roomRow.name_as_per_passport,
                                                        passport_number:
                                                            memberUpdate.passport_number ??
                                                            roomRow.passport_number,
                                                        relationship:
                                                            memberUpdate.relationship ??
                                                            roomRow.relationship,
                                                        group_relationship:
                                                            memberUpdate.group_relationship ??
                                                            roomRow.group_relationship,
                                                        sharing_plan:
                                                            memberUpdate.sharing_plan ??
                                                            roomRow.sharing_plan,
                                                        nationality:
                                                            memberUpdate.nationality ??
                                                            roomRow.nationality,
                                                        gender:
                                                            memberUpdate.gender ??
                                                            roomRow.gender,
                                                        date_of_birth:
                                                            memberUpdate.date_of_birth ??
                                                            roomRow.date_of_birth,
                                                        date_of_issue:
                                                            memberUpdate.date_of_issue ??
                                                            roomRow.date_of_issue,
                                                        date_of_expiry:
                                                            memberUpdate.date_of_expiry ??
                                                            roomRow.date_of_expiry,
                                                        issue_place:
                                                            memberUpdate.issue_place ??
                                                            roomRow.issue_place,
                                                        birth_place:
                                                            memberUpdate.birth_place ??
                                                            roomRow.birth_place,
                                                        contact_no:
                                                            memberUpdate.contact_no ??
                                                            roomRow.contact_no,
                                                        package_price:
                                                            memberUpdate.package_price ??
                                                            roomRow.package_price,
                                                        age:
                                                            calculateAgeFromDob(
                                                                memberUpdate.date_of_birth ??
                                                                    roomRow.date_of_birth,
                                                            ) ?? roomRow.age,
                                                    };
                                                },
                                            ),
                                        ],
                                    ),
                                );

                                setFormData('manifest_members', nextMembers);
                                setAirlineRowsState(normalizedAirline);
                                setRoomListsState(nextRoomLists);
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
                                            members:
                                                nonCancelledNonOfficialMembers,
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
                                nonCancelledNonOfficialMembers as MemberWithUI[]
                            }
                            disabled={isView}
                            allowReorder={false}
                            errors={formErrors}
                            onRowsChange={(rows: MemberWithUI[]) => {
                                const checklistMap = new Map(
                                    rows.map((row, index) => [
                                        memberIdentityKey(row, index),
                                        row,
                                    ]),
                                );

                                const nextMembers = (
                                    (data.manifest_members ??
                                        []) as MemberWithUI[]
                                ).map((member, memberIndex) => {
                                    const updatedChecklist = checklistMap.get(
                                        memberIdentityKey(member, memberIndex),
                                    );

                                    if (!updatedChecklist) {
                                        return member;
                                    }

                                    return {
                                        ...member,
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

                                setFormData('manifest_members', nextMembers);
                            }}
                        />
                    </TabsContent>

                    {MANIFEST_DOCUMENT_TABS.map((tab) => {
                        const isMemberMirroredDocumentTab =
                            tab.key === 'passport' || tab.key === 'photo';
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
                                                {isMemberMirroredDocumentTab
                                                    ? `Auto-synced from member ${tab.label.toLowerCase()} files in customer confirmation.`
                                                    : tab.hint}
                                            </p>
                                        </div>
                                        {!isView &&
                                            !isMemberMirroredDocumentTab && (
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
                                                        !isMemberMirroredDocumentTab &&
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
                                                        showRemoveButton={
                                                            !isMemberMirroredDocumentTab
                                                        }
                                                        fileNameValue={
                                                            row.file_name ??
                                                            null
                                                        }
                                                        isView={isView}
                                                        disabled={
                                                            isView ||
                                                            isMemberMirroredDocumentTab
                                                        }
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
                                                    members:
                                                        nonCancelledNonOfficialMembersForArabicExport,
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
                                    {nonCancelledNonOfficialMembers.map(
                                        (member, index) => (
                                            <tr
                                                key={`${memberIdentityKey(member, index)}-arabic`}
                                                className="border-t"
                                            >
                                                <td className="px-4 py-3 align-top">
                                                    {index + 1}
                                                </td>
                                                <td className="px-4 py-3 align-top">
                                                    <ProperInput
                                                        value={
                                                            member.name_as_per_passport ??
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
                                                            member.arabic_name ??
                                                            ''
                                                        }
                                                        onCommit={(value) => {
                                                            updateMemberArabicName(
                                                                member,
                                                                value,
                                                            );
                                                        }}
                                                        disabled={isView}
                                                        inputProps={{
                                                            dir: 'rtl',
                                                            onInput: (
                                                                event,
                                                            ) => {
                                                                updateMemberArabicName(
                                                                    member,
                                                                    event
                                                                        .currentTarget
                                                                        .value,
                                                                );
                                                            },
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
                                    {nonCancelledNonOfficialMembers.map(
                                        (member, index) => {
                                            const sourceRows =
                                                member.receipt_documents ?? [];
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
                                                    key={`${memberIdentityKey(member, index)}-receipt`}
                                                    className="border-t"
                                                >
                                                    <td className="px-4 py-3 align-top">
                                                        {index + 1}
                                                    </td>
                                                    <td className="px-4 py-3 align-top">
                                                        <input
                                                            type="text"
                                                            value={
                                                                member.name_as_per_passport ??
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
                                                                            key={`${memberIdentityKey(member, index)}-receipt-doc-${row.id ?? visibleIndex}`}
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
                                                                                                updateMemberReceiptDocuments(
                                                                                                    member,
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
                                                                                hint="Upload receipt evidence for this member."
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
                                                                                    updateMemberReceiptDocuments(
                                                                                        member,
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
                                                                                    updateMemberReceiptDocuments(
                                                                                        member,
                                                                                        nextRows,
                                                                                    );
                                                                                }}
                                                                                onClear={() => {
                                                                                    updateMemberReceiptDocuments(
                                                                                        member,
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

                                                                        updateMemberReceiptDocuments(
                                                                            member,
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
                            <Button
                                type="submit"
                                disabled={processing || isSectionSaving}
                            >
                                {(processing || isSectionSaving) && (
                                    <Loader2 className="mr-1 h-4 w-4 animate-spin" />
                                )}
                                {isSectionSaving
                                    ? 'Updating Manifest...'
                                    : 'Update Manifest'}
                            </Button>
                        </>
                    )}
                </div>
            </form>
        </div>
    );
}
