<?php

namespace Tests\Unit;

use App\Rules\QuotationItemRule;
use PHPUnit\Framework\TestCase;

class QuotationItemRuleTest extends TestCase
{
    public function test_rate_rule_allows_negative_values(): void
    {
        $rules = (new QuotationItemRule)->rules('items');

        $this->assertArrayHasKey('items.*.rate', $rules);
        $this->assertContains('numeric', $rules['items.*.rate']);
        $this->assertNotContains('min:0', $rules['items.*.rate']);
    }
}
