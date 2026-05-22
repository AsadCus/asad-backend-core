<?php

namespace App\Services;

/**
 * Documentation Index Data
 *
 * This file contains the static documentation index data structure.
 * Separated from DocumentationService for maintainability and ease of editing.
 */
class DocumentationIndexData
{
    /**
     * Get the full index base data array.
     *
     * @return array<string, mixed>
     */
    public static function getBaseData(): array
    {
        return [
            'manual' => self::getManual(),
            'introduction' => self::getIntroduction(),
            'bibliography' => self::getBibliography(),
            'roleGuide' => self::getRoleGuide(),
            'menuStructure' => self::getMenuStructure(),
            'menuGroups' => self::getMenuGroups(),
            'coreWorkflows' => self::getCoreWorkflows(),
            'howToGuides' => self::getHowToGuides(),
            'commonStatuses' => self::getCommonStatuses(),
            'tips' => self::getTips(),
        ];
    }

    public static function getManual(): array
    {
        return [
            'title' => 'Documentation - KTS Manual',
            'version' => '1.0',
            'date' => '11 April 2026',
            'author' => 'Kherman',
        ];
    }

    public static function getIntroduction(): string
    {
        return 'This page explains the main menus, modules, and workflows used in this system. Use it as an operations handbook for daily work from enquiry intake until reporting and operations handoff.';
    }

    public static function getBibliography(): array
    {
        return [
            ['id' => 'dashboard-modules', 'title' => 'Dashboard Modules'],
            ['id' => 'master-modules', 'title' => 'Master Modules'],
            ['id' => 'product-and-services', 'title' => 'Product and Services'],
            ['id' => 'enquiry-modules', 'title' => 'Enquiry Modules'],
            ['id' => 'customer', 'title' => 'Customer'],
            ['id' => 'sales', 'title' => 'Sales'],
            ['id' => 'confirmed-customer', 'title' => 'Confirmed Customer'],
            ['id' => 'customer-holding-area', 'title' => 'Customer Holding Area'],
            ['id' => 'completed-customer', 'title' => 'Completed Customer'],
            ['id' => 'cancelled-customer', 'title' => 'Cancelled Customer'],
            ['id' => 'package', 'title' => 'Package'],
            ['id' => 'manifest', 'title' => 'Manifest'],
            ['id' => 'ops-movement', 'title' => 'Ops Movement'],
            ['id' => 'roles-access', 'title' => 'Roles and Access'],
            ['id' => 'core-workflows', 'title' => 'Core Workflows'],
            ['id' => 'how-to-guides', 'title' => 'How-To Guides'],
            ['id' => 'status-guidance', 'title' => 'Status Guidance'],
            ['id' => 'operational-tips', 'title' => 'Operational Tips'],
        ];
    }

    public static function getRoleGuide(): array
    {
        return [
            [
                'role' => 'Superadmin',
                'scope' => 'Manage core setup, system modules, package creation, manifests, and governance controls.',
                'primary_actions' => [
                    'Configure system-level references from Master module.',
                    'Manage broad workflow oversight across Sales, Package, Manifest, and Ops Movement.',
                    'Maintain compliance settings and user structure.',
                ],
            ],
            [
                'role' => 'Sales',
                'scope' => 'Handle enquiry conversion, customer management, sales documents, and follow-up execution.',
                'primary_actions' => [
                    'Work daily from Enquiry dashboard to Confirmed Customer flow.',
                    'Prepare quotation, order, invoice, and receipt sequence.',
                    'Coordinate readiness handoff to package and manifest operations.',
                ],
            ],
            [
                'role' => 'Operations',
                'scope' => 'Execute operational movement workflows, PIF outputs, itinerary, booklet, and budget tracking.',
                'primary_actions' => [
                    'Complete ops movement records by package and manifest context.',
                    'Generate operational exports for execution teams.',
                    'Maintain accurate operation-stage status and logistics records.',
                ],
            ],
        ];
    }

    public static function getMenuStructure(): array
    {
        return [
            [
                'menu' => 'Dashboard Modules',
                'children' => [
                    'Total Sales for Fiscal Year',
                    'Daily Payment',
                    'Upcoming Departures',
                    'Recent Customers',
                ],
            ],
            [
                'menu' => 'Master Modules',
                'children' => [
                    'User Roles',
                    'Add New User',
                    'Add New Country',
                    'Add New Fiscal Year',
                ],
            ],
            [
                'menu' => 'Product and Services',
                'children' => [
                    'Add New Product and Services',
                    'Add New Payment Method',
                    'Add New Payment Extension',
                    'Add New Tax Extensions',
                    'Add New Discount Extensions',
                ],
            ],
            [
                'menu' => 'Enquiry Modules',
                'children' => ['New Lead to Contacted', 'General Enquiry', 'Private Enquiry'],
            ],
            [
                'menu' => 'Customer',
                'children' => ['Add New Customer'],
            ],
            [
                'menu' => 'Sales',
                'children' => ['Sales Report', 'Quotation', 'Invoice', 'Receipt'],
            ],
            [
                'menu' => 'Customer Lifecycle',
                'children' => [
                    'Confirmed Customer',
                    'Customer Holding Area',
                    'Completed Customer',
                    'Cancelled Customer',
                ],
            ],
            [
                'menu' => 'Package',
                'children' => [
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
            ],
            [
                'menu' => 'Manifest',
                'children' => [
                    'Main',
                    'Gender',
                    'Discount',
                    'Payment Date',
                    'Receipt',
                    'Payment Status',
                    'Airline Name List',
                    'Room List – (Location 01)',
                    'Room List – (Location 02)',
                    'Room List for Official Check-In – (Location 01)',
                    'Room List for Official Check-In – (Location 02)',
                    'Name List Course & Collection Item',
                    'Flight Tickets',
                    'Visa',
                    'Train Tickets',
                    'Hotel',
                    'Passport',
                    'Photo',
                    'Arabic Names',
                    'Receipt Documents',
                ],
            ],
            [
                'menu' => 'Ops Movement',
                'children' => [
                    'Operations Movement',
                    'PIF',
                    'Itinerary',
                    'Booklet',
                    'Budget',
                    'User Access & Permissions',
                ],
            ],
        ];
    }

    public static function getMenuGroups(): array
    {
        return [
            [
                'menu' => 'Dashboard Modules',
                'route_path' => '/dashboard',
                'module' => 'Dashboard analytics and summaries',
                'purpose' => 'Monitor sales activities, payment collections, upcoming travel departures, and customer interest in one centralized screen.',
                'features' => [
                    'Total Sales for Fiscal Year',
                    'Daily Payment',
                    'Upcoming Departures',
                    'Recent Customers',
                ],
                'how_to' => [],
            ],
            [
                'menu' => 'Master Modules',
                'route_path' => '/master',
                'module' => 'System setup and reference management',
                'purpose' => 'Manage core system settings, master data, users, financial configurations, products, services, and payment settings.',
                'features' => [
                    'User Roles',
                    'Add New User',
                    'Add New Country',
                    'Add New Fiscal Year',
                ],
                'how_to' => [],
            ],
            [
                'menu' => 'Product and Services',
                'route_path' => '/master',
                'module' => 'Product and services catalog',
                'purpose' => 'Manages predefined items and services used throughout the system for invoicing, quotations, and operational purposes.',
                'features' => [
                    'Add New Product and Services',
                    'Add New Payment Method',
                    'Add New Payment Extension',
                    'Add New Tax Extensions',
                    'Add New Discount Extensions',
                ],
                'how_to' => [],
            ],
            [
                'menu' => 'Enquiry Modules',
                'route_path' => '/enquiries',
                'module' => 'General and Private enquiry pipelines',
                'purpose' => 'Capture incoming demand from website forms and convert qualified enquiries into confirmed customers.',
                'features' => [
                    'General Enquiry',
                    'Private Enquiry',
                    'Status updates and remarks',
                ],
                'how_to' => [],
            ],
            [
                'menu' => 'Customer',
                'route_path' => '/customer',
                'module' => 'Customer profile management',
                'purpose' => 'Maintain customer records that are manually added or auto-generated from enquiries.',
                'features' => [
                    'Add New Customer',
                ],
                'how_to' => [],
            ],
            [
                'menu' => 'Sales',
                'route_path' => '/sales',
                'module' => 'Sales Reports, Quotation, Invoice, Receipt',
                'purpose' => 'Handle full financial document lifecycle from reporting to offer creation to payment recording.',
                'features' => [
                    'Daily Received Report and Closing Report',
                    'Quotations (Non-umrah, Umrah Confirmed, Split)',
                    'Convert Quotation to Invoice and Instalment Plan',
                    'Generate and Recreate Receipts',
                ],
                'how_to' => [],
            ],
            [
                'menu' => 'Confirmed Customer',
                'route_path' => '/confirmed-customer',
                'module' => 'Confirmed traveller groups',
                'purpose' => 'Manage confirmed traveller groups, add participants, track payment progress, and manage the group lifecycle.',
                'features' => [
                    'Add Participants',
                    'One-Time Link for Details Update',
                    'Move Members to Holding Area',
                    'Refund for Trip Cancellation',
                ],
                'how_to' => [],
            ],
            [
                'menu' => 'Customer Holding Area',
                'route_path' => '/holding-area',
                'module' => 'Temporary holding for package assignment',
                'purpose' => 'Temporarily hold customers with unresolved statuses before moving them to a new package or pricing plan.',
                'features' => [
                    'Change Package and Pricing Plan With Overpaid Refund',
                    'Change Package With Higher Pricing Plan',
                ],
                'how_to' => [],
            ],
            [
                'menu' => 'Completed Customer',
                'route_path' => '/completed-customer',
                'module' => 'Completed trip records',
                'purpose' => 'Provides a read-only view of customer records whose trips have been completed.',
                'features' => [
                    'View Completed Customers',
                ],
                'how_to' => [],
            ],
            [
                'menu' => 'Cancelled Customer',
                'route_path' => '/cancelled-customer',
                'module' => 'Cancelled trip records',
                'purpose' => 'Provides a read-only view of customer records whose trips have been cancelled.',
                'features' => [
                    'View Cancelled Customers',
                ],
                'how_to' => [],
            ],
            [
                'menu' => 'Package',
                'route_path' => '/packages',
                'module' => 'Travel package setup',
                'purpose' => 'Serves as the master record for Manifest, Operations Movement, and PIF generation.',
                'features' => [
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
                'how_to' => [],
            ],
            [
                'menu' => 'Manifest',
                'route_path' => '/manifests',
                'module' => 'Travel execution manifest',
                'purpose' => 'Central information hub for customers who have successfully signed up and made the initial payment.',
                'features' => [
                    'Main Dashboard',
                    'Airline Name List',
                    'Room List (Location 01 and 02)',
                    'Room List for Official Check-In (Location 01 and 02)',
                    'Name List Course & Collection Item',
                    'Upload Flight Tickets, Visa, Train Tickets, and Hotel',
                    'Upload Passport, Photo, Arabic Names, and Receipts',
                ],
                'how_to' => [],
            ],
            [
                'menu' => 'Ops Movement',
                'route_path' => '/ops-movements',
                'module' => 'Operational movement and exports',
                'purpose' => 'Generate operational exports used by the field team before departure and manage budgets.',
                'features' => [
                    'Operations Movement',
                    'PIF (Passenger Information Form)',
                    'Itinerary and Booklet',
                    'Budget Tracking',
                    'User Access & Permissions',
                ],
                'how_to' => [],
            ],
        ];
    }

    public static function getCoreWorkflows(): array
    {
        return [
            [
                'name' => 'Enquiry to Confirmed Customer to Payment to Manifest',
                'goal' => 'Convert interest into operationally ready travelers with payment tracking.',
                'steps' => [
                    'Create General or Private Enquiry and assign follow-up owner.',
                    'Progress enquiry status and collect required traveller data.',
                    'Confirm enquiry into customer confirmation group and choose package.',
                    'Manage billing documents (quotation/order/invoice/receipt) and collect payments.',
                    'Validate member payment status and travel readiness.',
                    'Include traveler in manifest and finalize operations handoff.',
                ],
            ],
            [
                'name' => 'Quotation to Order to Invoice to Receipt',
                'goal' => 'Maintain clean sales-to-payment document chain.',
                'steps' => [
                    'Create quotation with correct line items, taxes, discounts, and terms.',
                    'Set quotation status to accepted when customer approves.',
                    'Convert accepted quotation to order.',
                    'Generate invoice from order and verify totals.',
                    'Issue receipt for each payment and validate outstanding balance.',
                ],
            ],
            [
                'name' => 'Package to Manifest to Ops Movement',
                'goal' => 'Prepare operations from package design to on-ground execution records.',
                'steps' => [
                    'Create package with travel dates, logistics, and seat capacity.',
                    'Use generated manifest to attach and organize confirmed members.',
                    'Complete rooming, document sections, and exports needed by operations.',
                    'Open Ops Movement and finalize movement details and budget reporting.',
                    'Publish final exports for field team (PIF, itinerary, booklet, budget as needed).',
                ],
            ],
            [
                'name' => 'Customer Lifecycle Buckets',
                'goal' => 'Keep customer state aligned with real operational condition.',
                'steps' => [
                    'Keep active and payable groups in Confirmed Customer.',
                    'Move unresolved or pending issues to Customer Holding Area.',
                    'Move completed trip records to Completed Customer.',
                    'Move cancelled groups to Cancelled Customer for historical tracking.',
                ],
            ],
        ];
    }

    public static function getHowToGuides(): array
    {
        return [
            [
                'task' => 'Create a new private enquiry',
                'steps' => [
                    'Open Enquiry -> Private Enquiry.',
                    'Click create, fill customer information, package preference, and notes.',
                    'Save and verify it appears in enquiry list/dashboard.',
                ],
            ],
            [
                'task' => 'Convert enquiry to confirmed customer group',
                'steps' => [
                    'Open enquiry detail and review qualification data.',
                    'Run confirm/create customer confirmation action.',
                    'Add members, select package, and validate group status.',
                ],
            ],
            [
                'task' => 'Collect payment with proper audit trail',
                'steps' => [
                    'Generate sales documents in order: quotation, order, invoice, receipt.',
                    'Record each payment transaction immediately after confirmation.',
                    'Verify outstanding and paid amounts before manifest finalization.',
                ],
            ],
            [
                'task' => 'Prepare a manifest for departure',
                'steps' => [
                    'Open manifest from related package.',
                    'Fill core and room sections, then validate traveler details.',
                    'Export required PDFs for field operations and airport handling.',
                ],
            ],
            [
                'task' => 'Use logs for troubleshooting',
                'steps' => [
                    'Open User Logs and filter by date or action.',
                    'Inspect change summary for fields that were modified.',
                    'Correlate with process stage to identify root cause quickly.',
                ],
            ],
        ];
    }

    public static function getCommonStatuses(): array
    {
        return [
            [
                'topic' => 'Enquiry statuses',
                'notes' => [
                    'Track progress from initial intake to qualified follow-up and confirmation readiness.',
                    'Use remarks and transitions consistently so sales handovers are clear.',
                ],
            ],
            [
                'topic' => 'Quotation statuses',
                'notes' => [
                    'Draft for in-progress edits, ready for internal review, accepted/rejected/expired/cancelled for final outcomes.',
                    'Only accepted quotations should move to order conversion.',
                ],
            ],
            [
                'topic' => 'Customer confirmation member statuses',
                'notes' => [
                    'Status impacts payment interpretation and downstream operations.',
                    'Keep member-level status synchronized with actual billing and travel condition.',
                ],
            ],
        ];
    }

    public static function getTips(): array
    {
        return [
            'Always complete data in sequence: enquiry -> confirmation -> financial docs -> manifest -> ops movement.',
            'Avoid creating duplicate customers by searching first during enquiry and confirmation.',
            'Use report previews before sharing PDFs with customers or management.',
            'When in doubt, verify last changes in User Logs before editing again.',
            'Align package capacity and manifest assignments frequently to prevent late-stage conflicts.',
        ];
    }
}
