<?php

namespace Tests\Unit;

use App\Rules\PackageRule;
use PHPUnit\Framework\TestCase;

class PackageRuleTest extends TestCase
{
    public function test_total_seats_is_required_and_has_minimum_one(): void
    {
        $rule = new PackageRule;
        $rules = $rule->rules();

        $this->assertContains('required', $rules['total_seats']);
        $this->assertContains('integer', $rules['total_seats']);
        $this->assertContains('min:1', $rules['total_seats']);
        $this->assertNotContains('nullable', $rules['total_seats']);
    }
}
