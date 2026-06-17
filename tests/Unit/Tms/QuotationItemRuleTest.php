<?php

namespace Tests\Unit\Tms;

use App\Rules\QuotationItemRule;
use Illuminate\Support\Facades\Validator;
use Tests\TmsTestCase as TestCase;

class QuotationItemRuleTest extends TestCase
{
    public function test_rate_rule_allows_negative_values(): void
    {
        $rules = (new QuotationItemRule)->rules('items');

        $this->assertArrayHasKey('items.*.rate', $rules);
        $this->assertContains('numeric', $rules['items.*.rate']);
        $this->assertNotContains('min:0', $rules['items.*.rate']);
    }

    public function test_header_item_can_exist_without_child(): void
    {
        $rules = (new QuotationItemRule)->rules('items');

        $payload = [
            'items' => [
                [
                    '_key' => 'header-1',
                    'description' => 'Header without child',
                    'is_header' => true,
                    'is_optional' => false,
                    'quantity' => null,
                    'rate' => null,
                    'taxes' => [],
                ],
            ],
        ];

        $validator = Validator::make($payload, $rules);

        $this->assertTrue($validator->passes(), 'Header item without child should be valid.');
    }
}
