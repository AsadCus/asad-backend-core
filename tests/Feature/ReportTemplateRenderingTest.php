<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReportTemplateRenderingTest extends TestCase
{
    /**
     * @param  array<int, string>  $sections
     */
    private function assertSectionsAppearInOrder(string $html, array $sections): void
    {
        $position = -1;

        foreach ($sections as $section) {
            $needle = '<div class="section-title">'.$section.'</div>';
            $nextPosition = strpos($html, $needle);

            $this->assertNotFalse($nextPosition, "Missing section label: {$section}");
            $this->assertGreaterThan($position, $nextPosition, "Section label is out of order: {$section}");

            $position = $nextPosition;
        }
    }

    public function test_member_receipts_report_hides_payment_history_rows(): void
    {
        $html = view('customer-confirmations.member-receipts-report', [
            'data' => [
                'customer_name' => 'Test Customer',
                'customer_number' => 'CUST-001',
                'customer_email' => 'test@example.com',
                'customer_contact' => '+6500000000',
                'customer_address' => 'Singapore',
                'nric_number' => 'S1234567A',
                'package_name' => 'Umrah Package',
                'date_of_application' => '16 April 2026',
                'payment_status' => 'paid',
                'paid_amount' => 5000,
                'total_amount' => 6000,
                'receipts' => [
                    [
                        'receipt_number' => 'RCP-001',
                        'receipt_date' => '16 April 2026',
                        'invoice_number' => 'INV-001',
                        'payment_method_label' => 'Bank Transfer',
                        'order_number' => 'ORD-001',
                        'reference' => 'REF-001',
                        'subtotal_amount' => 5000,
                        'total_amount' => 5000,
                        'extensions' => [],
                        'items' => [
                            [
                                'id' => 1,
                                'parent_id' => null,
                                'type' => null,
                                'description' => 'Flight',
                                'is_header' => false,
                                'quantity' => 1,
                                'rate' => 5000,
                                'sort_order' => 1,
                                '_key' => 'item-1',
                                'parent_key' => null,
                            ],
                        ],
                        'invoice_payment_progress' => [
                            [
                                'label' => '1st Payment',
                                'amount_paid' => 5000,
                                'total_amount' => 6000,
                            ],
                        ],
                    ],
                ],
            ],
            'branding' => [
                'title_color' => '#c05427',
                'footer_text' => 'Thank you',
            ],
            'is_pdf' => true,
        ])->render();

        $this->assertStringContainsString('Total Amount:', $html);
        $this->assertStringNotContainsString('Pending Payment:', $html);
        $this->assertStringNotContainsString('1st Payment:', $html);
    }

    public function test_pif_report_passenger_details_hides_company_name(): void
    {
        $html = view('ops-movements.pif-report-content', [
            'opsMovement' => [
                'company_name' => 'Wrong Company From Payload',
                'departure_return_range' => '16 April 2026 - 30 April 2026',
                'passengers' => [
                    'adult_total' => 1,
                    'child_total' => 0,
                    'infant_total' => 0,
                    'official_total' => 1,
                    'grand_total' => 2,
                ],
                'pif' => [
                    'tour_leaders' => [
                        [
                            'type' => 'Saudi',
                            'name' => 'Leader 1',
                            'contact_number' => '+9665000001',
                        ],
                    ],
                ],
                'flights' => [],
                'accommodations' => [],
                'rawdah_tasreehs' => [],
                'transportation_plans' => [],
            ],
            'branding' => [
                'company_name' => 'Karva Travel & Tours',
                'footer_text' => 'Footer text',
            ],
        ])->render();

        $this->assertStringNotContainsString('Wrong Company From Payload', $html);
        $this->assertStringNotContainsString('<th style="width: 20%;" rowspan="2">Company Name</th>', $html);
    }

    public function test_pif_report_accommodation_merges_cwb_and_cnb_into_single_and_hides_category_row(): void
    {
        $html = view('ops-movements.pif-report-content', [
            'opsMovement' => [
                'departure_return_range' => '16 April 2026 - 30 April 2026',
                'passengers' => [
                    'adult_total' => 1,
                    'child_total' => 0,
                    'infant_total' => 0,
                    'official_total' => 1,
                    'grand_total' => 2,
                    'child_with_bed_total' => 1,
                    'child_no_bed_total' => 3,
                ],
                'pif' => [
                    'tour_leaders' => [],
                ],
                'flights' => [],
                'accommodations' => [
                    [
                        'location' => 'Makkah',
                        'hotel_name' => 'Hotel A',
                        'check_in' => '16 Apr 2026',
                        'check_out' => '20 Apr 2026',
                        'nights' => 4,
                        'room_counts' => [
                            'single' => 2,
                            'child_with_bed' => 1,
                            'child_no_bed' => 3,
                            'double' => 0,
                            'triple' => 0,
                            'quad' => 0,
                            'infant' => 0,
                        ],
                        'remarks' => '-',
                    ],
                ],
                'rawdah_tasreehs' => [],
                'transportation_plans' => [],
            ],
            'branding' => [
                'company_name' => 'Karva Travel & Tours',
                'footer_text' => 'Footer text',
            ],
        ])->render();

        $this->assertStringNotContainsString('>CWB<', $html);
        $this->assertStringNotContainsString('>CNB<', $html);
        $this->assertStringNotContainsString('Passenger Category Count', $html);
        $this->assertStringContainsString('<td class="text-right">6</td>', $html);
    }

    public function test_ops_movement_reports_use_package_like_section_title_styling(): void
    {
        $html = view('ops-movements.report-content', [
            'opsMovement' => [
                'package_number' => 'PKG-001',
                'manifest_number' => 'MAN-001',
                'ops_movement_number' => 'OM-001',
                'name' => 'Ops Movement Package',
                'departure_return_range' => '16 April 2026 - 30 April 2026',
                'visa_type' => 'Umrah Visa',
                'first_hotel_name' => 'Hotel A',
                'ops_base' => 'Makkah Ops Desk',
                'infotech_ref' => 'INF-001',
                'location' => 'Makkah',
                'doa_by' => 'Amir',
                'doa_datetime' => '2026-01-08 05:30:00',
                'visa_submitted_to_z_umrah' => true,
                'visa_approved' => false,
                'passengers' => [
                    'adult_total' => 1,
                    'child_total' => 0,
                    'official_total' => 1,
                    'grand_total' => 2,
                    'adult_male' => 1,
                    'adult_female' => 0,
                    'child_boy' => 0,
                    'child_girl' => 0,
                    'wheelchair_non_official_total' => 0,
                ],
                'accommodations' => [],
                'officials' => [],
                'flights' => [],
                'vehicle_type' => 'Bus 45 Seater',
                'vehicle_driver_name' => 'Driver A',
                'vehicle_driver_contact_number' => '+6500000000',
                'train_description' => 'Train transfer from Makkah to Madinah',
            ],
            'branding' => [
                'title_color' => '#c05427',
                'footer_text' => 'Footer text',
            ],
        ])->render();

        $this->assertSectionsAppearInOrder($html, [
            'Ops Movement Info',
            'Pax / Passengers',
            'Hotels',
            'Officials',
            'Flight Info',
            'Vehicle',
            'Train',
            'Visa',
        ]);
        $this->assertStringContainsString('background: #f0f0f0;', $html);
        $this->assertStringContainsString('border-left: 3px solid #c05427;', $html);
        $this->assertStringContainsString('padding: 4px 8px;', $html);
    }

    public function test_package_report_sections_follow_form_order(): void
    {
        $html = view('packages.report-content', [
            'data' => [
                'package_number' => 'PKG-001',
                'status' => 'open',
                'name' => 'Package A',
                'ticket_type' => 'two_way',
                'train_description' => 'Train transfer from Makkah to Madinah',
                'departure_date' => '16 April 2026',
                'return_date' => '30 April 2026',
                'total_seats' => 10,
                'seats_left' => 4,
                'price_single' => 1000,
                'price_double' => 900,
                'price_triple' => 800,
                'price_quad' => 700,
                'child_with_bed_price' => 600,
                'child_no_bed_price' => 500,
                'infant_price' => 100,
                'vehicle_type' => 'Bus',
                'vehicle_driver_name' => 'Driver A',
                'vehicle_driver_contact_number' => '+6500000000',
                'flights' => [],
                'transportation_plans' => [],
                'train_tickets' => [],
                'accommodations' => [],
                'rawdah_tasreehs' => [],
                'officials' => [],
                'included' => 'Flights',
                'not_included' => 'Tips',
                'offer' => 'Special offer',
                'remarks' => 'Internal notes',
            ],
            'branding' => [
                'title_color' => '#c05427',
                'footer_text' => 'Footer text',
            ],
        ])->render();

        $this->assertSectionsAppearInOrder($html, [
            'Package Information',
            'Pricing',
            'Flight Details',
            'Transportation Plan',
            'Visa',
            'Vehicle',
            'Train Ticket Details',
            'Accommodations',
            'Rawdah Tasreehs',
            'Officials',
            'Package Inclusions',
            'Remarks',
        ]);
    }

    public function test_pif_report_sections_follow_form_order(): void
    {
        $html = view('ops-movements.pif-report-content', [
            'opsMovement' => [
                'departure_return_range' => '16 April 2026 - 30 April 2026',
                'passengers' => [
                    'adult_total' => 1,
                    'child_total' => 0,
                    'infant_total' => 0,
                    'official_total' => 1,
                    'grand_total' => 2,
                ],
                'pif' => [
                    'tour_leaders' => [
                        [
                            'type' => 'Makkah',
                            'name' => 'Leader 1',
                            'contact_number' => '+9665000001',
                        ],
                    ],
                ],
                'flights' => [],
                'accommodations' => [],
                'rawdah_tasreehs' => [],
                'transportation_plans' => [],
            ],
            'branding' => [
                'company_name' => 'Karva Travel & Tours',
                'footer_text' => 'Footer text',
            ],
        ])->render();

        $this->assertSectionsAppearInOrder($html, [
            'Passenger Details',
            'Tour Leader',
            'Flight Schedule',
            'Accommodation',
            'Rawdah Tasreeh',
            'Transportation Plan',
        ]);
    }
}
