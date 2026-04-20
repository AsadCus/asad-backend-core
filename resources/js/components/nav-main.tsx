import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    useSidebar,
} from '@/components/ui/sidebar';

import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';

export function NavMain({ items = [] }: { items: NavItem[] }) {
    const page = usePage();
    const { state, isMobile } = useSidebar();

    const isUrlActive = (href?: NavItem['href'], matchExact = false) => {
        if (!href) return false;

        const base = typeof href === 'string' ? href : href.url;
        const current = page.url;

        if (!base) return false;

        const normalize = (url: string) => url.replace(/\/$/, '').split('?')[0];

        const b = normalize(base);
        const c = normalize(current);

        if (matchExact) {
            return c === b;
        }

        if (c === b) return true;
        return c.startsWith(`${b}/`);
    };

    const renderSubMenu = (subItems: NavItem[]) => (
        <SidebarMenuSub>
            {subItems.map((subItem) => {
                const hasNested = (subItem.subItems?.length ?? 0) > 0;
                const isActive = isUrlActive(subItem.href, subItem.matchExact);
                const subActive = subItem.subItems?.some((c) =>
                    isUrlActive(c.href, c.matchExact),
                );

                if (!hasNested) {
                    return (
                        <SidebarMenuItem key={subItem.title}>
                            <SidebarMenuButton
                                asChild={!!subItem.href}
                                isActive={isActive}
                                tooltip={{ children: subItem.title }}
                                className="not-dark:hover:bg-gray-200 not-dark:active:bg-gray-300 not-dark:data-[active=true]:bg-gray-200 not-dark:data-[active=true]:text-foreground"
                            >
                                {subItem.href ? (
                                    <Link href={subItem.href}>
                                        {subItem.icon && <subItem.icon />}
                                        <span className="truncate">
                                            {subItem.title}
                                        </span>
                                    </Link>
                                ) : (
                                    <span className="flex items-center gap-2">
                                        {subItem.icon && <subItem.icon />}
                                        <span className="truncate">
                                            {subItem.title}
                                        </span>
                                    </span>
                                )}
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    );
                }

                return (
                    <Collapsible
                        key={subItem.title}
                        defaultOpen={isActive || subActive}
                        className="group/collapsible-item"
                    >
                        <SidebarMenuItem>
                            {subItem.href ? (
                                <div className="flex items-center">
                                    <SidebarMenuButton
                                        asChild
                                        isActive={isActive || subActive}
                                        tooltip={{ children: subItem.title }}
                                        className="flex-1 not-dark:hover:bg-gray-200 not-dark:active:bg-gray-300 not-dark:data-[active=true]:bg-gray-200 not-dark:data-[active=true]:text-foreground"
                                    >
                                        <Link href={subItem.href}>
                                            {subItem.icon && <subItem.icon />}
                                            <span className="truncate">
                                                {subItem.title}
                                            </span>
                                        </Link>
                                    </SidebarMenuButton>

                                    <CollapsibleTrigger asChild>
                                        <SidebarMenuButton
                                            isActive={isActive}
                                            tooltip={{
                                                children: `Toggle ${subItem.title}`,
                                            }}
                                            className="ml-1 h-8 w-8 shrink-0 justify-center not-dark:hover:bg-gray-200 not-dark:active:bg-gray-300 not-dark:data-[active=true]:bg-gray-200 not-dark:data-[active=true]:text-foreground"
                                        >
                                            <ChevronRight className="h-4 w-4 transition-transform duration-200 group-data-[state=open]/collapsible-item:rotate-90" />
                                        </SidebarMenuButton>
                                    </CollapsibleTrigger>
                                </div>
                            ) : (
                                <CollapsibleTrigger asChild>
                                    <SidebarMenuButton
                                        isActive={isActive || subActive}
                                        className="w-full not-dark:hover:bg-gray-200 not-dark:active:bg-gray-300 not-dark:data-[active=true]:bg-gray-200 not-dark:data-[active=true]:text-foreground"
                                        tooltip={{ children: subItem.title }}
                                    >
                                        {subItem.icon && <subItem.icon />}
                                        <span className="truncate">
                                            {subItem.title}
                                        </span>
                                        <ChevronRight className="ml-auto h-4 w-4 transition-transform duration-200 group-data-[state=open]/collapsible-item:rotate-90" />
                                    </SidebarMenuButton>
                                </CollapsibleTrigger>
                            )}
                        </SidebarMenuItem>

                        <CollapsibleContent>
                            {renderSubMenu(subItem.subItems ?? [])}
                        </CollapsibleContent>
                    </Collapsible>
                );
            })}
        </SidebarMenuSub>
    );

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Menu</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => {
                    const hasSubItems = (item.subItems?.length ?? 0) > 0;

                    const isActive = isUrlActive(item.href, item.matchExact);
                    const subActive = item.subItems?.some((sub) =>
                        isUrlActive(sub.href, sub.matchExact),
                    );

                    if (!hasSubItems) {
                        return (
                            <SidebarMenuItem key={item.title}>
                                <SidebarMenuButton
                                    asChild={!!item.href}
                                    isActive={isActive || subActive}
                                    tooltip={{ children: item.title }}
                                    className="not-dark:hover:bg-gray-200 not-dark:active:bg-gray-300 not-dark:data-[active=true]:bg-gray-200 not-dark:data-[active=true]:text-foreground"
                                >
                                    {item.href ? (
                                        <Link href={item.href}>
                                            {item.icon && <item.icon />}
                                            <span className="truncate">
                                                {item.title}
                                            </span>
                                        </Link>
                                    ) : (
                                        <span className="flex items-center gap-2">
                                            {item.icon && <item.icon />}
                                            <span className="truncate">
                                                {item.title}
                                            </span>
                                        </span>
                                    )}
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        );
                    }

                    /** Collapsed sidebar: use dropdown */
                    if (state === 'collapsed' && !isMobile) {
                        return (
                            <SidebarMenuItem key={item.title}>
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <SidebarMenuButton
                                            isActive={isActive || subActive}
                                            tooltip={{ children: item.title }}
                                            className="not-dark:hover:bg-gray-200 not-dark:active:bg-gray-300 not-dark:data-[active=true]:bg-gray-200 not-dark:data-[active=true]:text-foreground"
                                        >
                                            {item.icon && <item.icon />}
                                        </SidebarMenuButton>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        side="right"
                                        align="start"
                                    >
                                        {item.href && (
                                            <DropdownMenuItem asChild>
                                                <Link
                                                    href={item.href}
                                                    className="flex items-center"
                                                >
                                                    {item.icon && (
                                                        <item.icon className="mr-2 flex-shrink-0" />
                                                    )}
                                                    <span className="truncate">
                                                        {item.title}
                                                    </span>
                                                </Link>
                                            </DropdownMenuItem>
                                        )}
                                        {(item.subItems ?? []).map(
                                            (subItem) => (
                                                <DropdownMenuItem
                                                    key={subItem.title}
                                                    asChild={!!subItem.href}
                                                >
                                                    {subItem.href ? (
                                                        <Link
                                                            href={subItem.href}
                                                            className="flex items-center"
                                                        >
                                                            {subItem.icon && (
                                                                <subItem.icon className="mr-2 flex-shrink-0" />
                                                            )}
                                                            <span className="truncate">
                                                                {subItem.title}
                                                            </span>
                                                        </Link>
                                                    ) : (
                                                        <span className="flex items-center">
                                                            {subItem.icon && (
                                                                <subItem.icon className="mr-2 flex-shrink-0" />
                                                            )}
                                                            <span className="truncate">
                                                                {subItem.title}
                                                            </span>
                                                        </span>
                                                    )}
                                                </DropdownMenuItem>
                                            ),
                                        )}
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </SidebarMenuItem>
                        );
                    }

                    /** Sidebar expanded */
                    if (item.href) {
                        return (
                            <Collapsible
                                key={item.title}
                                defaultOpen={isActive || subActive}
                                className="group/collapsible"
                            >
                                <SidebarMenuItem>
                                    <div className="flex items-center">
                                        <SidebarMenuButton
                                            asChild
                                            isActive={isActive || subActive}
                                            tooltip={{ children: item.title }}
                                            className="flex-1 not-dark:hover:bg-gray-200 not-dark:active:bg-gray-300 not-dark:data-[active=true]:bg-gray-200 not-dark:data-[active=true]:text-foreground"
                                        >
                                            <Link href={item.href}>
                                                {item.icon && <item.icon />}
                                                <span className="truncate">
                                                    {item.title}
                                                </span>
                                            </Link>
                                        </SidebarMenuButton>

                                        <CollapsibleTrigger asChild>
                                            <SidebarMenuButton
                                                isActive={isActive || subActive}
                                                tooltip={{
                                                    children: `Toggle ${item.title}`,
                                                }}
                                                className="ml-1 h-8 w-8 shrink-0 justify-center not-dark:hover:bg-gray-200 not-dark:active:bg-gray-300 not-dark:data-[active=true]:bg-gray-200 not-dark:data-[active=true]:text-foreground"
                                            >
                                                <ChevronRight className="h-4 w-4 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                                            </SidebarMenuButton>
                                        </CollapsibleTrigger>
                                    </div>
                                </SidebarMenuItem>

                                <CollapsibleContent>
                                    {renderSubMenu(item.subItems ?? [])}
                                </CollapsibleContent>
                            </Collapsible>
                        );
                    }

                    return (
                        <Collapsible
                            key={item.title}
                            defaultOpen={isActive || subActive}
                            className="group/collapsible"
                        >
                            <SidebarMenuItem>
                                <CollapsibleTrigger asChild>
                                    <SidebarMenuButton
                                        isActive={isActive || subActive}
                                        tooltip={{ children: item.title }}
                                        className="w-full not-dark:hover:bg-gray-200 not-dark:active:bg-gray-300 not-dark:data-[active=true]:bg-gray-200 not-dark:data-[active=true]:text-foreground"
                                    >
                                        {item.icon && <item.icon />}
                                        <span className="truncate">
                                            {item.title}
                                        </span>
                                        <ChevronRight className="ml-auto h-4 w-4 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                                    </SidebarMenuButton>
                                </CollapsibleTrigger>
                            </SidebarMenuItem>

                            <CollapsibleContent>
                                {renderSubMenu(item.subItems ?? [])}
                            </CollapsibleContent>
                        </Collapsible>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
