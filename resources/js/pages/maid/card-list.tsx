import { ActionColumn, ActionType } from '@/components/action-column';
import { ColumnFilter } from '@/components/column-filter';
import { DataTablePagination } from '@/components/data-table-pagination';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
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
import { Input } from '@/components/ui/input';
import { ZoomableImage } from '@/components/zoomable-image';
import { getForShow } from '@/routes/maid';
import { OptionType, ValueNumberOptionType } from '@/types';
import {
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useReactTable,
} from '@tanstack/react-table';
import { format, parseISO } from 'date-fns';
import { ArrowDown, ArrowUp, Check } from 'lucide-react';
import { useMemo, useState } from 'react';
import { DocumentGenerator } from './components/document-generator';
import { MaidBiodataPreview } from './components/maid-biodata-preview';
import {
    MaidStatusActions,
    getAvailableMaidActions,
} from './components/MaidStatusActions';
import { MaidForm } from './form';
import { MaidSchema, maritalStatus, status } from './schema';

interface MaidCardListProps {
    data: MaidSchema[];
    dataNationality: OptionType[];
    dataReligion: OptionType[];
    dataEducationLevel: OptionType[];
    misc?: {
        nationalities: ValueNumberOptionType[];
        religions: ValueNumberOptionType[];
        education_levels: ValueNumberOptionType[];
        suppliers: ValueNumberOptionType[];
    };
    selectedMaids?: number[];
    actions?: ActionType[];
    hasEditPermission?: boolean;
    onAction?: (action: ActionType, item?: MaidSchema) => void;
    onSelect?: (maidId: number) => void;
}

type SortKey =
    | 'name'
    | 'age'
    | 'nationality'
    | 'religion'
    | 'education_level'
    | 'marital_status'
    | 'status'
    | '';

export function MaidCardList({
    data,
    dataNationality,
    dataReligion,
    dataEducationLevel,
    misc,
    selectedMaids = [],
    actions = [],
    hasEditPermission = false,
    onAction,
    onSelect,
}: MaidCardListProps) {
    const [search, setSearch] = useState('');
    const [filters, setFilters] = useState({
        nationality: [] as string[],
        religion: [] as string[],
        education_level: [] as string[],
        age: [] as string[],
        marital_status: [] as string[],
        status: [] as string[],
    });
    const [sortKey, setSortKey] = useState<SortKey>('');
    const [sortOrder, setSortOrder] = useState<'asc' | 'desc'>('asc');
    const [openPopoverId, setOpenPopoverId] = useState<number | null>(null);
    const [selectedMaidData, setSelectedMaidData] = useState<MaidSchema | null>(
        null,
    );
    const [statusActionDialogOpen, setStatusActionDialogOpen] = useState(false);
    const [statusActionType, setStatusActionType] = useState<
        'schedule' | 'complete' | 'cancel' | 'update' | null
    >(null);
    const [selectedMaidForStatus, setSelectedMaidForStatus] =
        useState<MaidSchema | null>(null);
    const [previewDialogOpen, setPreviewDialogOpen] = useState(false);
    const [selectedMaidForPreview, setSelectedMaidForPreview] =
        useState<MaidSchema | null>(null);

    const filteredData = useMemo(() => {
        const normalized = (val: unknown): string => {
            if (val === null || val === undefined) return '';
            return String(val).toLowerCase();
        };

        return data.filter((maid) => {
            const searchTerm = search.toLowerCase();

            const matchesSearch =
                !searchTerm ||
                Object.values(maid)
                    .filter((v) => v !== null && v !== undefined)
                    .some((value) => normalized(value).includes(searchTerm));

            const matchesNationality =
                filters.nationality.length === 0 ||
                filters.nationality.some(
                    (val) => normalized(maid.nationality) === val.toLowerCase(),
                );

            const matchesReligion =
                filters.religion.length === 0 ||
                filters.religion.some(
                    (val) => normalized(maid.religion) === val.toLowerCase(),
                );

            const matchesEducation =
                filters.education_level.length === 0 ||
                filters.education_level.some(
                    (val) =>
                        normalized(maid.education_level) === val.toLowerCase(),
                );

            const matchesAge =
                filters.age.length === 0 ||
                filters.age.some((val) => String(maid.age ?? '') === val);

            const matchesMarital =
                filters.marital_status.length === 0 ||
                filters.marital_status.some(
                    (val) =>
                        normalized(maid.marital_status) === val.toLowerCase(),
                );

            const matchesStatus =
                filters.status.length === 0 ||
                filters.status.some(
                    (val) => normalized(maid.status) === val.toLowerCase(),
                );

            return (
                matchesSearch &&
                matchesNationality &&
                matchesReligion &&
                matchesEducation &&
                matchesAge &&
                matchesMarital &&
                matchesStatus
            );
        });
    }, [data, search, filters]);

    const sortedData = useMemo(() => {
        if (!sortKey) return filteredData;

        return [...filteredData].sort((a, b) => {
            const aValue = String(a[sortKey] ?? '').toLowerCase();
            const bValue = String(b[sortKey] ?? '').toLowerCase();

            if (aValue < bValue) return sortOrder === 'asc' ? -1 : 1;
            if (aValue > bValue) return sortOrder === 'asc' ? 1 : -1;
            return 0;
        });
    }, [filteredData, sortKey, sortOrder]);

    const cardTable = useReactTable({
        data: sortedData,
        columns: [],
        getCoreRowModel: getCoreRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        initialState: {
            pagination: {
                pageIndex: 0,
                pageSize: 10,
            },
        },
    });

    const paginatedData = cardTable
        .getRowModel()
        .rows.map((row) => row.original);

    const getStatusBorderColor = (status: string): string => {
        switch (status) {
            case 'available':
                return 'border-l-green-500';
            case 'interviewing':
                return 'border-l-orange-500';
            case 'pending':
                return 'border-l-yellow-500';
            case 'assigned':
                return 'border-l-gray-500';
            default:
                return '';
        }
    };

    const getStatusBadgeClasses = (status: string): string => {
        switch (status) {
            case 'available':
                return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
            case 'interviewing':
                return 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200';
            case 'pending':
                return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
            case 'assigned':
                return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
            default:
                return '';
        }
    };

    const toggleSort = (key: SortKey) => {
        if (sortKey === key) {
            setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc');
        } else {
            setSortKey(key);
            setSortOrder('asc');
        }
    };

    const uniqueAges = Array.from(
        new Set(data.map((m) => m.age).filter((v) => v !== null)),
    )
        .sort((a, b) => {
            if (a === undefined || b === undefined) return 0;
            return Number(a) - Number(b);
        })
        .map((age) => ({ value: String(age), label: `${age} years` }));

    const SortButton = ({
        keyName,
        label,
    }: {
        keyName: SortKey;
        label: string;
    }) => (
        <Button
            variant={sortKey === keyName ? 'default' : 'outline'}
            onClick={() => toggleSort(keyName)}
            size="sm"
        >
            {label}
            {sortKey === keyName &&
                (sortOrder === 'asc' ? (
                    <ArrowUp className="ml-1 h-4 w-4" />
                ) : (
                    <ArrowDown className="ml-1 h-4 w-4" />
                ))}
        </Button>
    );

    const handleOpenDialog = async (open: boolean, id: number) => {
        if (open) {
            setSelectedMaidData(null);
            try {
                const response = await fetch(getForShow(id).url);
                if (!response) throw new Error('Network error');
                const maidData = await response.json();
                setSelectedMaidData(maidData);
            } catch (err) {
                console.error('Failed to fetch maid details:', err);
            }
        } else {
            setOpenPopoverId(null);
            setSelectedMaidData(null);
        }
    };

    const handleStatusAction = (action: ActionType, maidData: MaidSchema) => {
        if (action === 'maid-status-schedule') {
            setStatusActionType('schedule');
        } else if (action === 'maid-status-complete') {
            setStatusActionType('complete');
        } else if (action === 'maid-status-cancel') {
            setStatusActionType('cancel');
        } else if (action === 'maid-status-update') {
            setStatusActionType('update');
        }
        setSelectedMaidForStatus(maidData);
        setStatusActionDialogOpen(true);
    };

    return (
        <div className="flex flex-col gap-4">
            {/* Search + Add */}
            <div className="flex flex-wrap items-center justify-between gap-4 px-2">
                <div className="flex flex-row gap-3">
                    <Input
                        placeholder="Search maids..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="max-w-xs"
                    />

                    {actions?.includes('add') && (
                        <Button onClick={() => onAction?.('add')}>Add</Button>
                    )}
                </div>

                {/* Filters */}
                <div className="flex flex-wrap gap-3">
                    <ColumnFilter
                        title="Nationality"
                        options={dataNationality}
                        value={filters.nationality}
                        onChange={(value) =>
                            setFilters((prev) => ({
                                ...prev,
                                nationality: value,
                            }))
                        }
                    />
                    <ColumnFilter
                        title="Religion"
                        options={dataReligion}
                        value={filters.religion}
                        onChange={(value) =>
                            setFilters((prev) => ({ ...prev, religion: value }))
                        }
                    />
                    <ColumnFilter
                        title="Education"
                        options={dataEducationLevel}
                        value={filters.education_level}
                        onChange={(value) =>
                            setFilters((prev) => ({
                                ...prev,
                                education_level: value,
                            }))
                        }
                    />
                    <ColumnFilter
                        title="Age"
                        options={uniqueAges}
                        value={filters.age}
                        onChange={(value) =>
                            setFilters((prev) => ({ ...prev, age: value }))
                        }
                    />
                    <ColumnFilter
                        title="Marital Status"
                        options={maritalStatus}
                        value={filters.marital_status}
                        onChange={(value) =>
                            setFilters((prev) => ({
                                ...prev,
                                marital_status: value,
                            }))
                        }
                    />
                    <ColumnFilter
                        title="Status"
                        options={status}
                        value={filters.status}
                        onChange={(value) =>
                            setFilters((prev) => ({ ...prev, status: value }))
                        }
                    />
                </div>
            </div>

            {/* Sort buttons */}
            <div className="flex flex-wrap gap-2 px-2">
                <SortButton keyName="name" label="Name" />
                <SortButton keyName="age" label="Age" />
                <SortButton keyName="nationality" label="Nationality" />
                <SortButton keyName="religion" label="Religion" />
                <SortButton keyName="education_level" label="Education" />
                <SortButton keyName="marital_status" label="Marital Status" />
                <SortButton keyName="status" label="Status" />
            </div>

            {/* Cards */}
            <div className="mx-auto w-full p-2">
                <div className="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    {paginatedData.length > 0 ? (
                        paginatedData.map((maid) => {
                            const maidId = Number(maid.id);
                            const isSelected = selectedMaids.includes(maidId);

                            return (
                                <Dialog
                                    key={maidId}
                                    open={openPopoverId === maidId}
                                    onOpenChange={(open) =>
                                        handleOpenDialog(open, maidId)
                                    }
                                >
                                    <Card
                                        key={maidId}
                                        className={`overflow-hidden rounded-xl border-l-4 shadow-md transition-all duration-200 ${getStatusBorderColor(maid.status)} ${
                                            onSelect
                                                ? 'cursor-pointer hover:shadow-lg'
                                                : ''
                                        } ${isSelected ? 'ring-2 ring-primary' : 'hover:ring-1 hover:ring-muted'}`}
                                        onClick={() => onSelect?.(maidId)}
                                        onDoubleClick={(e) => {
                                            e.stopPropagation();
                                            setOpenPopoverId((prev) =>
                                                prev === maidId ? null : maidId,
                                            );
                                            handleOpenDialog(true, maidId);
                                        }}
                                    >
                                        <CardHeader>
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="flex items-start gap-3">
                                                    {maid.photo_url ? (
                                                        <ZoomableImage
                                                            src={
                                                                maid.photo_url instanceof
                                                                File
                                                                    ? URL.createObjectURL(
                                                                          maid.photo_url,
                                                                      )
                                                                    : maid.photo_url
                                                            }
                                                            alt={maid.name}
                                                            thumbnailSize={80}
                                                            rounded="rounded-full"
                                                        />
                                                    ) : (
                                                        <div className="flex h-20 w-20 items-center justify-center rounded-full bg-gray-200 text-gray-400">
                                                            -
                                                        </div>
                                                    )}
                                                    <div className="flex flex-col">
                                                        <CardTitle className="text-base font-semibold">
                                                            {maid.name}
                                                        </CardTitle>
                                                        <p className="text-sm text-gray-500 dark:text-gray-400">
                                                            {maid.nationality ??
                                                                '-'}
                                                        </p>
                                                    </div>
                                                </div>
                                                <ActionColumn
                                                    row={maid}
                                                    actions={(() => {
                                                        const rowActions = [
                                                            ...actions,
                                                        ];
                                                        // Add conditional status actions
                                                        if (hasEditPermission) {
                                                            const availableStatusActions =
                                                                getAvailableMaidActions(
                                                                    maid.status,
                                                                );
                                                            availableStatusActions.forEach(
                                                                (
                                                                    statusAction,
                                                                ) => {
                                                                    rowActions.push(
                                                                        `maid-status-${statusAction}` as ActionType,
                                                                    );
                                                                },
                                                            );
                                                        }
                                                        return rowActions;
                                                    })()}
                                                    onAction={(action, row) => {
                                                        const maidData =
                                                            row as MaidSchema;
                                                        if (
                                                            action.startsWith(
                                                                'maid-status-',
                                                            )
                                                        ) {
                                                            handleStatusAction(
                                                                action,
                                                                maidData,
                                                            );
                                                        } else if (
                                                            action === 'preview'
                                                        ) {
                                                            setSelectedMaidForPreview(
                                                                maidData,
                                                            );
                                                            setPreviewDialogOpen(
                                                                true,
                                                            );
                                                        } else {
                                                            onAction?.(
                                                                action,
                                                                maidData,
                                                            );
                                                        }
                                                    }}
                                                />
                                            </div>
                                        </CardHeader>

                                        <CardContent className="space-y-1 text-sm">
                                            <div className="mb-2">
                                                <span
                                                    className={`inline-block rounded-full px-2 py-1 text-sm font-medium capitalize ${getStatusBadgeClasses(maid.status)}`}
                                                >
                                                    {maid.status}
                                                </span>
                                            </div>
                                            <div>
                                                <strong>Age:</strong>{' '}
                                                {maid.age ?? '-'}
                                            </div>
                                            <div>
                                                <strong>Height:</strong>{' '}
                                                {maid.height ?? '-'}
                                            </div>
                                            <div>
                                                <strong>Weight:</strong>{' '}
                                                {maid.weight ?? '-'}
                                            </div>
                                            <div>
                                                <strong>Religion:</strong>{' '}
                                                {maid.religion ?? '-'}
                                            </div>
                                            <div>
                                                <strong>Education:</strong>{' '}
                                                {maid.education_level ?? '-'}
                                            </div>
                                            <div>
                                                <strong>Marital:</strong>{' '}
                                                {maid.marital_status ?? '-'}
                                            </div>
                                            {maid.interview_date_formatted && (
                                                <div>
                                                    <strong>Interview:</strong>{' '}
                                                    {
                                                        maid.interview_date_formatted
                                                    }
                                                </div>
                                            )}
                                            {maid.pending_until && (
                                                <div>
                                                    <strong className="text-orange-600">
                                                        Pending Until:
                                                    </strong>{' '}
                                                    <span className="font-medium text-orange-600">
                                                        {format(
                                                            parseISO(
                                                                maid.pending_until,
                                                            ),
                                                            'PP',
                                                        )}
                                                    </span>
                                                </div>
                                            )}
                                            {maid.pending_reason && (
                                                <div>
                                                    <strong className="text-sm text-muted-foreground">
                                                        Reason:
                                                    </strong>{' '}
                                                    <span className="text-sm text-muted-foreground">
                                                        {maid.pending_reason}
                                                    </span>
                                                </div>
                                            )}
                                            {isSelected && (
                                                <Check className="size-5 font-bold text-primary" />
                                            )}
                                        </CardContent>

                                        <CardFooter className="justify-between text-[10px] text-gray-500 dark:text-gray-400">
                                            <span>ID: {maid.id}</span>
                                            <span>
                                                {maid.place_of_birth ?? '-'}
                                            </span>
                                        </CardFooter>
                                    </Card>

                                    <DialogContent className="flex max-h-[95%] min-h-[95%] max-w-[95%] min-w-[95%] flex-col overflow-y-hidden">
                                        <DialogHeader>
                                            <div className="flex items-center justify-between">
                                                <div>
                                                    <DialogTitle>
                                                        View Maid Details
                                                    </DialogTitle>
                                                    <DialogDescription className="sr-only">
                                                        Displays detailed
                                                        information about the
                                                        selected maid.
                                                    </DialogDescription>
                                                </div>
                                            </div>
                                        </DialogHeader>

                                        <div
                                            className="h-full w-full flex-1 overflow-y-auto"
                                            style={{
                                                scrollbarWidth: 'none',
                                                msOverflowStyle: 'none',
                                            }}
                                        >
                                            {selectedMaidData ? (
                                                <>
                                                    <div className="mb-4 flex justify-end">
                                                        <DocumentGenerator
                                                            maidId={Number(
                                                                maidId,
                                                            )}
                                                            maidName={
                                                                selectedMaidData.name
                                                            }
                                                        />
                                                    </div>
                                                    <MaidForm
                                                        mode="view"
                                                        initialData={
                                                            selectedMaidData
                                                        }
                                                        nationalities={
                                                            misc?.nationalities
                                                        }
                                                        religions={
                                                            misc?.religions
                                                        }
                                                        educationLevels={
                                                            misc?.education_levels
                                                        }
                                                        suppliers={
                                                            misc?.suppliers
                                                        }
                                                        onCancel={() =>
                                                            setOpenPopoverId(
                                                                null,
                                                            )
                                                        }
                                                    />
                                                </>
                                            ) : (
                                                <div className="flex h-full items-center justify-center text-muted-foreground">
                                                    Loading maid details...
                                                </div>
                                            )}
                                        </div>
                                    </DialogContent>
                                </Dialog>
                            );
                        })
                    ) : (
                        <p className="col-span-full text-center text-base text-muted-foreground">
                            No maids found matching your filters.
                        </p>
                    )}
                </div>
            </div>

            {/* Pagination */}
            {sortedData.length > 0 && (
                <DataTablePagination table={cardTable} data={sortedData} />
            )}

            {/* Status Actions Dialog */}
            {selectedMaidForStatus && (
                <MaidStatusActions
                    maid={selectedMaidForStatus}
                    isOpen={statusActionDialogOpen}
                    onClose={() => {
                        setStatusActionDialogOpen(false);
                        setStatusActionType(null);
                        setSelectedMaidForStatus(null);
                    }}
                    action={statusActionType}
                />
            )}

            {/* Preview Dialog */}
            {selectedMaidForPreview && (
                <MaidBiodataPreview
                    maidId={Number(selectedMaidForPreview.id)}
                    maidName={selectedMaidForPreview.name}
                    isOpen={previewDialogOpen}
                    onOpenChange={setPreviewDialogOpen}
                />
            )}
        </div>
    );
}
