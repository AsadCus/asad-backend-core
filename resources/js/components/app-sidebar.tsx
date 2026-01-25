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
import agreement from '@/routes/agreement';
import customer from '@/routes/customer';
import invoice from '@/routes/invoice';
import maid from '@/routes/maid';
import master from '@/routes/master';
import branch from '@/routes/master/branch';
import financialYear from '@/routes/master/financial-year';
import user, { create as createUser } from '@/routes/master/user';
import masterAdmin from '@/routes/master/user/admin';
import masterCustomer from '@/routes/master/user/customer';
import masterSales from '@/routes/master/user/sales';
import masterSupplier from '@/routes/master/user/supplier';
import order from '@/routes/order';
import quotation from '@/routes/quotation';
import quotationItem from '@/routes/quotation-items';
import receipt from '@/routes/receipt';
import sales from '@/routes/sales';
import schedule from '@/routes/schedule';
import supplier from '@/routes/supplier';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    Calendar,
    FileCheck,
    FileCheck2,
    FilePlus,
    FileText,
    FileUser,
    Handshake,
    HeartHandshake,
    Landmark,
    LayoutGrid,
    ListOrdered,
    Map,
    Receipt,
    ReceiptText,
    TicketCheck,
    User,
    Wallet,
} from 'lucide-react';
import AppLogo from './app-logo';

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const permissions = auth?.permissions || [];
    const roles = auth?.roles || [];

    const mainNavItems: NavItem[] = [
        ...(permissions.includes('dashboard view')
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
                              subItems: [
                                  {
                                      title: 'Administrator',
                                      href: masterAdmin.index.url(),
                                  },
                                  {
                                      title: 'Sales',
                                      href: masterSales.index.url(),
                                  },

                                  {
                                      title: 'Customer',
                                      href: masterCustomer.index.url(),
                                  },
                                  {
                                      title: 'Supplier',
                                      href: masterSupplier.index.url(),
                                  },
                              ],
                          },
                          {
                              title: 'Branch',
                              href: branch.index.url(),
                              icon: Map,
                          },
                          {
                              title: 'Fiscal Year',
                              href: financialYear.index.url(),
                              icon: Landmark,
                          },
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
                                        title: 'Products and Services',
                                        icon: ListOrdered,
                                        href: quotationItem.index.url(),
                                    },
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
        ...(permissions.includes('supplier view')
            ? [
                  {
                      title: 'Supplier',
                      href: supplier.index.url(),
                      icon: FilePlus,
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
        ...(permissions.includes('maid view') && !roles.includes('customer')
            ? [
                  {
                      title: 'Maid Profile',
                      href: maid.index.url(),
                      icon: HeartHandshake,
                  },
              ]
            : []),
        ...(permissions.includes('schedule view') &&
        permissions.includes('agreement view')
            ? [
                  {
                      title: 'Report',
                      icon: FileCheck2,
                      subItems: [
                          ...(permissions.includes('schedule view')
                              ? [
                                    {
                                        title: 'Payment Schedule',
                                        icon: Calendar,
                                        href: schedule.index.url(),
                                    },
                                ]
                              : []),
                          ...(permissions.includes('agreement view')
                              ? [
                                    {
                                        title: 'Payment Agreement',
                                        icon: FileCheck,
                                        href: agreement.index.url(),
                                    },
                                ]
                              : []),
                      ],
                  },
              ]
            : []),
    ];

    const footerNavItems: NavItem[] = [
        // {
        //     title: 'Repository',
        //     href: '#',
        //     icon: Folder,
        // },
        // {
        //     title: 'Documentation',
        //     href: '#',
        //     icon: BookOpen,
        // },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
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
