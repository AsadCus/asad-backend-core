import {
    destroy,
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/EnquiryRemarkController';
import { ProperInput } from '@/components/proper-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/react';
import { MessageSquarePlus, Pencil, Trash2, User } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import {
    enquiryRemarkValidationSchema,
    type EnquiryRemarkSchema,
} from '../remark-schema';
import { statusColors } from '../schema';

interface EnquiryRemarksTimelineProps {
    isOpen: boolean;
    enquiryId: number | undefined;
}

export default function EnquiryRemarksTimeline({
    isOpen,
    enquiryId,
}: EnquiryRemarksTimelineProps) {
    const [remarks, setRemarks] = useState<EnquiryRemarkSchema[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [remark, setRemark] = useState('');
    const [editingRemarkId, setEditingRemarkId] = useState<number | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [error, setError] = useState('');

    const fetchRemarks = useCallback(async () => {
        if (!enquiryId) return;

        setIsLoading(true);
        try {
            const response = await fetch(index(enquiryId).url);
            if (!response.ok) throw new Error('Failed to fetch remarks');
            const data = await response.json();
            setRemarks(data);
        } catch {
            console.error('Failed to fetch remarks');
        } finally {
            setIsLoading(false);
        }
    }, [enquiryId]);

    useEffect(() => {
        if (isOpen && enquiryId) {
            fetchRemarks();
            setRemark('');
            setEditingRemarkId(null);
            setError('');
        }
    }, [isOpen, enquiryId, fetchRemarks]);

    const handleSubmit = async () => {
        if (!enquiryId) return;

        const result = enquiryRemarkValidationSchema.safeParse({ remark });
        if (!result.success) {
            setError(result.error.issues[0]?.message ?? 'Invalid input');
            return;
        }

        setIsSubmitting(true);
        setError('');

        try {
            if (editingRemarkId) {
                router.put(
                    update({ enquiryId, remarkId: editingRemarkId }).url,
                    { remark },
                    {
                        preserveScroll: true,
                        onSuccess: () => {
                            setRemark('');
                            setEditingRemarkId(null);
                            fetchRemarks();
                        },
                        onFinish: () => setIsSubmitting(false),
                    },
                );
            } else {
                router.post(
                    store(enquiryId).url,
                    { remark },
                    {
                        preserveScroll: true,
                        onSuccess: () => {
                            setRemark('');
                            fetchRemarks();
                        },
                        onFinish: () => setIsSubmitting(false),
                    },
                );
            }
        } catch {
            setIsSubmitting(false);
        }
    };

    const handleEdit = (remarkItem: EnquiryRemarkSchema) => {
        setEditingRemarkId(remarkItem.id ?? null);
        setRemark(remarkItem.remark ?? '');
        setError('');
    };

    const handleCancelEdit = () => {
        setEditingRemarkId(null);
        setRemark('');
        setError('');
    };

    const handleDelete = (remarkId: number) => {
        if (!enquiryId) return;

        router.delete(destroy({ enquiryId, remarkId }).url, {
            preserveScroll: true,
            onSuccess: () => fetchRemarks(),
        });
    };

    const getStatusBadgeClass = (status: string): string => {
        return statusColors[status] ?? 'bg-gray-100 text-gray-800';
    };

    const formatStatusLabel = (status: string): string => {
        return status
            .replace(/_/g, ' ')
            .replace(/\b\w/g, (c) => c.toUpperCase());
    };

    return (
        <>
            {/* Add/Edit Remark Form */}
            <div className="space-y-2 border-b pb-4">
                <ProperInput
                    textarea
                    id="remark"
                    value={remark}
                    onCommit={(v) => setRemark(v)}
                    placeholder={
                        editingRemarkId
                            ? 'Edit your remark...'
                            : 'Add a new remark...'
                    }
                    disabled={isSubmitting}
                />
                {error && <p className="text-base text-red-600">{error}</p>}
                <div className="flex items-center gap-2">
                    <Button
                        size="sm"
                        onClick={handleSubmit}
                        disabled={isSubmitting || !remark.trim()}
                    >
                        {isSubmitting
                            ? 'Saving...'
                            : editingRemarkId
                              ? 'Update Remark'
                              : 'Add Remark'}
                    </Button>
                    {editingRemarkId && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={handleCancelEdit}
                        >
                            Cancel
                        </Button>
                    )}
                </div>
            </div>

            {/* Remarks Timeline */}
            <div className="flex-1 overflow-y-auto">
                {isLoading && (
                    <div className="space-y-4 py-4">
                        {[1, 2, 3].map((i) => (
                            <div key={i} className="animate-pulse space-y-2">
                                <div className="h-4 w-1/3 rounded bg-gray-200 dark:bg-gray-700" />
                                <div className="h-3 w-2/3 rounded bg-gray-200 dark:bg-gray-700" />
                            </div>
                        ))}
                    </div>
                )}

                {!isLoading && remarks.length === 0 && (
                    <div className="flex flex-col items-center justify-center py-8 text-muted-foreground">
                        <MessageSquarePlus className="mb-2 h-8 w-8 opacity-50" />
                        <p>No remarks yet. Add the first one above.</p>
                    </div>
                )}

                {!isLoading && remarks.length > 0 && (
                    <div className="relative space-y-0 py-4 pl-6">
                        {/* Timeline line */}
                        <div className="absolute top-0 bottom-0 left-2.5 w-px bg-gray-200 dark:bg-gray-700" />

                        {remarks.map((item) => (
                            <div
                                key={item.id}
                                className="group relative pb-6 last:pb-0"
                            >
                                {/* Timeline dot */}
                                <div className="absolute top-1 -left-6 flex h-5 w-5 items-center justify-center rounded-full border-2 border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                                    <div className="h-2 w-2 rounded-full bg-gray-400 dark:bg-gray-500" />
                                </div>

                                <div className="rounded-lg border border-gray-100 bg-gray-50/50 p-3 dark:border-gray-800 dark:bg-gray-900/50">
                                    {/* Header */}
                                    <div className="mb-2 flex items-center justify-between gap-2">
                                        <div className="flex flex-wrap items-center gap-2 text-base">
                                            <span className="flex items-center gap-1 font-medium text-gray-900 dark:text-gray-100">
                                                <User className="h-4 w-4" />
                                                {item.creator_name}
                                            </span>
                                            <span className="text-xs text-muted-foreground md:text-sm">
                                                {item.created_at}
                                            </span>
                                            {item.status_at_time && (
                                                <Badge
                                                    className={`text-xs md:text-sm ${getStatusBadgeClass(item.status_at_time)}`}
                                                >
                                                    {formatStatusLabel(
                                                        item.status_at_time,
                                                    )}
                                                </Badge>
                                            )}
                                        </div>

                                        {/* Edit/Delete actions */}
                                        <div className="flex flex-col items-center gap-1 transition-opacity md:flex-row md:opacity-0 md:group-hover:opacity-100">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="h-7 w-7"
                                                onClick={() => handleEdit(item)}
                                            >
                                                <Pencil className="h-3.5 w-3.5" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="h-7 w-7 text-red-600 hover:text-red-700"
                                                onClick={() =>
                                                    item.id &&
                                                    handleDelete(item.id)
                                                }
                                            >
                                                <Trash2 className="h-3.5 w-3.5" />
                                            </Button>
                                        </div>
                                    </div>

                                    {/* Remark text */}
                                    <p className="text-base whitespace-pre-wrap text-gray-700 dark:text-gray-300">
                                        {item.remark}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
