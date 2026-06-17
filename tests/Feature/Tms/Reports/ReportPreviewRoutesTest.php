<?php

namespace Tests\Feature\Tms\Reports;

use Tests\TmsTestCase as TestCase;

class ReportPreviewRoutesTest extends TestCase
{
    public function test_preview_routes_require_authentication(): void
    {
        $this->get(route('invoice.preview', ['id' => 1]))
            ->assertRedirect(route('login'));

        $this->get(route('quotation.preview', ['id' => 1]))
            ->assertRedirect(route('login'));

        $this->get(route('receipt.preview', ['id' => 1]))
            ->assertRedirect(route('login'));

        $this->get(route('sales.preview', ['sale' => 1]))
            ->assertRedirect(route('login'));
    }
}
