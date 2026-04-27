import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { index } from '@/routes/master';
import branch from '@/routes/master/branch';
import financialYear from '@/routes/master/financial-year';
import { create as createUser } from '@/routes/master/user';
import quotationItem from '@/routes/quotation-items';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import {
    Building,
    Calendar,
    Globe,
    ListOrdered,
    Plus,
    Users,
} from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Master',
        href: index().url,
    },
];

interface MasterProps {
    stats: {
        users: number;
        countries: number;
        branches: number;
        fiscalYears: number;
        productsAndServices: number;
        scopeMode: string;
    };
    hideCustomerFromMaster?: boolean;
}

export default function Master({ stats }: MasterProps) {
    const scopeMode = String(stats.scopeMode ?? 'country').toLowerCase();

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
            title: 'Country',
            description: 'Manage country options',
            icon: Globe,
            count: stats.countries,
            hasAddButton: true,
            onAdd: () => router.get('/master/country/create'),
            onClick: () => router.get('/master/country'),
            visible: scopeMode === 'country',
        },
        {
            title: 'Branch',
            description: 'Manage branch locations',
            icon: Building,
            count: stats.branches,
            hasAddButton: true,
            onAdd: () => router.get(branch.create().url),
            onClick: () => router.get(branch.index.url()),
            visible: scopeMode === 'branch',
        },
        {
            title: 'Fiscal Year',
            description: 'Manage fiscal year settings',
            icon: Calendar,
            count: stats.fiscalYears,
            hasAddButton: true,
            onAdd: () => router.get(financialYear.create().url),
            onClick: () => router.get(financialYear.index.url()),
            visible: true,
        },
        {
            title: 'Products & Services',
            description: 'Manage item and extension defaults',
            icon: ListOrdered,
            count: stats.productsAndServices,
            hasAddButton: false,
            onAdd: () => undefined,
            onClick: () => router.get(quotationItem.index.url()),
            visible: true,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Master" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">Master</h2>
                </div>
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {menuItems
                        .filter((item) => item.visible !== false)
                        .map((item) => {
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
                                            <CardTitle className="text-base font-medium">
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
                                        <CardDescription className="text-sm text-muted-foreground">
                                            {item.description}
                                        </CardDescription>
                                    </CardContent>
                                </Card>
                            );
                        })}
                </div>
            </div>
        </AppLayout>
    );
}
