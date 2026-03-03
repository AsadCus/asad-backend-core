import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    closestCenter,
    DndContext,
    type DragEndEvent,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import {
    type FilterFn,
    flexRender,
    getCoreRowModel,
    useReactTable,
    type ColumnDef,
} from '@tanstack/react-table';
import { GripVertical } from 'lucide-react';
import { useMemo } from 'react';
import { type TravelerSchema } from '../schema';

type TravelerDndRow = TravelerSchema & {
    rowId: string;
};

interface TravelersDndDatatableProps {
    rows: TravelerSchema[];
    disabled?: boolean;
    onReorder: (rows: TravelerSchema[]) => void;
}

function getRowId(row: TravelerSchema, index: number): string {
    return String(
        row.customer_confirmation_member_id ?? row.customer_id ?? row.id ?? index,
    );
}

function SortableBodyRow({
    rowId,
    disabled,
    children,
}: {
    rowId: string;
    disabled: boolean;
    children: (
        props: Pick<ReturnType<typeof useSortable>, 'setNodeRef' | 'attributes' | 'listeners' | 'transform' | 'transition'>,
    ) => React.ReactNode;
}) {
    const sortable = useSortable({
        id: rowId,
        disabled,
    });

    return children(sortable);
}

export default function TravelersDndDatatable({
    rows,
    disabled = false,
    onReorder,
}: TravelersDndDatatableProps) {
    const tableRows = useMemo<TravelerDndRow[]>(() => {
        return rows.map((row, index) => ({
            ...row,
            rowId: getRowId(row, index),
        }));
    }, [rows]);

    const sensors = useSensors(
        useSensor(PointerSensor, {
            activationConstraint: { distance: 6 },
        }),
    );

    const columns = useMemo<ColumnDef<TravelerDndRow>[]>(
        () => [
            {
                id: 'drag',
                header: '',
                cell: () => (
                    <span className="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-background">
                        <GripVertical className="h-4 w-4 text-muted-foreground" />
                    </span>
                ),
            },
            {
                accessorKey: 'sn',
                header: 'S/N',
                cell: ({ row }) => row.original.sn ?? row.index + 1,
            },
            {
                accessorKey: 'name_as_per_passport',
                header: 'Name',
                cell: ({ row }) => row.original.name_as_per_passport || '-',
            },
            {
                accessorKey: 'passport_no',
                header: 'Passport No',
                cell: ({ row }) => row.original.passport_no || row.original.ppt_no || '-',
            },
            {
                accessorKey: 'gender',
                header: 'Gender',
                cell: ({ row }) => row.original.gender || '-',
            },
            {
                accessorKey: 'age',
                header: 'Age',
                cell: ({ row }) => row.original.age ?? '-',
            },
            {
                accessorKey: 'contact_no',
                header: 'Contact',
                cell: ({ row }) => row.original.contact_no || '-',
            },
        ],
        [],
    );

    const table = useReactTable({
        data: tableRows,
        columns,
        filterFns: {
            includesValue: ((row, columnId, filterValue: string[]) => {
                if (!filterValue || filterValue.length === 0) {
                    return true;
                }

                const rawValue = row.getValue<unknown>(columnId);

                if (rawValue == null) {
                    return false;
                }

                return filterValue.includes(String(rawValue));
            }) as FilterFn<TravelerDndRow>,
            dateRangeFilter: (() => true) as FilterFn<TravelerDndRow>,
        },
        getCoreRowModel: getCoreRowModel(),
    });

    const handleDragEnd = (event: DragEndEvent): void => {
        const { active, over } = event;

        if (!over || active.id === over.id || disabled) {
            return;
        }

        const oldIndex = tableRows.findIndex((row) => row.rowId === active.id);
        const newIndex = tableRows.findIndex((row) => row.rowId === over.id);

        if (oldIndex < 0 || newIndex < 0) {
            return;
        }

        const moved = arrayMove(rows, oldIndex, newIndex).map((row, index) => ({
            ...row,
            sn: index + 1,
        }));

        onReorder(moved);
    };

    return (
        <div className="overflow-hidden rounded-md border">
            <DndContext
                sensors={sensors}
                collisionDetection={closestCenter}
                onDragEnd={handleDragEnd}
            >
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id}>
                                {headerGroup.headers.map((header) => (
                                    <TableHead
                                        key={header.id}
                                        className={
                                            header.column.id === 'drag'
                                                ? 'w-[52px]'
                                                : undefined
                                        }
                                    >
                                        {header.isPlaceholder
                                            ? null
                                            : flexRender(
                                                  header.column.columnDef.header,
                                                  header.getContext(),
                                              )}
                                    </TableHead>
                                ))}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        <SortableContext
                            items={tableRows.map((row) => row.rowId)}
                            strategy={verticalListSortingStrategy}
                        >
                            {table.getRowModel().rows.map((row) => (
                                <SortableBodyRow
                                    key={row.id}
                                    rowId={row.original.rowId}
                                    disabled={disabled}
                                >
                                    {({
                                        setNodeRef,
                                        attributes,
                                        listeners,
                                        transform,
                                        transition,
                                    }) => (
                                        <TableRow
                                            ref={setNodeRef}
                                            style={{
                                                transform:
                                                    CSS.Transform.toString(transform),
                                                transition,
                                            }}
                                            className="odd:bg-muted/50"
                                        >
                                            {row.getVisibleCells().map((cell) => (
                                                <TableCell
                                                    key={cell.id}
                                                    className={
                                                        cell.column.id ===
                                                        'drag'
                                                            ? 'w-[52px]'
                                                            : undefined
                                                    }
                                                >
                                                    {cell.column.id === 'drag' ? (
                                                        <button
                                                            type="button"
                                                            className="cursor-grab active:cursor-grabbing"
                                                            disabled={disabled}
                                                            {...attributes}
                                                            {...listeners}
                                                        >
                                                            {flexRender(
                                                                cell.column
                                                                    .columnDef
                                                                    .cell,
                                                                cell.getContext(),
                                                            )}
                                                        </button>
                                                    ) : (
                                                        flexRender(
                                                            cell.column.columnDef
                                                                .cell,
                                                            cell.getContext(),
                                                        )
                                                    )}
                                                </TableCell>
                                            ))}
                                        </TableRow>
                                    )}
                                </SortableBodyRow>
                            ))}
                        </SortableContext>
                    </TableBody>
                </Table>
            </DndContext>
        </div>
    );
}
