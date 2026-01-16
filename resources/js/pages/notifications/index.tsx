import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import { action, index, read, readAll } from '@/routes/notifications';
import { BreadcrumbItem, SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Info, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Notifications',
        href: index().url,
    },
];

export interface NotificationItem {
    id: number;
    title: string;
    message: string;
    type: 'info' | 'success' | 'warning' | 'error';
    link?: string;
    is_read: boolean;
    read_at?: string;
    exclusive: boolean;
    action_taken_by?: number;
    action_taken_at?: string;
    created_at: string;
}

export const typeStyles: Record<
    NotificationItem['type'],
    { color: string; icon: React.ElementType }
> = {
    info: { color: 'text-blue-500', icon: Info },
    success: { color: 'text-green-500', icon: CheckCircle2 },
    warning: { color: 'text-yellow-500', icon: AlertTriangle },
    error: { color: 'text-red-500', icon: XCircle },
};

export default function Notification({
    notifications,
}: {
    notifications: NotificationItem[];
}) {
    const { auth } = usePage<SharedData>().props;

    const [notifList, setNotifList] = useState<NotificationItem[]>(
        notifications.sort((a, b) => Number(a.is_read) - Number(b.is_read)),
    );
    const [selectedNotif, setSelectedNotif] = useState<NotificationItem | null>(
        null,
    );

    const handleMarkAllRead = () => {
        router.put(
            readAll().url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    setNotifList((prev) =>
                        prev.map((n) => ({ ...n, is_read: true })),
                    );
                },
            },
        );
    };

    const handleOpenNotif = (id: number) => {
        router.put(
            read(id).url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    setNotifList((prev) =>
                        prev.map((n) =>
                            n.id === id ? { ...n, is_read: true } : n,
                        ),
                    );
                },
            },
        );

        const notif = notifList.find((n) => n.id === id);
        setSelectedNotif(notif || null);
    };

    const handleNotificationLinkAction = (id: number) => {
        router.post(
            action(id),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    setNotifList((prev) =>
                        prev.map((n) =>
                            n.id === id
                                ? {
                                      ...n,
                                      action_taken_by: auth?.user?.id,
                                      action_taken_at: new Date().toISOString(),
                                  }
                                : n,
                        ),
                    );
                    handleCloseDialog();
                },
            },
        );
    };

    const handleCloseDialog = () => setSelectedNotif(null);

    useEffect(() => {
        const hash = window.location.hash;
        if (hash) {
            const element = document.querySelector(hash);
            if (element) {
                element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                element.classList.add('ring-2', 'ring-primary');
                setTimeout(
                    () => element.classList.remove('ring-2', 'ring-primary'),
                    2000,
                );
            }
        }
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Notifications" />
            <div className="px-4 py-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Notifications"
                        description="Manage notifications"
                    />
                    {notifList.some((n) => !n.is_read) && (
                        <button
                            onClick={handleMarkAllRead}
                            className="text-sm font-medium text-primary hover:underline"
                        >
                            Mark all as read
                        </button>
                    )}
                </div>
                <div className="rounded-lg border bg-background p-4">
                    {notifList.length === 0 ? (
                        <p className="py-10 text-center text-sm text-muted-foreground">
                            You have no notifications.
                        </p>
                    ) : (
                        <div className="space-y-2">
                            {notifList.map((notif) => {
                                const { color, icon: Icon } =
                                    typeStyles[notif.type];
                                return (
                                    <div
                                        key={notif.id}
                                        id={`notif-${notif.id}`}
                                        onClick={() =>
                                            handleOpenNotif(notif.id)
                                        }
                                        className={`flex cursor-pointer flex-col rounded-md border p-3 transition hover:bg-accent ${
                                            notif.is_read
                                                ? 'bg-muted/40 text-muted-foreground'
                                                : 'bg-background'
                                        }`}
                                    >
                                        <div className="flex items-start justify-between">
                                            <div className="flex items-center gap-2">
                                                <Icon
                                                    className={`h-4 w-4 ${color}`}
                                                />
                                                <h4
                                                    className={`text-sm font-medium ${
                                                        notif.is_read
                                                            ? 'text-muted-foreground'
                                                            : 'text-foreground'
                                                    }`}
                                                >
                                                    {notif.title}
                                                </h4>
                                            </div>
                                            {!notif.is_read && (
                                                <span className="mt-1 ml-2 h-2 w-2 rounded-full bg-red-500"></span>
                                            )}
                                        </div>
                                        <p className="mt-1 text-xs">
                                            {notif.message}
                                        </p>
                                        <p className="mt-1 text-right text-[10px] text-muted-foreground">
                                            {new Date(
                                                notif.created_at,
                                            ).toLocaleString()}
                                        </p>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>
            <Dialog open={!!selectedNotif} onOpenChange={handleCloseDialog}>
                <DialogContent>
                    {selectedNotif && (
                        <>
                            <DialogHeader>
                                <div className="flex items-center gap-2">
                                    {(() => {
                                        const { color, icon: Icon } =
                                            typeStyles[selectedNotif.type];
                                        return (
                                            <Icon
                                                className={`h-5 w-5 ${color}`}
                                            />
                                        );
                                    })()}
                                    <DialogTitle>
                                        {selectedNotif.title}
                                    </DialogTitle>
                                </div>
                                <DialogDescription>
                                    {new Date(
                                        selectedNotif.created_at,
                                    ).toLocaleString()}
                                </DialogDescription>
                            </DialogHeader>
                            <div className="py-2 text-sm">
                                {selectedNotif.message}
                            </div>
                            <DialogFooter>
                                {selectedNotif.link &&
                                    (!selectedNotif.exclusive ||
                                        (selectedNotif.exclusive &&
                                            !selectedNotif.action_taken_by)) && (
                                        <Button
                                            onClick={() =>
                                                handleNotificationLinkAction(
                                                    selectedNotif.id,
                                                )
                                            }
                                        >
                                            Go there
                                        </Button>
                                    )}
                                <Button
                                    variant={'outline'}
                                    onClick={handleCloseDialog}
                                >
                                    Close
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
