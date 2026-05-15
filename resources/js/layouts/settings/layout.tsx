import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editPassword } from '@/routes/password';
import { edit } from '@/routes/profile';
import { edit as editReportTemplate } from '@/routes/report-template';
import { show } from '@/routes/two-factor';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: edit(),
        icon: null,
    },
    {
        title: 'Password',
        href: editPassword(),
        icon: null,
    },
    {
        title: 'Two-Factor Auth',
        href: show(),
        icon: null,
    },
];

const adminOnlySidebarNavItems: NavItem[] = [
    {
        title: 'Appearance',
        href: editAppearance(),
        icon: null,
    },
];

const ghostAdminOnlySidebarNavItems: NavItem[] = [
    {
        title: 'Report Template',
        href: editReportTemplate(),
        icon: null,
    },
    {
        title: 'Model Number Formats',
        href: '/settings/model-number-formats',
        icon: null,
    },
];

interface SettingsLayoutProps extends PropsWithChildren {
    wide?: boolean;
    fullWidth?: boolean;
}

const toHrefString = (href: NavItem['href']): string | undefined => {
    if (typeof href === 'string') {
        return href;
    }

    return href?.url;
};

export default function SettingsLayout({
    children,
    wide = false,
    fullWidth = false,
}: SettingsLayoutProps) {
    const { auth } = usePage<SharedData>().props;
    const isSuperadmin = auth.roles.includes('superadmin');
    const isGhostSuperadmin = isSuperadmin && Boolean(auth.is_ghost_user);
    const navItems = [
        ...sidebarNavItems,
        ...(isSuperadmin ? adminOnlySidebarNavItems : []),
        ...(isGhostSuperadmin ? ghostAdminOnlySidebarNavItems : []),
    ];

    // When server-side rendering, we only render the layout on the client...
    if (typeof window === 'undefined') {
        return null;
    }

    const currentPath = window.location.pathname;

    return (
        <div className="px-4 py-6">
            <Heading
                title="Settings"
                description="Manage your profile and account settings"
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav className="flex flex-col space-y-1 space-x-0">
                        {navItems.map((item, index) => {
                            if (!item.href) {
                                return null;
                            }

                            const href = toHrefString(item.href);

                            if (!href) {
                                return null;
                            }

                            return (
                                <Button
                                    key={`${href}-${index}`}
                                    size="sm"
                                    variant="ghost"
                                    asChild
                                    className={cn('w-full justify-start', {
                                        'bg-muted': currentPath === href,
                                    })}
                                >
                                    <Link href={item.href}>
                                        {item.icon && (
                                            <item.icon className="h-4 w-4" />
                                        )}
                                        {item.title}
                                    </Link>
                                </Button>
                            );
                        })}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div
                    className={cn('min-w-0 flex-1', {
                        'md:max-w-2xl': !wide && !fullWidth,
                        'md:max-w-6xl': wide && !fullWidth,
                        'md:max-w-none': fullWidth,
                    })}
                >
                    <section
                        className={cn('space-y-12', {
                            'max-w-xl': !wide && !fullWidth,
                            'max-w-6xl': wide && !fullWidth,
                            'max-w-none': fullWidth,
                        })}
                    >
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}
