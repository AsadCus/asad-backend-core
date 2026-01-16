import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import NoteForm from '@/pages/notes/form';
import { NoteSchema } from '@/pages/notes/schema';
import QuotationItemTableForm from '@/pages/quotations/items/form';
import {
    QuotationItemSchema,
    quotationItemsSchema,
} from '@/pages/quotations/items/schema';
import master, { index as masterIndex } from '@/routes/master';
import { index } from '@/routes/quotation-items';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { nanoid } from 'nanoid';
import { toast } from 'sonner';

interface MastersQuotationIndexProps {
    quotationItems?: QuotationItemSchema[];
    quotationMasterNote?: NoteSchema[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Master',
        href: masterIndex().url,
    },
    {
        title: 'Quotation Items',
        href: index().url,
    },
];

export default function MastersQuotationIndex({
    quotationItems = [],
    quotationMasterNote = [],
}: MastersQuotationIndexProps) {
    const initialItems = quotationItems.map((item) => ({
        ...item,
        _key: item.id ? `id-${item.id}` : nanoid(),
    }));

    const initialNotes = (quotationMasterNote ?? []).map((note) => ({
        ...note,
        _key: note.id ? `id-${note.id}` : nanoid(),
    }));

    const {
        data,
        setData,
        post,
        processing,
        errors,
        reset,
        setError,
        clearErrors,
    } = useForm<{
        model: string;
        items: QuotationItemSchema[];
        notes: NoteSchema[];
    }>({
        model: 'master',
        items: initialItems,
        notes: initialNotes,
    });

    const handleChange = (next: QuotationItemSchema[]) => {
        setData('items', next);
    };

    const handleNoteChange = (next: NoteSchema[]) => {
        setData('notes', next);
    };

    const handleReset = () => {
        reset();
    };

    function validateClientSide() {
        clearErrors();

        const result = quotationItemsSchema.safeParse(data);

        if (!result.success) {
            result.error.issues.forEach((issue) => {
                const path = issue.path.join('.');
                setError(path as unknown as keyof typeof errors, issue.message);
            });
            return false;
        }

        return true;
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (!validateClientSide()) return;

        const url = index().url;

        post(url, {
            preserveScroll: true,
            onSuccess: () => {
                window.location.reload();
            },
            onError: (errors) => setError(errors),
        });
    }

    function submitNotes(e: React.FormEvent) {
        e.preventDefault();

        const cleanNotes = data.notes
            .filter((n) => n.description?.trim().length)
            .map((n, i) => ({
                ...n,
                model: 'quotation',
                sort_order: i + 1,
            }));

        if (!cleanNotes.length) {
            toast.error('At least one note is required.');
            return;
        }

        setData((prev) => ({
            ...prev,
            notes: cleanNotes,
        }));

        post(master.note.store.url(), {
            preserveScroll: true,
            onStart: () => {
                toast.loading('Saving notes...');
            },
            onSuccess: () => {
                toast.success('Quotation master notes updated.');
            },
            onError: (e) => {
                console.error(e);
                toast.error('Failed to save notes.');
            },
            onFinish: () => {
                toast.dismiss();
            },
        });
    }

    // format err
    function cleanMessage(message: string) {
        return message
            .replace(/^The\s.+?\s/, '')
            .replace(
                /when\s.+?\.is_header\sis\sfalse\.?/i,
                'when header is false',
            )
            .replace(/\.$/, '');
    }

    function formatError(path: string, message: string) {
        const parts = path.split('.');
        const clean = cleanMessage(message);

        if (parts[0] === 'items' && parts.length >= 3) {
            const itemIndex = Number(parts[1]) + 1;
            const field = parts[2];

            return `Itemd #${itemIndex} ${field} ${clean}`;
        }

        return path;
    }

    const renderError = (path: string) => {
        const errorMap = errors as Record<string, string | undefined>;
        const message = errorMap[path];

        if (!message) return null;

        return (
            <p className="mt-1 text-xs text-red-500">
                {formatError(path, message)}
            </p>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Quotation Item Masters" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">Quotation Items</h2>
                </div>

                {/* items */}
                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 md:min-h-min dark:border-sidebar-border">
                    <div className="mx-auto w-full">
                        <form onSubmit={submit} className="space-y-6 py-2">
                            <QuotationItemTableForm
                                mode={'master'}
                                items={data.items}
                                onChange={handleChange}
                                renderError={renderError}
                                disabled={processing}
                                showOptionalColumn={true}
                                showPlacementFeeColumn={false}
                            />

                            <div className="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={handleReset}
                                    disabled={processing}
                                >
                                    Reset
                                </Button>
                                <Button
                                    type="submit"
                                    className="min-w-[140px]"
                                    disabled={processing}
                                >
                                    {processing ? (
                                        <>
                                            <LoaderCircle className="h-4 w-4 animate-spin" />{' '}
                                            Saving...
                                        </>
                                    ) : (
                                        'Save'
                                    )}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>

                {/* notes */}
                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 md:min-h-min dark:border-sidebar-border">
                    <div className="mx-auto w-full">
                        <form onSubmit={submitNotes} className="space-y-6 py-2">
                            <NoteForm
                                mode="master"
                                model="quotation"
                                notes={data.notes}
                                onChange={handleNoteChange}
                                disabled={processing}
                            />

                            <div className="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        setData('notes', initialNotes)
                                    }
                                    disabled={processing}
                                >
                                    Reset Notes
                                </Button>
                                <Button
                                    type="submit"
                                    className="min-w-[140px]"
                                    disabled={processing}
                                >
                                    {processing ? (
                                        <>
                                            <LoaderCircle className="h-4 w-4 animate-spin" />{' '}
                                            Saving...
                                        </>
                                    ) : (
                                        'Save Notes'
                                    )}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
