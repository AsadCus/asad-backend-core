import { RichTextEditor } from '@/components/rich-text-editor';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    DndContext,
    DragEndEvent,
    PointerSensor,
    closestCenter,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    SortableContext,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Copy, GripVertical, Plus, Trash2 } from 'lucide-react';
import { nanoid } from 'nanoid';
import { useMemo, useState } from 'react';
import { NoteSchema } from './schema';

interface NoteFormProps {
    mode: 'master' | 'quotation' | 'invoice' | 'receipt';
    model?: string;
    notes: NoteSchema[];
    disabled?: boolean;
    onChange: (notes: NoteSchema[]) => void;
}

type SortableProps = {
    ref: (el: HTMLElement | null) => void;
    style: React.CSSProperties;
    attributes: React.HTMLAttributes<HTMLElement>;
    listeners: React.HTMLAttributes<HTMLElement>;
};

export default function NoteForm({
    mode,
    model,
    notes,
    disabled = false,
    onChange,
}: NoteFormProps) {
    const [enableDnd, setEnableDnd] = useState(true);

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
    );

    const normalizeOrder = (list: NoteSchema[]) =>
        list.map((n, i) => ({ ...n, sort_order: i + 1 }));

    const addNote = () => {
        onChange(
            normalizeOrder([
                ...notes,
                {
                    _key: nanoid(),
                    ...(mode !== 'master' ? { model: mode } : { model: model }),
                    description: '',
                    sort_order: notes.length + 1,
                },
            ]),
        );
    };

    const updateNote = (key: string, patch: Partial<NoteSchema>) => {
        onChange(notes.map((n) => (n._key === key ? { ...n, ...patch } : n)));
    };

    const duplicateNote = (index: number) => {
        const next = [...notes];
        next.splice(index + 1, 0, {
            ...notes[index],
            _key: nanoid(),
            id: undefined,
        });
        onChange(normalizeOrder(next));
    };

    const removeNote = (index: number) => {
        onChange(normalizeOrder(notes.filter((_, i) => i !== index)));
    };

    const handleDragEnd = (event: DragEndEvent) => {
        const { active, over } = event;
        if (!over || active.id === over.id) return;

        const from = notes.findIndex((n) => n._key === active.id);
        const to = notes.findIndex((n) => n._key === over.id);
        if (from === -1 || to === -1) return;

        const next = [...notes];
        const [moved] = next.splice(from, 1);
        next.splice(to, 0, moved);

        onChange(normalizeOrder(next));
    };

    function SortableRow({
        id,
        children,
    }: {
        id: string;
        children: (props: SortableProps) => React.ReactNode;
    }) {
        const { setNodeRef, attributes, listeners, transform, transition } =
            useSortable({ id });

        return children({
            ref: setNodeRef,
            style: {
                transform: CSS.Transform.toString(transform),
                transition,
            },
            attributes: attributes as React.HTMLAttributes<HTMLElement>,
            listeners: listeners as React.HTMLAttributes<HTMLElement>,
        });
    }

    const rows = useMemo(() => notes, [notes]);

    return (
        <div className="space-y-3">
            <div className="flex justify-between">
                <h2 className="text-lg font-semibold">Notes</h2>
                {!disabled && (
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant={enableDnd ? 'default' : 'outline'}
                            onClick={() => setEnableDnd(!enableDnd)}
                        >
                            {enableDnd ? 'Edit' : 'Preview'}
                        </Button>
                        <Button type="button" onClick={addNote}>
                            <Plus size={14} />
                            Add Note
                        </Button>
                    </div>
                )}
            </div>

            <div className="rounded-md border">
                {enableDnd ? (
                    <Preview
                        rows={rows}
                        disabled={disabled}
                        sensors={sensors}
                        handleDragEnd={handleDragEnd}
                        duplicateNote={duplicateNote}
                        removeNote={removeNote}
                        SortableRow={SortableRow}
                    />
                ) : (
                    <NoteTableWithoutDND
                        rows={rows}
                        disabled={disabled}
                        updateNote={updateNote}
                        duplicateNote={duplicateNote}
                        removeNote={removeNote}
                    />
                )}
            </div>
        </div>
    );
}

interface PreviewDNDProps {
    rows: NoteSchema[];
    disabled: boolean;
    sensors: ReturnType<typeof useSensors>;
    handleDragEnd: (event: DragEndEvent) => void;
    duplicateNote: (index: number) => void;
    removeNote: (index: number) => void;
    SortableRow: React.ComponentType<{
        id: string;
        children: (props: SortableProps) => React.ReactNode;
    }>;
}

function Preview({
    rows,
    disabled,
    sensors,
    handleDragEnd,
    duplicateNote,
    removeNote,
    SortableRow,
}: PreviewDNDProps) {
    return (
        <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            onDragEnd={handleDragEnd}
        >
            <SortableContext
                items={rows.map((n) => n._key)}
                strategy={verticalListSortingStrategy}
            >
                <Table className="[&_td]:p-1 [&_th]:p-2">
                    <TableHeader>
                        <TableRow>
                            {!disabled && (
                                <TableHead className="w-8"></TableHead>
                            )}
                            <TableHead>Description</TableHead>
                            {!disabled && (
                                <TableHead className="w-20"></TableHead>
                            )}
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        {rows.length ? (
                            rows.map((note, index) => (
                                <SortableRow key={note._key} id={note._key}>
                                    {({ ref, style, listeners }) => (
                                        <TableRow ref={ref} style={style}>
                                            {!disabled && (
                                                <TableCell
                                                    {...listeners}
                                                    className="cursor-grab"
                                                >
                                                    <GripVertical size={14} />
                                                </TableCell>
                                            )}

                                            <TableCell>
                                                <div
                                                    className="tiptap w-full rounded-lg border p-3 text-wrap"
                                                    dangerouslySetInnerHTML={{
                                                        __html:
                                                            note.description ??
                                                            '',
                                                    }}
                                                ></div>
                                            </TableCell>

                                            {!disabled && (
                                                <TableCell>
                                                    <div className="flex gap-1">
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="ghost"
                                                            onClick={() =>
                                                                duplicateNote(
                                                                    index,
                                                                )
                                                            }
                                                        >
                                                            <Copy size={14} />
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="ghost"
                                                            className="text-red-600"
                                                            onClick={() =>
                                                                removeNote(
                                                                    index,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 size={14} />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            )}
                                        </TableRow>
                                    )}
                                </SortableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell
                                    colSpan={disabled ? 1 : 3}
                                    className="text-center text-muted-foreground"
                                >
                                    No notes yet
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </SortableContext>
        </DndContext>
    );
}

interface NoteTableWithoutDNDProps {
    rows: NoteSchema[];
    disabled: boolean;
    updateNote: (key: string, patch: Partial<NoteSchema>) => void;
    duplicateNote: (index: number) => void;
    removeNote: (index: number) => void;
}

function NoteTableWithoutDND({
    rows,
    disabled,
    updateNote,
}: NoteTableWithoutDNDProps) {
    return (
        <Table className="[&_td]:p-1 [&_th]:p-2">
            <TableHeader>
                <TableRow>
                    <TableHead>Description</TableHead>
                </TableRow>
            </TableHeader>

            <TableBody>
                {rows.length ? (
                    rows.map((note) => (
                        <TableRow key={note._key}>
                            <TableCell>
                                <RichTextEditor
                                    className="m-3"
                                    value={note.description ?? ''}
                                    disabled={disabled}
                                    disableDragHandle={false}
                                    size="compact"
                                    onCommit={(v) =>
                                        updateNote(note._key, {
                                            description: v,
                                        })
                                    }
                                />
                            </TableCell>
                        </TableRow>
                    ))
                ) : (
                    <TableRow>
                        <TableCell
                            colSpan={1}
                            className="text-center text-muted-foreground"
                        >
                            No notes yet
                        </TableCell>
                    </TableRow>
                )}
            </TableBody>
        </Table>
    );
}
