import { DatePickerField } from '@/components/date-picker';
import { ProperInput } from '@/components/proper-input';
import { ProperInputSelect } from '@/components/proper-input-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuTrigger,
} from '@/components/ui/context-menu';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
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
import { cn } from '@/lib/utils';
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
    GripVertical,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import {
    confirmationMemberStatusColors,
    confirmationMemberStatusLabels,
    packageCategoryOptions,
} from '../../customer/schema';
import { type TravelerWithUI } from '../types';

type TableMode = 'travelers' | 'room' | 'airline';

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

const PACKAGE_CATEGORY_LABELS = Object.fromEntries(
    packageCategoryOptions.map((option) => [option.value, option.label]),
) as Record<string, string>;

interface GroupMember {
    traveler: TravelerWithUI;
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
    traveler?: TravelerWithUI;
    flatIndex: number;
    memberCount: number;
}

interface ManifestDatatableProps {
    mode: TableMode;
    rows: TravelerWithUI[];
    disabled?: boolean;
    allowReorder?: boolean;
    errorPrefix?: string;
    roomGroupErrorPrefix?: string;
    roomGroupStartIndex?: number;
    errors?: Record<string, string>;
    onRowsChange: (rows: TravelerWithUI[]) => void;
    onMoveToHolding?: (traveler: TravelerWithUI) => void;
}

function getCapacityForSharingPlan(sharingPlan?: string): number {
    if (!sharingPlan) {
        return Infinity;
    }

    return SHARING_PLAN_CAPACITY[sharingPlan.toLowerCase()] ?? Infinity;
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

function getRoomTypeFromSharingPlan(sharingPlan?: string): string {
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

    return '';
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

function toMemberId(traveler: TravelerWithUI, flatIndex: number): string {
    if (traveler.row_key && traveler.row_key.trim().length > 0) {
        return traveler.row_key;
    }

    return String(
        traveler.customer_confirmation_member_id ??
            traveler.customer_id ??
            traveler.id ??
            `idx-${flatIndex}`,
    );
}

function toMemberDndId(traveler: TravelerWithUI, flatIndex: number): string {
    return `m-${toMemberId(traveler, flatIndex)}-${flatIndex}`;
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

function TravelerActionItems({
    disabled,
    canMoveToHolding,
    onMoveToHolding,
}: {
    disabled: boolean;
    canMoveToHolding: boolean;
    onMoveToHolding: () => void;
}) {
    return (
        <>
            <DropdownMenuItem
                disabled={!canMoveToHolding || disabled}
                onClick={onMoveToHolding}
            >
                Move to Holding
            </DropdownMenuItem>
        </>
    );
}

export default function ManifestDatatable({
    mode,
    rows,
    disabled = false,
    allowReorder = false,
    errorPrefix,
    roomGroupErrorPrefix,
    roomGroupStartIndex = 0,
    errors = {},
    onRowsChange,
    onMoveToHolding,
}: ManifestDatatableProps) {
    const isGrouped =
        mode === 'travelers' || mode === 'room' || mode === 'airline';

    const getErrorPath = useCallback(
        (flatIndex: number, field: keyof TravelerWithUI | string): string => {
            return `${errorPrefix}.${flatIndex}.${String(field)}`;
        },
        [errorPrefix],
    );

    const renderCellError = useCallback(
        (flatIndex: number, field: keyof TravelerWithUI | string) => {
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

            if (field === 'role') {
                const fallbackPath = getErrorPath(flatIndex, 'relationship');
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
            const key =
                row.sharing_group_key ??
                `solo-${row.customer_confirmation_member_id ?? flatIndex}`;

            if (!map.has(key)) {
                map.set(key, []);
            }

            map.get(key)?.push({ traveler: row, flatIndex });
        });

        return Array.from(map.entries()).map(([key, members]) => ({
            key,
            members,
        }));
    }, [rows, isGrouped]);

    const [expanded, setExpanded] = useState<Record<string, boolean>>({});

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

        groups.forEach((group, groupIndex) => {
            items.push({
                dndId: `g-${group.key}`,
                isGroupHeader: true,
                groupKey: group.key,
                groupIndex,
                flatIndex: -1,
                memberCount: group.members.length,
            });

            if (expanded[group.key] !== false) {
                group.members.forEach((member) => {
                    items.push({
                        dndId: toMemberDndId(member.traveler, member.flatIndex),
                        isGroupHeader: false,
                        groupKey: group.key,
                        groupIndex,
                        traveler: member.traveler,
                        flatIndex: member.flatIndex,
                        memberCount: 0,
                    });
                });
            }
        });

        return items;
    }, [groups, expanded, isGrouped]);

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
        field: keyof TravelerWithUI,
        value: string | number,
    ) => {
        if (
            mode === 'room' &&
            field === 'sharing_plan' &&
            typeof value === 'string'
        ) {
            const source = rows[flatIndex];
            const groupKey =
                source.sharing_group_key ??
                `solo-${source.customer_confirmation_member_id ?? flatIndex}`;
            const normalizedSharingPlan = value.toLowerCase();
            const roomType = getRoomTypeFromSharingPlan(normalizedSharingPlan);

            const next = rows.map((row, index) => {
                const rowKey =
                    row.sharing_group_key ??
                    `solo-${row.customer_confirmation_member_id ?? index}`;

                if (rowKey !== groupKey) {
                    return row;
                }

                return {
                    ...row,
                    sharing_plan: normalizedSharingPlan,
                    room_type: roomType || row.room_type,
                    bed_type: getBedTypeFromRoomType(roomType) || row.bed_type,
                };
            });

            onRowsChange(next);

            return;
        }

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
        field: keyof TravelerWithUI,
        value: string | number,
    ) => {
        const patch: Partial<TravelerWithUI> = { [field]: value };

        if (
            mode === 'room' &&
            field === 'room_type' &&
            typeof value === 'string'
        ) {
            patch.bed_type = getBedTypeFromRoomType(value);
        }

        if (
            mode === 'room' &&
            field === 'sharing_plan' &&
            typeof value === 'string'
        ) {
            const normalizedSharingPlan = value.toLowerCase();
            const roomType = getRoomTypeFromSharingPlan(normalizedSharingPlan);

            patch.sharing_plan = normalizedSharingPlan;
            patch.room_type = roomType;
            patch.bed_type = getBedTypeFromRoomType(roomType);
        }

        const next = rows.map((row) => {
            const key =
                row.sharing_group_key ??
                `solo-${row.customer_confirmation_member_id}`;

            if (key === groupKey) {
                return { ...row, ...patch };
            }

            return row;
        });

        onRowsChange(next);
    };

    const updateFlatRow = (
        index: number,
        field: keyof TravelerWithUI,
        value: string | number,
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

        const result: TravelerWithUI[] = reorderedGroups.flatMap((group) =>
            group.members.map((member) => ({
                ...member.traveler,
                sn: ++counter,
                sort_order: counter,
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
                toMemberDndId(member.traveler, member.flatIndex) === activeId,
        );
        const overIndex = group.members.findIndex(
            (member) =>
                toMemberDndId(member.traveler, member.flatIndex) === overId,
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
        if (!activeItem.traveler) {
            return;
        }

        const targetGroup = groups.find(
            (group) => group.key === targetGroupKey,
        );

        if (!targetGroup) {
            return;
        }

        const targetShared = targetGroup.members[0]?.traveler;

        const movedTraveler: TravelerWithUI = {
            ...activeItem.traveler,
            sharing_group_key: targetGroupKey,
            ...(mode === 'room' && targetShared
                ? {
                      room_relationship: targetShared.room_relationship,
                      room_label: targetShared.room_label,
                      room_no: targetShared.room_no,
                      sharing_plan: targetShared.sharing_plan,
                      room_type: targetShared.room_type,
                      bed_type: targetShared.bed_type,
                      meal: targetShared.meal,
                      room_remarks: targetShared.room_remarks,
                  }
                : {}),
        };

        const movedMember: GroupMember = {
            traveler: movedTraveler,
            flatIndex: activeItem.flatIndex,
        };

        let insertPos = targetGroup.members.length;

        if (!overId.startsWith('g-')) {
            const overIndex = targetGroup.members.findIndex(
                (member) =>
                    toMemberDndId(member.traveler, member.flatIndex) === overId,
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

        if (!activeItem || !activeItem.traveler) {
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
                activeItem.traveler.customer_confirmation_id;
            const targetConfirmationId =
                targetGroup.members[0]?.traveler.customer_confirmation_id;

            if (
                sourceConfirmationId &&
                targetConfirmationId &&
                sourceConfirmationId !== targetConfirmationId
            ) {
                toast.error(
                    'Cannot move: traveler must stay within the same customer confirmation group.',
                );

                return;
            }
        }

        if (mode === 'room') {
            const sharingPlan =
                targetGroup.members[0]?.traveler.sharing_plan ?? '';
            const targetSharingPlan = sharingPlan.toLowerCase();
            const activeSharingPlan =
                activeItem.traveler.sharing_plan?.toLowerCase() ?? '';

            if (
                activeSharingPlan !== '' &&
                targetSharingPlan !== '' &&
                activeSharingPlan !== targetSharingPlan
            ) {
                toast.error(
                    `Cannot move: traveler sharing plan (${activeSharingPlan}) must match target room sharing plan (${targetSharingPlan}).`,
                );

                return;
            }

            const capacity = getCapacityForSharingPlan(sharingPlan);
            const currentCount = targetGroup.members.length;

            if (Number.isFinite(capacity) && currentCount >= capacity) {
                toast.error(
                    `Cannot move: ${(sharingPlan || 'this').toString()} room is at full capacity (${capacity} pax)`,
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

        if (mode === 'travelers') {
            return drag + 21;
        }

        if (mode === 'room') {
            return drag + 15;
        }

        return drag + 11;
    };

    const getGroupRoomInfo = (groupKey: string) => {
        const group = groups.find((item) => item.key === groupKey);
        const first = group?.members[0]?.traveler;

        return {
            room_relationship: first?.room_relationship ?? '',
            room_label: first?.room_label ?? '',
            room_no: first?.room_no ?? '',
            sharing_plan: first?.sharing_plan ?? '',
            room_type: first?.room_type ?? '',
            bed_type: first?.bed_type ?? '',
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
            {(mode === 'travelers' || mode === 'room') && (
                <TableHead className="min-w-40">Role</TableHead>
            )}
            {mode === 'travelers' && (
                <TableHead className="min-w-40">Relation</TableHead>
            )}
            {mode === 'travelers' && (
                <TableHead className="min-w-40">Package Category</TableHead>
            )}
            {mode === 'travelers' && (
                <TableHead className="min-w-55">Date of Sign Up</TableHead>
            )}
            {mode === 'travelers' && (
                <TableHead className="min-w-40">1st Time Umrah</TableHead>
            )}
            {mode === 'travelers' && (
                <TableHead className="min-w-40">Room Type</TableHead>
            )}
            {mode === 'travelers' && (
                <TableHead className="min-w-40">Passport No</TableHead>
            )}
            {mode === 'travelers' && (
                <TableHead className="min-w-40">Gender</TableHead>
            )}
            {mode === 'travelers' && (
                <TableHead className="min-w-55">Date of Birth</TableHead>
            )}
            {mode === 'travelers' && (
                <TableHead className="min-w-20">Age</TableHead>
            )}
            {mode === 'travelers' && (
                <TableHead className="min-w-45">Contact No</TableHead>
            )}
            {mode === 'travelers' && (
                <TableHead className="min-w-55">Date of Issue</TableHead>
            )}
            {mode === 'travelers' && (
                <TableHead className="min-w-55">Date of Expiry</TableHead>
            )}
            {mode === 'travelers' && (
                <TableHead className="min-w-60">Issue Place</TableHead>
            )}
            {mode === 'travelers' && (
                <TableHead className="min-w-40">Birth Place</TableHead>
            )}
            {mode === 'travelers' && (
                <TableHead className="min-w-35">Package Price</TableHead>
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
            {mode === 'room' && (
                <TableHead className="min-w-60">Relationship</TableHead>
            )}
            {mode === 'room' && (
                <TableHead className="min-w-40">Passport No</TableHead>
            )}
            {mode === 'room' && (
                <TableHead className="min-w-40">Room Label</TableHead>
            )}
            {mode === 'room' && (
                <TableHead className="min-w-40">Room No</TableHead>
            )}
            {mode === 'room' && (
                <TableHead className="min-w-40">Sharing Plan</TableHead>
            )}
            {mode === 'room' && (
                <TableHead className="min-w-40">Room Type</TableHead>
            )}
            {mode === 'room' && (
                <TableHead className="min-w-40">Bed Type</TableHead>
            )}
            {mode === 'room' && (
                <TableHead className="min-w-55">Date of Birth</TableHead>
            )}
            {mode === 'room' && <TableHead className="min-w-20">Age</TableHead>}
            {mode === 'room' && (
                <TableHead className="min-w-40">Meal</TableHead>
            )}
            {mode === 'travelers' && <TableHead>Status</TableHead>}
            <TableHead className="min-w-60">Remarks</TableHead>
            <TableHead>Action</TableHead>
        </TableRow>
    );

    const renderGroupHeader = (
        item: VisibleItem,
        sortableProps: ReturnType<typeof useSortable>,
    ) => {
        const { setNodeRef, transform, transition, attributes, listeners } =
            sortableProps;
        const isExpanded = expanded[item.groupKey] !== false;
        const roomInfo =
            mode === 'room' ? getGroupRoomInfo(item.groupKey) : null;
        const groupConfirmationNumber =
            mode !== 'room'
                ? groups
                      .find((group) => group.key === item.groupKey)
                      ?.members?.at(0)?.traveler.customer_confirmation_number
                : null;
        const capacity =
            mode === 'room'
                ? getCapacityForSharingPlan(roomInfo?.sharing_plan)
                : Infinity;
        const capacityLabel = Number.isFinite(capacity)
            ? `${item.memberCount}/${capacity}`
            : `${item.memberCount}`;
        const isAtCapacity =
            Number.isFinite(capacity) && item.memberCount >= capacity;

        return (
            <TableRow
                ref={setNodeRef}
                style={{
                    transform: CSS.Transform.toString(transform),
                    transition,
                }}
                className={
                    (cn('bg-muted/30', isExpanded && 'border-b-0'),
                    'items-start')
                }
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
                            {mode === 'room'
                                ? (roomInfo?.room_label?.trim() ?? '') ||
                                  `Room ${item.groupIndex + 1}`
                                : `Group ${item.groupIndex + 1}`}
                        </span>
                        {mode === 'room' && (
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
                        {mode !== 'room' && groupConfirmationNumber && (
                            <Badge variant="secondary" className="text-sm">
                                {groupConfirmationNumber}
                            </Badge>
                        )}
                    </div>
                </TableCell>

                {mode === 'travelers' && (
                    <>
                        <TableCell />
                        <TableCell>
                            <ProperInput
                                value={
                                    groups.find((g) => g.key === item.groupKey)
                                        ?.members?.[0]?.traveler.relationship ??
                                    ''
                                }
                                onCommit={(value) =>
                                    updateGroupField(
                                        item.groupKey,
                                        'relationship',
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
                        <TableCell />
                    </>
                )}

                {mode === 'room' && (
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
                                disabled={disabled}
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
                                disabled={disabled}
                                placeholder={`Room ${item.groupIndex + 1}`}
                                size="default"
                            />
                        </TableCell>
                        <TableCell>
                            <ProperInput
                                value={roomInfo?.room_no ?? ''}
                                onCommit={(value) =>
                                    updateGroupField(
                                        item.groupKey,
                                        'room_no',
                                        value,
                                    )
                                }
                                disabled={disabled}
                                placeholder="Room no"
                                size="default"
                            />
                        </TableCell>
                        <TableCell>
                            <SelectCell
                                value={roomInfo?.sharing_plan ?? ''}
                                placeholder="Sharing plan"
                                onValueChange={(value) =>
                                    updateGroupField(
                                        item.groupKey,
                                        'sharing_plan',
                                        value,
                                    )
                                }
                                disabled={disabled}
                                options={SHARING_PLAN_OPTIONS}
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
                                disabled={disabled}
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
                                disabled={disabled}
                                options={BED_TYPE_OPTIONS}
                            />
                        </TableCell>
                        <TableCell>
                            <span className="text-muted-foreground">-</span>
                        </TableCell>
                        <TableCell>
                            <span className="text-muted-foreground">-</span>
                        </TableCell>
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
                                disabled={disabled}
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
                                                groups.find(
                                                    (g) =>
                                                        g.key === item.groupKey,
                                                )?.members?.[0]?.traveler
                                                    ?.room_remarks ?? ''
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

                <TableCell />
            </TableRow>
        );
    };

    const renderMemberRow = (
        item: VisibleItem,
        sortableProps: ReturnType<typeof useSortable>,
    ) => {
        const { setNodeRef, transform, transition, attributes, listeners } =
            sortableProps;
        const traveler = item.traveler!;
        const flatIndex = item.flatIndex;

        const itemIndex = visibleItems.indexOf(item);
        const nextItem = visibleItems[itemIndex + 1];
        const isLastChild = !nextItem || nextItem.isGroupHeader;

        const canMoveToHolding =
            !disabled &&
            traveler.status !== 'cancelled' &&
            !!traveler.customer_confirmation_member_id;

        const rowContent = (
            <TableRow
                ref={setNodeRef}
                style={{
                    transform: CSS.Transform.toString(transform),
                    transition,
                }}
                className={cn(!isLastChild && 'border-b-0')}
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
                    {traveler.sn ?? flatIndex + 1}
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
                        value={traveler.name_as_per_passport ?? ''}
                        onCommit={(value) =>
                            updateMemberField(
                                flatIndex,
                                'name_as_per_passport',
                                value,
                            )
                        }
                        disabled={disabled}
                        size="default"
                    />
                    {renderCellError(flatIndex, 'name_as_per_passport')}
                </TableCell>

                {(mode === 'travelers' || mode === 'room') && (
                    <TableCell>
                        <ProperInput
                            id={
                                errorPrefix
                                    ? getErrorPath(flatIndex, 'role')
                                    : undefined
                            }
                            value={traveler.role ?? traveler.relationship ?? ''}
                            onCommit={(value) =>
                                updateMemberField(flatIndex, 'role', value)
                            }
                            disabled={disabled}
                            size="default"
                        />
                        {renderCellError(flatIndex, 'role')}
                    </TableCell>
                )}

                {mode === 'travelers' && (
                    <TableCell>
                        <span className="text-muted-foreground">-</span>
                    </TableCell>
                )}

                {mode === 'travelers' && (
                    <TableCell>
                        <ProperInput
                            value={
                                PACKAGE_CATEGORY_LABELS[
                                    traveler.package_category ?? ''
                                ] ??
                                traveler.package_category ??
                                ''
                            }
                            disabled={true}
                            onCommit={() => {}}
                            size="default"
                        />
                    </TableCell>
                )}

                {mode === 'travelers' && (
                    <TableCell>
                        <ProperInput
                            value={traveler.date_of_sign_up ?? ''}
                            disabled={true}
                            onCommit={() => {}}
                            size="default"
                        />
                    </TableCell>
                )}

                {mode === 'travelers' && (
                    <TableCell>
                        <ProperInput
                            value={
                                traveler.is_first_time_umrah === null ||
                                traveler.is_first_time_umrah === undefined
                                    ? ''
                                    : traveler.is_first_time_umrah
                                      ? 'Yes'
                                      : 'No'
                            }
                            disabled={true}
                            onCommit={() => {}}
                            size="default"
                        />
                    </TableCell>
                )}

                {mode === 'travelers' && (
                    <TableCell>
                        <SelectCell
                            value={traveler.sharing_plan ?? ''}
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

                {mode === 'travelers' && (
                    <TableCell>
                        <ProperInput
                            id={
                                errorPrefix
                                    ? getErrorPath(flatIndex, 'passport_number')
                                    : undefined
                            }
                            value={traveler.passport_number ?? ''}
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

                {mode === 'travelers' && (
                    <TableCell>
                        <SelectCell
                            value={traveler.gender ?? ''}
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

                {mode === 'travelers' && (
                    <TableCell>
                        <DatePickerField
                            id={
                                errorPrefix
                                    ? getErrorPath(flatIndex, 'date_of_birth')
                                    : `main-date-of-birth-${flatIndex}`
                            }
                            value={traveler.date_of_birth ?? ''}
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

                {mode === 'travelers' && (
                    <TableCell>
                        <ProperInput
                            value={
                                traveler.age !== null &&
                                traveler.age !== undefined
                                    ? String(traveler.age)
                                    : getAgeFromDate(traveler.date_of_birth)
                            }
                            disabled={true}
                            onCommit={() => {}}
                            size="default"
                        />
                    </TableCell>
                )}

                {mode === 'travelers' && (
                    <TableCell>
                        <ProperInput
                            value={traveler.contact_no ?? ''}
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

                {mode === 'travelers' && (
                    <TableCell>
                        <DatePickerField
                            id={
                                errorPrefix
                                    ? getErrorPath(flatIndex, 'date_of_issue')
                                    : `main-date-of-issue-${flatIndex}`
                            }
                            value={traveler.date_of_issue ?? ''}
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

                {mode === 'travelers' && (
                    <TableCell>
                        <DatePickerField
                            id={
                                errorPrefix
                                    ? getErrorPath(flatIndex, 'date_of_expiry')
                                    : `main-date-of-expiry-${flatIndex}`
                            }
                            value={traveler.date_of_expiry ?? ''}
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

                {mode === 'travelers' && (
                    <TableCell>
                        <ProperInput
                            value={traveler.issue_place ?? ''}
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

                {mode === 'travelers' && (
                    <TableCell>
                        <ProperInput
                            value={traveler.birth_place ?? ''}
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

                {mode === 'travelers' && (
                    <TableCell>
                        <ProperInput
                            value={
                                traveler.package_price !== null &&
                                traveler.package_price !== undefined
                                    ? String(traveler.package_price)
                                    : ''
                            }
                            disabled={true}
                            onCommit={() => {}}
                            size="default"
                        />
                    </TableCell>
                )}

                {mode === 'travelers' && (
                    <TableCell>
                        {(() => {
                            const status = traveler.status ?? 'draft';
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
                        })()}
                    </TableCell>
                )}

                {mode === 'room' && (
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
                                value={traveler.passport_number ?? ''}
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
                        <TableCell />
                        <TableCell />
                        <TableCell>
                            <SelectCell
                                value={traveler.sharing_plan ?? ''}
                                placeholder="Sharing plan"
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
                                value={traveler.date_of_birth ?? ''}
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
                            <ProperInput
                                value={
                                    traveler.age !== null &&
                                    traveler.age !== undefined
                                        ? String(traveler.age)
                                        : getAgeFromDate(traveler.date_of_birth)
                                }
                                disabled={true}
                                onCommit={() => {}}
                                size="default"
                            />
                        </TableCell>
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
                                    traveler.passport_number ??
                                    traveler.passport_number ??
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
                                value={traveler.nationality ?? ''}
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
                                value={traveler.gender ?? ''}
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
                                value={traveler.date_of_birth ?? ''}
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
                                value={traveler.date_of_issue ?? ''}
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
                                value={traveler.date_of_expiry ?? ''}
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
                                value={traveler.issue_place ?? ''}
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

                <TableCell>
                    <ProperInput
                        id={
                            errorPrefix
                                ? getErrorPath(flatIndex, 'remarks')
                                : undefined
                        }
                        value={traveler.remarks ?? ''}
                        onCommit={(value) =>
                            updateMemberField(flatIndex, 'remarks', value)
                        }
                        disabled={disabled}
                        textarea
                        size="default"
                        className="min-h-[70px]"
                    />
                    {renderCellError(flatIndex, 'remarks')}
                </TableCell>

                <TableCell>
                    {mode === 'travelers' ? (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    className="h-8 w-8 p-0"
                                    disabled={disabled}
                                >
                                    <span className="sr-only">
                                        Open actions
                                    </span>
                                    <EllipsisVertical className="h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <TravelerActionItems
                                    disabled={disabled}
                                    canMoveToHolding={canMoveToHolding}
                                    onMoveToHolding={() =>
                                        onMoveToHolding?.(traveler)
                                    }
                                />
                            </DropdownMenuContent>
                        </DropdownMenu>
                    ) : (
                        <span className="text-muted-foreground">-</span>
                    )}
                </TableCell>
            </TableRow>
        );

        if (mode !== 'travelers') {
            return rowContent;
        }

        return (
            <ContextMenu>
                <ContextMenuTrigger asChild>{rowContent}</ContextMenuTrigger>
                <ContextMenuContent className="w-48">
                    <ContextMenuItem
                        disabled={!canMoveToHolding || disabled}
                        onClick={() => onMoveToHolding?.(traveler)}
                    >
                        Move to Holding
                    </ContextMenuItem>
                </ContextMenuContent>
            </ContextMenu>
        );
    };

    const renderAirlineRow = (
        row: TravelerWithUI & { _rowId: string },
        index: number,
    ) => (
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
                        errorPrefix ? getErrorPath(index, 'remarks') : undefined
                    }
                    value={row.remarks ?? ''}
                    onCommit={(value) => updateFlatRow(index, 'remarks', value)}
                    disabled={disabled}
                    textarea
                    size="default"
                    className="min-h-[70px]"
                />
                {renderCellError(index, 'remarks')}
            </TableCell>
            <TableCell>
                <span className="text-muted-foreground">-</span>
            </TableCell>
        </TableRow>
    );

    const dndIds = isGrouped
        ? visibleItems.map((visibleItem) => visibleItem.dndId)
        : flatRows.map((row) => row._rowId);

    return (
        <div className="overflow-hidden rounded-md border">
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
                                : flatRows.map((row, index) =>
                                      renderAirlineRow(row, index),
                                  )}

                            {(isGrouped
                                ? visibleItems.length
                                : flatRows.length) === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={getColumnCount()}
                                        className="py-8 text-center text-muted-foreground"
                                    >
                                        No travelers added yet
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
