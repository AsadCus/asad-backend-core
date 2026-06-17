<?php

namespace Tests\Feature\Tms;

use App\Models\PaymentMethodMaster;
use App\Services\QuotationService;
use App\Services\ReceiptService;
use Tests\TmsTestCase as TestCase;

class PaymentMethodRefundFlagTest extends TestCase
{
    public function test_store_persists_is_available_for_refund_flag(): void
    {
        app(QuotationService::class)->storePaymentMethodMasters([
            ['name' => 'Cash', 'is_active' => true, 'is_available_for_refund' => true, 'sort_order' => 1],
            ['name' => 'Voucher', 'is_active' => true, 'is_available_for_refund' => false, 'sort_order' => 2],
        ]);

        $this->assertDatabaseHas('payment_method_masters', ['value' => 'cash', 'is_available_for_refund' => true]);
        $this->assertDatabaseHas('payment_method_masters', ['value' => 'voucher', 'is_available_for_refund' => false]);
    }

    public function test_payment_method_options_expose_refund_flag(): void
    {
        PaymentMethodMaster::create(['name' => 'Cash', 'value' => 'cash', 'is_active' => true, 'is_available_for_refund' => true, 'sort_order' => 1]);
        PaymentMethodMaster::create(['name' => 'Voucher', 'value' => 'voucher', 'is_active' => true, 'is_available_for_refund' => false, 'sort_order' => 2]);

        $options = collect(app(ReceiptService::class)->getPaymentMethodOptions())->keyBy('value');

        $this->assertTrue($options['cash']['is_available_for_refund']);
        $this->assertFalse($options['voucher']['is_available_for_refund']);
    }
}
