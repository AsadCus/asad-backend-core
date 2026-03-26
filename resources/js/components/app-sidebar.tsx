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
// import agreement from '@/routes/agreement';
import confirmedCustomer from '@/routes/confirmed-customer';
import customer from '@/routes/customer';
import customerHolding from '@/routes/customer-holding';
import enquiries from '@/routes/enquiries';
import generalEnquiries from '@/routes/general-enquiries';
import invoice from '@/routes/invoice';
// import maid from '@/routes/maid';
import manifests from '@/routes/manifests';
import master from '@/routes/master';
import branch from '@/routes/master/branch';
import financialYear from '@/routes/master/financial-year';
import user, { create as createUser } from '@/routes/master/user';
import masterAdmin from '@/routes/master/user/admin';
import masterCustomer from '@/routes/master/user/customer';
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
// import schedule from '@/routes/schedule';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    // Calendar,
    ClipboardList,
    // FileCheck,
    // FileCheck2,
    // FilePlus,
    FileText,
    FileUser,
    Globe,
    Handshake,
    // HeartHandshake,
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

        // ...(permissions.includes('maid view') && !roles.includes('customer')
        //     ? [
        //           {
        //               title: 'Maid Profile',
        //               href: maid.index.url(),
        //               icon: HeartHandshake,
        //           },
        //       ]
        //     : []),
        ...(permissions.includes('general-enquiry view') ||
        permissions.includes('private-enquiry view')
            ? [
                  {
                      title: 'Enquiry',
                      icon: Inbox,
                      subItems: [
                          ...(permissions.includes('general-enquiry view') &&
                          permissions.includes('private-enquiry view')
                              ? [
                                    {
                                        title: 'Enquiry Dashboard',
                                        href: enquiries.index.url(),
                                        icon: ClipboardList,
                                    },
                                ]
                              : []),
                          ...(permissions.includes('general-enquiry view') &&
                          !roles.includes('customer')
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
                      title: 'Customer Holding',
                      href: customerHolding.index.url(),
                      icon: UserCheck,
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
        ...(permissions.includes('manifest view') || roles.includes('sales')
            ? [
                  {
                      title: 'Manifest',
                      href: manifests.index.url(),
                      icon: ClipboardList,
                  },
              ]
            : []),
        ...(permissions.includes('manifest view')
            ? [
                  {
                      title: 'Ops Movement',
                      href: opsMovements.index.url(),
                      icon: Route,
                  },
              ]
            : []),
        ...(!roles.includes('sales')
            ? [
                  {
                      title: 'User Logs',
                      href: userLogs.index.url(),
                      icon: FileText,
                  },
              ]
            : []),
        // ...(permissions.includes('schedule view') &&
        // permissions.includes('agreement view')
        //     ? [
        //           {
        //               title: 'Report',
        //               icon: FileCheck2,
        //               subItems: [
        //                   ...(permissions.includes('schedule view')
        //                       ? [
        //                             {
        //                                 title: 'Payment Schedule',
        //                                 icon: Calendar,
        //                                 href: schedule.index.url(),
        //                             },
        //                         ]
        //                       : []),
        //                   ...(permissions.includes('agreement view')
        //                       ? [
        //                             {
        //                                 title: 'Payment Agreement',
        //                                 icon: FileCheck,
        //                                 href: agreement.index.url(),
        //                             },
        //                         ]
        //                       : []),
        //               ],
        //           },
        //       ]
        //     : []),
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
