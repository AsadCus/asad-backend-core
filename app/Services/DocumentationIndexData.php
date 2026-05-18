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
            'title' => 'Documentation - Travel Management System Manual',
            'version' => '1.1',
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
            ['id' => 'dashboard-module', 'title' => 'Dashboard Module'],
            ['id' => 'master-module', 'title' => 'Master Module'],
            ['id' => 'sales-module', 'title' => 'Sales Module'],
            ['id' => 'customer-module', 'title' => 'Customer Module'],
            ['id' => 'enquiry-module', 'title' => 'Enquiry Module'],
            ['id' => 'confirmed-customer-module', 'title' => 'Confirmed Customer Module'],
            ['id' => 'quotation-module', 'title' => 'Quotation Module'],
            ['id' => 'invoice-module', 'title' => 'Invoice Module'],
            ['id' => 'receipt-module', 'title' => 'Receipt Module'],
            ['id' => 'change-package-module', 'title' => 'Change Package Module'],
            ['id' => 'refund-module', 'title' => 'Refund Module'],
            ['id' => 'customer-holding-area-module', 'title' => 'Customer Holding Area Module'],
            ['id' => 'completed-customer-module', 'title' => 'Completed Customer Module'],
            ['id' => 'cancelled-customer-module', 'title' => 'Cancelled Customer Module'],
            ['id' => 'ops-movement-module', 'title' => 'Ops Movement Module'],
            ['id' => 'pif-module', 'title' => 'PIF Module'],
            ['id' => 'itinerary-module', 'title' => 'Itinerary Module'],
            ['id' => 'budget-module', 'title' => 'Budget Module'],
            ['id' => 'roles-access', 'title' => 'Roles and Access'],
            ['id' => 'menu-structure', 'title' => 'Menu Structure'],
            ['id' => 'menus-modules', 'title' => 'Menus and Modules'],
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
                'menu' => 'Dashboard',
                'children' => [
                    'Total Sales for Fiscal Year',
                    'Daily Payment',
                    'Recent Customers',
                ],
            ],
            [
                'menu' => 'Master',
                'children' => [
                    'Add User',
                    'User Management: Superadmin, Admin, Sales, Customer, Operations',
                    'Country',
                    'Branch',
                    'Fiscal Year',
                    'Products and Services',
                ],
            ],
            [
                'menu' => 'Sales',
                'children' => ['Quotation', 'Order', 'Invoice', 'Receipt'],
            ],
            [
                'menu' => 'Enquiry',
                'children' => ['Enquiry Dashboard', 'General Enquiry', 'Private Enquiry'],
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
                'menu' => 'Manifest',
                'children' => [
                    'Main',
                    'Airline Name List',
                    'Room List',
                    'Room List for Official Check-In',
                    'Namelist for Course and Item Collection',
                    'Flight Tickets',
                    'VISA',
                    'Train Tickets',
                    'Hotel',
                    'Passport',
                    'Photo',
                    'Arabic Name List',
                    'Receipt',
                ],
            ],
            [
                'menu' => 'Ops Movement',
                'children' => ['Ops Movement Main', 'PIF', 'Itinerary', 'Booklet', 'Budget'],
            ],
            [
                'menu' => 'User Logs',
                'children' => ['Activity timeline', 'Change summary', 'Audit tracing'],
            ],
        ];
    }

    public static function getMenuGroups(): array
    {
        return [
            [
                'menu' => 'Dashboard',
                'route_path' => '/dashboard',
                'module' => 'Dashboard analytics and summaries',
                'purpose' => 'Monitor key metrics based on your role, including revenue, customer trend, and enquiry summary.',
                'features' => [
                    'Role-based dashboard content for superadmin, sales, and customer users.',
                    'Fiscal year based chart and payment summary data.',
                    'Export payment summary report for audit and sharing.',
                ],
                'how_to' => [
                    'Select fiscal year to view period-specific data.',
                    'Use payment summary export when finance asks for snapshot reports.',
                ],
            ],
            [
                'menu' => 'Master',
                'route_path' => '/master',
                'module' => 'System setup and reference management',
                'purpose' => 'Configure users, country, branch, fiscal year, and product-service catalogs before operations start.',
                'features' => [
                    'Create and manage superadmin, admin, sales, operations, and customer users.',
                    'Maintain country and branch references (based on scope mode).',
                    'Manage fiscal year defaults and product-service items.',
                ],
                'how_to' => [
                    'Set fiscal year default before processing new period transactions.',
                    'Keep products and services updated to avoid pricing mismatch in quotation.',
                ],
            ],
            [
                'menu' => 'Sales',
                'route_path' => '/sales',
                'module' => 'Quotation, Order, Invoice, Receipt',
                'purpose' => 'Handle full financial document lifecycle from offer creation to payment recording.',
                'features' => [
                    'Quotation state control (draft, ready, accept, reject, expire, cancel).',
                    'Order generation and invoice conversion workflows.',
                    'Receipt generation and print/preview for customer payment proof.',
                ],
                'how_to' => [
                    'Create quotation first, then convert to order when customer confirms.',
                    'Generate invoice from order and issue receipt after receiving payment.',
                    'Issue receipt immediately after payment posting to avoid balance mismatch.',
                ],
            ],
            [
                'menu' => 'Customer',
                'route_path' => '/customer',
                'module' => 'Customer profile and assignment tracking',
                'purpose' => 'Maintain customer records and assignment status for sales and admin follow-up.',
                'features' => [
                    'Customer list with assignment and handling actions.',
                    'Enable or disable customer records based on account status.',
                    'Link customer records into quotation and enquiry workflows.',
                ],
                'how_to' => [
                    'Search customer by profile fields before creating duplicates.',
                    'Use handle and assignment actions to keep ownership clear.',
                ],
            ],
            [
                'menu' => 'Enquiry',
                'route_path' => '/enquiries',
                'module' => 'General and Private enquiry pipelines',
                'purpose' => 'Capture incoming demand and move qualified enquiries into customer confirmation flow.',
                'features' => [
                    'Separate general and private enquiry forms with status updates.',
                    'Unified enquiry dashboard for sales follow-up.',
                    'Direct conversion into customer confirmation when ready.',
                ],
                'how_to' => [
                    'Create enquiry from form or public link submissions.',
                    'Update enquiry status and remarks before confirmation decision.',
                    'Convert qualified leads quickly to customer confirmation to prevent duplicate records.',
                ],
            ],
            [
                'menu' => 'Confirmed Customer',
                'route_path' => '/confirmed-customer',
                'module' => 'Customer confirmation groups and member-level lifecycle',
                'purpose' => 'Manage confirmed traveller groups, payment progress, and customer lifecycle buckets.',
                'features' => [
                    'Group members by confirmation record tied to enquiry and package.',
                    'Support member move, cancellation, holding area, completion, and refund handling.',
                    'Generate public create/edit links for customer self-update scenarios.',
                ],
                'how_to' => [
                    'Use Confirmed Customer for active groups.',
                    'Move unresolved cases to Holding, completed trips to Completed, and dropped cases to Cancelled.',
                ],
            ],
            [
                'menu' => 'Package',
                'route_path' => '/packages',
                'module' => 'Travel package setup and capacity control',
                'purpose' => 'Define package pricing, schedule, accommodation, flights, and seat inventory.',
                'features' => [
                    'Package seat and availability tracking.',
                    'Officials, transport, and itinerary components in one package form.',
                    'Automatic manifest creation when package is created.',
                ],
                'how_to' => [
                    'Create package with accurate departure and return dates.',
                    'Confirm total seats before linking customers to prevent overbooking.',
                ],
            ],
            [
                'menu' => 'Manifest',
                'route_path' => '/manifests',
                'module' => 'Travel execution manifest and rooming operations',
                'purpose' => 'Prepare traveler operational list and finalize room and section data before departure.',
                'features' => [
                    'Manage manifest core, sharing groups, rooms, documents, and receipt documents.',
                    'Move members to holding and synchronize official member data.',
                    'Export collection items, Arabic names, airline names, and room-check PDFs.',
                ],
                'how_to' => [
                    'Review member completeness before room assignments.',
                    'Use section-level saves frequently to avoid payload conflicts in large manifests.',
                ],
            ],
            [
                'menu' => 'Ops Movement',
                'route_path' => '/ops-movements',
                'module' => 'Operational movement and budget tracking',
                'purpose' => 'Track operational workflow linked to package and manifest, including execution and reporting outputs.',
                'features' => [
                    'View and update movement details per package/manifest context.',
                    'Export operational PDF, PIF PDF, and budget PDF.',
                    'Superadmin-controlled budget fields with role-safe restrictions.',
                ],
                'how_to' => [
                    'Open Ops Movement from list and complete required movement sections.',
                    'Use budget export for management review and audit handoff.',
                ],
            ],
            [
                'menu' => 'User Logs',
                'route_path' => '/user-logs',
                'module' => 'Audit trail and change history',
                'purpose' => 'Trace system actions and inspect before-after changes for compliance and troubleshooting.',
                'features' => [
                    'Centralized activity entries with actor, time, and action details.',
                    'Structured diff view of old and updated values.',
                    'Useful for verification of critical financial and manifest edits.',
                ],
                'how_to' => [
                    'Filter logs by action context when investigating issues.',
                    'Use change summary to validate who changed sensitive fields.',
                ],
            ],
            [
                'menu' => 'Settings',
                'route_path' => '/settings',
                'module' => 'Profile, password, appearance, report template, number formats',
                'purpose' => 'Manage account settings and system-level output formatting for documents and visuals.',
                'features' => [
                    'Profile and password management for all users.',
                    'Appearance settings for superadmin users.',
                    'Report Template and Model Number Formats for authorized ghost-superadmin users.',
                ],
                'how_to' => [
                    'Configure report branding before generating customer-facing PDFs.',
                    'Set model number format rules before introducing new numbering sequences.',
                ],
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
