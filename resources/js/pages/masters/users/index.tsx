import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { index as masterIndex } from '@/routes/master';
import { index } from '@/routes/master/user';
import masterAdmin from '@/routes/master/user/admin';
import masterCustomer from '@/routes/master/user/customer';
import masterOfficial from '@/routes/master/user/official';
import masterOperations from '@/routes/master/user/operations';
import masterSales from '@/routes/master/user/sales';
import masterSuperadmin from '@/routes/master/user/superadmin';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import {
    BadgeCheck,
    Plus,
    Route,
    Shield,
    TrendingUp,
    User,
    Users,
} from 'lucide-react';

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
        superadmin: number;
        admin: number;
        sales: number;
        operations: number;
        customer: number;
        official: number;
    };
    countryStats: {
        superadmin: {
            totalCountries: number;
            breakdown: Array<{ country: string; count: number }>;
        };
        admin: {
            totalCountries: number;
            breakdown: Array<{ country: string; count: number }>;
        };
        sales: {
            totalCountries: number;
            breakdown: Array<{ country: string; count: number }>;
        };
        operations: {
            totalCountries: number;
            breakdown: Array<{ country: string; count: number }>;
        };
    };
    hideCustomerFromMaster?: boolean;
}

export default function MasterUser({
    // data,
    // dataRole,
    roleStats,
    // countryStats,
    hideCustomerFromMaster = false,
}: MasterUserProps) {
    // const countrySummary = (
    //     entries: Array<{ country: string; count: number }>,
    // ): string => {
    //     if (!entries.length) {
    //         return 'No country assignment yet';
    //     }

    //     return entries
    //         .slice(0, 2)
    //         .map((entry) => `${entry.country} (${entry.count})`)
    //         .join(', ');
    // };

    const roleMenus = [
        {
            title: 'Superadmin',
            // description: `Assigned countries: ${countryStats.superadmin.totalCountries} (${countrySummary(countryStats.superadmin.breakdown)})`,
            description: 'Superadmin accounts',
            hidden: false,
            icon: Shield,
            count: roleStats.superadmin,
            href: masterSuperadmin.index.url(),
            hasAddButton: true,
            onAdd: () => router.get(masterSuperadmin.create().url),
        },
        {
            // title: 'Admin',
            title: 'Sales',
            // description: `Assigned countries: ${countryStats.admin.totalCountries} (${countrySummary(countryStats.admin.breakdown)})`,
            // description: 'Admin accounts',
            description: 'Sales accounts',
            hidden: false,
            icon: User,
            count: roleStats.admin,
            href: masterAdmin.index.url(),
            hasAddButton: true,
            onAdd: () => router.get(masterAdmin.create().url),
        },
        {
            // title: 'Sales',
            title: 'Finance',
            // description: `Assigned countries: ${countryStats.sales.totalCountries} (${countrySummary(countryStats.sales.breakdown)})`,
            // description: 'Sales accounts',
            description: 'Finance accounts',
            hidden: false,
            icon: TrendingUp,
            count: roleStats.sales,
            href: masterSales.index.url(),
            hasAddButton: true,
            onAdd: () => router.get(masterSales.create().url),
        },
        {
            title: 'Customer',
            description: 'Customer accounts',
            hidden: hideCustomerFromMaster,
            icon: Users,
            count: roleStats.customer,
            href: masterCustomer.index.url(),
            hasAddButton: true,
            onAdd: () => router.get(masterCustomer.create().url),
        },
        {
            title: 'Operations',
            // description: `Assigned countries: ${countryStats.operations.totalCountries} (${countrySummary(countryStats.operations.breakdown)})`,
            description: 'Operations accounts',
            hidden: false,
            icon: Route,
            count: roleStats.operations,
            href: masterOperations.index.url(),
            hasAddButton: true,
            onAdd: () => router.get(masterOperations.create().url),
        },
        {
            title: 'Official',
            description: 'Official accounts (non-login)',
            hidden: false,
            icon: BadgeCheck,
            count: roleStats.official,
            href: masterOfficial.index.url(),
            hasAddButton: true,
            onAdd: () => router.get(masterOfficial.create().url),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="User" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">User</h2>
                </div>
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    {roleMenus.map((role) => {
                        const IconComponent = role.icon;
                        return (
                            <Card
                                key={role.title}
                                className={cn(
                                    'cursor-pointer transition-shadow hover:shadow-md',
                                    role.hidden ? 'hidden' : '',
                                )}
                                onClick={() => router.get(role.href)}
                            >
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <div className="flex items-center space-x-2">
                                        <IconComponent className="h-5 w-5" />
                                        <CardTitle className="text-base font-medium">
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
                                            <Plus className="h-4 w-4" />
                                        </Button>
                                    )}
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">
                                        {role.count}
                                    </div>
                                    <CardDescription className="text-sm text-muted-foreground">
                                        {role.description}
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
