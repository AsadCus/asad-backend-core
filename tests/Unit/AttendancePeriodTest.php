<?php

namespace Tests\Unit;

use App\Support\AttendancePeriod;
use InvalidArgumentException;
use Tests\TestCase;

class AttendancePeriodTest extends TestCase
{
    public function test_cutoff_day_one_is_a_plain_calendar_month(): void
    {
        $period = AttendancePeriod::forMonth(2026, 6, 1);

        $this->assertSame('2026-06-01', $period['from']->toDateString());
        $this->assertSame('2026-06-30', $period['to']->toDateString());
    }

    public function test_cutoff_day_sixteen_spans_the_16th_of_the_prior_month_to_the_15th(): void
    {
        // Matches the common "payday on the 25th" cycle: period "June 2026" runs
        // 16 May 2026 -> 15 June 2026, not the calendar month.
        $period = AttendancePeriod::forMonth(2026, 6, 16);

        $this->assertSame('2026-05-16', $period['from']->toDateString());
        $this->assertSame('2026-06-15', $period['to']->toDateString());
    }

    public function test_cutoff_day_spanning_january_crosses_the_year_boundary(): void
    {
        $period = AttendancePeriod::forMonth(2026, 1, 21);

        $this->assertSame('2025-12-21', $period['from']->toDateString());
        $this->assertSame('2026-01-20', $period['to']->toDateString());
    }

    public function test_cutoff_day_handles_a_short_february_correctly(): void
    {
        // "March 2026" period starting the 28th of February (the latest allowed cutoff day).
        $period = AttendancePeriod::forMonth(2026, 3, 28);

        $this->assertSame('2026-02-28', $period['from']->toDateString());
        $this->assertSame('2026-03-27', $period['to']->toDateString());
    }

    public function test_rejects_a_cutoff_day_outside_the_valid_range(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AttendancePeriod::forMonth(2026, 6, 29);
    }

    public function test_rejects_a_zero_cutoff_day(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AttendancePeriod::forMonth(2026, 6, 0);
    }
}
