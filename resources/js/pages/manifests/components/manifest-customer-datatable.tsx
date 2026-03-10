import { ProperInput } from '@/components/proper-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
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
import { ChevronDown, ChevronRight, GripVertical } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { type TravelerWithUI } from '../types';

const ROOM_TYPE_OPTIONS = ['Quad', 'Triple', 'Double', 'Single'];
const BED_TYPE_OPTIONS = ['Single', 'King', 'Twin'];
const MEAL_OPTIONS = ['Breakfast Only', 'Half Board', 'Full Board'];
const GENDER_OPTIONS = ['male', 'female', 'other'];

const ROOM_TYPE_CAPACITY: Record<string, number> = {
    quad: 4,
    triple: 3,
    double: 2,
    single: 1,
};

function getCapacityForRoomType(roomType?: string): number {
    if (!roomType) return Infinity;
    return ROOM_TYPE_CAPACITY[roomType.toLowerCase()] ?? Infinity;
}

type TableMode = 'travelers' | 'room' | 'airline';

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

interface ManifestCustomerDatatableProps {
    mode: TableMode;
    rows: TravelerWithUI[];
    disabled?: boolean;
    allowReorder?: boolean;
    onRowsChange: (rows: TravelerWithUI[]) => void;
    onMoveToHolding?: (traveler: TravelerWithUI) => void;
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

export default function ManifestCustomerDatatable({
    mode,
    rows,
    disabled = false,
    allowReorder = false,
    onRowsChange,
    onMoveToHolding,
}: ManifestCustomerDatatableProps) {
    const isGrouped =
        mode === 'travelers' || mode === 'room' || mode === 'airline';

    // ──── Group computation ────
    const groups = useMemo<GroupData[]>(() => {
        if (!isGrouped) return [];

        const map = new Map<string, GroupMember[]>();
        rows.forEach((row, flatIndex) => {
            const key =
                row.sharing_group_key ??
                `solo-${row.customer_confirmation_member_id ?? flatIndex}`;
            if (!map.has(key)) map.set(key, []);
            map.get(key)!.push({ traveler: row, flatIndex });
        });

        return Array.from(map.entries()).map(([key, members]) => ({
            key,
            members,
        }));
    }, [rows, isGrouped]);

    // ──── Expanded state (default: all expanded) ────
    const [expanded, setExpanded] = useState<Record<string, boolean>>({});

    useEffect(() => {
        setExpanded((prev) => {
            const next = { ...prev };
            let changed = false;
            groups.forEach((g) => {
                if (!(g.key in next)) {
                    next[g.key] = true;
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

    // ──── Visible items for grouped modes ────
    const visibleItems = useMemo<VisibleItem[]>(() => {
        if (!isGrouped) return [];

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
                group.members.forEach((m) => {
                    items.push({
                        dndId: toMemberDndId(m.traveler, m.flatIndex),
                        isGroupHeader: false,
                        groupKey: group.key,
                        groupIndex,
                        traveler: m.traveler,
                        flatIndex: m.flatIndex,
                        memberCount: 0,
                    });
                });
            }
        });

        return items;
    }, [groups, expanded, isGrouped]);

    // ──── Flat rows for airline mode ────
    const flatRows = useMemo(() => {
        if (isGrouped) return [];
        return rows.map((row, index) => ({
            ...row,
            _rowId: toMemberId(row, index),
        }));
    }, [rows, isGrouped]);

    // ──── DnD sensors ────
    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
    );

    // ──── Update handlers ────
    const updateMemberField = (
        flatIndex: number,
        field: keyof TravelerWithUI,
        value: string | number,
    ) => {
        const next = [...rows];
        next[flatIndex] = { ...next[flatIndex], [field]: value };
        onRowsChange(next);
    };

    const updateGroupField = (
        groupKey: string,
        field: keyof TravelerWithUI,
        value: string | number,
    ) => {
        const next = rows.map((row) => {
            const key =
                row.sharing_group_key ??
                `solo-${row.customer_confirmation_member_id}`;
            if (key === groupKey) {
                return { ...row, [field]: value };
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
        next[index] = { ...next[index], [field]: value };
        onRowsChange(next);
    };

    // ──── Rebuild flat rows from group structure ────
    const emitReorderedRows = (reorderedGroups: GroupData[]) => {
        let counter = 0;
        const result: TravelerWithUI[] = reorderedGroups.flatMap((group) =>
            group.members.map((m) => ({
                ...m.traveler,
                sn: ++counter,
                sort_order: counter,
            })),
        );
        onRowsChange(result);
    };

    // ──── Find group key from a DnD ID ────
    const getGroupKeyFromDndId = (dndId: string): string | null => {
        if (dndId.startsWith('g-')) return dndId.slice(2);
        const item = visibleItems.find((vi) => vi.dndId === dndId);
        return item?.groupKey ?? null;
    };

    // ──── DnD handler ────
    const handleDragEnd = (event: DragEndEvent) => {
        if (!allowReorder || disabled) return;

        const { active, over } = event;
        if (!over || active.id === over.id) return;

        const activeId = String(active.id);
        const overId = String(over.id);

        if (isGrouped) {
            handleGroupedDrag(activeId, overId);
        } else {
            handleFlatDrag(activeId, overId);
        }
    };

    const handleFlatDrag = (activeId: string, overId: string) => {
        const oldIndex = flatRows.findIndex((r) => r._rowId === activeId);
        const newIndex = flatRows.findIndex((r) => r._rowId === overId);

        if (oldIndex < 0 || newIndex < 0) return;

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

        if (!overGroupKey) return;

        if (isActiveGroup) {
            // ── Reorder groups ──
            const activeGroupKey = activeId.slice(2);
            if (activeGroupKey === overGroupKey) return;

            const activeIdx = groups.findIndex((g) => g.key === activeGroupKey);
            const overIdx = groups.findIndex((g) => g.key === overGroupKey);

            if (activeIdx < 0 || overIdx < 0) return;

            const reordered = arrayMove(groups, activeIdx, overIdx);
            emitReorderedRows(reordered);
        } else {
            // ── Move a member ──
            const activeItem = visibleItems.find((vi) => vi.dndId === activeId);

            if (!activeItem || !activeItem.traveler) return;

            const sourceGroupKey = activeItem.groupKey;

            if (sourceGroupKey === overGroupKey) {
                reorderWithinGroup(sourceGroupKey, activeId, overId);
            } else {
                const targetGroup = groups.find((g) => g.key === overGroupKey);

                if (!targetGroup) return;

                if (mode === 'room') {
                    const roomType =
                        targetGroup.members[0]?.traveler.room_type ?? '';
                    const capacity = getCapacityForRoomType(roomType);
                    const currentCount = targetGroup.members.length;

                    if (Number.isFinite(capacity) && currentCount >= capacity) {
                        toast.error(
                            `Cannot move: ${roomType || 'this'} room is at full capacity (${capacity} pax)`,
                        );
                        return;
                    }
                }

                moveMemberToGroup(
                    activeItem,
                    sourceGroupKey,
                    overGroupKey,
                    overId,
                );
            }
        }
    };

    const reorderWithinGroup = (
        groupKey: string,
        activeId: string,
        overId: string,
    ) => {
        const group = groups.find((g) => g.key === groupKey);
        if (!group) return;

        if (overId.startsWith('g-')) return;

        const activeIdx = group.members.findIndex(
            (m) => toMemberDndId(m.traveler, m.flatIndex) === activeId,
        );
        const overIdx = group.members.findIndex(
            (m) => toMemberDndId(m.traveler, m.flatIndex) === overId,
        );

        if (activeIdx < 0 || overIdx < 0) return;

        const reorderedMembers = arrayMove(group.members, activeIdx, overIdx);
        const newGroups = groups.map((g) =>
            g.key === groupKey ? { ...g, members: reorderedMembers } : g,
        );

        emitReorderedRows(newGroups);
    };

    const moveMemberToGroup = (
        activeItem: VisibleItem,
        sourceGroupKey: string,
        targetGroupKey: string,
        overId: string,
    ) => {
        if (!activeItem.traveler) return;

        const targetGroup = groups.find((g) => g.key === targetGroupKey);
        if (!targetGroup) return;

        const targetShared = targetGroup.members[0]?.traveler;

        const movedTraveler: TravelerWithUI = {
            ...activeItem.traveler,
            sharing_group_key: targetGroupKey,
            ...(mode === 'room' && targetShared
                ? {
                      room_no: targetShared.room_no,
                      room_type: targetShared.room_type,
                      bed_type: targetShared.bed_type,
                      meal: targetShared.meal,
                  }
                : {}),
        };

        const movedMember: GroupMember = {
            traveler: movedTraveler,
            flatIndex: activeItem.flatIndex,
        };

        let insertPos = targetGroup.members.length;

        if (!overId.startsWith('g-')) {
            const overIdx = targetGroup.members.findIndex(
                (m) => toMemberDndId(m.traveler, m.flatIndex) === overId,
            );
            if (overIdx >= 0) insertPos = overIdx;
        }

        const newGroups = groups
            .map((g) => {
                if (g.key === sourceGroupKey) {
                    return {
                        ...g,
                        members: g.members.filter(
                            (m) => m.flatIndex !== activeItem.flatIndex,
                        ),
                    };
                }

                if (g.key === targetGroupKey) {
                    const updatedMembers = [...g.members];
                    updatedMembers.splice(insertPos, 0, movedMember);
                    return { ...g, members: updatedMembers };
                }

                return g;
            })
            .filter((g) => g.members.length > 0);

        setExpanded((prev) => ({ ...prev, [targetGroupKey]: true }));

        emitReorderedRows(newGroups);
    };

    // ──── Column count (for empty state colspan) ────
    const getColumnCount = (): number => {
        const drag = allowReorder ? 1 : 0;
        if (mode === 'travelers') return drag + 7;
        if (mode === 'room') return drag + 8;
        return drag + 10;
    };

    // ──── Room group info helper ────
    const getGroupRoomInfo = (groupKey: string) => {
        const group = groups.find((g) => g.key === groupKey);
        const first = group?.members[0]?.traveler;
        return {
            room_no: first?.room_no ?? '',
            room_type: first?.room_type ?? '',
            bed_type: first?.bed_type ?? '',
            meal: first?.meal ?? '',
        };
    };

    // ──── Render: Table headers ────
    const renderTableHeaders = () => (
        <TableRow>
            {allowReorder && <TableHead className="w-[52px]" />}
            <TableHead className="w-[72px]">
                {isGrouped ? '#' : 'S/N'}
            </TableHead>
            <TableHead>Name as per passport</TableHead>
            {(mode === 'travelers' || mode === 'room') && (
                <TableHead>Role</TableHead>
            )}
            {mode === 'airline' && <TableHead>Nationality</TableHead>}
            {(mode === 'travelers' || mode === 'airline') && (
                <TableHead>Passport No</TableHead>
            )}
            {mode === 'airline' && <TableHead>Gender</TableHead>}
            {mode === 'airline' && <TableHead>Date of Birth</TableHead>}
            {mode === 'airline' && <TableHead>Date of Issue</TableHead>}
            {mode === 'airline' && <TableHead>Date of Expiry</TableHead>}
            {mode === 'airline' && <TableHead>Issue Place</TableHead>}
            {mode === 'room' && <TableHead>Room No</TableHead>}
            {mode === 'room' && <TableHead>Room Type</TableHead>}
            {mode === 'room' && <TableHead>Bed Type</TableHead>}
            {mode === 'room' && <TableHead>Meal</TableHead>}
            {mode === 'travelers' && <TableHead>Status</TableHead>}
            <TableHead>Remarks</TableHead>
            {mode === 'travelers' && <TableHead>Action</TableHead>}
        </TableRow>
    );

    // ──── Render: Group header row ────
    const renderGroupHeader = (
        item: VisibleItem,
        sortableProps: ReturnType<typeof useSortable>,
    ) => {
        const { setNodeRef, transform, transition, attributes, listeners } =
            sortableProps;
        const isExpanded = expanded[item.groupKey] !== false;
        const roomInfo =
            mode === 'room' ? getGroupRoomInfo(item.groupKey) : null;
        const capacity =
            mode === 'room'
                ? getCapacityForRoomType(roomInfo?.room_type)
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
                className={cn('bg-muted/30', isExpanded && 'border-b-0')}
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
                        <span className="text-sm font-medium">
                            {mode === 'room'
                                ? `Room ${item.groupIndex + 1}`
                                : `Group ${item.groupIndex + 1}`}
                        </span>
                        <Badge
                            variant="outline"
                            className={cn(
                                'text-xs',
                                isAtCapacity &&
                                    'border-red-300 bg-red-50 text-red-700',
                            )}
                        >
                            {capacityLabel} pax
                        </Badge>
                    </div>
                </TableCell>

                {mode === 'travelers' && (
                    <>
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
                    </>
                )}

                {mode === 'room' && (
                    <>
                        <TableCell />
                        <TableCell>
                            <Input
                                value={roomInfo?.room_no ?? ''}
                                onChange={(e) =>
                                    updateGroupField(
                                        item.groupKey,
                                        'room_no',
                                        e.target.value,
                                    )
                                }
                                disabled={disabled}
                                placeholder="Room no"
                            />
                        </TableCell>
                        <TableCell>
                            <Select
                                value={roomInfo?.room_type ?? ''}
                                onValueChange={(value) =>
                                    updateGroupField(
                                        item.groupKey,
                                        'room_type',
                                        value,
                                    )
                                }
                                disabled={disabled}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Room type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {ROOM_TYPE_OPTIONS.map((option) => (
                                        <SelectItem key={option} value={option}>
                                            {option}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </TableCell>
                        <TableCell>
                            <Select
                                value={roomInfo?.bed_type ?? ''}
                                onValueChange={(value) =>
                                    updateGroupField(
                                        item.groupKey,
                                        'bed_type',
                                        value,
                                    )
                                }
                                disabled={disabled}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Bed type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {BED_TYPE_OPTIONS.map((option) => (
                                        <SelectItem key={option} value={option}>
                                            {option}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </TableCell>
                        <TableCell>
                            <Select
                                value={roomInfo?.meal ?? ''}
                                onValueChange={(value) =>
                                    updateGroupField(
                                        item.groupKey,
                                        'meal',
                                        value,
                                    )
                                }
                                disabled={disabled}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Meal" />
                                </SelectTrigger>
                                <SelectContent>
                                    {MEAL_OPTIONS.map((option) => (
                                        <SelectItem key={option} value={option}>
                                            {option}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </TableCell>
                    </>
                )}

                <TableCell />
            </TableRow>
        );
    };

    // ──── Render: Member row (child) ────
    const renderMemberRow = (
        item: VisibleItem,
        sortableProps: ReturnType<typeof useSortable>,
    ) => {
        const { setNodeRef, transform, transition, attributes, listeners } =
            sortableProps;
        const traveler = item.traveler!;
        const flatIndex = item.flatIndex;

        const itemIdx = visibleItems.indexOf(item);
        const nextItem = visibleItems[itemIdx + 1];
        const isLastChild = !nextItem || nextItem.isGroupHeader;

        return (
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
                    <Input
                        value={traveler.name_as_per_passport ?? ''}
                        onChange={(e) =>
                            updateMemberField(
                                flatIndex,
                                'name_as_per_passport',
                                e.target.value,
                            )
                        }
                        disabled={disabled}
                    />
                </TableCell>

                {(mode === 'travelers' || mode === 'room') && (
                    <TableCell>
                        <Input
                            value={traveler.role ?? traveler.relationship ?? ''}
                            onChange={(e) =>
                                updateMemberField(
                                    flatIndex,
                                    'role',
                                    e.target.value,
                                )
                            }
                            disabled={disabled}
                        />
                    </TableCell>
                )}

                {mode === 'travelers' && (
                    <TableCell>
                        <Input
                            value={
                                traveler.passport_no ?? traveler.ppt_no ?? ''
                            }
                            onChange={(e) =>
                                updateMemberField(
                                    flatIndex,
                                    'passport_no',
                                    e.target.value,
                                )
                            }
                            disabled={disabled}
                        />
                    </TableCell>
                )}

                {mode === 'travelers' && (
                    <TableCell>
                        <Badge
                            variant="outline"
                            className={cn(
                                traveler.status === 'cancelled'
                                    ? 'border-red-300 bg-red-50 text-red-700'
                                    : traveler.status === 'unavailable'
                                      ? 'border-amber-300 bg-amber-50 text-amber-700'
                                      : 'border-green-300 bg-green-50 text-green-700',
                            )}
                        >
                            {traveler.status
                                ? traveler.status
                                      .replace('_', ' ')
                                      .replace(/\b\w/g, (c) => c.toUpperCase())
                                : 'Confirmed'}
                        </Badge>
                    </TableCell>
                )}

                {mode === 'travelers' && (
                    <TableCell>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={
                                disabled ||
                                traveler.status === 'cancelled' ||
                                !traveler.customer_confirmation_member_id
                            }
                            onClick={() => onMoveToHolding?.(traveler)}
                        >
                            Move to Holding
                        </Button>
                    </TableCell>
                )}

                {mode === 'room' && (
                    <>
                        <TableCell />
                        <TableCell />
                        <TableCell />
                        <TableCell />
                    </>
                )}

                {mode === 'airline' && (
                    <>
                        <TableCell>
                            <Input
                                value={
                                    traveler.passport_no ??
                                    traveler.ppt_no ??
                                    ''
                                }
                                onChange={(e) =>
                                    updateMemberField(
                                        flatIndex,
                                        'passport_no',
                                        e.target.value,
                                    )
                                }
                                disabled={disabled}
                            />
                        </TableCell>
                        <TableCell>
                            <Input
                                value={traveler.nationality ?? ''}
                                onChange={(e) =>
                                    updateMemberField(
                                        flatIndex,
                                        'nationality',
                                        e.target.value,
                                    )
                                }
                                disabled={disabled}
                            />
                        </TableCell>
                        <TableCell>
                            <Select
                                value={traveler.gender ?? ''}
                                onValueChange={(value) =>
                                    updateMemberField(
                                        flatIndex,
                                        'gender',
                                        value,
                                    )
                                }
                                disabled={disabled}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Gender" />
                                </SelectTrigger>
                                <SelectContent>
                                    {GENDER_OPTIONS.map((option) => (
                                        <SelectItem key={option} value={option}>
                                            {option}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </TableCell>
                        <TableCell>
                            <Input
                                value={traveler.date_of_birth ?? ''}
                                onChange={(e) =>
                                    updateMemberField(
                                        flatIndex,
                                        'date_of_birth',
                                        e.target.value,
                                    )
                                }
                                disabled={disabled}
                            />
                        </TableCell>
                        <TableCell>
                            <Input
                                value={traveler.date_of_issue ?? ''}
                                onChange={(e) =>
                                    updateMemberField(
                                        flatIndex,
                                        'date_of_issue',
                                        e.target.value,
                                    )
                                }
                                disabled={disabled}
                            />
                        </TableCell>
                        <TableCell>
                            <Input
                                value={traveler.date_of_expiry ?? ''}
                                onChange={(e) =>
                                    updateMemberField(
                                        flatIndex,
                                        'date_of_expiry',
                                        e.target.value,
                                    )
                                }
                                disabled={disabled}
                            />
                        </TableCell>
                        <TableCell>
                            <Input
                                value={traveler.issue_place ?? ''}
                                onChange={(e) =>
                                    updateMemberField(
                                        flatIndex,
                                        'issue_place',
                                        e.target.value,
                                    )
                                }
                                disabled={disabled}
                            />
                        </TableCell>
                    </>
                )}

                <TableCell>
                    <ProperInput
                        value={traveler.remarks ?? ''}
                        onCommit={(value) =>
                            updateMemberField(flatIndex, 'remarks', value)
                        }
                        disabled={disabled}
                        textarea
                        size="compact"
                        className="min-h-[70px]"
                    />
                </TableCell>
            </TableRow>
        );
    };

    // ──── Render: Airline flat row ────
    const renderAirlineRow = (
        row: TravelerWithUI & { _rowId: string },
        index: number,
    ) => (
        <TableRow key={row._rowId} className="odd:bg-muted/40">
            <TableCell>{row.sn ?? index + 1}</TableCell>
            <TableCell>
                <Input
                    value={row.name_as_per_passport ?? ''}
                    onChange={(e) =>
                        updateFlatRow(
                            index,
                            'name_as_per_passport',
                            e.target.value,
                        )
                    }
                    disabled={disabled}
                />
            </TableCell>
            <TableCell>
                <Input
                    value={row.passport_no ?? row.ppt_no ?? ''}
                    onChange={(e) =>
                        updateFlatRow(index, 'passport_no', e.target.value)
                    }
                    disabled={disabled}
                />
            </TableCell>
            <TableCell>
                <Input
                    value={row.nationality ?? ''}
                    onChange={(e) =>
                        updateFlatRow(index, 'nationality', e.target.value)
                    }
                    disabled={disabled}
                />
            </TableCell>
            <TableCell>
                <Select
                    value={row.gender ?? ''}
                    onValueChange={(value) =>
                        updateFlatRow(index, 'gender', value)
                    }
                    disabled={disabled}
                >
                    <SelectTrigger>
                        <SelectValue placeholder="Gender" />
                    </SelectTrigger>
                    <SelectContent>
                        {GENDER_OPTIONS.map((option) => (
                            <SelectItem key={option} value={option}>
                                {option}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </TableCell>
            <TableCell>
                <Input
                    value={row.date_of_birth ?? ''}
                    onChange={(e) =>
                        updateFlatRow(index, 'date_of_birth', e.target.value)
                    }
                    disabled={disabled}
                />
            </TableCell>
            <TableCell>
                <Input
                    value={row.date_of_issue ?? ''}
                    onChange={(e) =>
                        updateFlatRow(index, 'date_of_issue', e.target.value)
                    }
                    disabled={disabled}
                />
            </TableCell>
            <TableCell>
                <Input
                    value={row.date_of_expiry ?? ''}
                    onChange={(e) =>
                        updateFlatRow(index, 'date_of_expiry', e.target.value)
                    }
                    disabled={disabled}
                />
            </TableCell>
            <TableCell>
                <Input
                    value={row.issue_place ?? ''}
                    onChange={(e) =>
                        updateFlatRow(index, 'issue_place', e.target.value)
                    }
                    disabled={disabled}
                />
            </TableCell>
            <TableCell>
                <ProperInput
                    value={row.remarks ?? ''}
                    onCommit={(value) => updateFlatRow(index, 'remarks', value)}
                    disabled={disabled}
                    textarea
                    size="compact"
                    className="min-h-[70px]"
                />
            </TableCell>
        </TableRow>
    );

    // ──── DnD IDs ────
    const dndIds = isGrouped
        ? visibleItems.map((vi) => vi.dndId)
        : flatRows.map((r) => r._rowId);

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
                    <Table>
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
