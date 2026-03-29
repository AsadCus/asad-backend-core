import { DatePickerField } from '@/components/date-picker';
import { ImagePreviewDialog } from '@/components/image-preview-dialog';
import { ProperInput } from '@/components/proper-input';
import { ProperInputSelect } from '@/components/proper-input-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuSub,
    ContextMenuSubContent,
    ContextMenuSubTrigger,
    ContextMenuTrigger,
} from '@/components/ui/context-menu';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn, formatCurrency } from '@/lib/utils';
import { type OptionType } from '@/types';
import {
    closestCenter,
    DndContext,
    PointerSensor,
    useSensor,
    useSensors,
    type DragEndEvent,
} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import {
    ChevronDown,
    ChevronRight,
    EllipsisVertical,
    ExternalLink,
    FileText,
    GripVertical,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import {
    confirmationMemberStatusColors,
    confirmationMemberStatusLabels,
    packageCategoryOptions,
} from '../../customer/schema';
import { type MemberWithUI } from '../types';

type TableMode =
    | 'members'
    | 'room'
    | 'room_check'
    | 'airline'
    | 'course_collection';

type SelectOption = {
    value: string;
    label: string;
};

const ROOM_TYPE_OPTIONS: SelectOption[] = [
    { value: 'quad', label: 'Quad' },
    { value: 'triple', label: 'Triple' },
    { value: 'twin', label: 'Twin' },
    { value: 'double', label: 'Double' },
    { value: 'single', label: 'Single' },
];

const SHARING_PLAN_OPTIONS: SelectOption[] = [
    { value: 'quad', label: 'Quad' },
    { value: 'triple', label: 'Triple' },
    { value: 'double', label: 'Double' },
    { value: 'single', label: 'Single' },
];

const BED_TYPE_OPTIONS: SelectOption[] = [
    { value: 'single', label: 'Single' },
    { value: 'king', label: 'King' },
    { value: 'queen', label: 'Queen' },
];

const MEAL_OPTIONS: SelectOption[] = [
    { value: 'Breakfast Only', label: 'Breakfast Only' },
    { value: 'Half Board', label: 'Half Board' },
    { value: 'Full Board', label: 'Full Board' },
];

const GENDER_OPTIONS: SelectOption[] = [
    { value: 'male', label: 'Male' },
    { value: 'female', label: 'Female' },
];

const SHARING_PLAN_CAPACITY: Record<string, number> = {
    quad: 4,
    triple: 3,
    double: 2,
    single: 1,
};

const MANIFEST_DATATABLE_EXPANDED_STORAGE_PREFIX =
    'manifest-datatable-expanded';

const PACKAGE_CATEGORY_LABELS = Object.fromEntries(
    packageCategoryOptions.map((option) => [option.value, option.label]),
) as Record<string, string>;

interface GroupMember {
    member: MemberWithUI;
    flatIndex: number;
}

interface GroupData {
    key: string;
    members: GroupMember[];
}

interface VisibleItem {
    dndId: string;
    isGroupHeader: boolean;
    groupKey: string;
    groupIndex: number;
    member?: MemberWithUI;
    flatIndex: number;
    memberCount: number;
}

interface ManifestDatatableProps {
    mode: TableMode;
    rows: MemberWithUI[];
    currentRoomLocationKey?: string;
    disabled?: boolean;
    allowReorder?: boolean;
    errorPrefix?: string;
    roomGroupErrorPrefix?: string;
    roomGroupStartIndex?: number;
    errors?: Record<string, string>;
    roomAssignmentOptions?: Array<{ key: string; label: string }>;
    getMemberAssignedLocations?: (member: MemberWithUI) => string[];
    onRoomAssignmentChange?: (
        member: MemberWithUI,
        action: 'assign' | 'unassign',
        scope: string,
    ) => void;
    onRowsChange: (rows: MemberWithUI[]) => void;
    onMoveToHolding?: (member: MemberWithUI) => void;
}

function normalizeRoomLocationKey(value: string): string {
    return value.trim().toLowerCase();
}

function normalizeDocumentPath(path?: string | null): string {
    const trimmed = String(path ?? '').trim();

    if (trimmed.length === 0) {
        return '';
    }

    if (/^https?:\/\//i.test(trimmed)) {
        return trimmed;
    }

    const normalized = trimmed.replace(/^\/+/, '').replace(/^storage\//i, '');

    return `/storage/${normalized}`;
}

function isImageDocument(pathOrName?: string | null): boolean {
    const value = String(pathOrName ?? '').toLowerCase();

    return /\.(jpg|jpeg|png|gif|webp)(\?|$)/i.test(value);
}

function documentLabel(path?: string | null, fileName?: string | null): string {
    const preferredName = String(fileName ?? '').trim();

    if (preferredName.length > 0) {
        return preferredName;
    }

    const normalizedPath = String(path ?? '').trim();

    if (normalizedPath.length === 0) {
        return 'Document';
    }

    const fileNameFromPath = normalizedPath.split('/').pop() ?? normalizedPath;

    return decodeURIComponent(fileNameFromPath);
}

function getCapacityForSharingPlan(sharingPlan?: string): number {
    if (!sharingPlan) {
        return Infinity;
    }

    return SHARING_PLAN_CAPACITY[sharingPlan.toLowerCase()] ?? Infinity;
}

function getCapacityForRoomType(roomType?: string): number {
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

    if (normalizedRoomType === 'single') {
        return 1;
    }

    return Infinity;
}

function getBedTypeFromRoomType(roomType?: string): string {
    const value = String(roomType ?? '').toLowerCase();

    if (value === 'double' || value === 'quad') {
        return 'king';
    }

    if (value === 'single' || value === 'twin' || value === 'triple') {
        return 'single';
    }

    return '';
}

function getBedsCountByRoomTypeAndBedType(
    roomType?: string | null,
    bedType?: string | null,
): number {
    const normalizedRoomType = String(roomType ?? '').toLowerCase();
    const normalizedBedType = String(bedType ?? '').toLowerCase();

    if (normalizedRoomType === 'single') {
        return 1;
    }

    if (normalizedRoomType === 'twin' && normalizedBedType === 'single') {
        return 2;
    }

    if (normalizedRoomType === 'triple' && normalizedBedType === 'single') {
        return 3;
    }

    if (normalizedRoomType === 'quad' && normalizedBedType === 'single') {
        return 4;
    }

    if (normalizedRoomType === 'double') {
        if (normalizedBedType === 'single') {
            return 2;
        }

        if (normalizedBedType === 'king' || normalizedBedType === 'queen') {
            return 1;
        }
    }

    return 1;
}

function isOfficialMember(member: MemberWithUI): boolean {
    return !!member.package_official_id;
}

function shouldHideRoomMember(): boolean {
    return false;
}

function getAgeFromDate(dateValue?: string | null): string {
    if (!dateValue) {
        return '';
    }

    const parsedDate = new Date(dateValue);
    if (Number.isNaN(parsedDate.getTime())) {
        return '';
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

    return age >= 0 ? String(age) : '';
}

function toMemberId(member: MemberWithUI, flatIndex: number): string {
    if (member.row_key && member.row_key.trim().length > 0) {
        return member.row_key;
    }

    return String(
        member.customer_confirmation_member_id ??
            member.customer_id ??
            member.id ??
            `idx-${flatIndex}`,
    );
}

function toMemberDndId(member: MemberWithUI, flatIndex: number): string {
    return `m-${toMemberId(member, flatIndex)}-${flatIndex}`;
}

function getMemberGroupKey(member: MemberWithUI, index: number): string {
    if (member.sharing_group_key && member.sharing_group_key.trim()) {
        return member.sharing_group_key;
    }

    return `solo-${member.customer_confirmation_member_id ?? member.customer_id ?? member.package_official_id ?? member.id ?? index}`;
}

function SortableRow({
    id,
    disabled,
    children,
}: {
    id: string;
    disabled: boolean;
    children: (props: ReturnType<typeof useSortable>) => React.ReactNode;
}) {
    const sortable = useSortable({ id, disabled });

    return children(sortable);
}

function SelectCell({
    value,
    placeholder,
    disabled,
    onValueChange,
    options,
}: {
    value: string;
    placeholder: string;
    disabled: boolean;
    onValueChange: (value: string) => void;
    options: SelectOption[];
}) {
    const normalizedOptions: OptionType[] = options.map((option) => ({
        value: option.value,
        label: option.label,
    }));

    return (
        <ProperInputSelect
            options={normalizedOptions}
            value={value}
            onValueChange={(nextValue) => onValueChange(String(nextValue))}
            placeholder={placeholder}
            disabled={disabled}
            size="default"
        />
    );
}

export default function ManifestDatatable({
    mode,
    rows,
    currentRoomLocationKey,
    disabled = false,
    allowReorder = false,
    errorPrefix,
    roomGroupErrorPrefix,
    roomGroupStartIndex = 0,
    errors = {},
    roomAssignmentOptions = [],
    getMemberAssignedLocations,
    onRoomAssignmentChange,
    onRowsChange,
    onMoveToHolding,
}: ManifestDatatableProps) {
    const isGrouped =
        mode === 'members' ||
        mode === 'room' ||
        mode === 'room_check' ||
        mode === 'airline';

    const getErrorPath = useCallback(
        (flatIndex: number, field: keyof MemberWithUI | string): string => {
            return `${errorPrefix}.${flatIndex}.${String(field)}`;
        },
        [errorPrefix],
    );

    const renderCellError = useCallback(
        (flatIndex: number, field: keyof MemberWithUI | string) => {
            if (!errorPrefix) {
                return null;
            }

            const directPath = getErrorPath(flatIndex, field);
            const directMessage = errors[directPath];

            if (directMessage) {
                return (
                    <p id={directPath} className="mt-1 text-xs text-red-500">
                        {directMessage}
                    </p>
                );
            }

            if (field === 'relationship') {
                const fallbackPath = getErrorPath(flatIndex, 'role');
                const fallbackMessage = errors[fallbackPath];

                if (fallbackMessage) {
                    return (
                        <p
                            id={fallbackPath}
                            className="mt-1 text-xs text-red-500"
                        >
                            {fallbackMessage}
                        </p>
                    );
                }
            }

            return null;
        },
        [errorPrefix, errors, getErrorPath],
    );

    const groups = useMemo<GroupData[]>(() => {
        if (!isGrouped) {
            return [];
        }

        const map = new Map<string, GroupMember[]>();

        rows.forEach((row, flatIndex) => {
            const key = getMemberGroupKey(row, flatIndex);

            if (!map.has(key)) {
                map.set(key, []);
            }

            map.get(key)?.push({ member: row, flatIndex });
        });

        return Array.from(map.entries()).map(([key, members]) => ({
            key,
            members,
        }));
    }, [rows, isGrouped]);

    const expandedStorageKey = useMemo(() => {
        const pathname =
            typeof window !== 'undefined' ? window.location.pathname : 'ssr';
        const locationKey = normalizeRoomLocationKey(
            String(currentRoomLocationKey ?? 'none'),
        );

        return `${MANIFEST_DATATABLE_EXPANDED_STORAGE_PREFIX}::${pathname}::${mode}::${locationKey}`;
    }, [currentRoomLocationKey, mode]);

    const [expanded, setExpanded] = useState<Record<string, boolean>>(() => {
        if (typeof window === 'undefined') {
            return {};
        }

        try {
            const raw = window.localStorage.getItem(expandedStorageKey);

            if (!raw) {
                return {};
            }

            const parsed = JSON.parse(raw);

            if (!parsed || typeof parsed !== 'object') {
                return {};
            }

            return parsed as Record<string, boolean>;
        } catch {
            return {};
        }
    });

    const expandAllGroups = useCallback(() => {
        setExpanded(
            Object.fromEntries(groups.map((group) => [group.key, true])),
        );
    }, [groups]);

    const collapseAllGroups = useCallback(() => {
        setExpanded(
            Object.fromEntries(groups.map((group) => [group.key, false])),
        );
    }, [groups]);

    useEffect(() => {
        setExpanded((prev) => {
            const next = { ...prev };
            let changed = false;

            groups.forEach((group) => {
                if (!(group.key in next)) {
                    next[group.key] = true;
                    changed = true;
                }
            });

            return changed ? next : prev;
        });
    }, [groups]);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        try {
            window.localStorage.setItem(
                expandedStorageKey,
                JSON.stringify(expanded),
            );
        } catch {
            // Ignore storage write errors and keep table interaction functional.
        }
    }, [expanded, expandedStorageKey]);

    const toggleExpanded = useCallback((key: string) => {
        setExpanded((prev) => ({
            ...prev,
            [key]: prev[key] === false,
        }));
    }, []);

    const visibleItems = useMemo<VisibleItem[]>(() => {
        if (!isGrouped) {
            return [];
        }

        const items: VisibleItem[] = [];
        let visibleGroupIndex = 0;

        groups.forEach((group) => {
            const visibleMembers = group.members.filter(
                () => !shouldHideRoomMember(),
            );

            if (visibleMembers.length === 0) {
                return;
            }

            items.push({
                dndId: `g-${group.key}`,
                isGroupHeader: true,
                groupKey: group.key,
                groupIndex: visibleGroupIndex,
                flatIndex: -1,
                memberCount: visibleMembers.length,
            });

            if (expanded[group.key] !== false) {
                visibleMembers.forEach((member) => {
                    items.push({
                        dndId: toMemberDndId(member.member, member.flatIndex),
                        isGroupHeader: false,
                        groupKey: group.key,
                        groupIndex: visibleGroupIndex,
                        member: member.member,
                        flatIndex: member.flatIndex,
                        memberCount: 0,
                    });
                });
            }

            visibleGroupIndex += 1;
        });

        return items;
    }, [groups, expanded, isGrouped]);

    const roomRowColorByGroupKey = useMemo<Record<string, string>>(() => {
        if (mode !== 'room' && mode !== 'room_check') {
            return {};
        }

        const colorByGroupKey: Record<string, string> = {};
        const colorClasses = [
            'bg-orange-50 dark:bg-orange-950/35',
            'bg-orange-100 dark:bg-orange-900/35',
        ];

        let activeColorIndex = 0;
        let previousLabelKey: string | null = null;

        const groupHeaders = visibleItems.filter((item) => item.isGroupHeader);

        groupHeaders.forEach((item, index) => {
            const groupMembers = groups.find(
                (group) => group.key === item.groupKey,
            )?.members;
            const leadMember = groupMembers?.[0]?.member;

            const normalizedLabel = String(leadMember?.room_label ?? '')
                .trim()
                .toLowerCase();
            const labelKey =
                normalizedLabel.length > 0
                    ? normalizedLabel
                    : `__group-${item.groupKey}`;

            if (index > 0 && previousLabelKey !== labelKey) {
                activeColorIndex = activeColorIndex === 0 ? 1 : 0;
            }

            colorByGroupKey[item.groupKey] = colorClasses[activeColorIndex];
            previousLabelKey = labelKey;
        });

        return colorByGroupKey;
    }, [mode, visibleItems, groups]);

    const flatRows = useMemo(() => {
        if (isGrouped) {
            return [];
        }

        return rows.map((row, index) => ({
            ...row,
            _rowId: toMemberId(row, index),
        }));
    }, [rows, isGrouped]);

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
    );

    const updateMemberField = (
        flatIndex: number,
        field: keyof MemberWithUI,
        value: string | number | string[],
    ) => {
        const next = [...rows];

        if (field === 'date_of_birth' && typeof value === 'string') {
            const ageValue = getAgeFromDate(value);

            next[flatIndex] = {
                ...next[flatIndex],
                date_of_birth: value,
                age: ageValue === '' ? null : Number(ageValue),
            };
            onRowsChange(next);

            return;
        }

        next[flatIndex] = { ...next[flatIndex], [field]: value };
        onRowsChange(next);
    };

    const updateGroupField = (
        groupKey: string,
        field: keyof MemberWithUI,
        value: string | number,
    ) => {
        const patch: Partial<MemberWithUI> = { [field]: value };

        if (
            (mode === 'room' || mode === 'room_check') &&
            field === 'room_type' &&
            typeof value === 'string'
        ) {
            patch.bed_type = getBedTypeFromRoomType(value);
        }

        const next = rows.map((row, rowIndex) => {
            const key = getMemberGroupKey(row, rowIndex);

            if (key === groupKey) {
                return { ...row, ...patch };
            }

            return row;
        });

        onRowsChange(next);
    };

    const updateFlatRow = (
        index: number,
        field: keyof MemberWithUI,
        value: string | number | boolean,
    ) => {
        const next = [...rows];

        if (field === 'date_of_birth' && typeof value === 'string') {
            const ageValue = getAgeFromDate(value);

            next[index] = {
                ...next[index],
                date_of_birth: value,
                age: ageValue === '' ? null : Number(ageValue),
            };
            onRowsChange(next);

            return;
        }

        next[index] = { ...next[index], [field]: value };
        onRowsChange(next);
    };

    const emitReorderedRows = (reorderedGroups: GroupData[]) => {
        let counter = 0;

        const result: MemberWithUI[] = reorderedGroups.flatMap(
            (group, groupIndex) =>
                group.members.map((member, memberIndex) => ({
                    ...member.member,
                    sn: ++counter,
                    group_sort_order: groupIndex + 1,
                    sort_order: memberIndex + 1,
                })),
        );

        onRowsChange(result);
    };

    const getGroupKeyFromDndId = (dndId: string): string | null => {
        if (dndId.startsWith('g-')) {
            return dndId.slice(2);
        }

        const item = visibleItems.find(
            (visibleItem) => visibleItem.dndId === dndId,
        );
        return item?.groupKey ?? null;
    };

    const reorderWithinGroup = (
        groupKey: string,
        activeId: string,
        overId: string,
    ) => {
        const group = groups.find((item) => item.key === groupKey);

        if (!group || overId.startsWith('g-')) {
            return;
        }

        const activeIndex = group.members.findIndex(
            (member) =>
                toMemberDndId(member.member, member.flatIndex) === activeId,
        );
        const overIndex = group.members.findIndex(
            (member) =>
                toMemberDndId(member.member, member.flatIndex) === overId,
        );

        if (activeIndex < 0 || overIndex < 0) {
            return;
        }

        const reorderedMembers = arrayMove(
            group.members,
            activeIndex,
            overIndex,
        );
        const newGroups = groups.map((item) =>
            item.key === groupKey
                ? { ...item, members: reorderedMembers }
                : item,
        );

        emitReorderedRows(newGroups);
    };

    const moveMemberToGroup = (
        activeItem: VisibleItem,
        sourceGroupKey: string,
        targetGroupKey: string,
        overId: string,
    ) => {
        if (!activeItem.member) {
            return;
        }

        const targetGroup = groups.find(
            (group) => group.key === targetGroupKey,
        );

        if (!targetGroup) {
            return;
        }

        const targetShared = targetGroup.members[0]?.member;

        const movedMemberRow: MemberWithUI = {
            ...activeItem.member,
            sharing_group_key: targetGroupKey,
            ...(mode === 'room' && targetShared
                ? {
                      room_relationship: targetShared.room_relationship,
                      room_label: targetShared.room_label,
                      room_number: targetShared.room_number,
                      room_type: targetShared.room_type,
                      bed_type: targetShared.bed_type,
                      meal: targetShared.meal,
                      room_remarks: targetShared.room_remarks,
                  }
                : {}),
        };

        const movedMember: GroupMember = {
            member: movedMemberRow,
            flatIndex: activeItem.flatIndex,
        };

        let insertPos = targetGroup.members.length;

        if (!overId.startsWith('g-')) {
            const overIndex = targetGroup.members.findIndex(
                (member) =>
                    toMemberDndId(member.member, member.flatIndex) === overId,
            );

            if (overIndex >= 0) {
                insertPos = overIndex;
            }
        }

        const newGroups = groups
            .map((group) => {
                if (group.key === sourceGroupKey) {
                    return {
                        ...group,
                        members: group.members.filter(
                            (member) =>
                                member.flatIndex !== activeItem.flatIndex,
                        ),
                    };
                }

                if (group.key === targetGroupKey) {
                    const updatedMembers = [...group.members];
                    updatedMembers.splice(insertPos, 0, movedMember);

                    return {
                        ...group,
                        members: updatedMembers,
                    };
                }

                return group;
            })
            .filter((group) => group.members.length > 0);

        setExpanded((prev) => ({ ...prev, [targetGroupKey]: true }));

        emitReorderedRows(newGroups);
    };

    const splitGroupByCapacity = (groupKey: string) => {
        if (mode !== 'members' && mode !== 'room') {
            return;
        }

        const group = groups.find((item) => item.key === groupKey);

        if (!group || group.members.length < 2) {
            return;
        }

        const splitGroups: GroupData[] = [];
        let splitIndex = 1;

        if (mode === 'members') {
            const planBuckets = new Map<string, GroupMember[]>();

            group.members.forEach((member) => {
                const sharingPlan = String(
                    member.member.sharing_plan ?? 'single',
                ).toLowerCase();

                if (!planBuckets.has(sharingPlan)) {
                    planBuckets.set(sharingPlan, []);
                }

                planBuckets.get(sharingPlan)?.push(member);
            });

            planBuckets.forEach((members, sharingPlan) => {
                const capacity = Math.max(
                    getCapacityForSharingPlan(sharingPlan),
                    1,
                );

                for (
                    let memberIndex = 0;
                    memberIndex < members.length;
                    memberIndex += capacity
                ) {
                    const chunk = members.slice(
                        memberIndex,
                        memberIndex + capacity,
                    );

                    const nextKey =
                        splitGroups.length === 0
                            ? group.key
                            : `split-${group.key}-${splitIndex++}`;

                    splitGroups.push({
                        key: nextKey,
                        members: chunk.map((member) => ({
                            ...member,
                            member: {
                                ...member.member,
                                sharing_group_key: nextKey,
                                sharing_plan: sharingPlan,
                            },
                        })),
                    });
                }
            });
        }

        if (mode === 'room') {
            const roomType = String(
                group.members[0]?.member.room_type ?? '',
            ).toLowerCase();
            const normalizedRoomType =
                roomType.length > 0 ? roomType : 'single';
            const capacity = Math.max(
                getCapacityForRoomType(normalizedRoomType),
                1,
            );
            const planBuckets = new Map<string, GroupMember[]>();

            group.members.forEach((member) => {
                const memberSharingPlan = String(
                    member.member.sharing_plan ?? 'single',
                )
                    .toLowerCase()
                    .trim();
                const bucketKey =
                    memberSharingPlan.length > 0 ? memberSharingPlan : 'single';

                if (!planBuckets.has(bucketKey)) {
                    planBuckets.set(bucketKey, []);
                }

                planBuckets.get(bucketKey)?.push(member);
            });

            planBuckets.forEach((members, sharingPlan) => {
                for (
                    let memberIndex = 0;
                    memberIndex < members.length;
                    memberIndex += capacity
                ) {
                    const chunk = members.slice(
                        memberIndex,
                        memberIndex + capacity,
                    );

                    const nextKey =
                        splitGroups.length === 0
                            ? group.key
                            : `split-${group.key}-${splitIndex++}`;

                    splitGroups.push({
                        key: nextKey,
                        members: chunk.map((member) => ({
                            ...member,
                            member: {
                                ...member.member,
                                sharing_group_key: nextKey,
                                sharing_plan: sharingPlan,
                                room_type: normalizedRoomType,
                                bed_type:
                                    getBedTypeFromRoomType(
                                        normalizedRoomType,
                                    ) || member.member.bed_type,
                            },
                        })),
                    });
                }
            });
        }

        if (splitGroups.length <= 1) {
            toast.error('No split needed for this group.');

            return;
        }

        const rebuiltGroups = groups.flatMap((item) =>
            item.key === groupKey ? splitGroups : [item],
        );

        splitGroups.forEach((item) => {
            setExpanded((prev) => ({
                ...prev,
                [item.key]: true,
            }));
        });

        emitReorderedRows(rebuiltGroups);
    };

    const splitMemberIntoNewRoomGroup = (
        flatIndex: number,
        sourceGroupKey: string,
    ) => {
        if ((mode !== 'room' && mode !== 'members') || disabled) {
            return;
        }

        const sourceGroup = groups.find(
            (group) => group.key === sourceGroupKey,
        );

        if (!sourceGroup || sourceGroup.members.length <= 1) {
            return;
        }

        const targetMember = rows[flatIndex];

        if (!targetMember) {
            return;
        }

        const existingGroupKeys = new Set(groups.map((group) => group.key));
        const memberKey = toMemberId(targetMember, flatIndex);
        const baseGroupKey = `split-member-${sourceGroupKey}-${memberKey}`;
        let nextGroupKey = baseGroupKey;
        let counter = 1;

        while (existingGroupKeys.has(nextGroupKey)) {
            nextGroupKey = `${baseGroupKey}-${counter}`;
            counter += 1;
        }

        const nextRows = rows.map((row, rowIndex) => {
            if (rowIndex !== flatIndex) {
                return row;
            }

            return {
                ...row,
                sharing_group_key: nextGroupKey,
                sort_order: 1,
            };
        });

        setExpanded((prev) => ({
            ...prev,
            [sourceGroupKey]: true,
            [nextGroupKey]: true,
        }));

        onRowsChange(nextRows);
    };

    const handleFlatDrag = (activeId: string, overId: string) => {
        const oldIndex = flatRows.findIndex((row) => row._rowId === activeId);
        const newIndex = flatRows.findIndex((row) => row._rowId === overId);

        if (oldIndex < 0 || newIndex < 0) {
            return;
        }

        const reordered = arrayMove(rows, oldIndex, newIndex).map(
            (row, index) => ({
                ...row,
                sn: index + 1,
                sort_order: index + 1,
            }),
        );

        onRowsChange(reordered);
    };

    const handleGroupedDrag = (activeId: string, overId: string) => {
        const isActiveGroup = activeId.startsWith('g-');
        const overGroupKey = getGroupKeyFromDndId(overId);

        if (!overGroupKey) {
            return;
        }

        if (isActiveGroup) {
            const activeGroupKey = activeId.slice(2);

            if (activeGroupKey === overGroupKey) {
                return;
            }

            const activeIndex = groups.findIndex(
                (group) => group.key === activeGroupKey,
            );
            const overIndex = groups.findIndex(
                (group) => group.key === overGroupKey,
            );

            if (activeIndex < 0 || overIndex < 0) {
                return;
            }

            const reordered = arrayMove(groups, activeIndex, overIndex);
            emitReorderedRows(reordered);

            return;
        }

        const activeItem = visibleItems.find((item) => item.dndId === activeId);

        if (!activeItem || !activeItem.member) {
            return;
        }

        const sourceGroupKey = activeItem.groupKey;

        if (sourceGroupKey === overGroupKey) {
            reorderWithinGroup(sourceGroupKey, activeId, overId);

            return;
        }

        const targetGroup = groups.find((group) => group.key === overGroupKey);

        if (!targetGroup) {
            return;
        }

        if (mode !== 'room') {
            const sourceConfirmationId =
                activeItem.member.customer_confirmation_id;
            const targetConfirmationId =
                targetGroup.members[0]?.member.customer_confirmation_id;

            if (
                sourceConfirmationId &&
                targetConfirmationId &&
                sourceConfirmationId !== targetConfirmationId
            ) {
                toast.error(
                    'Cannot move: member must stay within the same customer confirmation group.',
                );

                return;
            }
        }

        moveMemberToGroup(activeItem, sourceGroupKey, overGroupKey, overId);
    };

    const handleDragEnd = (event: DragEndEvent) => {
        if (!allowReorder || disabled) {
            return;
        }

        const { active, over } = event;

        if (!over || active.id === over.id) {
            return;
        }

        const activeId = String(active.id);
        const overId = String(over.id);

        if (isGrouped) {
            handleGroupedDrag(activeId, overId);

            return;
        }

        handleFlatDrag(activeId, overId);
    };

    const getColumnCount = (): number => {
        const drag = allowReorder ? 1 : 0;

        if (mode === 'members') {
            return drag + 27;
        }

        if (mode === 'room') {
            return drag + 14;
        }

        if (mode === 'room_check') {
            return drag + 15;
        }

        if (mode === 'course_collection') {
            return drag + 13;
        }

        return drag + 11;
    };

    const getGroupRoomInfo = (groupKey: string) => {
        const group = groups.find((item) => item.key === groupKey);
        const first = group?.members[0]?.member;

        return {
            room_relationship: first?.room_relationship ?? '',
            room_label: first?.room_label ?? '',
            room_number: first?.room_number ?? '',
            sharing_plan: first?.sharing_plan ?? '',
            room_type: first?.room_type ?? '',
            bed_type: first?.bed_type ?? '',
            number_of_beds_checked: !!first?.number_of_beds_checked,
            meal: first?.meal ?? '',
        };
    };

    const renderTableHeaders = () => (
        <TableRow>
            {allowReorder && <TableHead className="w-[52px]" />}
            <TableHead className="w-[72px]">
                {isGrouped ? '#' : 'S/N'}
            </TableHead>
            <TableHead className="min-w-60">Name as per passport</TableHead>
            {(mode === 'members' ||
                mode === 'room' ||
                mode === 'room_check') && (
                <TableHead className="min-w-40">Relationship</TableHead>
            )}
            {mode === 'members' && (
                <TableHead className="min-w-40">Group</TableHead>
            )}
            {mode === 'members' && (
                <TableHead className="min-w-40">Package Category</TableHead>
            )}
            {mode === 'members' && (
                <TableHead className="min-w-55">Date of Sign Up</TableHead>
            )}
            {mode === 'members' && (
                <TableHead className="min-w-40">1st Time Umrah</TableHead>
            )}
            {mode === 'members' && (
                <TableHead className="min-w-40">Room Type</TableHead>
            )}
            {mode === 'members' && (
                <TableHead className="min-w-40">Passport No</TableHead>
            )}
            {mode === 'members' && (
                <TableHead className="min-w-40">Gender</TableHead>
            )}
            {mode === 'members' && (
                <TableHead className="min-w-55">Date of Birth</TableHead>
            )}
            {mode === 'members' && (
                <TableHead className="min-w-20">Age</TableHead>
            )}
            {mode === 'members' && (
                <TableHead className="min-w-45">Contact No</TableHead>
            )}
            {mode === 'members' && (
                <TableHead className="min-w-55">Date of Issue</TableHead>
            )}
            {mode === 'members' && (
                <TableHead className="min-w-55">Date of Expiry</TableHead>
            )}
            {mode === 'members' && (
                <TableHead className="min-w-60">Issue Place</TableHead>
            )}
            {mode === 'members' && (
                <TableHead className="min-w-40">Birth Place</TableHead>
            )}
            {mode === 'members' && (
                <TableHead className="min-w-35">Package Price</TableHead>
            )}
            {mode === 'members' && (
                <TableHead className="min-w-35">Discount</TableHead>
            )}
            {mode === 'members' && (
                <TableHead className="min-w-55">
                    Date of Deposit Payment
                </TableHead>
            )}
            {mode === 'members' && (
                <TableHead className="min-w-35">Deposit Payment</TableHead>
            )}
            {mode === 'members' && (
                <TableHead className="min-w-55">
                    Date of Second Payment
                </TableHead>
            )}
            {mode === 'members' && (
                <TableHead className="min-w-35">Second Payment</TableHead>
            )}
            {mode === 'members' && (
                <TableHead className="min-w-55">
                    Date of Third Payment
                </TableHead>
            )}
            {mode === 'members' && (
                <TableHead className="min-w-35">Third Payment</TableHead>
            )}
            {mode === 'members' && (
                <TableHead className="min-w-35">Balance Due</TableHead>
            )}
            {mode === 'members' && (
                <TableHead className="min-w-40">Receipt 1</TableHead>
            )}
            {mode === 'members' && (
                <TableHead className="min-w-40">Receipt 2</TableHead>
            )}
            {mode === 'members' && (
                <TableHead className="min-w-50">Receipt 3</TableHead>
            )}
            {mode === 'airline' && (
                <TableHead className="min-w-40">Passport No</TableHead>
            )}
            {mode === 'airline' && (
                <TableHead className="min-w-50">Nationality</TableHead>
            )}
            {mode === 'airline' && (
                <TableHead className="min-w-40">Gender</TableHead>
            )}
            {mode === 'airline' && (
                <TableHead className="min-w-55">Date of Birth</TableHead>
            )}
            {mode === 'airline' && (
                <TableHead className="min-w-55">Date of Issue</TableHead>
            )}
            {mode === 'airline' && (
                <TableHead className="min-w-55">Date of Expiry</TableHead>
            )}
            {mode === 'airline' && (
                <TableHead className="min-w-60">Issue Place</TableHead>
            )}
            {mode === 'course_collection' && (
                <TableHead className="min-w-40 text-center">Course 1</TableHead>
            )}
            {mode === 'course_collection' && (
                <TableHead className="min-w-40 text-center">Course 2</TableHead>
            )}
            {mode === 'course_collection' && (
                <TableHead className="min-w-40 text-center">Lanyard</TableHead>
            )}
            {mode === 'course_collection' && (
                <TableHead className="min-w-40 text-center">
                    Luggage Tag
                </TableHead>
            )}
            {mode === 'course_collection' && (
                <TableHead className="min-w-40 text-center">
                    Cabin Tag
                </TableHead>
            )}
            {mode === 'course_collection' && (
                <TableHead className="min-w-40 text-center">
                    Passport Cover
                </TableHead>
            )}
            {mode === 'course_collection' && (
                <TableHead className="min-w-40 text-center">
                    Umrah Guidebook
                </TableHead>
            )}
            {mode === 'course_collection' && (
                <TableHead className="min-w-40 text-center">
                    Sling Bag
                </TableHead>
            )}
            {mode === 'course_collection' && (
                <TableHead className="min-w-46 text-center">
                    Cabin Size Luggage
                </TableHead>
            )}
            {mode === 'course_collection' && (
                <TableHead className="min-w-44 text-center">
                    Umrah Essentials
                </TableHead>
            )}
            {(mode === 'room' || mode === 'room_check') && (
                <TableHead className="min-w-60">Group</TableHead>
            )}
            {(mode === 'room' || mode === 'room_check') && (
                <TableHead className="min-w-40">Passport No</TableHead>
            )}
            {(mode === 'room' || mode === 'room_check') && (
                <TableHead className="min-w-40">Room Label</TableHead>
            )}
            {(mode === 'room' || mode === 'room_check') && (
                <TableHead className="min-w-40">Room No</TableHead>
            )}
            {(mode === 'room' || mode === 'room_check') && (
                <TableHead className="min-w-40">Room Type</TableHead>
            )}
            {(mode === 'room' || mode === 'room_check') && (
                <TableHead className="min-w-40">Bed Type</TableHead>
            )}
            {(mode === 'room' || mode === 'room_check') && (
                <TableHead className="min-w-55">Date of Birth</TableHead>
            )}
            {(mode === 'room' || mode === 'room_check') && (
                <TableHead className="min-w-20">Age</TableHead>
            )}
            {mode === 'room_check' && (
                <TableHead className="min-w-52">No. Of Beds Checked</TableHead>
            )}
            {(mode === 'room' || mode === 'room_check') && (
                <TableHead className="min-w-40">Meal</TableHead>
            )}
            {mode === 'members' && <TableHead>Payment Status</TableHead>}
            {mode !== 'course_collection' && (
                <TableHead className="min-w-60">Remarks</TableHead>
            )}
            <TableHead>Action</TableHead>
        </TableRow>
    );

    const renderGroupHeader = (
        item: VisibleItem,
        sortableProps: ReturnType<typeof useSortable>,
    ) => {
        const { setNodeRef, transform, transition, attributes, listeners } =
            sortableProps;
        const isRoomMode = mode === 'room' || mode === 'room_check';
        const isRoomCheckMode = mode === 'room_check';
        const isExpanded = expanded[item.groupKey] !== false;
        const groupLeadMember = groups.find(
            (group) => group.key === item.groupKey,
        )?.members?.[0]?.member;
        const roomInfo = isRoomMode ? getGroupRoomInfo(item.groupKey) : null;
        const groupConfirmationNumber = !isRoomMode
            ? groupLeadMember?.customer_confirmation_number
            : null;
        const capacity = isRoomMode
            ? getCapacityForRoomType(roomInfo?.room_type)
            : Infinity;
        const capacityLabel = Number.isFinite(capacity)
            ? `${item.memberCount}/${capacity}`
            : `${item.memberCount}`;
        const isAtCapacity =
            Number.isFinite(capacity) && item.memberCount >= capacity;
        const disableRoomFields = isRoomCheckMode || disabled;

        const canSplitGroup =
            (mode === 'members' || mode === 'room') &&
            !disabled &&
            item.memberCount > 1;

        const rowContent = (
            <TableRow
                ref={setNodeRef}
                style={{
                    transform: CSS.Transform.toString(transform),
                    transition,
                }}
                className={cn(
                    'items-start bg-muted/30',
                    isExpanded && 'border-b-0',
                    roomRowColorByGroupKey[item.groupKey],
                )}
            >
                {allowReorder && (
                    <TableCell>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            disabled={disabled}
                            className="h-7 w-7 cursor-grab active:cursor-grabbing"
                            {...attributes}
                            {...listeners}
                        >
                            <GripVertical className="h-4 w-4" />
                        </Button>
                    </TableCell>
                )}

                <TableCell>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="h-7 w-7"
                        onClick={() => toggleExpanded(item.groupKey)}
                    >
                        {isExpanded ? (
                            <ChevronDown className="h-4 w-4" />
                        ) : (
                            <ChevronRight className="h-4 w-4" />
                        )}
                    </Button>
                </TableCell>

                <TableCell>
                    <div className="flex items-center gap-2">
                        <span className="text-base font-medium">
                            {isRoomMode
                                ? (roomInfo?.room_label?.trim() ?? '') ||
                                  `Room ${item.groupIndex + 1}`
                                : `Group ${item.groupIndex + 1}`}
                        </span>
                        {isRoomMode && (
                            <Badge
                                variant="secondary"
                                className="text-sm uppercase"
                            >
                                Room Row
                            </Badge>
                        )}
                        <Badge
                            variant="outline"
                            className={cn(
                                'text-sm',
                                isAtCapacity &&
                                    'border-red-300 bg-red-50 text-red-700',
                            )}
                        >
                            {capacityLabel} pax
                        </Badge>
                        {!isRoomMode && groupConfirmationNumber && (
                            <Badge variant="secondary" className="text-sm">
                                {groupConfirmationNumber}
                            </Badge>
                        )}
                    </div>
                </TableCell>

                {mode === 'members' && (
                    <>
                        <TableCell />
                        <TableCell>
                            <ProperInput
                                value={
                                    groupLeadMember?.group_relationship ?? ''
                                }
                                onCommit={(value) =>
                                    updateGroupField(
                                        item.groupKey,
                                        'group_relationship',
                                        value,
                                    )
                                }
                                disabled={disabled}
                                size="default"
                            />
                        </TableCell>
                        <TableCell />
                        <TableCell />
                        <TableCell />
                        <TableCell />
                        <TableCell />
                        <TableCell />
                        <TableCell />
                        <TableCell />
                        <TableCell />
                        <TableCell />
                        <TableCell />
                        <TableCell />
                        <TableCell />
                        <TableCell />
                        <TableCell />
                        <TableCell />
                        <TableCell />
                        <TableCell />
                        <TableCell />
                        <TableCell />
                        <TableCell />
                        <TableCell />
                        <TableCell />
                        <TableCell />
                        <TableCell />
                        <TableCell />
                        <TableCell>
                            <ProperInput
                                value={groupLeadMember?.group_remarks ?? ''}
                                onCommit={(value) =>
                                    updateGroupField(
                                        item.groupKey,
                                        'group_remarks',
                                        value,
                                    )
                                }
                                disabled={disabled}
                                textarea
                                size="default"
                            />
                        </TableCell>
                    </>
                )}

                {mode === 'airline' && (
                    <>
                        <TableCell />
                        <TableCell />
                        <TableCell />
                        <TableCell />
                        <TableCell />
                        <TableCell />
                        <TableCell />
                        <TableCell>
                            <ProperInput
                                value={groupLeadMember?.group_remarks ?? ''}
                                onCommit={(value) =>
                                    updateGroupField(
                                        item.groupKey,
                                        'group_remarks',
                                        value,
                                    )
                                }
                                disabled={disabled}
                                textarea
                                size="default"
                            />
                        </TableCell>
                    </>
                )}

                {isRoomMode && (
                    <>
                        <TableCell />
                        <TableCell>
                            <ProperInput
                                value={roomInfo?.room_relationship ?? ''}
                                onCommit={(value) =>
                                    updateGroupField(
                                        item.groupKey,
                                        'room_relationship',
                                        value,
                                    )
                                }
                                disabled={disableRoomFields}
                                placeholder="Relationship"
                                size="default"
                            />
                        </TableCell>
                        <TableCell />
                        <TableCell>
                            <ProperInput
                                value={
                                    (roomInfo?.room_label?.trim() ?? '') ||
                                    `Room ${item.groupIndex + 1}`
                                }
                                onCommit={(value) =>
                                    updateGroupField(
                                        item.groupKey,
                                        'room_label',
                                        value,
                                    )
                                }
                                disabled={disableRoomFields}
                                placeholder={`Room ${item.groupIndex + 1}`}
                                size="default"
                            />
                        </TableCell>
                        <TableCell>
                            <ProperInput
                                value={roomInfo?.room_number ?? ''}
                                onCommit={(value) =>
                                    updateGroupField(
                                        item.groupKey,
                                        'room_number',
                                        value,
                                    )
                                }
                                disabled={disableRoomFields}
                                placeholder="Room No"
                                size="default"
                            />
                        </TableCell>
                        <TableCell>
                            <SelectCell
                                value={roomInfo?.room_type ?? ''}
                                placeholder="Room type"
                                onValueChange={(value) =>
                                    updateGroupField(
                                        item.groupKey,
                                        'room_type',
                                        value,
                                    )
                                }
                                disabled={disableRoomFields}
                                options={ROOM_TYPE_OPTIONS}
                            />
                        </TableCell>
                        <TableCell>
                            <SelectCell
                                value={roomInfo?.bed_type ?? ''}
                                placeholder="Bed type"
                                onValueChange={(value) =>
                                    updateGroupField(
                                        item.groupKey,
                                        'bed_type',
                                        value,
                                    )
                                }
                                disabled={disableRoomFields}
                                options={BED_TYPE_OPTIONS}
                            />
                        </TableCell>
                        <TableCell>
                            <span className="text-muted-foreground">-</span>
                        </TableCell>
                        <TableCell>
                            <span className="text-muted-foreground">-</span>
                        </TableCell>
                        {mode === 'room_check' && (
                            <TableCell>
                                <div className="inline-flex items-center gap-3 rounded-md border border-dashed border-muted-foreground/40 bg-muted/30 px-3 py-2 text-base">
                                    <span>
                                        {getBedsCountByRoomTypeAndBedType(
                                            roomInfo?.room_type,
                                            roomInfo?.bed_type,
                                        )}
                                    </span>
                                    <span className="h-5 w-px bg-muted-foreground/40" />
                                    <Checkbox
                                        checked={
                                            !!roomInfo?.number_of_beds_checked
                                        }
                                        onCheckedChange={(checked) =>
                                            updateGroupField(
                                                item.groupKey,
                                                'number_of_beds_checked',
                                                checked ? 1 : 0,
                                            )
                                        }
                                        disabled={disabled}
                                        aria-label="Mark number of beds checked"
                                        className="h-6 w-6 border-2"
                                    />
                                </div>
                            </TableCell>
                        )}
                        <TableCell>
                            <SelectCell
                                value={roomInfo?.meal ?? ''}
                                placeholder="Meal"
                                onValueChange={(value) =>
                                    updateGroupField(
                                        item.groupKey,
                                        'meal',
                                        value,
                                    )
                                }
                                disabled={disableRoomFields}
                                options={MEAL_OPTIONS}
                            />
                        </TableCell>
                        <TableCell>
                            {(() => {
                                const absoluteRoomGroupIndex =
                                    roomGroupStartIndex + item.groupIndex;
                                const roomRemarksPath =
                                    roomGroupErrorPrefix && mode === 'room'
                                        ? `${roomGroupErrorPrefix}.${absoluteRoomGroupIndex}.remarks`
                                        : undefined;

                                return (
                                    <>
                                        <ProperInput
                                            id={roomRemarksPath}
                                            value={
                                                groupLeadMember?.room_remarks ??
                                                ''
                                            }
                                            onCommit={(value) =>
                                                updateGroupField(
                                                    item.groupKey,
                                                    'room_remarks',
                                                    value,
                                                )
                                            }
                                            disabled={disabled}
                                            textarea
                                            size="default"
                                        />
                                        {roomRemarksPath &&
                                            errors[roomRemarksPath] && (
                                                <p
                                                    id={roomRemarksPath}
                                                    className="mt-1 text-xs text-red-500"
                                                >
                                                    {errors[roomRemarksPath]}
                                                </p>
                                            )}
                                    </>
                                );
                            })()}
                        </TableCell>
                    </>
                )}

                <TableCell>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                className="h-8 w-8 p-0"
                            >
                                <span className="sr-only">
                                    Open group actions
                                </span>
                                <EllipsisVertical className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {canSplitGroup ? (
                                <DropdownMenuItem
                                    onClick={() =>
                                        splitGroupByCapacity(item.groupKey)
                                    }
                                >
                                    Split Up Group
                                </DropdownMenuItem>
                            ) : (
                                <DropdownMenuItem disabled>
                                    No actions available
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </TableCell>
            </TableRow>
        );

        return (
            <ContextMenu>
                <ContextMenuTrigger asChild>{rowContent}</ContextMenuTrigger>
                <ContextMenuContent className="w-48">
                    {canSplitGroup ? (
                        <ContextMenuItem
                            onClick={() => splitGroupByCapacity(item.groupKey)}
                        >
                            Split Up Group
                        </ContextMenuItem>
                    ) : (
                        <ContextMenuItem disabled>
                            No actions available
                        </ContextMenuItem>
                    )}
                </ContextMenuContent>
            </ContextMenu>
        );
    };

    const renderMemberRow = (
        item: VisibleItem,
        sortableProps: ReturnType<typeof useSortable>,
    ) => {
        const { setNodeRef, transform, transition, attributes, listeners } =
            sortableProps;
        const member = item.member!;
        const flatIndex = item.flatIndex;

        const itemIndex = visibleItems.indexOf(item);
        const nextItem = visibleItems[itemIndex + 1];
        const isLastChild = !nextItem || nextItem.isGroupHeader;

        const canMoveToHolding =
            !disabled &&
            member.status !== 'cancelled' &&
            !!member.customer_confirmation_member_id;

        const isOfficialRoomMember = isOfficialMember(member);
        const roomLocationKeys = roomAssignmentOptions
            .map((option) => normalizeRoomLocationKey(option.key))
            .filter((value) => value.length > 0);
        const assignedLocations = Array.from(
            new Set(
                (getMemberAssignedLocations?.(member) ?? roomLocationKeys)
                    .map((location) =>
                        normalizeRoomLocationKey(String(location)),
                    )
                    .filter((location) => location.length > 0),
            ),
        );
        const explicitUnassignedLocations = roomLocationKeys.filter(
            (location) => !assignedLocations.includes(location),
        );
        const canShowUnassignByLocation =
            mode === 'members' &&
            isOfficialRoomMember &&
            assignedLocations.length > 0;
        const canShowAssignByLocation =
            mode === 'members' &&
            isOfficialRoomMember &&
            explicitUnassignedLocations.length > 0;
        const canShowAssignAllOption = explicitUnassignedLocations.length > 1;

        const defaultLocationKey = normalizeRoomLocationKey(
            String(
                currentRoomLocationKey ?? roomAssignmentOptions[0]?.key ?? '',
            ),
        );
        const isRoomAssigned =
            defaultLocationKey.length === 0
                ? true
                : assignedLocations.includes(defaultLocationKey);

        const memberDisabled = mode === 'room_check' || disabled;
        const remarksDisabled = disabled;
        const isOfficialMemberRow = isOfficialMember(member);
        const receiptDocuments = (member.receipt_documents ?? [])
            .filter((document) => {
                return (
                    !document.removed &&
                    (document.file_path || document.file_name)
                );
            })
            .sort((left, right) => {
                return Number(left.id ?? 0) - Number(right.id ?? 0);
            });

        const renderReceiptDocument = (
            document?: {
                file_path?: string | null;
                file_name?: string | null;
            },
            fallbackValue?: string | null,
        ) => {
            const href = normalizeDocumentPath(document?.file_path);
            const label = documentLabel(
                document?.file_path,
                document?.file_name,
            );
            const isImage = isImageDocument(
                document?.file_path ?? document?.file_name,
            );

            if (href.length === 0) {
                return (
                    <div className="text-sm text-muted-foreground">
                        {String(fallbackValue ?? '').trim() || '-'}
                    </div>
                );
            }

            if (isImage) {
                return (
                    <div className="flex items-center gap-2">
                        <ImagePreviewDialog
                            imageSrc={href}
                            imageAlt={label}
                            title={label}
                            thumbnailSize={48}
                        />
                    </div>
                );
            }

            return (
                <a
                    href={href}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-2 rounded-md border px-2 py-1 text-xs hover:bg-muted/40"
                    title={label}
                >
                    <FileText className="h-4 w-4" />
                    <span className="max-w-[120px] truncate">{label}</span>
                    <ExternalLink className="h-3 w-3" />
                </a>
            );
        };

        const renderReceiptDocumentCell = (receiptIndex: number) => {
            const fallbackValue =
                receiptIndex === 0
                    ? member.receipt_1
                    : receiptIndex === 1
                      ? member.receipt_2
                      : member.receipt_3;

            if (receiptIndex < 2) {
                const document = receiptDocuments[receiptIndex];

                return renderReceiptDocument(document, fallbackValue);
            }

            const thirdAndLaterDocuments = receiptDocuments.slice(2);

            if (thirdAndLaterDocuments.length === 0) {
                return renderReceiptDocument(undefined, fallbackValue);
            }

            return (
                <div className="flex flex-wrap items-center gap-2">
                    {thirdAndLaterDocuments.map((document, documentIndex) => (
                        <div
                            key={`${member.row_key ?? member.id ?? flatIndex}-receipt-3-${document.id ?? documentIndex}`}
                        >
                            {renderReceiptDocument(document)}
                        </div>
                    ))}
                </div>
            );
        };

        const canToggleRoomAssignment =
            isOfficialRoomMember && (mode === 'room' || mode === 'members');

        const setMemberRoomAssignment = (
            action: 'assign' | 'unassign',
            scope: string,
        ) => {
            if (!canToggleRoomAssignment) {
                return;
            }

            if (mode === 'room') {
                if (action === 'unassign') {
                    onRowsChange(
                        rows.filter((_, rowIndex) => rowIndex !== flatIndex),
                    );
                }

                return;
            }

            onRoomAssignmentChange?.(member, action, scope);
        };

        const toggleRoomAssignment = () => {
            if (!canToggleRoomAssignment) {
                return;
            }

            const fallbackScope =
                defaultLocationKey.length > 0 ? defaultLocationKey : 'all';
            setMemberRoomAssignment(
                isRoomAssigned ? 'unassign' : 'assign',
                fallbackScope,
            );
        };

        const canShowMoveToHoldingAction =
            mode === 'members' && canMoveToHolding && !disabled;
        const canShowToggleRoomAction =
            (mode === 'room' || mode === 'room_check') &&
            canToggleRoomAssignment &&
            !disabled;
        const currentGroupMemberCount =
            groups.find((group) => group.key === item.groupKey)?.members
                .length ?? 0;
        const canSplitMemberFromGroup =
            (mode === 'room' || mode === 'members') &&
            !disabled &&
            currentGroupMemberCount > 1;
        const hasMemberAction =
            canShowMoveToHoldingAction ||
            canShowToggleRoomAction ||
            canSplitMemberFromGroup ||
            canShowUnassignByLocation ||
            canShowAssignByLocation;

        const rowContent = (
            <TableRow
                ref={setNodeRef}
                style={{
                    transform: CSS.Transform.toString(transform),
                    transition,
                }}
                className={cn(
                    !isLastChild && 'border-b-0',
                    roomRowColorByGroupKey[item.groupKey],
                )}
            >
                {allowReorder && (
                    <TableCell>
                        <div className="pl-2">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                disabled={disabled}
                                className="h-7 w-7 cursor-grab active:cursor-grabbing"
                                {...attributes}
                                {...listeners}
                            >
                                <GripVertical className="h-4 w-4 text-muted-foreground" />
                            </Button>
                        </div>
                    </TableCell>
                )}

                <TableCell className="text-muted-foreground">
                    {member.sn ?? flatIndex + 1}
                </TableCell>

                <TableCell>
                    {/* {mode === 'room' && (
                        <Badge
                            variant="outline"
                            className="mb-2 text-xs uppercase"
                        >
                            Member Row
                        </Badge>
                    )} */}
                    <ProperInput
                        id={
                            errorPrefix
                                ? getErrorPath(
                                      flatIndex,
                                      'name_as_per_passport',
                                  )
                                : undefined
                        }
                        value={member.name_as_per_passport ?? ''}
                        onCommit={(value) =>
                            updateMemberField(
                                flatIndex,
                                'name_as_per_passport',
                                value,
                            )
                        }
                        disabled={memberDisabled}
                        size="default"
                    />
                    {renderCellError(flatIndex, 'name_as_per_passport')}
                </TableCell>

                {(mode === 'members' ||
                    mode === 'room' ||
                    mode === 'room_check') && (
                    <TableCell>
                        <ProperInput
                            id={
                                errorPrefix
                                    ? getErrorPath(flatIndex, 'relationship')
                                    : undefined
                            }
                            value={member.relationship ?? ''}
                            onCommit={(value) =>
                                updateMemberField(
                                    flatIndex,
                                    'relationship',
                                    value,
                                )
                            }
                            disabled={memberDisabled}
                            size="default"
                        />
                        {renderCellError(flatIndex, 'relationship')}
                    </TableCell>
                )}

                {mode === 'members' && (
                    <TableCell>
                        <span className="text-muted-foreground">-</span>
                    </TableCell>
                )}

                {mode === 'members' && (
                    <TableCell>
                        <ProperInput
                            value={
                                PACKAGE_CATEGORY_LABELS[
                                    member.package_category ?? ''
                                ] ??
                                member.package_category ??
                                ''
                            }
                            disabled={true}
                            onCommit={() => {}}
                            size="default"
                        />
                    </TableCell>
                )}

                {mode === 'members' && (
                    <TableCell>
                        <ProperInput
                            value={member.date_of_sign_up ?? ''}
                            disabled={true}
                            onCommit={() => {}}
                            size="default"
                        />
                    </TableCell>
                )}

                {mode === 'members' && (
                    <TableCell>
                        <ProperInput
                            value={
                                member.is_first_time_umrah === null ||
                                member.is_first_time_umrah === undefined
                                    ? ''
                                    : member.is_first_time_umrah
                                      ? 'Yes'
                                      : 'No'
                            }
                            disabled={true}
                            onCommit={() => {}}
                            size="default"
                        />
                    </TableCell>
                )}

                {mode === 'members' && (
                    <TableCell>
                        <SelectCell
                            value={member.sharing_plan ?? ''}
                            placeholder="Room type"
                            onValueChange={(value) =>
                                updateMemberField(
                                    flatIndex,
                                    'sharing_plan',
                                    value,
                                )
                            }
                            disabled={disabled}
                            options={SHARING_PLAN_OPTIONS}
                        />
                        {renderCellError(flatIndex, 'sharing_plan')}
                    </TableCell>
                )}

                {mode === 'members' && (
                    <TableCell>
                        <ProperInput
                            id={
                                errorPrefix
                                    ? getErrorPath(flatIndex, 'passport_number')
                                    : undefined
                            }
                            value={member.passport_number ?? ''}
                            onCommit={(value) =>
                                updateMemberField(
                                    flatIndex,
                                    'passport_number',
                                    value,
                                )
                            }
                            disabled={disabled}
                            size="default"
                        />
                        {renderCellError(flatIndex, 'passport_number')}
                    </TableCell>
                )}

                {mode === 'members' && (
                    <TableCell>
                        <SelectCell
                            value={member.gender ?? ''}
                            placeholder="Gender"
                            onValueChange={(value) =>
                                updateMemberField(flatIndex, 'gender', value)
                            }
                            disabled={disabled}
                            options={GENDER_OPTIONS}
                        />
                        {renderCellError(flatIndex, 'gender')}
                    </TableCell>
                )}

                {mode === 'members' && (
                    <TableCell>
                        <DatePickerField
                            id={
                                errorPrefix
                                    ? getErrorPath(flatIndex, 'date_of_birth')
                                    : `main-date-of-birth-${flatIndex}`
                            }
                            value={member.date_of_birth ?? ''}
                            fromYear={new Date().getFullYear() - 100}
                            onChange={(value) =>
                                updateMemberField(
                                    flatIndex,
                                    'date_of_birth',
                                    value,
                                )
                            }
                            disabled={disabled}
                        />
                        {renderCellError(flatIndex, 'date_of_birth')}
                    </TableCell>
                )}

                {mode === 'members' && (
                    <TableCell>
                        <ProperInput
                            value={
                                member.age !== null && member.age !== undefined
                                    ? String(member.age)
                                    : getAgeFromDate(member.date_of_birth)
                            }
                            disabled={true}
                            onCommit={() => {}}
                            size="default"
                        />
                    </TableCell>
                )}

                {mode === 'members' && (
                    <TableCell>
                        <ProperInput
                            value={member.contact_no ?? ''}
                            onCommit={(value) =>
                                updateMemberField(
                                    flatIndex,
                                    'contact_no',
                                    value,
                                )
                            }
                            disabled={disabled}
                            size="default"
                        />
                        {renderCellError(flatIndex, 'contact_no')}
                    </TableCell>
                )}

                {mode === 'members' && (
                    <TableCell>
                        <DatePickerField
                            id={
                                errorPrefix
                                    ? getErrorPath(flatIndex, 'date_of_issue')
                                    : `main-date-of-issue-${flatIndex}`
                            }
                            value={member.date_of_issue ?? ''}
                            fromYear={new Date().getFullYear() - 10}
                            onChange={(value) =>
                                updateMemberField(
                                    flatIndex,
                                    'date_of_issue',
                                    value,
                                )
                            }
                            disabled={disabled}
                        />
                        {renderCellError(flatIndex, 'date_of_issue')}
                    </TableCell>
                )}

                {mode === 'members' && (
                    <TableCell>
                        <DatePickerField
                            id={
                                errorPrefix
                                    ? getErrorPath(flatIndex, 'date_of_expiry')
                                    : `main-date-of-expiry-${flatIndex}`
                            }
                            value={member.date_of_expiry ?? ''}
                            toYear={new Date().getFullYear() + 10}
                            onChange={(value) =>
                                updateMemberField(
                                    flatIndex,
                                    'date_of_expiry',
                                    value,
                                )
                            }
                            disabled={disabled}
                        />
                        {renderCellError(flatIndex, 'date_of_expiry')}
                    </TableCell>
                )}

                {mode === 'members' && (
                    <TableCell>
                        <ProperInput
                            value={member.issue_place ?? ''}
                            onCommit={(value) =>
                                updateMemberField(
                                    flatIndex,
                                    'issue_place',
                                    value,
                                )
                            }
                            disabled={disabled}
                            size="default"
                        />
                        {renderCellError(flatIndex, 'issue_place')}
                    </TableCell>
                )}

                {mode === 'members' && (
                    <TableCell>
                        <ProperInput
                            value={member.birth_place ?? ''}
                            onCommit={(value) =>
                                updateMemberField(
                                    flatIndex,
                                    'birth_place',
                                    value,
                                )
                            }
                            disabled={disabled}
                            size="default"
                        />
                        {renderCellError(flatIndex, 'birth_place')}
                    </TableCell>
                )}

                {mode === 'members' && (
                    <TableCell>
                        <ProperInput
                            value={
                                !isOfficialMemberRow &&
                                member.package_price !== null &&
                                member.package_price !== undefined
                                    ? formatCurrency(member.package_price, '$')
                                    : ''
                            }
                            disabled={true}
                            onCommit={() => {}}
                            size="default"
                        />
                    </TableCell>
                )}

                {mode === 'members' && (
                    <TableCell>
                        <ProperInput
                            value={
                                !isOfficialMemberRow &&
                                member.discount !== null &&
                                member.discount !== undefined
                                    ? formatCurrency(member.discount, '$')
                                    : ''
                            }
                            disabled={true}
                            onCommit={() => {}}
                            size="default"
                        />
                    </TableCell>
                )}

                {mode === 'members' && (
                    <TableCell>
                        <ProperInput
                            value={
                                !isOfficialMemberRow
                                    ? (member.date_of_deposit_payment ?? '')
                                    : ''
                            }
                            disabled={true}
                            onCommit={() => {}}
                            size="default"
                        />
                    </TableCell>
                )}

                {mode === 'members' && (
                    <TableCell>
                        <ProperInput
                            value={
                                !isOfficialMemberRow &&
                                member.deposit_payment !== null &&
                                member.deposit_payment !== undefined
                                    ? formatCurrency(
                                          Number(member.deposit_payment ?? 0),
                                          '$',
                                      )
                                    : ''
                            }
                            disabled={true}
                            onCommit={() => {}}
                            size="default"
                        />
                    </TableCell>
                )}

                {mode === 'members' && (
                    <TableCell>
                        <ProperInput
                            value={
                                !isOfficialMemberRow
                                    ? (member.date_of_second_payment ?? '')
                                    : ''
                            }
                            disabled={true}
                            onCommit={() => {}}
                            size="default"
                        />
                    </TableCell>
                )}

                {mode === 'members' && (
                    <TableCell>
                        <ProperInput
                            value={
                                !isOfficialMemberRow &&
                                member.second_payment !== null &&
                                member.second_payment !== undefined
                                    ? formatCurrency(
                                          Number(member.second_payment ?? 0),
                                          '$',
                                      )
                                    : ''
                            }
                            disabled={true}
                            onCommit={() => {}}
                            size="default"
                        />
                    </TableCell>
                )}

                {mode === 'members' && (
                    <TableCell>
                        <ProperInput
                            value={
                                !isOfficialMemberRow
                                    ? (member.date_of_third_payment ?? '')
                                    : ''
                            }
                            disabled={true}
                            onCommit={() => {}}
                            size="default"
                        />
                    </TableCell>
                )}

                {mode === 'members' && (
                    <TableCell>
                        <ProperInput
                            value={
                                !isOfficialMemberRow &&
                                member.third_payment !== null &&
                                member.third_payment !== undefined
                                    ? formatCurrency(
                                          Number(member.third_payment ?? 0),
                                          '$',
                                      )
                                    : ''
                            }
                            disabled={true}
                            onCommit={() => {}}
                            size="default"
                        />
                    </TableCell>
                )}

                {mode === 'members' && (
                    <TableCell>
                        <ProperInput
                            value={
                                !isOfficialMemberRow &&
                                member.balance_due !== null &&
                                member.balance_due !== undefined
                                    ? formatCurrency(
                                          Number(member.balance_due ?? 0),
                                          '$',
                                      )
                                    : ''
                            }
                            disabled={true}
                            onCommit={() => {}}
                            size="default"
                        />
                    </TableCell>
                )}

                {mode === 'members' && (
                    <TableCell>
                        {isOfficialMemberRow ? (
                            <span className="text-muted-foreground">-</span>
                        ) : (
                            renderReceiptDocumentCell(0)
                        )}
                    </TableCell>
                )}

                {mode === 'members' && (
                    <TableCell>
                        {isOfficialMemberRow ? (
                            <span className="text-muted-foreground">-</span>
                        ) : (
                            renderReceiptDocumentCell(1)
                        )}
                    </TableCell>
                )}

                {mode === 'members' && (
                    <TableCell>
                        {isOfficialMemberRow ? (
                            <span className="text-muted-foreground">-</span>
                        ) : (
                            renderReceiptDocumentCell(2)
                        )}
                    </TableCell>
                )}

                {mode === 'members' && (
                    <TableCell>
                        {isOfficialMemberRow ? (
                            <span className="text-muted-foreground">-</span>
                        ) : (
                            (() => {
                                const status =
                                    member.status ?? 'pending_payment';
                                const statusColor =
                                    confirmationMemberStatusColors[status] ??
                                    'bg-gray-100 text-gray-800';
                                const statusLabel =
                                    confirmationMemberStatusLabels[status] ??
                                    status;

                                return (
                                    <Badge
                                        className={`${statusColor} rounded-full px-3 py-1 text-base`}
                                    >
                                        {statusLabel}
                                    </Badge>
                                );
                            })()
                        )}
                    </TableCell>
                )}

                {(mode === 'room' || mode === 'room_check') && (
                    <>
                        <TableCell />
                        <TableCell>
                            <ProperInput
                                id={
                                    errorPrefix
                                        ? getErrorPath(
                                              flatIndex,
                                              'passport_number',
                                          )
                                        : undefined
                                }
                                value={member.passport_number ?? ''}
                                onCommit={(value) =>
                                    updateMemberField(
                                        flatIndex,
                                        'passport_number',
                                        value,
                                    )
                                }
                                disabled={memberDisabled}
                                size="default"
                            />
                            {renderCellError(flatIndex, 'passport_number')}
                        </TableCell>
                        <TableCell />
                        <TableCell />
                        <TableCell />
                        <TableCell />
                        <TableCell>
                            <DatePickerField
                                id={
                                    errorPrefix
                                        ? getErrorPath(
                                              flatIndex,
                                              'date_of_birth',
                                          )
                                        : `room-date-of-birth-${flatIndex}`
                                }
                                value={member.date_of_birth ?? ''}
                                fromYear={new Date().getFullYear() - 100}
                                onChange={(value) =>
                                    updateMemberField(
                                        flatIndex,
                                        'date_of_birth',
                                        value,
                                    )
                                }
                                disabled={memberDisabled}
                            />
                            {renderCellError(flatIndex, 'date_of_birth')}
                        </TableCell>
                        <TableCell>
                            <ProperInput
                                value={
                                    member.age !== null &&
                                    member.age !== undefined
                                        ? String(member.age)
                                        : getAgeFromDate(member.date_of_birth)
                                }
                                disabled={true}
                                onCommit={() => {}}
                                size="default"
                            />
                        </TableCell>
                        {mode === 'room_check' && (
                            <TableCell>
                                <span className="text-muted-foreground">-</span>
                            </TableCell>
                        )}
                        <TableCell />
                    </>
                )}

                {mode === 'airline' && (
                    <>
                        <TableCell>
                            <ProperInput
                                id={
                                    errorPrefix
                                        ? getErrorPath(
                                              flatIndex,
                                              'passport_number',
                                          )
                                        : undefined
                                }
                                value={
                                    member.passport_number ??
                                    member.passport_number ??
                                    ''
                                }
                                onCommit={(value) =>
                                    updateMemberField(
                                        flatIndex,
                                        'passport_number',
                                        value,
                                    )
                                }
                                disabled={disabled}
                                size="default"
                            />
                            {renderCellError(flatIndex, 'passport_number')}
                        </TableCell>
                        <TableCell>
                            <ProperInput
                                id={
                                    errorPrefix
                                        ? getErrorPath(flatIndex, 'nationality')
                                        : undefined
                                }
                                value={member.nationality ?? ''}
                                onCommit={(value) =>
                                    updateMemberField(
                                        flatIndex,
                                        'nationality',
                                        value,
                                    )
                                }
                                disabled={disabled}
                                size="default"
                            />
                            {renderCellError(flatIndex, 'nationality')}
                        </TableCell>
                        <TableCell>
                            <SelectCell
                                value={member.gender ?? ''}
                                placeholder="Gender"
                                onValueChange={(value) =>
                                    updateMemberField(
                                        flatIndex,
                                        'gender',
                                        value,
                                    )
                                }
                                disabled={disabled}
                                options={GENDER_OPTIONS}
                            />
                            {renderCellError(flatIndex, 'gender')}
                        </TableCell>
                        <TableCell>
                            <DatePickerField
                                id={
                                    errorPrefix
                                        ? getErrorPath(
                                              flatIndex,
                                              'date_of_birth',
                                          )
                                        : `airline-date-of-birth-${flatIndex}`
                                }
                                value={member.date_of_birth ?? ''}
                                fromYear={new Date().getFullYear() - 100}
                                onChange={(value) =>
                                    updateMemberField(
                                        flatIndex,
                                        'date_of_birth',
                                        value,
                                    )
                                }
                                disabled={disabled}
                            />
                            {renderCellError(flatIndex, 'date_of_birth')}
                        </TableCell>
                        <TableCell>
                            <DatePickerField
                                id={
                                    errorPrefix
                                        ? getErrorPath(
                                              flatIndex,
                                              'date_of_issue',
                                          )
                                        : `airline-date-of-issue-${flatIndex}`
                                }
                                value={member.date_of_issue ?? ''}
                                fromYear={new Date().getFullYear() - 10}
                                onChange={(value) =>
                                    updateMemberField(
                                        flatIndex,
                                        'date_of_issue',
                                        value,
                                    )
                                }
                                disabled={disabled}
                            />
                            {renderCellError(flatIndex, 'date_of_issue')}
                        </TableCell>
                        <TableCell>
                            <DatePickerField
                                id={
                                    errorPrefix
                                        ? getErrorPath(
                                              flatIndex,
                                              'date_of_expiry',
                                          )
                                        : `airline-date-of-expiry-${flatIndex}`
                                }
                                value={member.date_of_expiry ?? ''}
                                toYear={new Date().getFullYear() + 10}
                                onChange={(value) =>
                                    updateMemberField(
                                        flatIndex,
                                        'date_of_expiry',
                                        value,
                                    )
                                }
                                disabled={disabled}
                            />
                            {renderCellError(flatIndex, 'date_of_expiry')}
                        </TableCell>
                        <TableCell>
                            <ProperInput
                                id={
                                    errorPrefix
                                        ? getErrorPath(flatIndex, 'issue_place')
                                        : undefined
                                }
                                value={member.issue_place ?? ''}
                                onCommit={(value) =>
                                    updateMemberField(
                                        flatIndex,
                                        'issue_place',
                                        value,
                                    )
                                }
                                disabled={disabled}
                                size="default"
                            />
                            {renderCellError(flatIndex, 'issue_place')}
                        </TableCell>
                    </>
                )}

                {mode === 'course_collection' && (
                    <>
                        <TableCell className="text-center">
                            <Checkbox
                                checked={!!member.course_1}
                                onCheckedChange={(checked) =>
                                    updateFlatRow(
                                        flatIndex,
                                        'course_1',
                                        checked === true,
                                    )
                                }
                                disabled={disabled}
                                className="mx-auto h-6 w-6 border-2"
                                aria-label="Course 1"
                            />
                        </TableCell>
                        <TableCell className="text-center">
                            <Checkbox
                                checked={!!member.course_2}
                                onCheckedChange={(checked) =>
                                    updateFlatRow(
                                        flatIndex,
                                        'course_2',
                                        checked === true,
                                    )
                                }
                                disabled={disabled}
                                className="mx-auto h-6 w-6 border-2"
                                aria-label="Course 2"
                            />
                        </TableCell>
                        <TableCell className="text-center">
                            <Checkbox
                                checked={!!member.lanyard}
                                onCheckedChange={(checked) =>
                                    updateFlatRow(
                                        flatIndex,
                                        'lanyard',
                                        checked === true,
                                    )
                                }
                                disabled={disabled}
                                className="mx-auto h-6 w-6 border-2"
                                aria-label="Lanyard"
                            />
                        </TableCell>
                        <TableCell className="text-center">
                            <Checkbox
                                checked={!!member.luggage_tag}
                                onCheckedChange={(checked) =>
                                    updateFlatRow(
                                        flatIndex,
                                        'luggage_tag',
                                        checked === true,
                                    )
                                }
                                disabled={disabled}
                                className="mx-auto h-6 w-6 border-2"
                                aria-label="Luggage Tag"
                            />
                        </TableCell>
                        <TableCell className="text-center">
                            <Checkbox
                                checked={!!member.cabin_tag}
                                onCheckedChange={(checked) =>
                                    updateFlatRow(
                                        flatIndex,
                                        'cabin_tag',
                                        checked === true,
                                    )
                                }
                                disabled={disabled}
                                className="mx-auto h-6 w-6 border-2"
                                aria-label="Cabin Tag"
                            />
                        </TableCell>
                        <TableCell className="text-center">
                            <Checkbox
                                checked={!!member.passport_cover}
                                onCheckedChange={(checked) =>
                                    updateFlatRow(
                                        flatIndex,
                                        'passport_cover',
                                        checked === true,
                                    )
                                }
                                disabled={disabled}
                                className="mx-auto h-6 w-6 border-2"
                                aria-label="Passport Cover"
                            />
                        </TableCell>
                        <TableCell className="text-center">
                            <Checkbox
                                checked={!!member.umrah_guidebook}
                                onCheckedChange={(checked) =>
                                    updateFlatRow(
                                        flatIndex,
                                        'umrah_guidebook',
                                        checked === true,
                                    )
                                }
                                disabled={disabled}
                                className="mx-auto h-6 w-6 border-2"
                                aria-label="Umrah Guidebook"
                            />
                        </TableCell>
                        <TableCell className="text-center">
                            <Checkbox
                                checked={!!member.sling_bag}
                                onCheckedChange={(checked) =>
                                    updateFlatRow(
                                        flatIndex,
                                        'sling_bag',
                                        checked === true,
                                    )
                                }
                                disabled={disabled}
                                className="mx-auto h-6 w-6 border-2"
                                aria-label="Sling Bag"
                            />
                        </TableCell>
                        <TableCell className="text-center">
                            <Checkbox
                                checked={!!member.cabin_size_luggage}
                                onCheckedChange={(checked) =>
                                    updateFlatRow(
                                        flatIndex,
                                        'cabin_size_luggage',
                                        checked === true,
                                    )
                                }
                                disabled={disabled}
                                className="mx-auto h-6 w-6 border-2"
                                aria-label="Cabin Size Luggage"
                            />
                        </TableCell>
                        <TableCell className="text-center">
                            <Checkbox
                                checked={!!member.umrah_essentials}
                                onCheckedChange={(checked) =>
                                    updateFlatRow(
                                        flatIndex,
                                        'umrah_essentials',
                                        checked === true,
                                    )
                                }
                                disabled={disabled}
                                className="mx-auto h-6 w-6 border-2"
                                aria-label="Umrah Essentials"
                            />
                        </TableCell>
                    </>
                )}

                {mode !== 'course_collection' && (
                    <TableCell>
                        <ProperInput
                            id={
                                errorPrefix
                                    ? getErrorPath(flatIndex, 'remarks')
                                    : undefined
                            }
                            value={member.remarks ?? ''}
                            onCommit={(value) =>
                                updateMemberField(flatIndex, 'remarks', value)
                            }
                            disabled={remarksDisabled}
                            textarea
                            size="default"
                            className="min-h-[70px]"
                        />
                        {renderCellError(flatIndex, 'remarks')}
                    </TableCell>
                )}

                {mode !== 'course_collection' && (
                    <TableCell>
                        {mode === 'members' ||
                        mode === 'room' ||
                        mode === 'room_check' ? (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        className="h-8 w-8 p-0"
                                    >
                                        <span className="sr-only">
                                            Open actions
                                        </span>
                                        <EllipsisVertical className="h-4 w-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    {hasMemberAction ? (
                                        <>
                                            {canSplitMemberFromGroup && (
                                                <DropdownMenuItem
                                                    onClick={() =>
                                                        splitMemberIntoNewRoomGroup(
                                                            flatIndex,
                                                            item.groupKey,
                                                        )
                                                    }
                                                >
                                                    Split Member From Group
                                                </DropdownMenuItem>
                                            )}
                                            {canShowMoveToHoldingAction && (
                                                <DropdownMenuItem
                                                    onClick={() =>
                                                        onMoveToHolding?.(
                                                            member,
                                                        )
                                                    }
                                                >
                                                    Move to Holding
                                                </DropdownMenuItem>
                                            )}
                                            {canShowToggleRoomAction && (
                                                <DropdownMenuItem
                                                    onClick={
                                                        toggleRoomAssignment
                                                    }
                                                >
                                                    {isRoomAssigned
                                                        ? 'Unassign Room'
                                                        : 'Assign Room'}
                                                </DropdownMenuItem>
                                            )}
                                            {canShowUnassignByLocation && (
                                                <DropdownMenuSub>
                                                    <DropdownMenuSubTrigger>
                                                        Unassign by Location
                                                    </DropdownMenuSubTrigger>
                                                    <DropdownMenuSubContent>
                                                        {assignedLocations.map(
                                                            (location) => {
                                                                const option =
                                                                    roomAssignmentOptions.find(
                                                                        (
                                                                            item,
                                                                        ) =>
                                                                            normalizeRoomLocationKey(
                                                                                item.key,
                                                                            ) ===
                                                                            location,
                                                                    );

                                                                return (
                                                                    <DropdownMenuItem
                                                                        key={`unassign-${location}`}
                                                                        onClick={() =>
                                                                            setMemberRoomAssignment(
                                                                                'unassign',
                                                                                location,
                                                                            )
                                                                        }
                                                                    >
                                                                        {option?.label ??
                                                                            location}
                                                                    </DropdownMenuItem>
                                                                );
                                                            },
                                                        )}
                                                        <DropdownMenuItem
                                                            onClick={() =>
                                                                setMemberRoomAssignment(
                                                                    'unassign',
                                                                    'all',
                                                                )
                                                            }
                                                        >
                                                            All
                                                        </DropdownMenuItem>
                                                    </DropdownMenuSubContent>
                                                </DropdownMenuSub>
                                            )}

                                            {canShowAssignByLocation && (
                                                <DropdownMenuSub>
                                                    <DropdownMenuSubTrigger>
                                                        Assign by Location
                                                    </DropdownMenuSubTrigger>
                                                    <DropdownMenuSubContent>
                                                        {explicitUnassignedLocations.map(
                                                            (location) => {
                                                                const option =
                                                                    roomAssignmentOptions.find(
                                                                        (
                                                                            item,
                                                                        ) =>
                                                                            normalizeRoomLocationKey(
                                                                                item.key,
                                                                            ) ===
                                                                            location,
                                                                    );

                                                                return (
                                                                    <DropdownMenuItem
                                                                        key={`assign-${location}`}
                                                                        onClick={() =>
                                                                            setMemberRoomAssignment(
                                                                                'assign',
                                                                                location,
                                                                            )
                                                                        }
                                                                    >
                                                                        {option?.label ??
                                                                            location}
                                                                    </DropdownMenuItem>
                                                                );
                                                            },
                                                        )}
                                                        {canShowAssignAllOption && (
                                                            <DropdownMenuItem
                                                                onClick={() =>
                                                                    setMemberRoomAssignment(
                                                                        'assign',
                                                                        'all',
                                                                    )
                                                                }
                                                            >
                                                                All
                                                            </DropdownMenuItem>
                                                        )}
                                                    </DropdownMenuSubContent>
                                                </DropdownMenuSub>
                                            )}
                                        </>
                                    ) : (
                                        <DropdownMenuItem disabled>
                                            No actions available
                                        </DropdownMenuItem>
                                    )}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        ) : (
                            <span className="text-muted-foreground">-</span>
                        )}
                    </TableCell>
                )}
            </TableRow>
        );

        return (
            <ContextMenu>
                <ContextMenuTrigger asChild>{rowContent}</ContextMenuTrigger>
                <ContextMenuContent className="w-48">
                    {hasMemberAction ? (
                        <>
                            {canSplitMemberFromGroup && (
                                <ContextMenuItem
                                    onClick={() =>
                                        splitMemberIntoNewRoomGroup(
                                            flatIndex,
                                            item.groupKey,
                                        )
                                    }
                                >
                                    Split Member From Group
                                </ContextMenuItem>
                            )}
                            {canShowMoveToHoldingAction && (
                                <ContextMenuItem
                                    onClick={() => onMoveToHolding?.(member)}
                                >
                                    Move to Holding
                                </ContextMenuItem>
                            )}
                            {canShowToggleRoomAction && (
                                <ContextMenuItem onClick={toggleRoomAssignment}>
                                    {isRoomAssigned
                                        ? 'Unassign Room'
                                        : 'Assign Room'}
                                </ContextMenuItem>
                            )}
                            {canShowUnassignByLocation && (
                                <>
                                    <ContextMenuSub>
                                        <ContextMenuSubTrigger>
                                            Unassign by Location
                                        </ContextMenuSubTrigger>
                                        <ContextMenuSubContent>
                                            {assignedLocations.map(
                                                (location) => {
                                                    const option =
                                                        roomAssignmentOptions.find(
                                                            (item) =>
                                                                normalizeRoomLocationKey(
                                                                    item.key,
                                                                ) === location,
                                                        );

                                                    return (
                                                        <ContextMenuItem
                                                            key={`ctx-unassign-${location}`}
                                                            onClick={() =>
                                                                setMemberRoomAssignment(
                                                                    'unassign',
                                                                    location,
                                                                )
                                                            }
                                                        >
                                                            {option?.label ??
                                                                location}
                                                        </ContextMenuItem>
                                                    );
                                                },
                                            )}
                                            <ContextMenuItem
                                                onClick={() =>
                                                    setMemberRoomAssignment(
                                                        'unassign',
                                                        'all',
                                                    )
                                                }
                                            >
                                                All
                                            </ContextMenuItem>
                                        </ContextMenuSubContent>
                                    </ContextMenuSub>
                                </>
                            )}
                            {canShowAssignByLocation && (
                                <ContextMenuSub>
                                    <ContextMenuSubTrigger>
                                        Assign by Location
                                    </ContextMenuSubTrigger>
                                    <ContextMenuSubContent>
                                        {explicitUnassignedLocations.map(
                                            (location) => {
                                                const option =
                                                    roomAssignmentOptions.find(
                                                        (item) =>
                                                            normalizeRoomLocationKey(
                                                                item.key,
                                                            ) === location,
                                                    );

                                                return (
                                                    <ContextMenuItem
                                                        key={`ctx-assign-${location}`}
                                                        onClick={() =>
                                                            setMemberRoomAssignment(
                                                                'assign',
                                                                location,
                                                            )
                                                        }
                                                    >
                                                        {option?.label ??
                                                            location}
                                                    </ContextMenuItem>
                                                );
                                            },
                                        )}
                                        {canShowAssignAllOption && (
                                            <ContextMenuItem
                                                onClick={() =>
                                                    setMemberRoomAssignment(
                                                        'assign',
                                                        'all',
                                                    )
                                                }
                                            >
                                                All
                                            </ContextMenuItem>
                                        )}
                                    </ContextMenuSubContent>
                                </ContextMenuSub>
                            )}
                        </>
                    ) : (
                        <ContextMenuItem disabled>
                            No actions available
                        </ContextMenuItem>
                    )}
                </ContextMenuContent>
            </ContextMenu>
        );
    };

    const renderCourseCollectionRow = (
        row: MemberWithUI & { _rowId: string },
        index: number,
    ) => {
        const toggleCheck = (field: keyof MemberWithUI) => {
            const currentValue = row[field];
            updateFlatRow(index, field, !currentValue);
        };

        const rowContent = (
            <TableRow key={row._rowId} className="odd:bg-muted/35">
                <TableCell>{row.sn ?? index + 1}</TableCell>
                <TableCell>
                    <span className="font-medium">
                        {row.name_as_per_passport ?? '-'}
                    </span>
                </TableCell>
                <TableCell className="text-center">
                    <Checkbox
                        checked={!!row.course_1}
                        onCheckedChange={() => toggleCheck('course_1')}
                        disabled={disabled}
                        className="mx-auto h-6 w-6 border-2"
                    />
                </TableCell>
                <TableCell className="text-center">
                    <Checkbox
                        checked={!!row.course_2}
                        onCheckedChange={() => toggleCheck('course_2')}
                        disabled={disabled}
                        className="mx-auto h-6 w-6 border-2"
                    />
                </TableCell>
                <TableCell className="text-center">
                    <Checkbox
                        checked={!!row.lanyard}
                        onCheckedChange={() => toggleCheck('lanyard')}
                        disabled={disabled}
                        className="mx-auto h-6 w-6 border-2"
                    />
                </TableCell>
                <TableCell className="text-center">
                    <Checkbox
                        checked={!!row.luggage_tag}
                        onCheckedChange={() => toggleCheck('luggage_tag')}
                        disabled={disabled}
                        className="mx-auto h-6 w-6 border-2"
                    />
                </TableCell>
                <TableCell className="text-center">
                    <Checkbox
                        checked={!!row.cabin_tag}
                        onCheckedChange={() => toggleCheck('cabin_tag')}
                        disabled={disabled}
                        className="mx-auto h-6 w-6 border-2"
                    />
                </TableCell>
                <TableCell className="text-center">
                    <Checkbox
                        checked={!!row.passport_cover}
                        onCheckedChange={() => toggleCheck('passport_cover')}
                        disabled={disabled}
                        className="mx-auto h-6 w-6 border-2"
                    />
                </TableCell>
                <TableCell className="text-center">
                    <Checkbox
                        checked={!!row.umrah_guidebook}
                        onCheckedChange={() => toggleCheck('umrah_guidebook')}
                        disabled={disabled}
                        className="mx-auto h-6 w-6 border-2"
                    />
                </TableCell>
                <TableCell className="text-center">
                    <Checkbox
                        checked={!!row.sling_bag}
                        onCheckedChange={() => toggleCheck('sling_bag')}
                        disabled={disabled}
                        className="mx-auto h-6 w-6 border-2"
                    />
                </TableCell>
                <TableCell className="text-center">
                    <Checkbox
                        checked={!!row.cabin_size_luggage}
                        onCheckedChange={() =>
                            toggleCheck('cabin_size_luggage')
                        }
                        disabled={disabled}
                        className="mx-auto h-6 w-6 border-2"
                    />
                </TableCell>
                <TableCell className="text-center">
                    <Checkbox
                        checked={!!row.umrah_essentials}
                        onCheckedChange={() => toggleCheck('umrah_essentials')}
                        disabled={disabled}
                        className="mx-auto h-6 w-6 border-2"
                    />
                </TableCell>
                <TableCell>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                className="h-8 w-8 p-0"
                            >
                                <span className="sr-only">Open actions</span>
                                <EllipsisVertical className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem disabled>
                                No actions available
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </TableCell>
            </TableRow>
        );

        return (
            <ContextMenu key={row._rowId}>
                <ContextMenuTrigger asChild>{rowContent}</ContextMenuTrigger>
                <ContextMenuContent className="w-48">
                    <ContextMenuItem disabled>
                        No actions available
                    </ContextMenuItem>
                </ContextMenuContent>
            </ContextMenu>
        );
    };

    const renderAirlineRow = (
        row: MemberWithUI & { _rowId: string },
        index: number,
    ) => {
        const rowContent = (
            <TableRow key={row._rowId} className="odd:bg-muted/40">
                <TableCell>{row.sn ?? index + 1}</TableCell>
                <TableCell>
                    <ProperInput
                        id={
                            errorPrefix
                                ? getErrorPath(index, 'name_as_per_passport')
                                : undefined
                        }
                        value={row.name_as_per_passport ?? ''}
                        onCommit={(value) =>
                            updateFlatRow(index, 'name_as_per_passport', value)
                        }
                        disabled={disabled}
                        size="default"
                    />
                    {renderCellError(index, 'name_as_per_passport')}
                </TableCell>
                <TableCell>
                    <ProperInput
                        id={
                            errorPrefix
                                ? getErrorPath(index, 'passport_number')
                                : undefined
                        }
                        value={row.passport_number ?? ''}
                        onCommit={(value) =>
                            updateFlatRow(index, 'passport_number', value)
                        }
                        disabled={disabled}
                        size="default"
                    />
                    {renderCellError(index, 'passport_number')}
                </TableCell>
                <TableCell>
                    <ProperInput
                        id={
                            errorPrefix
                                ? getErrorPath(index, 'nationality')
                                : undefined
                        }
                        value={row.nationality ?? ''}
                        onCommit={(value) =>
                            updateFlatRow(index, 'nationality', value)
                        }
                        disabled={disabled}
                        size="default"
                    />
                    {renderCellError(index, 'nationality')}
                </TableCell>
                <TableCell>
                    <SelectCell
                        value={row.gender ?? ''}
                        placeholder="Gender"
                        onValueChange={(value) =>
                            updateFlatRow(index, 'gender', value)
                        }
                        disabled={disabled}
                        options={GENDER_OPTIONS}
                    />
                    {renderCellError(index, 'gender')}
                </TableCell>
                <TableCell>
                    <DatePickerField
                        id={
                            errorPrefix
                                ? getErrorPath(index, 'date_of_birth')
                                : `airline-flat-date-of-birth-${index}`
                        }
                        value={row.date_of_birth ?? ''}
                        fromYear={new Date().getFullYear() - 100}
                        onChange={(value) =>
                            updateFlatRow(index, 'date_of_birth', value)
                        }
                        disabled={disabled}
                    />
                    {renderCellError(index, 'date_of_birth')}
                </TableCell>
                <TableCell>
                    <DatePickerField
                        id={
                            errorPrefix
                                ? getErrorPath(index, 'date_of_issue')
                                : `airline-flat-date-of-issue-${index}`
                        }
                        value={row.date_of_issue ?? ''}
                        onChange={(value) =>
                            updateFlatRow(index, 'date_of_issue', value)
                        }
                        disabled={disabled}
                    />
                    {renderCellError(index, 'date_of_issue')}
                </TableCell>
                <TableCell>
                    <DatePickerField
                        id={
                            errorPrefix
                                ? getErrorPath(index, 'date_of_expiry')
                                : `airline-flat-date-of-expiry-${index}`
                        }
                        value={row.date_of_expiry ?? ''}
                        onChange={(value) =>
                            updateFlatRow(index, 'date_of_expiry', value)
                        }
                        disabled={disabled}
                    />
                    {renderCellError(index, 'date_of_expiry')}
                </TableCell>
                <TableCell>
                    <ProperInput
                        id={
                            errorPrefix
                                ? getErrorPath(index, 'issue_place')
                                : undefined
                        }
                        value={row.issue_place ?? ''}
                        onCommit={(value) =>
                            updateFlatRow(index, 'issue_place', value)
                        }
                        disabled={disabled}
                        size="default"
                    />
                    {renderCellError(index, 'issue_place')}
                </TableCell>
                <TableCell>
                    <ProperInput
                        id={
                            errorPrefix
                                ? getErrorPath(index, 'remarks')
                                : undefined
                        }
                        value={row.remarks ?? ''}
                        onCommit={(value) =>
                            updateFlatRow(index, 'remarks', value)
                        }
                        disabled={disabled}
                        textarea
                        size="default"
                        className="min-h-[70px]"
                    />
                    {renderCellError(index, 'remarks')}
                </TableCell>
                <TableCell>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                className="h-8 w-8 p-0"
                            >
                                <span className="sr-only">Open actions</span>
                                <EllipsisVertical className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem disabled>
                                No actions available
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </TableCell>
            </TableRow>
        );

        return (
            <ContextMenu key={row._rowId}>
                <ContextMenuTrigger asChild>{rowContent}</ContextMenuTrigger>
                <ContextMenuContent className="w-48">
                    <ContextMenuItem disabled>
                        No actions available
                    </ContextMenuItem>
                </ContextMenuContent>
            </ContextMenu>
        );
    };

    const dndIds = isGrouped
        ? visibleItems.map((visibleItem) => visibleItem.dndId)
        : flatRows.map((row) => row._rowId);

    return (
        <div className="overflow-hidden rounded-md border">
            {isGrouped && groups.length > 0 && (
                <div className="flex items-center justify-end gap-2 border-b bg-muted/20 px-3 py-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={expandAllGroups}
                    >
                        Expand All
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={collapseAllGroups}
                    >
                        Collapse All
                    </Button>
                </div>
            )}
            <DndContext
                sensors={sensors}
                collisionDetection={closestCenter}
                onDragEnd={handleDragEnd}
            >
                <SortableContext
                    items={dndIds}
                    strategy={verticalListSortingStrategy}
                >
                    <Table
                        className={cn(
                            'min-w-[1600px]',
                            '[&_td]:align-top [&_th]:align-middle',
                            // '[&_td]:p-1 [&_th]:px-1 [&_th]:py-2',
                            // '[&_tfoot_td]:p-2',
                        )}
                    >
                        <TableHeader>{renderTableHeaders()}</TableHeader>

                        <TableBody>
                            {isGrouped
                                ? visibleItems.map((item) => (
                                      <SortableRow
                                          key={item.dndId}
                                          id={item.dndId}
                                          disabled={!allowReorder || disabled}
                                      >
                                          {(sortableProps) =>
                                              item.isGroupHeader
                                                  ? renderGroupHeader(
                                                        item,
                                                        sortableProps,
                                                    )
                                                  : renderMemberRow(
                                                        item,
                                                        sortableProps,
                                                    )
                                          }
                                      </SortableRow>
                                  ))
                                : flatRows.map((row, index) => {
                                      if (mode === 'course_collection') {
                                          return renderCourseCollectionRow(
                                              row,
                                              index,
                                          );
                                      }

                                      return renderAirlineRow(row, index);
                                  })}

                            {(isGrouped
                                ? visibleItems.length
                                : flatRows.length) === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={getColumnCount()}
                                        className="py-8 text-center text-muted-foreground"
                                    >
                                        No members added yet
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </SortableContext>
            </DndContext>
        </div>
    );
}
