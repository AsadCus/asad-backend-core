import { clearAllDataTableSettings } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Bell, Globe, Monitor, Moon, RotateCcw, Sun } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Badge } from './ui/badge';

export function NavActions() {
    const [isOpen, setIsOpen] = useState(false);
    const [isScopeOpen, setIsScopeOpen] = useState(false);
    const { auth } = usePage<SharedData>().props;
    const notifications = auth.notifications;
    const roles = auth.roles ?? [];
    const isScopeIndicatorVisible =
        roles.includes('admin') ||
        roles.includes('sales') ||
        roles.includes('operations');
    const scopeMode = String(auth.scope_mode ?? 'country').toLowerCase();
    const scopeModeLabel = scopeMode === 'branch' ? 'Branch' : 'Country';
    const scopeLabels = Array.isArray(auth.scope_labels)
        ? auth.scope_labels
        : [];
    const scopeCountryOptions = useMemo(() => {
        if (!Array.isArray(auth.scope_country_options)) {
            return [];
        }

        return auth.scope_country_options
            .map((option) => ({
                id: Number(option.id),
                label: String(option.label ?? ''),
            }))
            .filter(
                (option) =>
                    Number.isFinite(option.id) &&
                    option.id > 0 &&
                    option.label.length > 0,
            );
    }, [auth.scope_country_options]);
    const scopeSelectedCountryIdsFromServer = useMemo(() => {
        if (!Array.isArray(auth.scope_selected_country_ids)) {
            return [];
        }

        return auth.scope_selected_country_ids
            .map((id) => Number(id))
            .filter((id) => Number.isFinite(id) && id > 0);
    }, [auth.scope_selected_country_ids]);
    const defaultSelectedScopeCountryIds = useMemo(() => {
        return scopeSelectedCountryIdsFromServer.length > 0
            ? scopeSelectedCountryIdsFromServer
            : scopeCountryOptions.map((option) => option.id);
    }, [scopeSelectedCountryIdsFromServer, scopeCountryOptions]);
    const [selectedScopeCountryIds, setSelectedScopeCountryIds] = useState<
        number[]
    >(defaultSelectedScopeCountryIds);
    const [scopeError, setScopeError] = useState<string | null>(null);
    const [isApplyingScope, setIsApplyingScope] = useState(false);
    const { appearance, updateAppearance } = useAppearance();

    useEffect(() => {
        setSelectedScopeCountryIds(defaultSelectedScopeCountryIds);
        setScopeError(null);
    }, [defaultSelectedScopeCountryIds]);

    const selectedScopeCountryLabels = scopeCountryOptions
        .filter((option) => selectedScopeCountryIds.includes(option.id))
        .map((option) => option.label);

    const isAllAssignedCountriesSelected =
        scopeCountryOptions.length > 0 &&
        selectedScopeCountryIds.length === scopeCountryOptions.length;

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

    const applyScopeSelection = () => {
        if (selectedScopeCountryIds.length === 0) {
            setScopeError(
                'Please select at least one country before applying.',
            );

            return;
        }

        setIsApplyingScope(true);
        setScopeError(null);

        router.post(
            '/data-scope/countries',
            {
                country_ids: selectedScopeCountryIds,
            },
            {
                preserveScroll: true,
                preserveState: false,
                onSuccess: () => {
                    toast.success('Data scope updated.');
                    setIsScopeOpen(false);
                },
                onError: (errors) => {
                    const message =
                        typeof errors.country_ids === 'string'
                            ? errors.country_ids
                            : 'Failed to update data scope.';

                    setScopeError(message);
                },
                onFinish: () => {
                    setIsApplyingScope(false);
                },
            },
        );
    };

    return (
        <div className="flex items-center gap-2 text-base">
            <div className="hidden items-center gap-2 text-muted-foreground md:flex">
                <span className="font-medium">
                    {new Date().toLocaleDateString('en-GB', {
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric',
                    })}
                </span>
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

            {isScopeIndicatorVisible &&
                scopeMode === 'country' &&
                scopeCountryOptions.length === 1 && (
                    <div className="flex items-center gap-2">
                        <span className="text-base text-muted-foreground">
                            You are viewing:
                        </span>
                        <Badge variant="secondary">
                            {scopeCountryOptions[0].label}
                        </Badge>
                    </div>
                )}

            {isScopeIndicatorVisible &&
                scopeMode === 'country' &&
                scopeCountryOptions.length > 1 && (
                    <Popover open={isScopeOpen} onOpenChange={setIsScopeOpen}>
                        <PopoverTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="relative h-7 w-7 data-[state=open]:bg-accent"
                                aria-label="View data scope"
                            >
                                <Globe className="h-4 w-4" />
                            </Button>
                        </PopoverTrigger>

                        <PopoverContent
                            className="w-72 rounded-lg p-4"
                            align="end"
                        >
                            <div className="space-y-3">
                                <div>
                                    <h3 className="text-base font-semibold text-foreground">
                                        Data Scope
                                    </h3>
                                    <p className="text-base text-muted-foreground">
                                        Select countries and apply scope
                                    </p>
                                </div>

                                <div className="space-y-2 border-t pt-2">
                                    <div>
                                        <p className="text-base font-medium text-muted-foreground">
                                            You are viewing
                                        </p>
                                        {selectedScopeCountryLabels.length >
                                        0 ? (
                                            <div className="flex flex-wrap gap-2">
                                                {selectedScopeCountryLabels.map(
                                                    (label, idx) => (
                                                        <Badge
                                                            key={`${label}-${idx}`}
                                                        >
                                                            {label}
                                                        </Badge>
                                                    ),
                                                )}
                                            </div>
                                        ) : (
                                            <p className="mt-1 text-base text-muted-foreground">
                                                No country selected
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <p className="text-base font-medium text-muted-foreground">
                                            Assigned Countries
                                        </p>
                                        <div className="space-y-2 rounded-md border p-2">
                                            {scopeCountryOptions.map(
                                                (option) => (
                                                    <label
                                                        key={option.id}
                                                        className="flex items-center gap-2"
                                                    >
                                                        <Checkbox
                                                            checked={selectedScopeCountryIds.includes(
                                                                option.id,
                                                            )}
                                                            onCheckedChange={(
                                                                value,
                                                            ) => {
                                                                const checked =
                                                                    Boolean(
                                                                        value,
                                                                    );

                                                                setScopeError(
                                                                    null,
                                                                );
                                                                setSelectedScopeCountryIds(
                                                                    (
                                                                        previous,
                                                                    ) => {
                                                                        if (
                                                                            checked
                                                                        ) {
                                                                            return Array.from(
                                                                                new Set(
                                                                                    [
                                                                                        ...previous,
                                                                                        option.id,
                                                                                    ],
                                                                                ),
                                                                            );
                                                                        }

                                                                        return previous.filter(
                                                                            (
                                                                                countryId,
                                                                            ) =>
                                                                                countryId !==
                                                                                option.id,
                                                                        );
                                                                    },
                                                                );
                                                            }}
                                                        />
                                                        <span>
                                                            {option.label}
                                                        </span>
                                                    </label>
                                                ),
                                            )}
                                        </div>

                                        {scopeError && (
                                            <p className="text-sm font-medium text-destructive">
                                                {scopeError}
                                            </p>
                                        )}

                                        <Button
                                            type="button"
                                            className="w-full"
                                            disabled={isApplyingScope}
                                            onClick={applyScopeSelection}
                                        >
                                            {isApplyingScope
                                                ? 'Applying...'
                                                : isAllAssignedCountriesSelected
                                                  ? 'Apply (All Countries)'
                                                  : 'Apply'}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </PopoverContent>
                    </Popover>
                )}

            {isScopeIndicatorVisible &&
                (scopeMode !== 'country' ||
                    scopeCountryOptions.length === 0) && (
                    <Popover open={isScopeOpen} onOpenChange={setIsScopeOpen}>
                        <PopoverTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="relative h-7 w-7 data-[state=open]:bg-accent"
                                aria-label="View data scope"
                            >
                                <Globe className="h-4 w-4" />
                            </Button>
                        </PopoverTrigger>

                        <PopoverContent
                            className="w-72 rounded-lg p-4"
                            align="end"
                        >
                            <div className="space-y-3">
                                <div>
                                    <h3 className="text-base font-semibold text-foreground">
                                        Data Scope
                                    </h3>
                                    <p className="text-base text-muted-foreground">
                                        Your assigned data access scope
                                    </p>
                                </div>

                                <div className="space-y-2 border-t pt-2">
                                    <div>
                                        <p className="text-base font-medium text-muted-foreground">
                                            Scope Mode
                                        </p>
                                        <p className="text-base font-medium text-foreground">
                                            {scopeModeLabel}
                                        </p>
                                    </div>

                                    <div>
                                        <p className="text-base font-medium text-muted-foreground">
                                            {scopeModeLabel === 'Branch'
                                                ? 'Assigned Branches'
                                                : 'Assigned Countries'}
                                        </p>
                                        {scopeLabels.length > 0 ? (
                                            <div className="flex flex-wrap gap-2">
                                                {scopeLabels.map(
                                                    (label, idx) => (
                                                        <Badge key={idx}>
                                                            {label}
                                                        </Badge>
                                                    ),
                                                )}
                                            </div>
                                        ) : (
                                            <p className="mt-1 text-base text-muted-foreground">
                                                Not configured
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </PopoverContent>
                    </Popover>
                )}

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
