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
                            'Click the Invoice menu.',
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
                            'Navigate to Package > Create New Package.',
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
                            'Enter description (e.g., "Singapore to Jeddah via Doha").',
                            'From: Select departure airport (Singapore, Kuala Lumpur, etc.).',
                            'To: Select arrival airport (Jeddah for Umrah, Amman for Jordan, etc.).',
                            'Airline: Enter airline name and flight number.',
                            'PNR: Enter 6-digit airline booking reference (confirm with booking agent).',
                            'Departure Date/Time: Click calendar, select date and time.',
                            'Arrival Date/Time: Select date/time of arrival.',
                            'Save.',
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
                            'Scroll to "Rawdah Tasreeh Section".',
                            'Click "Add Rawdah Tasreeh".',
                            'Enter: Visit Date, Gender (Male, Female, or Mixed), Allocated Time Slot, and Capacity.',
                            'Click Add again for additional time slots.',
                            'Save.',
                        ],
                    ],
                    [
                        'name' => 'Officials Section',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Scroll to "Officials Section".',
                            'Click "Add Official".',
                            'Enter: Official Type (Guide, Coordinator, Liaison Officer), Name, Contact Phone, and Hotel Location.',
                            'Save.',
                        ],
                    ],
                    [
                        'name' => 'Package Inclusions',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Verify all sections created above are auto-compiled.',
                            'Check Flight details, Hotels, Transportation, Visa info, and Officials.',
                            'Use this page as the package checklist baseline.',
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
                            'Navigate to Manifest and select the desired package.',
                            'View the Main Dashboard showing all confirmed customers/participants.',
                            'Verify customer names, pricing plans, and overall travel readiness.',
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
                            'Check the Receipt column on the Main Dashboard.',
                            'Click the receipt icon or link to view/print the corresponding payment receipt.',
                            'Confirm receipt validity.',
                        ],
                    ],
                    [
                        'name' => 'Payment Status',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Check the Payment Status column on the Main Dashboard.',
                            'Verify that the status is Paid, Partially Paid, Overpaid, or Pending.',
                            'Ensure full payment is cleared before finalizing the departure manifest.',
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
                            'Use the 6-dot drag icon to group customers into hotel rooms.',
                            'Ensure all grouped members in a room share the same pricing plan.',
                            'Click Update Manifest to save the room layout for Location 01.',
                        ],
                    ],
                    [
                        'name' => 'Room List – (Location 02)',
                        'type' => 'article',
                        'status' => 'done',
                        'steps' => [
                            'Navigate to Room List – (Location 02).',
                            'Use the drag-and-drop interface to group customers into hotel rooms at Location 02.',
                            'Alternatively, copy the layout: click Reset, select Duplicate from Location 01, and Update Manifest.',
                            'Save the changes.',
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
                            'Click Upload Receipt to attach bank transfer confirmations or receipts (max 3 per room group).',
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
                            'Navigate to Ops Movement and select the desired package.',
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
                            'Navigate to Ops Movement > Budget section (SuperAdmin only).',
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
                            'SuperAdmin can edit all sections, including operational master details and budgets.',
                            'Operations role can view information, upload itineraries/booklets, and export reports, but cannot edit master data or view budgets.',
                            'Sales role has view-only access (informational) to Ops Movement.',
                        ],
                    ],
                ],
            ],
        ];
    }
}
