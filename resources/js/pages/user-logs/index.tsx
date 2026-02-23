import { type ActionType } from '@/components/action-column';
import { DataTable } from '@/components/data-table';
import { createSelectColumn } from '@/components/select-column';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { index } from '@/routes/user-logs';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

import { ColumnDef } from '@tanstack/react-table';
import { useMemo, useState } from 'react';

interface ActivityLog {
    id: number;
    log_name?: string | null;
    description: string;
    subject_type: string;
    subject_id: number;
    causer_id: number | null;
    causer?: {
        id?: number;
        name?: string;
        email?: string;
    } | null;
    properties: Record<string, unknown>;
    created_at: string;
    updated_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'User Logs',
        href: index().url,
    },
];

const actions: ActionType[] = ['view'];

const formatDateTime = (value: string | null | undefined): string => {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const toObjectRecord = (value: unknown): Record<string, unknown> | null => {
    if (!value || typeof value !== 'object' || Array.isArray(value)) {
        return null;
    }

    return value as Record<string, unknown>;
};

const formatChangeValue = (value: unknown): string => {
    if (value === undefined) {
        return '-';
    }

    if (value === null) {
        return 'null';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value, null, 2);
    }

    return String(value);
};

type DiffType = 'added' | 'removed' | 'modified';

interface DiffEntry {
    path: string;
    type: DiffType;
    oldValue: unknown;
    newValue: unknown;
}

const isPlainObject = (value: unknown): value is Record<string, unknown> => {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
};

const areEqualValues = (left: unknown, right: unknown): boolean => {
    if (left === right) {
        return true;
    }

    try {
        return JSON.stringify(left) === JSON.stringify(right);
    } catch {
        return false;
    }
};

const collectDiffEntries = (
    oldValue: unknown,
    newValue: unknown,
    path = '',
): DiffEntry[] => {
    if (isPlainObject(oldValue) && isPlainObject(newValue)) {
        const keys = new Set<string>([
            ...Object.keys(oldValue),
            ...Object.keys(newValue),
        ]);

        return [...keys].flatMap((key) => {
            const nextPath = path ? `${path}.${key}` : key;

            return collectDiffEntries(oldValue[key], newValue[key], nextPath);
        });
    }

    if (Array.isArray(oldValue) && Array.isArray(newValue)) {
        const maxLength = Math.max(oldValue.length, newValue.length);

        return Array.from({ length: maxLength }, (_, index) => {
            const nextPath = path ? `${path}.${index}` : String(index);

            return collectDiffEntries(
                oldValue[index],
                newValue[index],
                nextPath,
            );
        }).flat();
    }

    if (oldValue === undefined && newValue !== undefined) {
        return [
            {
                path,
                type: 'added',
                oldValue,
                newValue,
            },
        ];
    }

    if (oldValue !== undefined && newValue === undefined) {
        return [
            {
                path,
                type: 'removed',
                oldValue,
                newValue,
            },
        ];
    }

    if (!areEqualValues(oldValue, newValue)) {
        return [
            {
                path,
                type: 'modified',
                oldValue,
                newValue,
            },
        ];
    }

    return [];
};

const formatDiffPath = (path: string): string => {
    if (!path) {
        return '(root)';
    }

    return path.replace(/\.(\d+)(?=\.|$)/g, '[$1]');
};

const setNestedValue = (
    target: Record<string, unknown>,
    path: string,
    value: unknown,
) => {
    const segments = path.split('.').filter(Boolean);

    if (segments.length === 0) {
        return;
    }

    let current: Record<string, unknown> = target;

    for (let index = 0; index < segments.length; index++) {
        const segment = segments[index];
        const isLast = index === segments.length - 1;

        if (isLast) {
            current[segment] = value;

            return;
        }

        if (!isPlainObject(current[segment])) {
            current[segment] = {};
        }

        current = current[segment] as Record<string, unknown>;
    }
};

const buildDiffTree = (diffEntries: DiffEntry[]): Record<string, unknown> => {
    const tree: Record<string, unknown> = {};

    diffEntries.forEach((entry) => {
        setNestedValue(tree, entry.path, {
            change_type: entry.type,
            old: entry.oldValue,
            new: entry.newValue,
        });
    });

    return tree;
};

const diffTypeClassName: Record<DiffType, string> = {
    added: 'bg-green-100 text-green-800',
    removed: 'bg-red-100 text-red-800',
    modified: 'bg-blue-100 text-blue-800',
};

const columns: ColumnDef<ActivityLog>[] = [
    createSelectColumn<ActivityLog>(),
    {
        accessorKey: 'id',
        header: 'Id',
        meta: { exportable: true },
    },
    {
        accessorKey: 'description',
        header: 'Description',
        meta: { exportable: true },
    },

    {
        accessorKey: 'causer.name',
        header: 'User ID',
        meta: { exportable: true },
    },
    {
        accessorKey: 'created_at',
        header: 'Date',
        meta: { exportable: true },
        cell: ({ row }) => formatDateTime(row.getValue('created_at')),
    },
];

interface UserLogsProps {
    activities: ActivityLog[];
}

export default function UserLogs({ activities }: UserLogsProps) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [selectedActivity, setSelectedActivity] =
        useState<ActivityLog | null>(null);

    const activityProperties = selectedActivity?.properties ?? {};

    const attributeChanges = useMemo(() => {
        const attributes = toObjectRecord(activityProperties.attributes);
        const old = toObjectRecord(activityProperties.old);

        return collectDiffEntries(old ?? {}, attributes ?? {});
    }, [activityProperties]);

    const changesTree = useMemo(
        () => buildDiffTree(attributeChanges),
        [attributeChanges],
    );

    const openDetailDialog = (activity: ActivityLog) => {
        setSelectedActivity(activity);
        setDialogOpen(true);
    };

    return (
        <>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="User Logs" />
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">
                            User Activity Logs
                        </h2>
                    </div>
                    <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                        <DataTable
                            columns={columns}
                            data={activities}
                            actions={actions}
                            onAction={(action, row) => {
                                if (action === 'view' && row?.original) {
                                    openDetailDialog(row.original);
                                }
                            }}
                            onRowDoubleClick={(row) => {
                                openDetailDialog(row as ActivityLog);
                            }}
                            initialState={{
                                columnVisibility: { id: false },
                            }}
                        />
                    </div>
                </div>
            </AppLayout>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="flex max-h-[95%] max-w-[95%] min-w-[95%] flex-col">
                    <DialogHeader>
                        <DialogTitle>Activity Log Details</DialogTitle>
                        <DialogDescription>
                            Detailed information for activity log entry.
                        </DialogDescription>
                    </DialogHeader>

                    {selectedActivity && (
                        <div className="grid gap-4 overflow-y-auto pb-2 text-sm">
                            <div className="grid grid-cols-1 gap-3 rounded-md border p-4 md:grid-cols-2">
                                <div>
                                    <p className="text-muted-foreground">ID</p>
                                    <p className="font-medium">
                                        {selectedActivity.id}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">
                                        Log Name
                                    </p>
                                    <p className="font-medium">
                                        {selectedActivity.log_name || '-'}
                                    </p>
                                </div>
                                <div className="md:col-span-2">
                                    <p className="text-muted-foreground">
                                        Description
                                    </p>
                                    <p className="font-medium">
                                        {selectedActivity.description || '-'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">
                                        Subject Type
                                    </p>
                                    <p className="font-medium">
                                        {selectedActivity.subject_type || '-'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">
                                        Subject ID
                                    </p>
                                    <p className="font-medium">
                                        {selectedActivity.subject_id ?? '-'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">
                                        Causer
                                    </p>
                                    <p className="font-medium">
                                        {selectedActivity.causer?.name || '-'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">
                                        Causer ID
                                    </p>
                                    <p className="font-medium">
                                        {selectedActivity.causer_id ?? '-'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">
                                        Created At
                                    </p>
                                    <p className="font-medium">
                                        {formatDateTime(
                                            selectedActivity.created_at,
                                        )}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">
                                        Updated At
                                    </p>
                                    <p className="font-medium">
                                        {formatDateTime(
                                            selectedActivity.updated_at,
                                        )}
                                    </p>
                                </div>
                            </div>

                            <div className="rounded-md border p-4">
                                <p className="mb-3 text-sm font-semibold">
                                    Changes Summary
                                </p>
                                {attributeChanges.length === 0 ? (
                                    <p className="text-muted-foreground">
                                        No old/new change data available.
                                    </p>
                                ) : (
                                    <Tabs
                                        defaultValue="path-view"
                                        className="w-full"
                                    >
                                        <TabsList>
                                            <TabsTrigger value="path-view">
                                                Path View
                                            </TabsTrigger>
                                            <TabsTrigger value="tree-view">
                                                Tree View
                                            </TabsTrigger>
                                        </TabsList>

                                        <TabsContent
                                            value="path-view"
                                            className="mt-3"
                                        >
                                            <div className="grid gap-3">
                                                {attributeChanges.map(
                                                    (change) => (
                                                        <div
                                                            key={change.path}
                                                            className="rounded-md border bg-muted/30 p-3"
                                                        >
                                                            <div className="mb-2 flex items-center justify-between gap-2">
                                                                <p className="font-medium">
                                                                    {formatDiffPath(
                                                                        change.path,
                                                                    )}
                                                                </p>
                                                                <Badge
                                                                    variant="secondary"
                                                                    className={
                                                                        diffTypeClassName[
                                                                            change
                                                                                .type
                                                                        ]
                                                                    }
                                                                >
                                                                    {change.type
                                                                        .charAt(
                                                                            0,
                                                                        )
                                                                        .toUpperCase() +
                                                                        change.type.slice(
                                                                            1,
                                                                        )}
                                                                </Badge>
                                                            </div>

                                                            {change.type !==
                                                                'added' && (
                                                                <div className="mb-2">
                                                                    <p className="text-xs font-medium text-muted-foreground">
                                                                        Old
                                                                    </p>
                                                                    <pre className="overflow-x-auto rounded-md bg-muted p-2 text-xs">
                                                                        {formatChangeValue(
                                                                            change.oldValue,
                                                                        )}
                                                                    </pre>
                                                                </div>
                                                            )}

                                                            {change.type !==
                                                                'removed' && (
                                                                <div>
                                                                    <p className="text-xs font-medium text-muted-foreground">
                                                                        New
                                                                    </p>
                                                                    <pre className="overflow-x-auto rounded-md bg-muted p-2 text-xs">
                                                                        {formatChangeValue(
                                                                            change.newValue,
                                                                        )}
                                                                    </pre>
                                                                </div>
                                                            )}
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        </TabsContent>

                                        <TabsContent
                                            value="tree-view"
                                            className="mt-3"
                                        >
                                            <pre className="overflow-x-auto rounded-md bg-muted/30 p-3 text-xs">
                                                {JSON.stringify(
                                                    changesTree,
                                                    null,
                                                    2,
                                                )}
                                            </pre>
                                        </TabsContent>
                                    </Tabs>
                                )}
                            </div>

                            <div className="rounded-md border p-4">
                                <p className="mb-3 text-sm font-semibold">
                                    Properties JSON
                                </p>
                                <pre className="overflow-x-auto rounded-md bg-muted/30 p-3 text-xs">
                                    {JSON.stringify(
                                        selectedActivity.properties ?? {},
                                        null,
                                        2,
                                    )}
                                </pre>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
