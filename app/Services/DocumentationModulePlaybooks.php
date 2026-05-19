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
                        'steps' => [
                            'Open the Dashboard and locate the Total Sales for Fiscal Year widget.',
                            'Use the fiscal year dropdown selector to switch the reporting year.',
                            'Review FYTD transaction count (FYTD #) and FYTD sales amount (FYTD $).',
                            'Use this metric for month-end and year-end performance checks.',
                        ],
                    ],
                    [
                        'name' => 'Daily Payment',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Open Dashboard and review the Daily Payment widget.',
                            'Confirm total payment received for the day.',
                            'Review grouped payment categories to identify major inflow sources.',
                            'Use this data for daily reconciliation with finance records.',
                        ],
                    ],
                    [
                        'name' => 'Upcoming Departures',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Review the Upcoming Departures section on Dashboard.',
                            'Validate departure date, package name, and available seat count.',
                            'Prioritize packages with nearest departure dates for operations follow-up.',
                            'Click View All to open complete departure listing when deeper checks are needed.',
                        ],
                    ],
                    [
                        'name' => 'Recent Customers',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Open the Recent Customers panel from Dashboard.',
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
                            'Define SuperAdmin with full control over users, countries, fiscal years, and package governance.',
                            'Define Sales for enquiry-to-sales pipeline including quotation, invoice, and receipt activities.',
                            'Define Admin similar to Sales but without Daily and Closing report access.',
                            'Define Operations for itinerary, booklet, and operational exports without budget editing rights.',
                        ],
                    ],
                    [
                        'name' => 'Add New User',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Navigate to Master and select Add New User.',
                            'Choose the role based on scope: SuperAdmin, Sales, Admin, or Operations.',
                            'Fill in identity and login details for the user account.',
                            'Assign country location for scoped roles to enforce country-specific visibility.',
                            'Click Create and verify new user appears in the user list.',
                        ],
                    ],
                    [
                        'name' => 'Add New Country',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Open Master > Country.',
                            'Click Add and enter the country name.',
                            'Save the country record.',
                            'Use this country for user assignment and country-scoped transaction visibility.',
                        ],
                    ],
                    [
                        'name' => 'Add New Fiscal Year',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Open Master > Fiscal Year and click Add.',
                            'Set start date and end date for the financial period.',
                            'Enable Set as Default when this fiscal year should drive dashboard and reports.',
                            'Save and confirm fiscal year is selectable in dashboard filters.',
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
                    'Add New GST Extension',
                    'Add New Discount',
                    'Reusable financial rules for invoicing',
                ],
                'procedures' => [
                    [
                        'name' => 'Add New Product and Services',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Open Product and Services and click Add.',
                            'Enter item name (header) and item details (sub header).',
                            'Set default quantity and cost when required for faster quotation usage.',
                            'Mark status active and save.',
                            'Use consistent naming to reduce billing mistakes.',
                        ],
                    ],
                    [
                        'name' => 'Add New Payment Method',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Open Payment Method settings under Product and Services.',
                            'Add payment method name such as Bank Transfer, Card, or Cash.',
                            'Set default method if it should be preselected on invoices.',
                            'Set status to active and save to publish the method in dropdowns.',
                        ],
                    ],
                    [
                        'name' => 'Add New Payment Extension',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Create a new extension under Product and Services.',
                            'Set extension type and naming for surcharge behavior.',
                            'Choose calculation mode: Fixed Amount or Percentage.',
                            'Input value and activate the extension.',
                            'Link extension to specific payment methods when required.',
                            'Save and validate invoice calculation behavior.',
                        ],
                    ],
                    [
                        'name' => 'Add New GST Extension',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Create a new extension from Product and Services.',
                            'Set name such as GST 7% and choose TYPE as TAX.',
                            'Set calculation mode to Percentage.',
                            'Enter tax value (for example 7).',
                            'Mark as active so tax appears in invoice calculations.',
                            'Save and confirm tax appears correctly on sample invoices.',
                        ],
                    ],
                    [
                        'name' => 'Add New Discount',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Add a new extension record from Product and Services.',
                            'Enter discount name and set TYPE to Discount.',
                            'Choose calculation mode: Percentage or Fixed Amount.',
                            'Enter discount value (for example 10 or 500).',
                            'Activate the discount so it is available during invoicing.',
                            'Save and validate discount output on quotation or invoice preview.',
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
                            'Open Enquiry Dashboard and find the target lead.',
                            'Right click the enquiry and open Enquiry Status.',
                            'Set status to Contacted after first outreach attempt.',
                            'Add structured remarks including needs, budget, preferred travel period, and follow-up date.',
                            'Use remarks to maintain clean handoff between Sales and Admin.',
                        ],
                    ],
                    [
                        'name' => 'Add a New General Enquiry (Walk-in Customer)',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Navigate to General Enquiry and click Create New General Enquiry.',
                            'Optionally choose package preference when customer already has one.',
                            'Select country correctly to enforce country-based access and reporting.',
                            'Fill mandatory customer and contact fields.',
                            'Save enquiry and assign for follow-up conversion pipeline.',
                        ],
                    ],
                    [
                        'name' => 'Add a New Private Enquiry (Walk-in Customer)',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Navigate to Private Enquiry and click Create New Private Enquiry.',
                            'Select customer country and ensure ownership scope is correct.',
                            'Capture mandatory contact data and private package intent.',
                            'Include special requirements and timing details in remarks.',
                            'Create record for custom package qualification and conversion.',
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
                            'Open Customer menu and click Add New Customer.',
                            'Fill mandatory fields including basic identity and contact details.',
                            'Save customer even if extended travel documents are not ready yet.',
                            'Update additional profile fields later through edit flow or one-time link process.',
                            'Use this record as source for quotation or confirmed customer workflows.',
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
                            'Open Sales Report > Daily Received.',
                            'Choose date or date range using date picker.',
                            'Apply filters to load payment data.',
                            'Review totals and transaction listing before export.',
                            'Export report for finance reconciliation.',
                        ],
                    ],
                    [
                        'name' => 'Generate Closing Report',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Open Sales Report > Closing Report.',
                            'Filter by package or category as needed.',
                            'Set date range and apply filter.',
                            'Review output rows for accuracy.',
                            'Export report for management summary and audit use.',
                        ],
                    ],
                    [
                        'name' => 'Create a Quotation for non-umrah Services',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Open Quotation and create a new quotation.',
                            'Select existing customer and set quotation plus validity dates.',
                            'Set description and choose payment plan (Full or Instalment).',
                            'Add service items with quantity and pricing details.',
                            'Apply discounts or extensions where applicable.',
                            'Set status to Ready for Conversion and save.',
                        ],
                    ],
                    [
                        'name' => 'Create a Quotation for Umrah Confirmed Customer',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Click on the Confirmed Customer menu.',
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
                            'Click on the Confirmed Customer menu, select customer, and click Create Quotation.',
                            'In the quotation pop-up, review the list of members.',
                            'For members who require separate quotations, click the payee dropdown beside their names and select the respective Payer name.',
                            'Click Close after updating all required payees, then click Create Quotation.',
                            'The quotations will appear as draft. Click any generated quotation to change details.',
                            'Change status to Ready, then click Update.',
                        ],
                    ],
                    [
                        'name' => 'How to Convert a Quotation to an Invoice',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Open quotation list and select a Ready quotation.',
                            'Change status to Accept Quotation through options menu.',
                            'Choose payment plan and review line items via Expand All.',
                            'Set payment method, invoice date, and due date.',
                            'Create invoice and verify generated amount including payment extension impact.',
                        ],
                    ],
                    [
                        'name' => 'How to Generate an Invoice for an Instalment Plan',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Go to the List of Quotations menu.',
                            'Select the quotation and right-click to convert the quotation to an invoice.',
                            'Change the Payment Plan to Instalment.',
                            'To change the deposit value type, select either Fixed Amount or Percentage and enter the value.',
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
                            'Click the Invoice menu.',
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
                            'Open Invoice menu and select invoice to be settled.',
                            'Verify received amount against invoice total and payment method.',
                            'Create receipt and review generated receipt data.',
                            'Finalize receipt so invoice status updates to Paid.',
                            'Use receipt output for customer and accounting records.',
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
                            'Open Confirmed Customer and edit the main customer record.',
                            'Add participant from existing customer list or create new participant profile.',
                            'Assign pricing plan and relationship under group customer section.',
                            'Complete mandatory fields and upload passport plus photo assets.',
                            'Save update and confirm participant appears in group listing.',
                        ],
                    ],
                    [
                        'name' => 'Create a One-Time Link for Customers to Update Their Details',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Use Options menu and select Copy One-Time Link.',
                            'Send secure link to customer via approved communication channel.',
                            'Ask customer to complete remaining profile and document fields before departure cutoff.',
                            'Track completion and follow up for missing mandatory fields.',
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
                            'Select the Refund Mode and Payment Method for the refund.',
                            'Enter the refund amount and add a description.',
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
                            'Move selected customer from Confirmed Customer to Holding Area.',
                            'Assign the customer to the new package from Holding Area options.',
                            'Return to Confirmed Customer and verify payment status shows Overpaid.',
                            'Create refund receipt with purpose Overpaid Refund and correct refund mode.',
                            'Confirm refund amount, description, and submit refund receipt.',
                        ],
                    ],
                    [
                        'name' => 'Change Package With Higher Pricing Plan',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Move customer to Holding Area before package change.',
                            'Select higher-tier package and move selected members back to confirmed flow.',
                            'Check updated payment status and expected balance due.',
                            'Create balance invoice for pricing difference.',
                            'Track payment and issue receipt after settlement.',
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
                            'Open the Completed Customer menu.',
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
                            'Open the Cancelled Customer menu.',
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
                    'Create New Package',
                    'Master source for Manifest and Ops Movement',
                ],
                'procedures' => [
                    [
                        'name' => 'Create New Package',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Complete Package Information with package code, location, dates, status, and seat capacity.',
                            'Fill Pricing section for all applicable plans used by customer confirmation flow.',
                            'Add Flight details including route, airline, PNR, and schedule values.',
                            'Add Transportation, Visa, Vehicle, Train, and Accommodation details for operations readiness.',
                            'Configure Rawdah Tasreeh and Officials sections where required.',
                            'Review package inclusions and save as master operational baseline.',
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
                    'Main',
                    'Room List',
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
                            'Review traveler records in Main table for the selected package.',
                            'Verify gender and payment status fields before downstream operations.',
                            'Check discount visibility and receipt availability.',
                            'Use this table as baseline validation before rooming and document checks.',
                        ],
                    ],
                    [
                        'name' => 'Room List (Location 01 and 02)',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Assign customers into room groups using drag-and-drop handle.',
                            'Ensure grouped members share compatible pricing plans.',
                            'Reset room structure when regrouping is required.',
                            'Duplicate Location 01 grouping to Location 02 when operationally applicable.',
                        ],
                    ],
                    [
                        'name' => 'Name List Course & Collection Item',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Mark course attendance per traveler.',
                            'Mark collection-item completion per traveler.',
                            'Use this tab as readiness checklist before departure.',
                        ],
                    ],
                    [
                        'name' => 'Upload Documents',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Upload flight ticket references for package travel routes.',
                            'Upload visa, train, and hotel documents by tab.',
                            'Upload passport and photo manually when one-time link completion is missing.',
                            'Upload receipt attachments to maintain payment evidence traceability.',
                            'Complete all critical document tabs before operational handoff.',
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
                    'User Access & Permissions',
                    'PIF and itinerary-centric operations governance',
                ],
                'procedures' => [
                    [
                        'name' => 'User Access & Permissions',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'SuperAdmin can edit Ops Movement structure, PIF controls, and Budget data.',
                            'Operations can upload itinerary and booklet files, view operational records, and export reports.',
                            'Operations users do not edit protected operational master fields.',
                            'Use role boundaries to preserve governance and auditability.',
                        ],
                    ],
                ],
            ],
        ];
    }
}
