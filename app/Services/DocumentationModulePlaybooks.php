<?php

namespace App\Services;

/**
 * Module Playbooks — All 13 modules from KTS Manual v1.
 *
 * This file contains the static documentation playbook data for all modules.
 * Separated from DocumentationService for maintainability.
 *
 * @see \App\Services\DocumentationService::getIndexData()
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
        return [
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
                                'text' => 'Contoh instruksi langkah dengan visual.',
                                'path' => '/fitur-terkait',
                                'screenshot' => '/documentations/sample-step.png'
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
                                'text' => 'Open Dashboard and review the Daily Payment widget.',
                                'path' => '/dashboard',
                            ],
                            'Confirm total payment received for the day.',
                            'Review grouped payment categories to identify major inflow sources.',
                            'Use this data for daily reconciliation with finance records.',
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
                                'text' => 'Review the Upcoming Departures section on Dashboard.',
                                'path' => '/dashboard',
                            ],
                            'Validate departure date, package name, and available seat count.',
                            'Prioritize packages with nearest departure dates for operations follow-up.',
                            'Click View All to open complete departure listing when deeper checks are needed.',
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
                                'text' => 'Open the Recent Customers panel from Dashboard.',
                                'path' => '/dashboard',
                            ],
                            'Review newly interested leads and their contact references.',
                            'Use this list as follow-up queue for Sales conversion activities.',
                            'Coordinate with enquiry and quotation flow for fast response.',
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
                            'SuperAdmin: Full system access and configuration control — manage users, countries, fiscal years, package governance, financial and fiscal configurations, and budgeting processes. Maintains overall system administration.',
                            'Sales: Manage enquiries and customer records, access the Sales Module (quotation, invoice, receipt), manage manifests, perform administrative sales tasks, and manage Daily Report and Closing Report.',
                            'Admin: Same access as Sales — manage enquiries, customer records, Sales Module, and manifests — but does NOT have permission to manage Daily Reports and Closing Reports.',
                            'Operations: Manage operational movements, PIF, itineraries, booklets, and coordinate operational workflows. Operations users do NOT have access to budgeting processes.',
                        ],
                    ],
                    [
                        'name' => 'Add New User',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'text' => 'Navigate to the User menu to select the appropriate User Role.',
                                'path' => '/master/user',
                            ],
                            'Fill in the user information and account details.',
                            'Select the assigned Country Location.',
                            'Click Create to save the new user.',
                        ],
                    ],
                    [
                        'name' => 'Add New Country',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'text' => 'Click on Country in the Master settings.',
                                'path' => '/master/country',
                            ],
                            'Click Add.',
                            'Enter the Country Name.',
                            'Click Create.',
                        ],
                    ],
                    [
                        'name' => 'Add New Fiscal Year',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'text' => 'Click on Fiscal Year in the Master settings.',
                                'path' => '/master/financial-year',
                            ],
                            'Click Add.',
                            'Enter the Start Date and End Date.',
                            'Select Set as Default if applicable.',
                            'Click Create.',
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
                                'text' => 'Navigate to the Products and Services Menu and click on Add Item.',
                                'path' => '/product-services',
                            ],
                            'Enter the Item Header.',
                            'Enter the Item Sub Header.',
                            'Add quantity and costing if required.',
                            'Click Save.',
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
                                'text' => 'Navigate to the Products and Services Menu and enter the new Payment Method Name.',
                                'path' => '/product-services',
                            ],
                            'Tick Default to set it as the default invoice payment method.',
                            'Tick Active to display it in the payment method dropdown.',
                            'Click Save.',
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
                                'text' => 'Navigate to the Products and Services Menu and enter the Payment Extension Name.',
                                'path' => '/product-services',
                            ],
                            'Select Others.',
                            'Choose the calculation type.',
                            'Enter the calculation value.',
                            'Tick Active.',
                            'Tick Link to Payment Method if applicable.',
                            'Click Save.',
                        ],
                    ],
                    [
                        'name' => 'Add New Tax Extensions',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'text' => 'Navigate to the Products and Services Menu.',
                                'path' => '/product-services',
                            ],
                            'Click Add.',
                            'Add GST to name of extension.',
                            'Select TAX in drop down menu for TYPE.',
                            'Select Percentage for Calculation.',
                            'Set Value eg. 7.',
                            'Set Status to Active to show in invoice.',
                            'Click Save.',
                        ],
                    ],
                    [
                        'name' => 'Add New Discount Extensions',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'text' => 'Navigate to the Products and Services Menu.',
                                'path' => '/product-services',
                            ],
                            'Click Add.',
                            'Add required name to Name.',
                            'Select Discount in drop down menu for TYPE.',
                            'Select Percentage or Fixed Amount in Calculation.',
                            'Set Value eg. 500.',
                            'Set Status to Active to show in invoice.',
                            'Click Save.',
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
                                'text' => 'Go to Enquiry Dashboard.',
                                'path' => '/enquiries',
                            ],
                            'Right click on the name.',
                            'Mouse over to Enquiry Status.',
                            'Click on Mark as Contacted.',
                            'The New Lead status will be changed to Contacted.',
                            'Right Click on the enquiry.',
                            'Select Remarks.',
                            'Type in remarks.',
                            'Click on Add Remarks.',
                        ],
                    ],
                    [
                        'name' => 'Add a New General Enquiry (Walk-in Customer)',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'text' => 'Go to the General Enquiry menu.',
                                'path' => '/general-enquiries',
                            ],
                            'Click Create New General Enquiry.',
                            'Select a Package (optional) or leave it empty.',
                            'Select the Country.',
                            'Fill in all mandatory fields.',
                            'Click Create to submit the enquiry.',
                        ],
                    ],
                    [
                        'name' => 'Add a New Private Enquiry (Walk-in Customer)',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'text' => 'Go to the Private Enquiry menu.',
                                'path' => '/private-enquiries',
                            ],
                            'Click Create New Private Enquiry.',
                            'Select the Country.',
                            'Fill in all mandatory fields.',
                            'Click Create to submit the enquiry.',
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
                                'text' => 'Click on the Customer menu.',
                                'path' => '/customer',
                            ],
                            'Fill in the mandatory customer details.',
                            'Additional details may be completed at a later stage.',
                            'Click Create.',
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
                                'text' => 'Select the Daily Received menu.',
                                'path' => '/reports/payment',
                            ],
                            'Click on the Date Range field.',
                            'Select the desired date range.',
                            'Click OK.',
                            'Click Apply.',
                            'Click Export.',
                            'The report will be downloaded to your computer.',
                        ],
                    ],
                    [
                        'name' => 'Generate Closing Report',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'text' => 'Select the Closing Report menu.',
                                'path' => '/reports/closing',
                            ],
                            'Select the desired Package or Category.',
                            'Select the desired date range.',
                            'Click OK.',
                            'Click Apply.',
                            'Click Export.',
                            'The report will be downloaded to your computer.',
                        ],
                    ],
                    [
                        'name' => 'Create a Quotation for non-umrah Services',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'text' => 'Click on the Quotation menu.',
                                'path' => '/quotation',
                            ],
                            'Click Create New Quotation.',
                            'In the Customer section, do not select any Umrah customer package.',
                            'Select the customer that has already been added to the customer list.',
                            'Add the quotation date and validation date. Note: The grand total will be auto-generated automatically.',
                            'In the Description field, enter the title of the quotation accordingly.',
                            'Select either Instalments or Full Payment. (Note: This can still be edited later during invoicing.)',
                            'Click Add Items.',
                            'Select the required item (e.g. Aqiqah).',
                            'Add the item description in the description box below the selected item.',
                            'Enter the quantity and cost.',
                            'Add any extension such as discounts for individual items if required, or leave blank.',
                            'Add a total quotation discount if applicable.',
                            'Add any additional charges if required.',
                            'Edit the notes section if necessary.',
                            'Under Status, select Ready for Conversion if the quotation will later be converted into an invoice.',
                            'Click Create.',
                        ],
                    ],
                    [
                        'name' => 'Create a Quotation for Umrah Confirmed Customer',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'text' => 'Click on the Confirmed Customer menu.',
                                'path' => '/confirmed-customer',
                            ],
                            'Select the customer and click the option to Create Quotation.',
                            'For a single quotation, ensure the Payer for all members is assigned to the main customer.',
                            'Review the quotation details.',
                            'Click Create Quotation.',
                        ],
                    ],
                    [
                        'name' => 'Create a Split Quotation',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'text' => 'Click on the Confirmed Customer menu.',
                                'path' => '/confirmed-customer',
                            ],
                            'Select the customer and click the option to Create Quotation.',
                            'In the quotation pop-up, review the list of members.',
                            'For members who require separate quotations, click the payee dropdown beside their names.',
                            'Select the respective Payer name for each member that requires a separate quotation.',
                            'Click Close after updating all required payees.',
                            'Review the quotation arrangement carefully.',
                            'Click Create Quotation. Note: Multiple quotations will be automatically created based on the selected payees. You may split the quotations into as many separate quotations as needed, depending on the total number of members in the group.',
                            'The quotations will now appear in the quotation list as draft.',
                            'Click any of the generated quotation to open it.',
                            'Change details as required.',
                            'Change status from Draft to Ready.',
                            'Click Update. Note: Quotation status must be Ready in order to convert to Invoice.',
                        ],
                    ],
                    [
                        'name' => 'How to Convert a Quotation to an Invoice',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'text' => 'Go to the List of Quotations menu.',
                                'path' => '/quotation',
                            ],
                            'Click Options and change the quotation status to Accept Quotation.',
                            'Once the quotation is accepted, an Order Form will be automatically generated by the system before the invoice is created.',
                            'For a full payment invoice, select Full Payment under the Payment Plan section.',
                            'Click Expand All to review the invoice details.',
                            'Change the Invoice Name if required.',
                            'Select the preferred Payment Method. Note: Payment charges are pre-defined in the Payment Extension settings. For example, payment via Visa may include an additional 3% charge on the total invoice amount. Payment Extension settings can be edited in the Product and Services section.',
                            'Change the Invoice Date and Due Date if required.',
                            'Add items if required.',
                            'Add discounts if required.',
                            'Click Create. Note: The invoice will be generated for the confirmed customer and will appear in the Invoice menu. To edit the invoice, go to the Order menu, right-click on the customer name, and select Edit.',
                        ],
                    ],
                    [
                        'name' => 'How to Generate an Invoice for an Instalment Plan',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'text' => 'Go to the List of Quotations menu.',
                                'path' => '/quotation',
                            ],
                            'Select the quotation and right-click to convert the quotation to an invoice.',
                            'Change the Payment Plan to Instalment.',
                            'To change the deposit value type, select either Fixed Amount or Percentage.',
                            'Enter the desired amount or percentage value.',
                            'Review all invoice details by clicking Expand All.',
                            'Click Create.',
                        ],
                    ],
                    [
                        'name' => 'Add More Invoices Within the Umrah Package',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Click Add Invoice.',
                            'Expand the new invoice section.',
                            'Click Add Items.',
                            'Click Select Item.',
                            'Select Umrah Packages under Customer Confirmation Items.',
                            'Modify the amount manually for all invoices if required.',
                        ],
                    ],
                    [
                        'name' => 'Add More Items',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Click Add Item.',
                            'Click Select Item.',
                            'Select the desired item from the list.',
                            'Add a description for the selected item.',
                            'Enter the amount.',
                            'Click Create.',
                        ],
                    ],
                    [
                        'name' => 'How to Generate a Receipt',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'text' => 'Click the Invoice menu.',
                                'path' => '/invoice',
                            ],
                            'Select the invoice to generate the receipt.',
                            'Verify that the received amount matches the invoice amount, then click Create.',
                            'Review and edit the receipt form if required.',
                            'Click Create.',
                        ],
                    ],
                    [
                        'name' => 'How to Recreate a Receipt',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'text' => 'Click the Invoice menu.',
                                'path' => '/invoice',
                            ],
                            'Select the invoice to recreate the receipt.',
                            'Right-click on the selected invoice.',
                            'Select Recreate Receipt.',
                            'Confirm by clicking Recreate Receipt.',
                        ],
                    ],
                ],
            ],

            // ─── 7. Confirmed Customer ───
            [
                'id' => 'confirmed-customer',
                'title' => 'Confirmed Customer',
                'overview' => 'The Confirmed Customer module handles post-conversion customer management including participant grouping, self-service data completion links, holding-area transfers, and cancellation refunds.',
                'highlights' => [
                    'Add Participants Within the Main Customer',
                    'Create a One-Time Link for Customers to Update Their Details',
                    'How to Move Members to the Holding Area',
                    'How to Process a Customer Refund for trip cancellation',
                    'Group-based traveler management before manifest finalization',
                ],
                'procedures' => [
                    [
                        'name' => 'Add Participants Within the Main Customer',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'text' => 'Click on the Confirmed Customer menu.',
                                'path' => '/confirmed-customer',
                            ],
                            'Click Options and select Edit for the customer.',
                            'Click on the drop-down menu.',
                            'Select the Customer.',
                            'Click Close.',
                            'Click Add Customer.',
                            'Select the Pricing Plan.',
                            'Add the relationship to the main customer.',
                            'Fill in all mandatory details.',
                            'Add additional details if required.',
                            'Upload the Passport copy.',
                            'Upload the customer Photo.',
                            'Click Update.',
                        ],
                    ],
                    [
                        'name' => 'Create a One-Time Link for Customers to Update Their Details',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Click Options and select Copy One-Time Link.',
                            'Send the link to the customer to complete the required details by pasting the link.',
                        ],
                    ],
                    [
                        'name' => 'How to Move Members to the Holding Area',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Click Options and select Move to Holding Area.',
                            'In the pop-up form, select the package that the customer intends to change to, or leave it empty.',
                            'Select the desired customer.',
                            'Click Move Selected Members.',
                        ],
                    ],
                    [
                        'name' => 'How to Process a Customer Refund for trip cancellation',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Click Options and select Refund.',
                            'In the Create Refund Receipt menu, select Trip Cancelled - Refund.',
                            'Select the Refund Mode.',
                            'Select the Payment Method for the refund.',
                            'Enter the refund amount.',
                            'Add a description for the refund receipt.',
                            'Click Refund Receipt.',
                        ],
                    ],
                ],
            ],

            // ─── 8. Customer Holding Area ───
            [
                'id' => 'customer-holding-area',
                'title' => 'Customer Holding Area',
                'overview' => 'Customer Holding Area is a transition workspace for package changes and payment rebalancing scenarios. It supports both overpaid refund paths and higher-tier package upgrade billing paths.',
                'highlights' => [
                    'Change Package and Pricing Plan With Overpaid Refund',
                    'Change Package With Higher Pricing Plan',
                    'Temporary reassignment before final package confirmation',
                ],
                'procedures' => [
                    [
                        'name' => 'Change Package and Pricing Plan With Overpaid Refund',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'text' => 'Click the Confirmed Customer menu.',
                                'path' => '/confirmed-customer',
                            ],
                            'Click Options and move the selected customer to the Holding Area.',
                            [
                                'text' => 'In the Customer Holding Area, click Options for the selected customer.',
                                'path' => '/customer-holding',
                            ],
                            'Move the customer to a new package by selecting the desired package from the list.',
                            'Click Move Selected Members.',
                            [
                                'text' => 'Go to the Confirmed Customer menu.',
                                'path' => '/confirmed-customer',
                            ],
                            'Expand the customer details.',
                            'The payment status will be reflected as Overpaid.',
                            'Click Refund.',
                            'Change the refund purpose by selecting Overpaid Refund.',
                            'Select the refund mode.',
                            'Select the payment method for the refund.',
                            'Enter the refund amount.',
                            'Add a description for the refund receipt.',
                            'Click Refund Receipt.',
                        ],
                    ],
                    [
                        'name' => 'Change Package With Higher Pricing Plan',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            [
                                'text' => 'Click the Confirmed Customer menu.',
                                'path' => '/confirmed-customer',
                            ],
                            'Click Options and move the selected customer to the Holding Area.',
                            [
                                'text' => 'In the Customer Holding Area, click Options for the selected customer.',
                                'path' => '/customer-holding',
                            ],
                            'Move the customer to a new package by selecting the desired package from the list.',
                            'Click Move Selected Members. Note: The selected members will now be assigned to the new package.',
                            [
                                'text' => 'Go to the Confirmed Customer menu.',
                                'path' => '/confirmed-customer',
                            ],
                            'Expand the customer details.',
                            'The payment status will be reflected as Partially Paid.',
                            'Click Options and select Create Balance Invoice.',
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
                                'text' => 'Open the Completed Customer menu.',
                                'path' => '/completed-customer',
                            ],
                            'Filter by package or period to review completed trip records.',
                            'Check historical payment and participation references when needed.',
                            'Use this data for repeat-customer offers and post-trip analysis.',
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
                                'text' => 'Open the Cancelled Customer menu.',
                                'path' => '/cancelled-customer',
                            ],
                            'Review cancelled records with related refund context.',
                            'Check reason trends for service and process improvement.',
                            'Use records as audit support for finance and operations.',
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
                                'text' => 'Navigate to the Package module.',
                                'path' => '/packages',
                            ],
                            'Click Create New Package.',
                            'Package Number: Auto-fills (e.g., KTG2-31); can override if needed.',
                            'Package Name: Enter descriptive name (include: destination, duration, tier, date).',
                            'Status: Leave as "Open" (available for new bookings).',
                            'Package Location: Select destination country.',
                            'Departure Date: Select date from calendar (must be future date).',
                            'Return Date: Select return date (must be after departure).',
                            'Total Seats: Enter total capacity.',
                        ],
                    ],
                    [
                        'name' => 'Pricing Section',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Scroll to "Pricing Section".',
                            'For each pricing plan (Standard, Premium, etc.): fill Plan Name, Price, and Capacity.',
                            'Leave unused plans empty.',
                            'Save each plan.',
                        ],
                    ],
                    [
                        'name' => 'Flight Details Section',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Scroll to "Flight Details Section".',
                            'Enter the flight route description/header (e.g., "Singapore to Jeddah via Doha").',
                            'From: Select the departure airport from the dropdown (e.g., Singapore, Kuala Lumpur).',
                            'To: Select the arrival/destination airport from the dropdown (e.g., Jeddah for Umrah, Amman for Jordan).',
                            'Airline: Enter the airline name and flight number.',
                            'PNR: Enter the 6-digit airline booking reference code (confirm with the booking agent).',
                            'Departure Date/Time: Click the calendar picker, select the departure date and time.',
                            'Arrival Date/Time: Click the calendar picker, select the arrival date and time.',
                            'Click Save.',
                        ],
                    ],
                    [
                        'name' => 'Transportation Plan Section',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Scroll to "Transportation Plan Section".',
                            'Click "Add Transportation".',
                            'Enter transportation details: Route, Vehicle Type, Transportation Provider, and Date/Time.',
                            'Click Add again for each transportation leg.',
                        ],
                    ],
                    [
                        'name' => 'Visa Section',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Scroll to "Visa Section".',
                            'Select visa type from dropdown (Umrah, Hajj, or Tourist Visa).',
                            'Enter visa details: Required documents, Processing time, and Expiry validity.',
                            'Save.',
                        ],
                    ],
                    [
                        'name' => 'Vehicle Section',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Scroll to "Vehicle Section".',
                            'Enter Driver 1 details: Name, License Number, Contact Phone, Vehicle Make/Model, and License Plate.',
                            'Enter Driver 2 details if applicable.',
                            'Save.',
                        ],
                    ],
                    [
                        'name' => 'Train Ticket Details Section',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Scroll to "Train Ticket Details Section".',
                            'Click "Add Train".',
                            'Enter: Train Description, Ticket Type, and additional fields (departure time, class, seats).',
                            'Save.',
                        ],
                    ],
                    [
                        'name' => 'Accommodations Section',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Scroll to "Accommodations Section".',
                            'Click "Add Accommodation".',
                            'Enter details: Location, Hotel Name, Hotel Category, Room Type, Check-In Date, Check-Out Date, and Number of Rooms.',
                            'Click Add again to add additional hotels.',
                            'Save.',
                        ],
                    ],
                    [
                        'name' => 'Rawdah Tasreeh Section',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Click Add Rawdah Tasreeh.',
                            'Enter the visit date.',
                            'Select the gender category (Men or Women).',
                            'Enter the allocated visit time.',
                            'Enter the total number of visitors for the selected slot.',
                        ],
                    ],
                    [
                        'name' => 'Officials Section',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Select the official type from the dropdown menu.',
                            'Enter the official’s name (required).',
                            'Enter the hotel location.',
                        ],
                    ],
                    [
                        'name' => 'Package Inclusions',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Navigate to the Inclusions section of the package details.',
                            'Select the checkbox for each service included in the package (e.g., Flight, Hotel, Transport, Visa, Insurance).',
                            'Add custom text or notes for inclusions if required.',
                            'Click Save.',
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
                                'text' => 'Navigate to the Manifest module and select a package.',
                                'path' => '/manifests',
                            ],
                            'Click Update Manifest.',
                        ],
                    ],
                    [
                        'name' => 'Gender',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Locate the Gender column on the Main Dashboard.',
                            'Verify the gender (Male/Female) for each traveler.',
                            'If a gender is incorrect, edit it directly (critical for Rawdah Tasreeh registration).',
                        ],
                    ],
                    [
                        'name' => 'Discount',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'View the Discount column on the Main Dashboard.',
                            'Hover over the discount field to see any discount applied to the customer.',
                            'Verify that the correct pricing adjustments have been applied.',
                        ],
                    ],
                    [
                        'name' => 'Payment Date',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Check the Payment Date column on the Main Dashboard.',
                            'Ensure the date recorded matches when the payment was successfully processed.',
                            'Use this for daily reconciliation and audit trails.',
                        ],
                    ],
                    [
                        'name' => 'Receipt',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Navigate to the Receipt Tab.',
                            'Upload the related payment receipt.',
                        ],
                    ],
                    [
                        'name' => 'Payment Status',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Verify the Payment Status column (e.g., Unpaid, Partially Paid, Fully Paid, Overpaid).',
                            'The payment status is automatically calculated based on issued invoices and receipts.',
                        ],
                    ],
                    [
                        'name' => 'Officials Assignment',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Right-click on the official’s name.',
                            'Assign the official to the intended hotel location.',
                            'Unassign the official when necessary.',
                        ],
                    ],
                    [
                        'name' => 'Airline Name List',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Navigate to the Airline Name List tab.',
                            'Review the list of travelers grouped by flight allocations.',
                            'Verify passenger details match passport data for flight manifest submission.',
                        ],
                    ],
                    [
                        'name' => 'Room List – (Location 01)',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Navigate to Room List – (Location 01).',
                            'Drag & Drop: Click and hold the 6-dot icon located on the far left of the table row, then drag the customer into the intended room group.',
                            'Business Rule: Customers must belong to the same pricing plan in order to be grouped together. Otherwise, the system will prompt an over-capacity or mismatch error.',
                            'To reset the room structure, click "Reset This Room List Structure".',
                            'Select the target replication source if applicable (e.g., replicate from an existing structure).',
                            'Click Update Manifest to save changes.',
                        ],
                    ],
                    [
                        'name' => 'Room List – (Location 02)',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Navigate to Room List – (Location 02).',
                            'Drag & Drop: Click and hold the 6-dot icon located on the far left of the table row, then drag the customer into the intended room group.',
                            'Business Rule: Customers must belong to the same pricing plan in order to be grouped together. Otherwise, the system will prompt an over-capacity or mismatch error.',
                            'To replicate room arrangement from Location 01, click "Reset This Room List Structure".',
                            'Select "Location 01" as the source to duplicate the room structure.',
                            'Click Update Manifest to save changes.',
                        ],
                    ],
                    [
                        'name' => 'Room List for Official Check-In – (Location 01)',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Navigate to Room List for Official Check-In – (Location 01).',
                            'Verify the room assignments specifically prepared for hotel check-in at Location 01.',
                            'Print or export the official check-in document for the hotel administration.',
                        ],
                    ],
                    [
                        'name' => 'Room List for Official Check-In – (Location 02)',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Navigate to Room List for Official Check-In – (Location 02).',
                            'Verify the room assignments prepared for hotel check-in at Location 02.',
                            'Export the official check-in sheet to coordinate with ground officials.',
                        ],
                    ],
                    [
                        'name' => 'Name List Course & Collection Item',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Navigate to Name List Course & Collection Item.',
                            'Mark attendance by checking the Course Attended checkbox for each traveler.',
                            'Mark gift/kit distribution by checking the Item Collected checkbox.',
                            'Click Update Manifest to save.',
                        ],
                    ],
                    [
                        'name' => 'Flight Tickets',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Navigate to the Flight Tickets section under Upload Documents.',
                            'Click Upload and select the airline ticket file (PDF or image).',
                            'Upload representative samples of flight ticket confirmations.',
                            'Click Save.',
                        ],
                    ],
                    [
                        'name' => 'Visa',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Navigate to the Visa section under Upload Documents.',
                            'Click Upload and select the visa approvals or stamps.',
                            'Ensure files are clear and readable for immigration validation.',
                            'Click Save.',
                        ],
                    ],
                    [
                        'name' => 'Train Tickets',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Navigate to the Train Tickets section under Upload Documents.',
                            'Click Upload and select the train booking confirmation file.',
                            'Verify train descriptions (e.g., Jeddah to Madinah Express).',
                            'Click Save.',
                        ],
                    ],
                    [
                        'name' => 'Hotel',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Navigate to the Hotel section under Upload Documents.',
                            'Click Upload and select hotel booking confirmations or room charts.',
                            'Ensure hotel category and check-in/out dates match the package.',
                            'Click Save.',
                        ],
                    ],
                    [
                        'name' => 'Passport',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Navigate to the Passport section under Upload Documents.',
                            'If a customer has not uploaded their passport via the one-time link, upload it manually.',
                            'Select the scanned passport file and click Save.',
                        ],
                    ],
                    [
                        'name' => 'Photo',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Navigate to the Photo section under Upload Documents.',
                            'Verify that a passport-style photo is uploaded (JPG/PNG).',
                            'If missing, upload it manually on behalf of the customer and click Save.',
                        ],
                    ],
                    [
                        'name' => 'Arabic Names',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Navigate to the Arabic Names section.',
                            'Verify or enter the customer\'s name in Arabic characters (required for Saudi visa processing).',
                            'Save the updated name details.',
                        ],
                    ],
                    [
                        'name' => 'Receipt Documents',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Navigate to the Receipt section under Upload Documents.',
                            'Click Upload Receipt to attach bank transfer confirmations or receipts.',
                            'Validation: A maximum of 3 receipt slots is available per room group for uploading bank transfer confirmations or receipts.',
                            'Click Save to maintain audit traceability.',
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
                                'text' => 'Navigate to Ops Movement and select the desired package.',
                                'path' => '/ops-movements',
                            ],
                            'Review the auto-populated flight, accommodation, transportation, visa, and guide details.',
                            'Verify all logistics are synchronized with the package and manifest.',
                        ],
                    ],
                    [
                        'name' => 'PIF',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Navigate to Ops Movement > PIF section.',
                            'Click Generate PIF to compile passenger names, passport numbers, dates of birth, and nationalities.',
                            'Click Export as PDF to download the passenger manifest for check-in and immigration.',
                        ],
                    ],
                    [
                        'name' => 'Itinerary',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Navigate to Ops Movement > Itinerary section.',
                            'Click Upload Itinerary and select the day-by-day schedule file (PDF or document).',
                            'Add a clear description (e.g., "Itinerary - Umrah May 2025") and save.',
                        ],
                    ],
                    [
                        'name' => 'Booklet',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Navigate to Ops Movement > Booklet section.',
                            'Click Upload Booklet and select the official travel guide document (PDF recommended).',
                            'Add a description (e.g., "Umrah Package Booklet - May 2025") and save.',
                        ],
                    ],
                    [
                        'name' => 'Budget',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Navigate to Ops Movement > Budget section.',
                            'Note: Hanya dapat diakses dan diedit oleh pengguna dengan peran SuperAdmin (SuperAdmin only).',
                            'Review the auto-calculated revenue derived from confirmed customer invoices.',
                            'Enter fixed and variable costs (flights, hotels, transport, guides, visa fees).',
                            'Save to calculate Total Cost, Profit, and Profit Margin %.',
                        ],
                    ],
                    [
                        'name' => 'User Access & Permissions',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'SuperAdmin memiliki otoritas penuh untuk mengedit seluruh section, termasuk detail master operasional dan budget (Full authority to edit all sections, including operational master details and budget).',
                            'Peran Operations diizinkan melihat informasi, mengunggah itinerary/booklet, dan mengekspor laporan, tetapi TIDAK memiliki izin untuk mengedit data master operasional atau melihat budget (Allowed to view info, upload itinerary/booklet, and export reports, but no permission to edit operational master data or view budget).',
                            'Peran Sales hanya memiliki akses view-only (informational) pada modul Ops Movement ini (View-only informational access on the Ops Movement module).',
                        ],
                    ],
                ],
            ],
        ];
    }
}
