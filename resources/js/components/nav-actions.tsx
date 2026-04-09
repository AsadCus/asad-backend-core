import { clearAllDataTableSettings } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Sidebar,
    SidebarContent,
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { Appearance, useAppearance } from '@/hooks/use-appearance';
import { NotificationItem, typeStyles } from '@/pages/notifications';
import { index, read } from '@/routes/notifications';
import { SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Bell, Monitor, Moon, RotateCcw, Sun } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

export function NavActions() {
    const [isOpen, setIsOpen] = useState(false);
    const { auth } = usePage<SharedData>().props;
    const notifications = auth.notifications;
    const { appearance, updateAppearance } = useAppearance();

    const unreadCount = notifications.filter((n) => !n.is_read).length;

    const themes: Array<{ value: Appearance; label: string }> = [
        { value: 'light', label: 'Light' },
        { value: 'dark', label: 'Dark' },
        { value: 'system', label: 'System' },
    ];

    const appearanceIcon =
        appearance === 'light' ? (
            <Sun className="h-4 w-4" />
        ) : appearance === 'dark' ? (
            <Moon className="h-4 w-4" />
        ) : (
            <Monitor className="h-4 w-4" />
        );

    const handleNotificationClick = async (notif: NotificationItem) => {
        router.put(
            read(notif.id).url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    router.visit(index().url);
                },
            },
        );
    };

    const handleDataTableReset = () => {
        clearAllDataTableSettings();
        toast.success('Data table settings reset to default.');
    };

    return (
        <div className="flex items-center gap-2 text-base">
            <div className="hidden font-medium text-muted-foreground md:inline-block">
                {new Date().toLocaleDateString('en-GB', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                })}
            </div>

            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="h-7 w-7"
                        aria-label="Change theme"
                    >
                        {appearanceIcon}
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-36">
                    {themes.map((theme) => (
                        <DropdownMenuItem
                            key={theme.value}
                            onClick={() => updateAppearance(theme.value)}
                            className="justify-between"
                        >
                            <span>{theme.label}</span>
                            {appearance === theme.value && (
                                <span className="text-xs text-muted-foreground">
                                    Active
                                </span>
                            )}
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>

            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="h-7 w-7"
                aria-label="Reset data table settings"
                onClick={handleDataTableReset}
            >
                <RotateCcw className="h-4 w-4" />
            </Button>

            <Popover open={isOpen} onOpenChange={setIsOpen}>
                <PopoverTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="relative h-7 w-7 data-[state=open]:bg-accent"
                    >
                        <Bell />
                        {unreadCount > 0 && (
                            <span className="absolute -top-1 -right-1 flex h-4 w-4 flex-col items-center justify-center rounded-full bg-red-500 text-sm font-bold text-white">
                                {unreadCount}
                            </span>
                        )}
                    </Button>
                </PopoverTrigger>

                <PopoverContent
                    className="max-h-[70vh] w-72 overflow-y-auto rounded-lg p-0"
                    align="end"
                >
                    <Sidebar collapsible="none" className="bg-transparent">
                        <SidebarContent>
                            <SidebarGroup>
                                <SidebarGroupContent className="gap-0">
                                    <SidebarMenu>
                                        <SidebarMenuItem>
                                            <div className="mb-1 flex items-center justify-between">
                                                <SidebarMenuButton
                                                    onClick={() => {
                                                        router.visit(
                                                            index().url,
                                                        );
                                                        setIsOpen(false);
                                                    }}
                                                >
                                                    <span className="flex-1">
                                                        Notifications
                                                    </span>
                                                </SidebarMenuButton>
                                            </div>

                                            {notifications.length === 0 ? (
                                                <p className="text-center text-sm text-muted-foreground">
                                                    No notifications
                                                </p>
                                            ) : (
                                                notifications.map((notif) => {
                                                    const {
                                                        color,
                                                        icon: Icon,
                                                    } = typeStyles[
                                                        notif.type
                                                    ] || {
                                                        color: 'text-gray-500',
                                                        icon: Bell,
                                                    };
                                                    return (
                                                        <div
                                                            key={notif.id}
                                                            onClick={() =>
                                                                handleNotificationClick(
                                                                    notif,
                                                                )
                                                            }
                                                            className={`cursor-pointer rounded-md p-2 transition hover:bg-accent ${
                                                                !notif.is_read
                                                                    ? 'bg-accent/40'
                                                                    : ''
                                                            }`}
                                                        >
                                                            <div className="flex items-start justify-between">
                                                                <div className="flex-1">
                                                                    <div className="flex items-center gap-2">
                                                                        <Icon
                                                                            className={`h-4 w-4 ${color}`}
                                                                        />
                                                                        <p className="text-base font-medium text-foreground">
                                                                            {
                                                                                notif.title
                                                                            }
                                                                        </p>
                                                                    </div>
                                                                    <p className="text-sm text-muted-foreground">
                                                                        {
                                                                            notif.message
                                                                        }
                                                                    </p>
                                                                </div>
                                                                {!notif.is_read && (
                                                                    <span className="mt-1 ml-2 h-2 w-2 rounded-full bg-red-500"></span>
                                                                )}
                                                            </div>
                                                            <p className="mt-1 text-right text-[10px] text-muted-foreground">
                                                                {new Date(
                                                                    notif.created_at,
                                                                ).toLocaleString()}
                                                            </p>
                                                        </div>
                                                    );
                                                })
                                            )}
                                        </SidebarMenuItem>
                                    </SidebarMenu>
                                </SidebarGroupContent>
                            </SidebarGroup>
                        </SidebarContent>
                    </Sidebar>
                </PopoverContent>
            </Popover>
        </div>
    );
}
