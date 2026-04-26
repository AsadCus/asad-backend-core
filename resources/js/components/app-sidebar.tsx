import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import cancelledCustomer from '@/routes/cancelled-customer';
import completedCustomer from '@/routes/completed-customer';
import confirmedCustomer from '@/routes/confirmed-customer';
import customer from '@/routes/customer';
import customerHolding from '@/routes/customer-holding';
import enquiries from '@/routes/enquiries';
import generalEnquiries from '@/routes/general-enquiries';
import invoice from '@/routes/invoice';
import manifests from '@/routes/manifests';
import master from '@/routes/master';
import branch from '@/routes/master/branch';
import financialYear from '@/routes/master/financial-year';
import user, { create as createUser } from '@/routes/master/user';
import masterAdmin from '@/routes/master/user/admin';
import masterCustomer from '@/routes/master/user/customer';
import masterOperations from '@/routes/master/user/operations';
import masterSales from '@/routes/master/user/sales';
import opsMovements from '@/routes/ops-movements';
import order from '@/routes/order';
import packages from '@/routes/packages';
import privateEnquiries from '@/routes/private-enquiries';
import quotation from '@/routes/quotation';
import quotationItem from '@/routes/quotation-items';
import receipt from '@/routes/receipt';
import sales from '@/routes/sales';
import userLogs from '@/routes/user-logs';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    ClipboardList,
    FileText,
    FileUser,
    Globe,
    Handshake,
    Inbox,
    Landmark,
    LayoutGrid,
    ListOrdered,
    Luggage,
    Map,
    Package,
    Receipt,
    ReceiptText,
    Route,
    TicketCheck,
    User,
    UserCheck,
    UserMinus,
    UserX,
    Wallet,
} from 'lucide-react';
import AppLogo from './app-logo';

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const permissions = auth?.permissions || [];
    const roles = auth?.roles || [];
    const canViewDocumentation = Boolean(auth?.can_view_documentation);
    const scopeMode = String(auth?.scope_mode ?? 'country').toLowerCase();
    const isSalesOnlyRole = roles.includes('sales') && !roles.includes('admin');
    const isOperationsOnlyRole =
        roles.includes('operations') && roles.length === 1;

    const mainNavItems: NavItem[] = isOperationsOnlyRole
        ? [
              ...(permissions.includes('ops-movement view')
                  ? [
                        {
                            title: 'Ops Movement',
                            href: opsMovements.index.url(),
                            icon: Route,
                        },
                    ]
                  : []),
          ]
        : [
              ...(permissions.includes('dashboard view') && !isSalesOnlyRole
                  ? [
                        {
                            title: 'Dashboard',
                            href: dashboard(),
                            icon: LayoutGrid,
                        },
                    ]
                  : []),
              ...(permissions.includes('master view')
                  ? [
                        {
                            title: 'Master',
                            href: master.index.url(),
                            icon: FileText,
                            subItems: [
                                {
                                    title: 'Add New User',
                                    href: createUser().url,
                                    icon: User,
                                },
                                {
                                    title: 'User Management',
                                    href: user.index.url(),
                                    icon: User,
                                    matchExact: true,
                                    subItems: [
                                        {
                                            title: 'Administrator',
                                            href: masterAdmin.index.url(),
                                        },
                                        {
                                            title: 'Salesperson',
                                            href: masterSales.index.url(),
                                        },
                                        {
                                            title: 'Operations',
                                            href: masterOperations.index.url(),
                                        },

                                        {
                                            title: 'Customer',
                                            href: masterCustomer.index.url(),
                                        },
                                    ],
                                },
                                {
                                    title: 'Country',
                                    href: '/master/country',
                                    icon: Globe,
                                },
                                ...(scopeMode === 'country'
                                    ? []
                                    : [
                                          {
                                              title: 'Branch',
                                              href: branch.index.url(),
                                              icon: Map,
                                          },
                                      ]),
                                {
                                    title: 'Fiscal Year',
                                    href: financialYear.index.url(),
                                    icon: Landmark,
                                },
                                ...(permissions.includes('quotation view')
                                    ? [
                                          {
                                              title: 'Products and Services',
                                              icon: ListOrdered,
                                              href: quotationItem.index.url(),
                                          },
                                      ]
                                    : []),
                            ],
                        },
                    ]
                  : []),
              ...(permissions.includes('sales view') ||
              permissions.includes('quotation view') ||
              permissions.includes('order view') ||
              permissions.includes('invoice view') ||
              permissions.includes('receipt view')
                  ? [
                        {
                            title: 'Sales',
                            icon: Handshake,
                            ...(permissions.includes('sales view')
                                ? { href: sales.index.url() }
                                : {}),
                            subItems: [
                                ...(permissions.includes('quotation view')
                                    ? [
                                          {
                                              title: 'Quotation',
                                              icon: ReceiptText,
                                              href: quotation.index.url(),
                                          },
                                      ]
                                    : []),
                                ...(permissions.includes('order view')
                                    ? [
                                          {
                                              title: 'Order',
                                              icon: TicketCheck,
                                              href: order.index.url(),
                                          },
                                      ]
                                    : []),
                                ...(permissions.includes('invoice view')
                                    ? [
                                          {
                                              title: 'Invoice',
                                              icon: Receipt,
                                              href: invoice.index.url(),
                                          },
                                      ]
                                    : []),
                                ...(permissions.includes('receipt view')
                                    ? [
                                          {
                                              title: 'Receipt',
                                              icon: Wallet,
                                              href: receipt.index.url(),
                                          },
                                      ]
                                    : []),
                            ],
                        },
                    ]
                  : []),
              ...(permissions.includes('customer view')
                  ? [
                        {
                            title: 'Customer',
                            href: customer.index.url(),
                            icon: FileUser,
                        },
                    ]
                  : []),

              ...(permissions.includes('general-enquiry view') ||
              permissions.includes('private-enquiry view')
                  ? [
                        {
                            title: 'Enquiry',
                            icon: Inbox,
                            subItems: [
                                ...(permissions.includes(
                                    'general-enquiry view',
                                ) &&
                                permissions.includes('private-enquiry view')
                                    ? [
                                          {
                                              title: 'Enquiry Dashboard',
                                              href: enquiries.index.url(),
                                              icon: ClipboardList,
                                          },
                                      ]
                                    : []),
                                ...(permissions.includes(
                                    'general-enquiry view',
                                ) && !roles.includes('customer')
                                    ? [
                                          {
                                              title: 'General Enquiry',
                                              href: generalEnquiries.index.url(),
                                              icon: Globe,
                                          },
                                      ]
                                    : []),
                                ...(permissions.includes('private-enquiry view')
                                    ? [
                                          {
                                              title: 'Private Enquiry',
                                              href: privateEnquiries.index.url(),
                                              icon: Luggage,
                                          },
                                      ]
                                    : []),
                            ],
                        },
                    ]
                  : []),
              ...(permissions.includes('customer view')
                  ? [
                        {
                            title: 'Confirmed Customer',
                            href: confirmedCustomer.index.url(),
                            icon: UserCheck,
                        },
                        {
                            title: 'Customer Holding Area',
                            href: customerHolding.index.url(),
                            icon: UserMinus,
                        },
                        {
                            title: 'Completed Customer',
                            href: completedCustomer.index.url(),
                            icon: UserCheck,
                        },
                        {
                            title: 'Cancelled Customer',
                            href: cancelledCustomer.index.url(),
                            icon: UserX,
                        },
                    ]
                  : []),
              ...(permissions.includes('package view')
                  ? [
                        {
                            title: 'Package',
                            href: packages.index.url(),
                            icon: Package,
                        },
                    ]
                  : []),
              ...(permissions.includes('manifest view') ||
              roles.includes('sales')
                  ? [
                        {
                            title: 'Manifest',
                            href: manifests.index.url(),
                            icon: ClipboardList,
                        },
                    ]
                  : []),
              ...(permissions.includes('ops-movement view')
                  ? [
                        {
                            title: 'Ops Movement',
                            href: opsMovements.index.url(),
                            icon: Route,
                        },
                    ]
                  : []),
              ...(!roles.includes('sales') && !roles.includes('operations')
                  ? [
                        {
                            title: 'User Logs',
                            href: userLogs.index.url(),
                            icon: FileText,
                        },
                    ]
                  : []),
          ];

    const footerNavItems: NavItem[] = [
        ...(canViewDocumentation
            ? [
                  {
                      title: 'Documentation',
                      href: '/documentations',
                      icon: BookOpen,
                  },
              ]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link
                                href={
                                    isOperationsOnlyRole
                                        ? opsMovements.index.url()
                                        : isSalesOnlyRole
                                          ? enquiries.index.url()
                                          : dashboard()
                                }
                                prefetch
                            >
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
