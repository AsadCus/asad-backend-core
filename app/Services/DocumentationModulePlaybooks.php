<?php

namespace App\Services;

/**
 * Module Playbooks — All 18 modules from PM Manual Register.
 *
 * This file contains the static documentation playbook data for all modules.
 * Separated from DocumentationService for maintainability.
 *
 * @see \App\Services\DocumentationService::getIndexData()
 */
class DocumentationModulePlaybooks
{
    /**
     * Get all 18 module playbooks.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return array_merge(
            static::modules1to9(),
            static::modules10to18(),
        );
    }

    /**
     * Modules 1.0–9.0: Dashboard, Master, Sales, Customer, Enquiry,
     * Confirmed Customer, Quotation, Invoice, Receipt.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function modules1to9(): array
    {
        return [
        // ─── 1.0 Dashboard Module ───
        [
            'id' => 'dashboard-module',
            'title' => 'Dashboard Module',
            'overview' => 'The Dashboard provides a real-time overview of your business performance. Use it to monitor total sales by fiscal year, track ongoing travel packages, and review recent customer activity at a glance.',
            'highlights' => [
                'Fiscal year sales summary',
                'Ongoing package tracking',
                'Recent customer activity feed',
            ],
            'procedures' => [
                [
                    'name' => 'Total Sales for Fiscal Year',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        ['text' => 'Open Dashboard from the sidebar.', 'path' => '/dashboard'],
                        'Select the desired fiscal year from the filter control at the top of the page.',
                        'Review the total sales figure displayed in the summary widget.',
                        'Observe the sales trend chart for monthly breakdown.',
                        'Use the export button to download the payment summary report if needed.',
                    ],
                ],
                [
                    'name' => 'Ongoing Package',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        ['text' => 'Open Dashboard from the sidebar.', 'path' => '/dashboard'],
                        'Locate the Ongoing Package widget on the dashboard.',
                        'Review the list of active travel packages with their departure dates.',
                        'Check the seat occupancy status for each listed package.',
                    ],
                ],
                [
                    'name' => 'Recent Customers',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        ['text' => 'Open Dashboard from the sidebar.', 'path' => '/dashboard'],
                        'Locate the Recent Customers widget on the dashboard.',
                        'Review the latest customer entries with their registration dates.',
                        'Click on a customer name to navigate to their detail page.',
                    ],
                ],
            ],
        ],

        // ─── 2.0 Master Module ───
        [
            'id' => 'master-module',
            'title' => 'Master Module',
            'overview' => 'The Master module is the central configuration area for the system. Superadmin users manage user accounts, country references, fiscal year periods, and product and service catalogs here. All settings configured in this module are referenced across the entire system.',
            'highlights' => [
                'User role management',
                'Country and branch configuration',
                'Fiscal year period control',
                'Product and services catalog',
                'Payment method and extension setup',
            ],
            'procedures' => [
                [
                    'name' => 'Add New User',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        ['text' => 'Open Master from the sidebar.', 'path' => '/master'],
                        'Click on the User Management section.',
                        'Select the user role category (Superadmin, Admin, Sales, Operations, or Customer).',
                        'Click the Create button.',
                        'Fill in the user\'s full name.',
                        'Enter a unique email address.',
                        'Set the password for the new user.',
                        'Select the appropriate role from the dropdown.',
                        'Configure the location scope if applicable.',
                        'Click Save to create the user.',
                        'Verify the new user appears in the user list.',
                    ],
                ],
                [
                    'name' => 'Add New Country',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        ['text' => 'Open Master > Country.', 'path' => '/master/country'],
                        'Click the Create button.',
                        'Enter the country name.',
                        'Fill in the country code.',
                        'Click Save to confirm.',
                        'Verify the new country appears in the country list.',
                    ],
                ],
                [
                    'name' => 'Add New Fiscal Year',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        ['text' => 'Open Master > Fiscal Year.', 'path' => '/master/financial-year'],
                        'Click the Create button.',
                        'Enter the fiscal year label.',
                        'Set the start date for the fiscal period.',
                        'Set the end date for the fiscal period.',
                        'Click Save to create the fiscal year record.',
                        'Click Set Default on the new fiscal year row if it should be the active period.',
                    ],
                ],
                [
                    'name' => 'Add New Product and Services',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        ['text' => 'Open Master > Products and Services.', 'path' => '/master'],
                        'Click the Create button.',
                        'Enter the product or service name.',
                        'Fill in the item header and sub-header details.',
                        'Set the default pricing if applicable.',
                        'Click Save to add the item to the catalog.',
                        'Verify the item appears in the product and services list.',
                    ],
                ],
                [
                    'name' => 'Add New Payment Method',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        ['text' => 'Open Master from the sidebar.', 'path' => '/master'],
                        'Navigate to the Payment Method section.',
                        'Click the Create button.',
                        'Enter the payment method name (e.g., Bank Transfer, Cash, Credit Card).',
                        'Fill in any additional configuration details.',
                        'Click Save to confirm.',
                        'Verify the new payment method appears in the list.',
                    ],
                ],
                [
                    'name' => 'Add New Payment Extension',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        ['text' => 'Open Master from the sidebar.', 'path' => '/master'],
                        'Navigate to the Payment Extension section.',
                        'Click the Create button.',
                        'Enter the payment extension name.',
                        'Configure the extension parameters.',
                        'Click Save to confirm.',
                        'Verify the new payment extension appears in the list.',
                    ],
                ],
            ],
        ],

        // ─── 3.0 Sales Module ───
        [
            'id' => 'sales-module',
            'title' => 'Sales Module',
            'overview' => 'The Sales module provides reporting tools for tracking daily payments and generating closing reports. Use this module to monitor collection progress and produce summary reports for management review.',
            'highlights' => [
                'Daily payment report generation',
                'Closing report by fiscal period',
                'Date range and category filtering',
                'PDF export for audit and sharing',
            ],
            'procedures' => [
                [
                    'name' => 'Daily Payment Report',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        ['text' => 'Open Reports > Payment from the sidebar.', 'path' => '/reports/payment'],
                        'Select the fiscal year from the filter control.',
                        'Set the date range for the report period.',
                        'Select the package filter if needed.',
                        'Select one or more category filters.',
                        'Review the payment records displayed in the list view.',
                        'Click Export to download the report as a PDF.',
                    ],
                ],
                [
                    'name' => 'Closing Reports',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        ['text' => 'Open Reports > Closing from the sidebar.', 'path' => '/reports/closing'],
                        'Select the fiscal year from the filter control.',
                        'Set the date range for the closing period.',
                        'Select one or more category filters.',
                        'Review the closing summary data in the list view.',
                        'Click Export to download the closing report as a PDF.',
                    ],
                ],
            ],
        ],

        // ─── 4.0 Customer Module ───
        [
            'id' => 'customer-module',
            'title' => 'Customer Module',
            'overview' => 'The Customer module manages all customer profile records. Customers can be created manually or auto-generated when an enquiry is converted to a confirmed customer. Use this module to search, view, and maintain customer data.',
            'highlights' => [
                'Customer profile management',
                'Manual and auto-created records',
                'Import and export capabilities',
                'Enable and disable customer status',
            ],
            'procedures' => [
                [
                    'name' => 'Add New Customer',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        ['text' => 'Open Customer from the sidebar.', 'path' => '/customer'],
                        'Search existing records to avoid creating duplicates.',
                        'Click the Create button.',
                        'Fill in the customer\'s full name.',
                        'Enter the customer\'s contact number.',
                        'Enter the customer\'s email address.',
                        'Fill in the customer\'s identification details.',
                        'Enter the customer\'s address information.',
                        'Click Save to create the customer record.',
                        'Verify the new customer appears in the customer list.',
                    ],
                ],
            ],
        ],

        // ─── 5.0 Enquiry Module ───
        [
            'id' => 'enquiry-module',
            'title' => 'Enquiry Module',
            'overview' => 'The Enquiry module is the primary intake point for new customer interest. Sales users manage two types of enquiries: General (group/open packages) and Private (individual/custom packages). Enquiries follow a status flow from New Lead to Contacted to Confirmed Customer.',
            'highlights' => [
                'General and Private enquiry intake',
                'Lead status progression tracking',
                'Remark and follow-up logging',
                'Direct conversion to confirmed customer',
            ],
            'procedures' => [
                [
                    'name' => 'Change New Lead to Contacted',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        ['text' => 'Open Enquiry from the sidebar.', 'path' => '/enquiries'],
                        'Locate the enquiry with "New Lead" status in the list view.',
                        'Click on the enquiry row to open the detail view.',
                        'Click the status action button.',
                        'Select "Contacted" from the status options.',
                        'Confirm the status change.',
                        'Verify the status badge updates to "Contacted".',
                    ],
                ],
                [
                    'name' => 'Add Remarks on Enquiry',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        ['text' => 'Open Enquiry from the sidebar.', 'path' => '/enquiries'],
                        'Click on the target enquiry row to open the detail view.',
                        'Locate the Remarks section.',
                        'Click the Add Remark button.',
                        'Type the follow-up summary or call outcome in the text field.',
                        'Click Save to record the remark.',
                        'Verify the remark appears in the remarks timeline.',
                    ],
                ],
                [
                    'name' => 'Change Contacted to a Confirmed Customer',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        ['text' => 'Open Enquiry from the sidebar.', 'path' => '/enquiries'],
                        'Locate the enquiry with "Contacted" status in the list view.',
                        'Click on the enquiry row to open the detail view.',
                        'Review the enquiry qualification data and customer information.',
                        'Click the Confirm action button.',
                        'Select the travel package to assign the customer to.',
                        'Fill in any additional confirmation details.',
                        'Click Confirm to create the confirmed customer group.',
                        'Verify the enquiry status changes to "Confirmed".',
                    ],
                ],
                [
                    'name' => 'Create New General Enquiry (Walk-In)',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        ['text' => 'Open Enquiry > General Enquiry from the sidebar.', 'path' => '/enquiries'],
                        'Click the Create button.',
                        'Fill in the customer\'s full name.',
                        'Enter the customer\'s contact number.',
                        'Enter the customer\'s email address.',
                        'Select the package of interest from the dropdown.',
                        'Fill in the number of travellers.',
                        'Add any notes or special requirements.',
                        'Click Save to create the general enquiry.',
                        'Verify the new enquiry appears in the list with "New Lead" status.',
                    ],
                ],
                [
                    'name' => 'Create New Private Enquiry (Walk-In)',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        ['text' => 'Open Enquiry > Private Enquiry from the sidebar.', 'path' => '/enquiries'],
                        'Click the Create button.',
                        'Fill in the customer\'s full name.',
                        'Enter the customer\'s contact number.',
                        'Enter the customer\'s email address.',
                        'Specify the preferred travel dates.',
                        'Fill in the number of travellers.',
                        'Describe the custom package requirements.',
                        'Add any notes or special requests.',
                        'Click Save to create the private enquiry.',
                        'Verify the new enquiry appears in the list with "New Lead" status.',
                    ],
                ],
            ],
        ],

        // ─── 6.0 Confirmed Customer Module ───
        [
            'id' => 'confirmed-customer-module',
            'title' => 'Confirmed Customer Module',
            'overview' => 'The Confirmed Customer module manages customer groups that have been confirmed for a travel package. Each confirmed customer group contains one or more participants (members) who will be travelling together. Use this module to add participants, track payment progress, and manage the group lifecycle.',
            'highlights' => [
                'Confirmed customer group management',
                'Participant and member registration',
                'Package assignment tracking',
                'Payment progress monitoring',
            ],
            'procedures' => [
                [
                    'name' => 'Add New Participants within Customer',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        ['text' => 'Open Confirmed Customer from the sidebar.', 'path' => '/confirmed-customer'],
                        'Locate the confirmed customer group in the list view.',
                        'Click on the customer group to open the detail view.',
                        'Click the Add Participant button.',
                        'Fill in the participant\'s full name.',
                        'Enter the participant\'s identification number.',
                        'Fill in the participant\'s contact details.',
                        'Enter the participant\'s passport information.',
                        'Click Save to add the participant to the group.',
                        'Verify the new participant appears in the group member list.',
                    ],
                ],
            ],
        ],

        // ─── 7.0 Quotation Module ───
        [
            'id' => 'quotation-module',
            'title' => 'Quotation Module',
            'overview' => 'The Quotation module handles the creation and management of price quotations for confirmed customers. Quotations can be created from a confirmed customer record, split for separate billing, or converted into invoices once accepted. Only quotations with an "Accepted" status can proceed to invoice generation.',
            'highlights' => [
                'Quotation creation from confirmed customer',
                'Quotation splitting for separate billing',
                'Status management (Draft, Accepted, Rejected)',
                'Conversion to invoice workflow',
            ],
            'procedures' => [
                [
                    'name' => 'Convert Confirmed Customer Quotation',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        ['text' => 'Open Confirmed Customer from the sidebar.', 'path' => '/confirmed-customer'],
                        'Locate the confirmed customer group in the list view.',
                        'Click on the customer group to open the detail view.',
                        'Click the Create Quotation action button.',
                        'Review the pre-filled customer and package details.',
                        'Verify the line items, pricing, and taxes.',
                        'Click Save to generate the quotation.',
                        'Verify the quotation appears in the Sales > Quotation list.',
                    ],
                ],
                [
                    'name' => 'Split Quotation',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        ['text' => 'Open Sales > Quotation from the sidebar.', 'path' => '/sales'],
                        'Locate the quotation to split in the list view.',
                        'Click on the quotation to open the detail view.',
                        'Click the Split action button.',
                        'Select the members to separate into a new quotation.',
                        'Review the split pricing allocation for each group.',
                        'Click Confirm to execute the split.',
                        'Verify both quotations appear in the list with correct amounts.',
                    ],
                ],
                [
                    'name' => 'Create New Quotation',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        ['text' => 'Open Sales > Quotation from the sidebar.', 'path' => '/sales'],
                        'Click the Create button.',
                        'Select the customer from the customer dropdown.',
                        'Select the travel package.',
                        'Add line items for products and services.',
                        'Set the pricing, discounts, and tax configuration.',
                        'Review the quotation total.',
                        'Click Save to create the quotation.',
                        'Verify the new quotation appears in the list with "Draft" status.',
                    ],
                ],
                [
                    'name' => 'Convert Quotation to Invoice',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        ['text' => 'Open Sales > Quotation from the sidebar.', 'path' => '/sales'],
                        'Locate the quotation with "Accepted" status in the list view.',
                        'Click on the quotation to open the detail view.',
                        'Click the Convert to Invoice action button.',
                        'Review the invoice details pre-filled from the quotation.',
                        'Confirm the conversion.',
                        'Verify the invoice appears in the Sales > Invoice list.',
                    ],
                ],
            ],
        ],

        // ─── 8.0 Invoice Module ───
        [
            'id' => 'invoice-module',
            'title' => 'Invoice Module',
            'overview' => 'The Invoice module manages billing documents generated from accepted quotations. Invoices define the payment obligation and can be configured with installment plans to allow customers to pay in scheduled amounts over time.',
            'highlights' => [
                'Invoice generation from accepted quotation',
                'Installment plan configuration',
                'Payment schedule tracking',
                'Invoice preview and PDF export',
            ],
            'procedures' => [
                [
                    'name' => 'Create New Invoice',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        ['text' => 'Open Sales > Invoice from the sidebar.', 'path' => '/sales'],
                        'Click the Create button.',
                        'Select the accepted quotation to convert.',
                        'Review the pre-filled invoice details and line items.',
                        'Verify the total amount and tax calculations.',
                        'Set the payment due date.',
                        'Click Save to create the invoice.',
                        'Verify the new invoice appears in the invoice list.',
                    ],
                ],
                [
                    'name' => 'Change Installment Plan',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        ['text' => 'Open Sales > Invoice from the sidebar.', 'path' => '/sales'],
                        'Locate the invoice in the list view.',
                        'Click on the invoice to open the detail view.',
                        'Click the Installment Plan action button.',
                        'Set the number of installments.',
                        'Configure the amount and due date for each installment.',
                        'Click Save to update the installment plan.',
                        'Verify the installment schedule displays correctly on the invoice.',
                    ],
                ],
            ],
        ],

        // ─── 9.0 Receipt Module ───
        [
            'id' => 'receipt-module',
            'title' => 'Receipt Module',
            'overview' => 'The Receipt module records payment collections against invoices. A receipt is issued each time a payment is received from a customer. If a receipt was issued with an error, the Recreate function allows generating a corrected version.',
            'highlights' => [
                'Payment collection recording',
                'Receipt generation and printing',
                'Receipt recreation for error correction',
                'Payment method tracking',
            ],
            'procedures' => [
                [
                    'name' => 'Create and Recreate Receipt',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        ['text' => 'Open Sales > Receipt from the sidebar.', 'path' => '/sales'],
                        'Click the Create button.',
                        'Select the invoice to record payment against.',
                        'Enter the payment amount received.',
                        'Select the payment method from the dropdown.',
                        'Fill in the payment reference number if applicable.',
                        'Click Save to generate the receipt.',
                        'Click Preview to review the receipt before printing.',
                        'Click Print to produce the receipt document.',
                        'To recreate a receipt, locate the original receipt in the list.',
                        'Click the Recreate action button on the receipt row.',
                        'Review and correct the receipt details.',
                        'Click Save to generate the corrected receipt.',
                    ],
                ],
            ],
        ],
        ];
    }

    /**
     * Modules 10.0–18.0: Change Package, Refund, Customer Holding Area,
     * Completed Customer, Cancelled Customer, Ops Movement, PIF, Itinerary, Budget.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function modules10to18(): array
    {
        return [
        // ─── 10.0 Change Package Module ───
        [
            'id' => 'change-package-module',
            'title' => 'Change Package Module',
            'overview' => 'The Change Package module allows confirmed customers to be moved to a different travel package. When a package change is processed, the previous package slot is released and the customer is reassigned to the new package with updated pricing and schedule details.',
            'highlights' => [
                'Package reassignment for confirmed customers',
                'Automatic slot release from previous package',
                'Pricing recalculation on transfer',
            ],
            'procedures' => [
                [
                    'name' => 'Change Package for Confirmed Customer',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        ['text' => 'Open Confirmed Customer from the sidebar.', 'path' => '/confirmed-customer'],
                        'Locate the confirmed customer group in the list view.',
                        'Click on the customer group to open the detail view.',
                        'Click the Change Package action button.',
                        'Select the new travel package from the dropdown.',
                        'Review the updated pricing and schedule details.',
                        'Confirm the package change.',
                        'Verify the customer is now assigned to the new package.',
                        'Verify the previous package slot has been released.',
                    ],
                ],
            ],
        ],

        // ─── 11.0 Refund Module ───
        [
            'id' => 'refund-module',
            'title' => 'Refund Module',
            'overview' => 'The Refund module handles two refund scenarios: overpayment refunds when a customer has paid more than the invoice total, and cancellation refunds when a customer cancels their trip. Refund amounts for cancellations are calculated based on the applicable cancellation policy.',
            'highlights' => [
                'Overpayment refund processing',
                'Trip cancellation refund handling',
                'Cancellation policy-based calculation',
                'Refund status tracking',
            ],
            'procedures' => [
                [
                    'name' => 'Overpaid Pricing',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        'Open the customer\'s invoice from the Sales module.',
                        'Identify the overpaid amount by comparing total paid against invoice total.',
                        'Click the Refund action button.',
                        'Select "Overpaid" as the refund type.',
                        'Verify the overpaid amount displayed.',
                        'Enter the refund payment method details.',
                        'Click Confirm to process the overpayment refund.',
                        'Verify the refund record appears with the correct amount.',
                    ],
                ],
                [
                    'name' => 'Refund for Trip Cancellation',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        ['text' => 'Open Confirmed Customer from the sidebar.', 'path' => '/confirmed-customer'],
                        'Locate the customer group requesting cancellation.',
                        'Click on the customer group to open the detail view.',
                        'Click the Cancel action button.',
                        'Select the cancellation reason from the dropdown.',
                        'Review the refund amount calculated based on the cancellation policy.',
                        'Adjust the refund amount if a partial refund is applicable.',
                        'Enter the refund payment method details.',
                        'Click Confirm to process the cancellation refund.',
                        'Verify the customer is moved to Cancelled Customer.',
                        'Verify the refund record is created with the correct status.',
                    ],
                ],
            ],
        ],

        // ─── 12.0 Customer Holding Area Module ───
        [
            'id' => 'customer-holding-area-module',
            'title' => 'Customer Holding Area Module',
            'overview' => 'The Customer Holding Area temporarily holds customers with unresolved statuses, such as pending package decisions or incomplete documentation. Customers in the holding area can be reassigned to a new package when their situation is resolved.',
            'highlights' => [
                'Temporary customer holding management',
                'Package reassignment capability',
                'Unresolved status tracking',
            ],
            'procedures' => [
                [
                    'name' => 'Assign to New Package',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        'Open Customer Holding Area from the sidebar.',
                        'Locate the customer in the holding area list view.',
                        'Click on the customer to open the detail view.',
                        'Review the customer\'s current status and holding reason.',
                        'Click the Assign to Package action button.',
                        'Select the new travel package from the dropdown.',
                        'Review the package details and pricing.',
                        'Click Confirm to assign the customer to the new package.',
                        'Verify the customer is moved out of the holding area.',
                        'Verify the customer appears in the Confirmed Customer list under the new package.',
                    ],
                ],
            ],
        ],

        // ─── 13.0 Completed Customer Module ───
        [
            'id' => 'completed-customer-module',
            'title' => 'Completed Customer Module',
            'overview' => 'The Completed Customer module provides a read-only view of customer records whose trips have been completed. No edits are allowed in this module. Use it to reference historical trip data, payment records, and customer details for completed journeys.',
            'highlights' => [
                'Read-only completed trip records',
                'Historical payment and trip data',
                'Customer journey archive',
            ],
            'procedures' => [
                [
                    'name' => 'View Completed Customer Records',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        'Open Completed Customer from the sidebar.',
                        'Browse the list of completed customer groups.',
                        'Use the search or filter controls to locate a specific customer.',
                        'Click on a customer group to view the detail page.',
                        'Review the trip details, package information, and travel dates.',
                        'Review the payment history and receipt records.',
                        'Review the participant list and their travel documentation status.',
                    ],
                ],
            ],
        ],

        // ─── 14.0 Cancelled Customer Module ───
        [
            'id' => 'cancelled-customer-module',
            'title' => 'Cancelled Customer Module',
            'overview' => 'The Cancelled Customer module provides a read-only historical view of customer records that were cancelled. Use this module to review cancellation reasons, check refund statuses, and reference historical data for reporting purposes.',
            'highlights' => [
                'Read-only cancellation records',
                'Cancellation reason documentation',
                'Refund status visibility',
            ],
            'procedures' => [
                [
                    'name' => 'View Cancelled Customer Records',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        'Open Cancelled Customer from the sidebar.',
                        'Browse the list of cancelled customer groups.',
                        'Use the search or filter controls to locate a specific customer.',
                        'Click on a customer group to view the detail page.',
                        'Review the cancellation reason displayed on the record.',
                        'Check the refund status and refund amount details.',
                        'Review the original trip and payment history for reference.',
                    ],
                ],
            ],
        ],

        // ─── 15.0 Ops Movement Module ───
        [
            'id' => 'ops-movement-module',
            'title' => 'Ops Movement Module',
            'overview' => 'The Ops Movement module is used by the Operations team to record ground movement details for each travel package. Movement records are linked to manifest data and track the operational logistics required for trip execution.',
            'highlights' => [
                'Ground movement recording per package',
                'Manifest data linkage',
                'Operations logistics tracking',
            ],
            'procedures' => [
                [
                    'name' => 'Record Ops Movement Details',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        ['text' => 'Open Ops Movement from the sidebar.', 'path' => '/ops-movements'],
                        'Locate the package in the ops movement list view.',
                        'Click on the package to open the movement detail page.',
                        'Fill in the ground transportation details.',
                        'Enter the movement schedule and timing information.',
                        'Record the vehicle and driver assignment details.',
                        'Fill in any special logistics notes.',
                        'Click Save to record the movement details.',
                        'Verify the movement record is linked to the correct manifest.',
                    ],
                ],
            ],
        ],

        // ─── 16.0 PIF Module ───
        [
            'id' => 'pif-module',
            'title' => 'PIF Module',
            'overview' => 'The PIF (Passenger Information Form) module generates operational exports used by the field team before departure. The PIF export contains essential passenger details required for ground operations and immigration processing.',
            'highlights' => [
                'Passenger Information Form generation',
                'Pre-departure operational export',
                'PDF export for field team distribution',
            ],
            'procedures' => [
                [
                    'name' => 'Generate PIF Export',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        ['text' => 'Open Ops Movement from the sidebar.', 'path' => '/ops-movements'],
                        'Locate the package in the ops movement list view.',
                        'Click on the package to open the detail page.',
                        'Navigate to the PIF section.',
                        'Review the passenger information data for completeness.',
                        'Click the Export PIF button.',
                        'Select the export format (PDF).',
                        'Save or print the generated PIF document.',
                        'Distribute the PIF to the field team before departure.',
                    ],
                ],
            ],
        ],

        // ─── 17.0 Itinerary Module ───
        [
            'id' => 'itinerary-module',
            'title' => 'Itinerary Module',
            'overview' => 'The Itinerary module generates day-by-day travel schedule exports for each package. The itinerary is used by the Operations team for trip planning and shared with customers as a reference for their journey.',
            'highlights' => [
                'Day-by-day schedule generation',
                'Per-package itinerary export',
                'Customer-shareable travel plan',
            ],
            'procedures' => [
                [
                    'name' => 'Generate Itinerary Export',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        ['text' => 'Open Ops Movement from the sidebar.', 'path' => '/ops-movements'],
                        'Locate the package in the ops movement list view.',
                        'Click on the package to open the detail page.',
                        'Navigate to the Itinerary section.',
                        'Review the day-by-day schedule for accuracy.',
                        'Verify accommodation, transport, and activity details for each day.',
                        'Click the Export Itinerary button.',
                        'Select the export format (PDF).',
                        'Save or print the generated itinerary document.',
                        'Share the itinerary with customers and the operations field team.',
                    ],
                ],
            ],
        ],

        // ─── 18.0 Budget Module ───
        [
            'id' => 'budget-module',
            'title' => 'Budget Module',
            'overview' => 'The Budget module tracks estimated versus actual costs for each travel package. Operations teams use this module for financial reconciliation, comparing planned expenses against real spending to maintain budget control.',
            'highlights' => [
                'Estimated vs actual cost tracking',
                'Per-package budget breakdown',
                'Financial reconciliation reporting',
                'Budget PDF export for audit',
            ],
            'procedures' => [
                [
                    'name' => 'Record and Review Package Budget',
                    'type' => 'article',
                    'status' => 'pending',
                    'steps' => [
                        ['text' => 'Open Ops Movement from the sidebar.', 'path' => '/ops-movements'],
                        'Locate the package in the ops movement list view.',
                        'Click on the package to open the detail page.',
                        'Navigate to the Budget section.',
                        'Enter the estimated cost for each budget category.',
                        'Fill in the actual cost as expenses are incurred.',
                        'Review the variance between estimated and actual amounts.',
                        'Click Save to update the budget record.',
                        'Click Export Budget to generate the budget report as a PDF.',
                        'Use the exported report for management review and audit handoff.',
                    ],
                ],
            ],
        ],
        ];
    }
}
