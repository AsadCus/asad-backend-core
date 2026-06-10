<?php

namespace App\Services;

/**
 * Module Playbooks — All 13 modules from KTS Manual v1.
 *
 * This file contains the static documentation playbook data for all modules.
 * Separated from DocumentationService for maintainability.
 *
 * @see DocumentationService::getIndexData()
 */
class DocumentationModulePlaybooks
{
    /**
     * Get all 13 module playbooks.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        $modules = [
            // ─── 1. Dashboard Modules ───
            [
                'id' => 'dashboard-module',
                'title' => 'Dashboard Modules',
                'overview' => 'The Dashboard Module provides a centralized operational view for sales performance, daily collections, upcoming departures, and recent customer interest. It is primarily used by SuperAdmin, Sales, and Admin users for daily monitoring and quick decision-making.',
                'highlights' => [
                    'Total Sales for Fiscal Year',
                    'Daily Payment',
                    'Upcoming Departures',
                    'Recent Customers',
                    'Fiscal year-based dashboard filtering',
                ],
                'procedures' => [
                    [
                        'name' => 'Total Sales for Fiscal Year',
                        'type' => 'article',
                        'status' => 'done',
                        'purpose' => 'This module helps management and sales teams monitor annual sales performance and track company revenue growth.',
                        'features' => [
                            'View the total number of sales transactions (FYTD #)',
                            'View the total sales amount generated (FYTD $)',
                            'Filter and select different fiscal years using the dropdown selector',
                        ],
                        'steps' => [
                            [
                                'path' => '/dashboard',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Open Dashboard and locate the Total Sales for Fiscal Year card in the KPI section.',
                                    ],
                                    [
                                        'type' => 'image',
                                        'src' => '/documentations/dashboard/total-sales-widget.png',
                                        'alt' => 'Total Sales for Fiscal Year KPI widget',
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => 'Use the fiscal year selector to change the current reporting period.',
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => 'Compare FYTD # and FYTD $ with your monthly target to identify if sales are on track.',
                                    ],
                                    [
                                        'type' => 'image',
                                        'src' => '/documentations/dashboard/fiscal-year-selector.png',
                                        'alt' => 'Fiscal year selector inside dashboard card',
                                    ],
                                ],
                            ],
                            'Use the fiscal year dropdown selector to switch the reporting year.',
                            'Review FYTD transaction count (FYTD #) and FYTD sales amount (FYTD $).',
                            'Use this metric for month-end and year-end performance checks.',
                        ],
                    ],
                    [
                        'name' => 'Daily Payment',
                        'type' => 'article',
                        'status' => 'done',
                        'purpose' => 'This module helps finance and sales teams monitor incoming daily payments and track payment activities efficiently.',
                        'features' => [
                            'Displays daily payment collections',
                            'Payment information will be grouped by category',
                        ],
                        'steps' => [
                            [
                                'path' => '/dashboard',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Open Dashboard and review the Daily Payment widget.',
                                    ],
                                    [
                                        'type' => 'image',
                                        'src' => '/documentations/dashboard/daily-payment-widget.png',
                                        'alt' => 'Daily Payment widget on dashboard',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Confirm total payment received for the day.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Review grouped payment categories to identify major inflow sources.',
                                    ],
                                    [
                                        'type' => 'image',
                                        'src' => '/documentations/dashboard/daily-payment-categories.png',
                                        'alt' => 'Daily payment categories grouped by type',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Use this data for daily reconciliation with finance records.',
                                    ],
                                ],
                                'path' => '/dashboard',
                            ],
                        ],
                    ],
                    [
                        'name' => 'Upcoming Departures',
                        'type' => 'article',
                        'status' => 'done',
                        'purpose' => 'This module helps SuperAdmin monitor departure schedules and seat availability for travel packages.',
                        'features' => [
                            'View upcoming departure schedules',
                            'Display available seat information',
                            'Sort departures based on nearest departure date',
                            'Quick access to full departure listing using the View All button',
                        ],
                        'steps' => [
                            [
                                'path' => '/dashboard',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Review the Upcoming Departures section on Dashboard.',
                                    ],
                                    [
                                        'type' => 'image',
                                        'src' => '/documentations/dashboard/upcoming-departures-widget.png',
                                        'alt' => 'Upcoming Departures widget overview',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Validate departure date, package name, and available seat count.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Prioritize packages with nearest departure dates for operations follow-up.',
                                    ],
                                    [
                                        'type' => 'image',
                                        'src' => '/documentations/dashboard/upcoming-departures-list.png',
                                        'alt' => 'Upcoming departures sorted by nearest date',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click View All to open complete departure listing when deeper checks are needed.',
                                    ],
                                ],
                                'path' => '/dashboard',
                            ],
                        ],
                    ],
                    [
                        'name' => 'Recent Customers',
                        'type' => 'article',
                        'status' => 'done',
                        'purpose' => 'This module helps sales teams manage customer follow-ups and improve conversion opportunities.',
                        'features' => [
                            'View recent customer enquiries or interests',
                            'Track customer engagement',
                            'Monitor potential leads for follow-up',
                        ],
                        'steps' => [
                            [
                                'path' => '/dashboard',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Open the Recent Customers panel from Dashboard.',
                                    ],
                                    [
                                        'type' => 'image',
                                        'src' => '/documentations/dashboard/recent-customers-panel.png',
                                        'alt' => 'Recent Customers panel with newest enquiries',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Review newly interested leads and their contact references.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Use this list as follow-up queue for Sales conversion activities.',
                                    ],
                                    [
                                        'type' => 'image',
                                        'src' => '/documentations/dashboard/recent-customers-followup.png',
                                        'alt' => 'Recent customers list used as follow-up queue',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Coordinate with enquiry and quotation flow for fast response.',
                                    ],
                                ],
                                'path' => '/dashboard',
                            ],
                        ],
                    ],
                ],
            ],

            // ─── 2. Master Modules ───
            [
                'id' => 'master-module',
                'title' => 'Master Modules',
                'overview' => 'The Master Module is the core configuration center for users, countries, fiscal years, and operational finance presets. It is mainly managed by SuperAdmin to keep data scope, financial setup, and governance consistent across countries.',
                'highlights' => [
                    'User Roles and Add New User',
                    'Add New Country',
                    'Add New Fiscal Year',
                    'Country-based access control',
                ],
                'procedures' => [
                    [
                        'name' => 'User Roles',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Superadmin: Full system access and configuration control — manage users, countries, fiscal years, package governance, financial and fiscal configurations, and budgeting processes. Maintains overall system administration.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Finance: Manage enquiries and customer records, access the Sales Module (quotation, invoice, receipt), manage manifests, perform administrative sales tasks, and manage Daily Report and Closing Report.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Sales: Same access as Finance — manage enquiries, customer records, Sales Module, and manifests — but does NOT have permission to manage Daily Reports and Closing Reports.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Operations: Manage operational movements, PIF, itineraries, booklets, and coordinate operational workflows. Operations users do NOT have access to budgeting processes.',
                                    ],
                                    [
                                        'type' => 'image',
                                        'src' => '/documentations/master/user-roles-matrix.png',
                                        'alt' => 'User role scope matrix for master module',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Add New User',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'path' => '/master/user',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Navigate to the User menu to select the appropriate User Role.',
                                    ],
                                    [
                                        'type' => 'image',
                                        'src' => '/documentations/master/add-user-form.png',
                                        'alt' => 'Create user form in master user page',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Fill in the user information and account details.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select the assigned Country Location.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Create to save the new user.',
                                    ],
                                ],
                                'path' => '/master/user',
                            ],
                        ],
                    ],
                    [
                        'name' => 'Add New Country',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'path' => '/master/country',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click on Country in the Master settings.',
                                    ],
                                    [
                                        'type' => 'image',
                                        'src' => '/documentations/master/country-settings.png',
                                        'alt' => 'Country settings page in master module',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Add.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Enter the Country Name.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Create.',
                                    ],
                                ],
                                'path' => '/master/country',
                            ],
                        ],
                    ],
                    [
                        'name' => 'Add New Fiscal Year',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'path' => '/master/financial-year',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click on Fiscal Year in the Master settings.',
                                    ],
                                    [
                                        'type' => 'image',
                                        'src' => '/documentations/master/fiscal-year-settings.png',
                                        'alt' => 'Fiscal year settings page',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Add.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Enter the Start Date and End Date.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select Set as Default if applicable.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Create.',
                                    ],
                                ],
                                'path' => '/master/financial-year',
                            ],
                        ],
                    ],
                ],
            ],

            // ─── 3. Product and Services ───
            [
                'id' => 'product-and-services',
                'title' => 'Product and Services',
                'overview' => 'The Product and Services module manages reusable invoice items, payment methods, payment extensions, tax extensions, and discount rules. It standardizes costing behavior across quotation and invoice workflows.',
                'highlights' => [
                    'Add New Product and Services',
                    'Add New Payment Method',
                    'Add New Payment Extension',
                    'Add New Tax Extensions',
                    'Add New Discount Extensions',
                    'Reusable financial rules for invoicing',
                ],
                'procedures' => [
                    [
                        'name' => 'Add New Product and Services',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'path' => '/product-services',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Navigate to the Products and Services Menu and click on Add Item.',
                                    ],
                                    [
                                        'type' => 'gif',
                                        'src' => '/documentations/product-services/add-item-form.gif',
                                        'alt' => 'Add item panel in product and services module',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Enter the Item Header.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Enter the Item Sub Header.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Add quantity and costing if required.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Save.',
                                    ],
                                ],
                                'path' => '/product-services',
                            ],
                        ],
                    ],
                    [
                        'name' => 'Add New Payment Method',
                        'type' => 'article',
                        'status' => 'done',
                        'features' => [
                            'Set default payment method for invoices',
                            'Activate or deactivate payment methods',
                            'Display payment methods in invoice dropdown selections',
                        ],
                        'steps' => [
                            [
                                'path' => '/product-services',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Navigate to the Products and Services Menu and enter the new Payment Method Name.',
                                    ],
                                    [
                                        'type' => 'image',
                                        'src' => '/documentations/product-services/payment-method-form.png',
                                        'alt' => 'Payment method form fields',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Tick Default to set it as the default invoice payment method.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Tick Active to display it in the payment method dropdown.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Save.',
                                    ],
                                ],
                                'path' => '/product-services',
                            ],
                        ],
                    ],
                    [
                        'name' => 'Add New Payment Extension',
                        'type' => 'article',
                        'status' => 'done',
                        'features' => [
                            'Fixed amount or percentage-based calculation',
                            'Link extension directly to payment methods',
                            'Enable or disable extensions when required',
                        ],
                        'steps' => [
                            [
                                'path' => '/product-services',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Navigate to the Products and Services Menu and enter the Payment Extension Name.',
                                    ],
                                    [
                                        'type' => 'image',
                                        'src' => '/documentations/product-services/payment-extension-form.png',
                                        'alt' => 'Payment extension form in product and services',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select Others.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Choose the calculation type.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Enter the calculation value.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Tick Active.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Tick Link to Payment Method if applicable.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Save.',
                                    ],
                                ],
                                'path' => '/product-services',
                            ],
                        ],
                    ],
                    [
                        'name' => 'Add New Tax Extensions',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'path' => '/product-services',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Navigate to the Products and Services Menu.',
                                    ],
                                    [
                                        'type' => 'gif',
                                        'src' => '/documentations/product-services/tax-extension-form.gif',
                                        'alt' => 'Tax extension setup form',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Add.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Add GST to name of extension.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select TAX in drop down menu for TYPE.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select Percentage for Calculation.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Set Value eg. 7.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Set Status to Active to show in invoice.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Save.',
                                    ],
                                ],
                                'path' => '/product-services',
                            ],
                        ],
                    ],
                    [
                        'name' => 'Add New Discount Extensions',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'path' => '/product-services',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Navigate to the Products and Services Menu.',
                                    ],
                                    [
                                        'type' => 'gif',
                                        'src' => '/documentations/product-services/discount-extension-form.gif',
                                        'alt' => 'Discount extension setup form',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Add.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Add required name to Name.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select Discount in drop down menu for TYPE.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select Percentage or Fixed Amount in Calculation.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Set Value eg. 500.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Set Status to Active to show in invoice.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Save.',
                                    ],
                                ],
                                'path' => '/product-services',
                            ],
                        ],
                    ],
                ],
            ],

            // ─── 4. Enquiry Modules ───
            [
                'id' => 'enquiry-module',
                'title' => 'Enquiry Modules',
                'overview' => 'The Enquiry Module captures lead intake from website and walk-in channels. It supports general and private enquiry flows, status progression, remarks, and conversion into Confirmed Customer records.',
                'highlights' => [
                    'Change status from New Lead to Contacted',
                    'Add a New General Enquiry',
                    'Add a New Private Enquiry',
                    'Country-scoped enquiry visibility',
                ],
                'procedures' => [
                    [
                        'name' => 'New Lead to Contacted and add remarks',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'path' => '/enquiries',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Go to Enquiry Dashboard.',
                                    ],
                                    [
                                        'type' => 'gif',
                                        'src' => '/documentations/enquiry/enquiry-dashboard.gif',
                                        'alt' => 'Enquiry dashboard main listing',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Right click on the name.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Mouse over to Enquiry Status.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click on Mark as Contacted.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'The New Lead status will be changed to Contacted.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Right Click on the enquiry.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select Remarks.',
                                    ],
                                    [
                                        'type' => 'gif',
                                        'src' => '/documentations/enquiry/enquiry-remarks-modal.gif',
                                        'alt' => 'Remarks modal in enquiry record actions',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Type in remarks.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click on Add Remarks.',
                                    ],
                                ],
                                'path' => '/enquiries',
                            ],
                        ],
                    ],
                    [
                        'name' => 'Add a New General Enquiry (Walk-in Customer)',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'path' => '/general-enquiries',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Go to the General Enquiry menu.',
                                    ],
                                    [
                                        'type' => 'gif',
                                        'src' => '/documentations/enquiry/general-enquiry-create.gif',
                                        'alt' => 'Create general enquiry form',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Create New General Enquiry.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select a Package (optional) or leave it empty.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select the Country.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Fill in all mandatory fields.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Create to submit the enquiry.',
                                    ],
                                ],
                                'path' => '/general-enquiries',
                            ],
                        ],
                    ],
                    [
                        'name' => 'Add a New Private Enquiry (Walk-in Customer)',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'path' => '/private-enquiries',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Go to the Private Enquiry menu.',
                                    ],
                                    [
                                        'type' => 'gif',
                                        'src' => '/documentations/enquiry/private-enquiry-create.gif',
                                        'alt' => 'Create private enquiry form',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Create New Private Enquiry.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select the Country.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Fill in all mandatory fields.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Create to submit the enquiry.',
                                    ],
                                ],
                                'path' => '/private-enquiries',
                            ],
                        ],
                    ],
                ],
            ],

            // ─── 5. Customer ───
            [
                'id' => 'customer-module',
                'title' => 'Customer',
                'overview' => 'The Customer module stores customer master records for both manually added and enquiry-converted customers. Mandatory fields are enough for initial creation, while profile completion can happen later.',
                'highlights' => [
                    'Add New Customer manually',
                    'Use as base profile before confirmation flow',
                ],
                'procedures' => [
                    [
                        'name' => 'Add New Customer',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'path' => '/customer',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click on the Customer menu.',
                                    ],
                                    [
                                        'type' => 'gif',
                                        'src' => '/documentations/customer/customer-create-form.gif',
                                        'alt' => 'Create customer form',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Fill in the mandatory customer details.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Additional details may be completed at a later stage.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Create.',
                                    ],
                                ],
                                'path' => '/customer',
                            ],
                        ],
                    ],
                ],
            ],

            // ─── 6. Sales ───
            [
                'id' => 'sales-module',
                'title' => 'Sales',
                'overview' => 'The Sales Module manages the full commercial lifecycle: reports, quotation generation, quotation-to-invoice conversion, instalment handling, and receipt issuance. It is the primary revenue execution area.',
                'highlights' => [
                    'Generate Daily Received and Closing Reports',
                    'Create and Split Quotations',
                    'Convert Quotations to Invoices and manage Instalment Plans',
                    'Generate and Recreate Receipts',
                    'Track additional invoice items and adjustments',
                ],
                'procedures' => [
                    [
                        'name' => 'Generate Daily Received Report',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'path' => '/reports/payment',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select the Daily Received menu.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click on the Date Range field.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select the desired date range.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click OK.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Apply.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Export.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'The report will be downloaded to your computer.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Generate Closing Report',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'path' => '/reports/closing',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select the Closing Report menu.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select the desired Package or Category.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select the desired date range.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click OK.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Apply.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Export.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'The report will be downloaded to your computer.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Create a Quotation for non-umrah Services',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'path' => '/quotation',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click on the Quotation menu.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Create New Quotation.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'In the Customer section, do not select any Umrah customer package.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select the customer that has already been added to the customer list.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Add the quotation date and validation date. Note: The grand total will be auto-generated automatically.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'In the Description field, enter the title of the quotation accordingly.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select either Instalments or Full Payment. (Note: This can still be edited later during invoicing.)',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Add Items.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select the required item (e.g. Aqiqah).',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Add the item description in the description box below the selected item.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Enter the quantity and cost.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Add any extension such as discounts for individual items if required, or leave blank.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Add a total quotation discount if applicable.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Add any additional charges if required.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Edit the notes section if necessary.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Under Status, select Ready for Conversion if the quotation will later be converted into an invoice.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Create.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Create a Quotation for Umrah Confirmed Customer',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'path' => '/confirmed-customer',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click on the Confirmed Customer menu.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select the customer and click the option to Create Quotation.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'For a single quotation, ensure the Payer for all members is assigned to the main customer.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Review the quotation details.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Create Quotation.',
                                    ],
                                    [
                                        'type' => 'image',
                                        'src' => '/documentations/sales/quotation-created.png',
                                        'alt' => 'Created quotation for umrah confirmed customer',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Create a Split Quotation',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'path' => '/confirmed-customer',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click on the Confirmed Customer menu.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select the customer and click the option to Create Quotation.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'In the quotation pop-up, review the list of members.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'For members who require separate quotations, click the payee dropdown beside their names.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select the respective Payer name for each member that requires a separate quotation.',
                                    ],
                                    [
                                        'type' => 'image',
                                        'src' => '/documentations/sales/split-quotation.png',
                                        'alt' => 'Split quotation example',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Close after updating all required payees.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Review the quotation arrangement carefully.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Create Quotation. Note: Multiple quotations will be automatically created based on the selected payees. You may split the quotations into as many separate quotations as needed, depending on the total number of members in the group.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'The quotations will now appear in the quotation list as draft.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click any of the generated quotation to open it.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Change details as required.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Change status from Draft to Ready.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Update. Note: Quotation status must be Ready in order to convert to Invoice.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'How to Convert a Quotation to an Invoice',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'path' => '/quotation',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Go to the List of Quotations menu.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Options and change the quotation status to Accept Quotation.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Once the quotation is accepted, an Order Form will be automatically generated by the system before the invoice is created. The Order Form displays the customer details, selected package, payment plan, invoice summary, payment method, and total amount before the invoice is created.',
                                    ],
                                    [
                                        'type' => 'image',
                                        'src' => '/documentations/sales/ready-quotation.png',
                                        'alt' => 'Order Form generated after quotation is accepted',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'For a full payment invoice, select Full Payment under the Payment Plan section.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Expand All to review the invoice details.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Change the Invoice Name if required.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select the preferred Payment Method.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Note: Payment charges are pre-defined in the Payment Extension settings. For example, payment via Visa may include an additional 3% charge on the total invoice amount. Payment Extension settings can be edited in the Product and Services section.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Change the Invoice Date and Due Date if required.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Add items if required.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Add discounts if required.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Create.',
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => 'Note: The invoice will be generated for the confirmed customer and will appear in the Invoice menu. To edit the invoice, go to the Order menu, right-click on the customer name, and select Edit.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'How to Generate an Invoice for an Instalment Plan',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'path' => '/quotation',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Go to the List of Quotations menu.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select the quotation and right-click to convert the quotation to an invoice.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Change the Payment Plan to Instalment.',
                                    ],
                                    [
                                        'type' => 'image',
                                        'src' => '/documentations/sales/payment-plan-dropdown.png',
                                        'alt' => 'Drop down menu for Payment Plan',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'To change the deposit value type, select either Fixed Amount or Percentage.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Enter the desired amount or percentage value.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Review all invoice details by clicking Expand All.',
                                    ],
                                    [
                                        'type' => 'image',
                                        'src' => '/documentations/sales/instalment-plan-view.png',
                                        'alt' => 'Instalment Plan view',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Create.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Add More Invoices Within the Umrah Package',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Add Invoice.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Expand the new invoice section.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Add Items.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Select Item.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select Umrah Packages under Customer Confirmation Items.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Modify the amount manually for all invoices if required.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Add More Items',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Add Item.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Select Item.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select the desired item from the list.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Add a description for the selected item.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Enter the amount.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Create.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'How to Generate a Receipt',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'path' => '/invoice',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click the Invoice menu.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select the invoice to generate the receipt.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Verify that the received amount matches the invoice amount, then click Create.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Review and edit the receipt form if required.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Create.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'How to Recreate a Receipt',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'path' => '/invoice',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click the Invoice menu.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select the invoice to recreate the receipt.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Right-click on the selected invoice.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select Recreate Receipt.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Confirm by clicking Recreate Receipt.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // ─── 7. Confirmed Customer ───
            [
                'id' => 'confirmed-customer',
                'title' => 'Confirmed Customer',
                'overview' => 'This module displays the list of customers who have been confirmed from the Enquiry Dashboard.',
                'highlights' => [
                    'Customer Name – Displays the name of the main applicant',
                    'Members – Displays the total number of applicants within the group',
                    'Payment Updates – Displays the payment status of the group',
                    'Package – Displays the selected package name',
                    'Payment – Displays the total outstanding payment amount',
                ],
                'procedures' => [
                    [
                        'name' => 'Add Participants Within the Main Customer',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click on the Confirmed Customer menu.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Options and select Edit for the customer.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Note: If the new customer has already been added to the Customer menu, click the field next to Add Customer under the Group Customers section.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click on the drop-down menu, select the Customer, and click Close.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Note: If the customer is not listed, click Add Customer.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select the Pricing Plan, add the relationship to the main customer, and fill in all mandatory details.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Add additional details if required, upload the Passport copy, and upload the customer Photo.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Update.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Create a One-Time Link for Customers to Update Their Details',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Options and select Copy One-Time Link.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Send the link to the customer to complete the required details by pasting the link.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'image',
                                        'src' => '/documentations/confirmed-customer/one-time-link.png',
                                        'alt' => 'One time link option',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'How to Move Members to the Holding Area',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Options and select Move to Holding Area.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'In the pop-up form, select the package that the customer intends to change to, or leave it empty.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select the desired customer and click Move Selected Members.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Note: The selected member will be detached from the existing group and moved to the Customer Holding Area menu.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'How to Process a Customer Refund for trip cancellation',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Options and select Refund.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'In the Create Refund Receipt menu, select Trip Cancelled - Refund.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select the Refund Mode and Payment Method for the refund.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Enter the refund amount, add a description for the refund receipt, and click Refund Receipt.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Note: The refund receipt will be generated in the Receipt menu. The customer will then be listed in the Cancelled Customer menu.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // ─── 8. Customer Holding Area ───
            [
                'id' => 'customer-holding-area',
                'title' => 'Customer Holding Area',
                'overview' => 'The Customer Holding Area is used to temporarily place customers before moving them to a new package or pricing plan.',
                'highlights' => [
                    'Change Package and Pricing Plan With Overpaid Refund',
                    'Change Package With Higher Pricing Plan',
                ],
                'procedures' => [
                    [
                        'name' => 'Change Package and Pricing Plan With Overpaid Refund',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'path' => '/confirmed-customer',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click the Confirmed Customer menu.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Options and move the selected customer to the Holding Area.',
                                    ],
                                ],
                            ],
                            [
                                'path' => '/customer-holding',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'In the Customer Holding Area, click Options for the selected customer.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Move the customer to a new package by selecting the desired package from the list.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Move Selected Members.',
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => 'Note: The selected members will now be assigned to the new package.',
                                    ],
                                ],
                            ],
                            [
                                'path' => '/confirmed-customer',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Go to the Confirmed Customer menu.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Expand the customer details.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'The payment status will be reflected as Overpaid.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Refund.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Change the refund purpose by selecting Overpaid Refund.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select the refund mode.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select the payment method for the refund.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Enter the refund amount.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Add a description for the refund receipt.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Refund Receipt.',
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => 'Note: The refund receipt will be generated in the Receipt menu. The customer\'s payment status will automatically reflect as Paid.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Change Package With Higher Pricing Plan',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'path' => '/confirmed-customer',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click the Confirmed Customer menu.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Options and move the selected customer to the Holding Area.',
                                    ],
                                ],
                            ],
                            [
                                'path' => '/customer-holding',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'In the Customer Holding Area, click Options for the selected customer.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Move the customer to a new package by selecting the desired package from the list.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Move Selected Members.',
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => 'Note: The selected members will now be assigned to the new package.',
                                    ],
                                ],
                            ],
                            [
                                'path' => '/confirmed-customer',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Go to the Confirmed Customer menu.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Expand the customer details.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'The payment status will be reflected as Partially Paid.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Options and select Create Balance Invoice.',
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => 'Note: A new invoice will be generated for the customer. Repeat the receipt creation process once payment has been received from the customer.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // ─── 9. Completed Customer ───
            [
                'id' => 'completed-customer',
                'title' => 'Completed Customer',
                'overview' => 'The Completed Customer module is an archival reference for travelers who have finished their trip. It supports historical lookup, performance review, and repeat-customer follow-up workflows.',
                'highlights' => [
                    'View Completed Customers',
                    'Post-trip reference and retention analysis',
                ],
                'procedures' => [
                    [
                        'name' => 'View Completed Customers',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'path' => '/completed-customer',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Open the Completed Customer menu.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Filter by package or period to review completed trip records.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Check historical payment and participation references when needed.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Use this data for repeat-customer offers and post-trip analysis.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // ─── 10. Cancelled Customer ───
            [
                'id' => 'cancelled-customer',
                'title' => 'Cancelled Customer',
                'overview' => 'The Cancelled Customer module stores cancellation and refund outcomes for audit and service improvement. It helps teams review cancellation causes and verify refund traceability.',
                'highlights' => [
                    'View Cancelled Customers',
                    'Cancellation and refund trace history',
                ],
                'procedures' => [
                    [
                        'name' => 'View Cancelled Customers',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'path' => '/cancelled-customer',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Open the Cancelled Customer menu.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Review cancelled records with related refund context.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Check reason trends for service and process improvement.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Use records as audit support for finance and operations.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // ─── 11. Package ───
            [
                'id' => 'package-module',
                'title' => 'Package',
                'overview' => 'The Package module is the master source for operational and manifest generation. Once created, related Manifest and Ops Movement records are initialized automatically and reused downstream.',
                'highlights' => [
                    'Package Information Section',
                    'Pricing Section',
                    'Flight Details Section',
                    'Transportation Plan Section',
                    'Visa Section',
                    'Vehicle Section',
                    'Train Ticket Details Section',
                    'Accommodations Section',
                    'Rawdah Tasreeh Section',
                    'Officials Section',
                    'Package Inclusions',
                ],
                'procedures' => [
                    [
                        'name' => 'Package Information Section',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'path' => '/packages',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Navigate to the Package module.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Create New Package.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Package Number: Auto-fills (e.g., KTG2-31); can override if needed.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'image',
                                        'src' => '/documentations/package/create-form.png',
                                        'alt' => 'Create Package form showing package number and name',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Package Name: Enter descriptive name (include: destination, duration, tier, date).',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Status: Leave as "Open" (available for new bookings).',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Package Location: Select destination country.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Departure Date: Select date from calendar (must be future date).',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Return Date: Select return date (must be after departure).',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Total Seats: Enter total capacity.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Pricing Section',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Scroll to "Pricing Section".',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'For each pricing plan (Standard, Premium, etc.): fill Plan Name, Price, and Capacity.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Leave unused plans empty.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Save each plan.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Flight Details Section',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Scroll to "Flight Details Section".',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Enter the flight route description/header (e.g., "Singapore to Jeddah via Doha").',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'From: Select the departure airport from the dropdown (e.g., Singapore, Kuala Lumpur).',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'To: Select the arrival/destination airport from the dropdown (e.g., Jeddah for Umrah, Amman for Jordan).',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Airline: Enter the airline name and flight number.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'PNR: Enter the 6-digit airline booking reference code (confirm with the booking agent).',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Departure Date/Time: Click the calendar picker, select the departure date and time.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Arrival Date/Time: Click the calendar picker, select the arrival date and time.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'image',
                                        'src' => '/documentations/package/flight-details.png',
                                        'alt' => 'Flight Details Section showing arrival and departure inputs',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Save.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Transportation Plan Section',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Scroll to "Transportation Plan Section".',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click "Add Transportation".',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Enter transportation details: Route, Vehicle Type, Transportation Provider, and Date/Time.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Add again for each transportation leg.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Visa Section',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Scroll to "Visa Section".',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select visa type from dropdown (Umrah, Hajj, or Tourist Visa).',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Enter visa details: Required documents, Processing time, and Expiry validity.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Save.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Vehicle Section',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Scroll to "Vehicle Section".',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Enter Driver 1 details: Name, License Number, Contact Phone, Vehicle Make/Model, and License Plate.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Enter Driver 2 details if applicable.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Save.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Train Ticket Details Section',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Scroll to "Train Ticket Details Section".',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click "Add Train".',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Enter: Train Description, Ticket Type, and additional fields (departure time, class, seats).',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Save.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Accommodations Section',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Scroll to "Accommodations Section".',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click "Add Accommodation".',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Enter details: Location, Hotel Name, Hotel Category, Room Type, Check-In Date, Check-Out Date, and Number of Rooms.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Add again to add additional hotels.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'image',
                                        'src' => '/documentations/package/accommodations.png',
                                        'alt' => 'Accommodations Section showing multiple hotels',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'If the package includes multiple hotels or accommodations, click Add Accommodation again to add additional entries.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Save.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Rawdah Tasreeh Section',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Add Rawdah Tasreeh.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Enter the visit date.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select the gender category (Men or Women).',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Enter the allocated visit time.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Enter the total number of visitors for the selected slot.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Officials Section',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select the official type from the dropdown menu.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Enter the official’s name (required).',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Enter the hotel location.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Package Inclusions',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Navigate to the Inclusions section of the package details.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select the checkbox for each service included in the package (e.g., Flight, Hotel, Transport, Visa, Insurance).',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Add custom text or notes for inclusions if required.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Save.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // ─── 12. Manifest ───
            [
                'id' => 'manifest-module',
                'title' => 'Manifest',
                'overview' => 'The Manifest module centralizes traveler-level package data including payment status, room assignment, attendance tracking, and required travel documents. Manifest records are generated automatically per package.',
                'highlights' => [
                    'Main Dashboard',
                    'Room List (Location 01 and 02)',
                    'Name List Course & Collection Item',
                    'Upload Documents (Flight Tickets, Visa, Train Tickets, Hotel, Passport, Photo, Receipt)',
                    'Room grouping and document completeness control',
                ],
                'procedures' => [
                    [
                        'name' => 'Main',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'path' => '/manifests',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Navigate to the Manifest module and select a package.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Update Manifest.',
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => 'Note: It is recommended not to move customers between groups within the Main Section. Group adjustments should instead be managed within the Room List Section.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Gender',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Locate the Gender column on the Main Dashboard.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Verify the gender (Male/Female) for each traveler.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'If a gender is incorrect, edit it directly (critical for Rawdah Tasreeh registration).',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Discount',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'View the Discount column on the Main Dashboard.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Hover over the discount field to see any discount applied to the customer.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Verify that the correct pricing adjustments have been applied.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Payment Date',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Check the Payment Date column on the Main Dashboard.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Ensure the date recorded matches when the payment was successfully processed.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Use this for daily reconciliation and audit trails.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Receipt',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Navigate to the Receipt Tab.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Upload the related payment receipt.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Payment Status',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Verify the Payment Status column (e.g., Unpaid, Partially Paid, Fully Paid, Overpaid).',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'The payment status is automatically calculated based on issued invoices and receipts.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Officials Assignment',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Right-click on the official’s name.',
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => 'Note: By default, officials are not assigned to any hotel or room location.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Assign the official to the intended hotel location.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Unassign the official when necessary.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Airline Name List',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Navigate to the Airline Name List tab.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Review the list of travelers grouped by flight allocations.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Verify passenger details match passport data for flight manifest submission.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Room List – (Location 01)',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Navigate to Room List – (Location 01).',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Drag & Drop: Click and hold the 6-dot icon located on the far left of the table row, then drag the customer into the intended room group.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'gif',
                                        'src' => '/documentations/manifest/room-list-drag.gif',
                                        'alt' => 'Room List drag and drop feature',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Note: Customers must belong to the same pricing plan in order to be grouped together. Otherwise, the system will prompt an over-capacity or mismatch error. If there are last-minute room arrangement changes, users may manually adjust the room type to match the required capacity.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'To reset the room structure, click "Reset This Room List Structure".',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select the target replication source if applicable (e.g., replicate from an existing structure).',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Update Manifest to save changes.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Room List – (Location 02)',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Navigate to Room List – (Location 02).',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Drag & Drop: Click and hold the 6-dot icon located on the far left of the table row, then drag the customer into the intended room group.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Business Rule: Customers must belong to the same pricing plan in order to be grouped together. Otherwise, the system will prompt an over-capacity or mismatch error.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'To replicate room arrangement from Location 01, click "Reset This Room List Structure".',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select "Location 01" as the source to duplicate the room structure.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Update Manifest to save changes.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Room List for Official Check-In – (Location 01)',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Navigate to Room List for Official Check-In – (Location 01).',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Verify the room assignments specifically prepared for hotel check-in at Location 01.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Print or export the official check-in document for the hotel administration.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'image',
                                        'src' => '/documentations/manifest/export-checkin.png',
                                        'alt' => 'Export Check-in Document',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Room List for Official Check-In – (Location 02)',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Navigate to Room List for Official Check-In – (Location 02).',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Verify the room assignments prepared for hotel check-in at Location 02.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Export the official check-in sheet to coordinate with ground officials.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Name List Course & Collection Item',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Navigate to Name List Course & Collection Item.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Mark attendance by checking the Course Attended checkbox for each traveler.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Mark gift/kit distribution by checking the Item Collected checkbox.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Update Manifest to save.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Flight Tickets',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Navigate to the Flight Tickets section under Upload Documents.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Upload and select the airline ticket file (PDF or image).',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Upload representative samples of flight ticket confirmations.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Save.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Visa',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Navigate to the Visa section under Upload Documents.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Upload and select the visa approvals or stamps.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Ensure files are clear and readable for immigration validation.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Save.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Train Tickets',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Navigate to the Train Tickets section under Upload Documents.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Upload and select the train booking confirmation file.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Verify train descriptions (e.g., Jeddah to Madinah Express).',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Save.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Hotel',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Navigate to the Hotel section under Upload Documents.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Upload and select hotel booking confirmations or room charts.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Ensure hotel category and check-in/out dates match the package.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Save.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Passport',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Navigate to the Passport section under Upload Documents.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'If a customer has not uploaded their passport via the one-time link, upload it manually.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Select the scanned passport file and click Save.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Photo',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Navigate to the Photo section under Upload Documents.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Verify that a passport-style photo is uploaded (JPG/PNG).',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'If missing, upload it manually on behalf of the customer and click Save.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Arabic Names',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Navigate to the Arabic Names section.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Verify or enter the customer\'s name in Arabic characters (required for Saudi visa processing).',
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => 'Note: Arabic names are automatically generated by the system. However, users are advised to verify the Arabic spelling for accuracy before final usage.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Save the updated name details.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Receipt Documents',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Navigate to the Receipt section under Upload Documents.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Upload Receipt to attach bank transfer confirmations or receipts.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Note: A maximum of 3 receipt slots is available per group.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Save to maintain audit traceability.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // ─── 13. Ops Movement ───
            [
                'id' => 'ops-movement',
                'title' => 'Ops Movement',
                'overview' => 'Ops Movement consolidates operational execution data through five sections: Operations Movement, PIF, Itinerary, Booklet, and Budget. Most fields are derived from Package and Manifest data with role-based edit control.',
                'highlights' => [
                    'Operations Movement',
                    'PIF (Passenger Information Form)',
                    'Itinerary and Booklet',
                    'Budget Tracking',
                    'User Access & Permissions',
                ],
                'procedures' => [
                    [
                        'name' => 'Operations Movement',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'path' => '/ops-movements',
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Navigate to Ops Movement and select the desired package.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Review the auto-populated flight, accommodation, transportation, visa, and guide details.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Verify all logistics are synchronized with the package and manifest.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'PIF',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Navigate to Ops Movement > PIF section.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Generate PIF to compile passenger names, passport numbers, dates of birth, and nationalities.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Export as PDF to download the passenger manifest for check-in and immigration.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Itinerary',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Navigate to Ops Movement > Itinerary section.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Upload Itinerary and select the day-by-day schedule file (PDF or document).',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Add a clear description (e.g., "Itinerary - Umrah May 2025") and save.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Booklet',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Navigate to Ops Movement > Booklet section.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Click Upload Booklet and select the official travel guide document (PDF recommended).',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Add a description (e.g., "Umrah Package Booklet - May 2025") and save.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Budget',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Navigate to Ops Movement > Budget section.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Note: Only users with the SuperAdmin role are authorized to edit the Budget section.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Review the auto-calculated revenue derived from confirmed customer invoices.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Enter fixed and variable costs (flights, hotels, transport, guides, visa fees).',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Save to calculate Total Cost, Profit, and Profit Margin %.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'User Access & Permissions',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'SuperAdmin users are authorized to edit Operations Movement, PIF, and Budget.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Users with the Operations role are permitted to upload itinerary details, upload package booklets, view Operations Movement information, export Operations Movement reports, and export PIF reports.',
                                    ],
                                ],
                            ],
                            [
                                'content_blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Note: Operations users do not have permission to edit operational data within these modules.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        return array_map(static function (array $module): array {
            if (! isset($module['procedures']) || ! is_array($module['procedures'])) {
                return $module;
            }

            $module['procedures'] = self::normalizeProcedures($module['procedures']);

            return $module;
        }, $modules);
    }

    /**
     * @param  array<int, array<string, mixed>>  $procedures
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeProcedures(array $procedures): array
    {
        return array_map(static function (array $procedure): array {
            $steps = $procedure['steps'] ?? [];

            if (! is_array($steps)) {
                $steps = [];
            }

            $procedure['steps'] = self::normalizeSteps($steps);

            return $procedure;
        }, $procedures);
    }

    /**
     * @param  array<int, mixed>  $steps
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeSteps(array $steps): array
    {
        return array_map(static function (mixed $step): array {
            if (is_string($step)) {
                return [
                    'content_blocks' => [
                        [
                            'type' => 'text',
                            'text' => $step,
                        ],
                    ],
                ];
            }

            if (! is_array($step)) {
                return [
                    'content_blocks' => [],
                ];
            }

            if (isset($step['content_blocks']) && is_array($step['content_blocks'])) {
                return $step;
            }

            $normalizedStep = $step;
            $contentBlocks = [];

            if (isset($step['text']) && is_string($step['text']) && $step['text'] !== '') {
                $contentBlocks[] = [
                    'type' => 'text',
                    'text' => $step['text'],
                ];
            }

            if (isset($step['screenshot']) && is_string($step['screenshot']) && $step['screenshot'] !== '') {
                $contentBlocks[] = [
                    'type' => 'image',
                    'src' => $step['screenshot'],
                    'alt' => 'Step visual guide',
                ];
            }

            unset($normalizedStep['text'], $normalizedStep['screenshot']);
            $normalizedStep['content_blocks'] = $contentBlocks;

            return $normalizedStep;
        }, $steps);
    }
}
