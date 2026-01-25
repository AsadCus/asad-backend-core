import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { index as masterIndex } from '@/routes/master';
import { create, index } from '@/routes/master/user';
import masterAdmin from '@/routes/master/user/admin';
import masterCustomer from '@/routes/master/user/customer';
import masterSales from '@/routes/master/user/sales';
import masterSupplier from '@/routes/master/user/supplier';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Plus, Shield, TrendingUp, Truck, Users } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Master',
        href: masterIndex().url,
    },
    {
        title: 'User',
        href: index().url,
    },
];

interface MasterUserProps {
    // data: User[];
    // dataRole: OptionType[];
    roleStats: {
        admin: number;
        sales: number;
        customer: number;
        supplier: number;
    };
}

export default function MasterUser({
    // data,
    // dataRole,
    roleStats,
}: MasterUserProps) {
    const roleMenus = [
        {
            title: 'Administrator',
            description: 'System administrators',
            icon: Shield,
            count: roleStats.admin,
            href: masterAdmin.index.url(),
            hasAddButton: true,
            onAdd: () => router.get(masterAdmin.create().url),
        },
        {
            title: 'Sales',
            description: 'Sales personnel',
            icon: TrendingUp,
            count: roleStats.sales,
            href: masterSales.index.url(),
            hasAddButton: true,
            onAdd: () => router.get(masterSales.create().url),
        },
        {
            title: 'Customer',
            description: 'Customer accounts',
            icon: Users,
            count: roleStats.customer,
            href: masterCustomer.index.url(),
            hasAddButton: true,
            onAdd: () => router.get(masterCustomer.create().url),
        },
        {
            title: 'Supplier',
            description: 'Supplier accounts',
            icon: Truck,
            count: roleStats.supplier,
            href: masterSupplier.index.url(),
            hasAddButton: true,
            onAdd: () => router.get(masterSupplier.create().url),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="User" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">User</h2>
                    <Button onClick={() => router.get(create().url)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Add New User
                    </Button>
                </div>
                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 p-6 md:min-h-min dark:border-sidebar-border">
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        {roleMenus.map((role) => {
                            const IconComponent = role.icon;
                            return (
                                <Card
                                    key={role.title}
                                    className="cursor-pointer transition-shadow hover:shadow-md"
                                    onClick={() => router.get(role.href)}
                                >
                                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                        <div className="flex items-center space-x-2">
                                            <IconComponent className="h-5 w-5" />
                                            <CardTitle className="text-sm font-medium">
                                                {role.title}
                                            </CardTitle>
                                        </div>
                                        {role.hasAddButton && (
                                            <Button
                                                size="sm"
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    role.onAdd();
                                                }}
                                                className="h-8 px-2"
                                            >
                                                <Plus className="mr-1 h-4 w-4" />
                                                Add
                                            </Button>
                                        )}
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold">
                                            {role.count}
                                        </div>
                                        <CardDescription className="text-xs text-muted-foreground">
                                            {role.description}
                                        </CardDescription>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
