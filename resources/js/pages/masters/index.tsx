import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import AppLayout from '@/layouts/app-layout';
import { index } from '@/routes/master';
import branch from '@/routes/master/branch';
import financialYear from '@/routes/master/financial-year';
import { create as createUser } from '@/routes/master/user';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Building, Calendar, Plus, Users } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Master',
        href: index().url,
    },
];

interface MasterProps {
    stats: {
        users: number;
        branches: number;
        fiscalYears: number;
    };
}

export default function Master({ stats }: MasterProps) {
    const menuItems = [
        {
            title: 'User Management',
            description: 'Manage users by roles',
            icon: Users,
            count: stats.users,
            hasAddButton: true,
            onAdd: () => router.get(createUser().url),
            onClick: () => router.get('/master/user'),
        },
        {
            title: 'Branch',
            description: 'Manage branch locations',
            icon: Building,
            count: stats.branches,
            hasAddButton: true,
            onAdd: () => router.get(branch.create().url),
            onClick: () => router.get(branch.index.url()),
        },
        {
            title: 'Fiscal Year',
            description: 'Manage fiscal year settings',
            icon: Calendar,
            count: stats.fiscalYears,
            hasAddButton: true,
            onAdd: () => router.get(financialYear.create().url),
            onClick: () => router.get(financialYear.index.url()),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Master" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">Master</h2>
                </div>
                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 p-6 md:min-h-min dark:border-sidebar-border">
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {menuItems.map((item) => {
                            const IconComponent = item.icon;
                            return (
                                <Card
                                    key={item.title}
                                    className="cursor-pointer transition-shadow hover:shadow-md"
                                    onClick={item.onClick}
                                >
                                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                        <div className="flex items-center space-x-2">
                                            <IconComponent className="h-5 w-5" />
                                            <CardTitle className="text-sm font-medium">
                                                {item.title}
                                            </CardTitle>
                                        </div>
                                        {item.hasAddButton && (
                                            <Button
                                                size="sm"
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    item.onAdd();
                                                }}
                                                className="h-8 px-2"
                                            >
                                                <Plus className="h-4 w-4" />
                                            </Button>
                                        )}
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold">
                                            {item.count}
                                        </div>
                                        <CardDescription className="text-xs text-muted-foreground">
                                            {item.description}
                                        </CardDescription>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                    <PlaceholderPattern className="absolute inset-0 -z-10 size-full stroke-neutral-900/10 dark:stroke-neutral-100/10" />
                </div>
            </div>
        </AppLayout>
    );
}
