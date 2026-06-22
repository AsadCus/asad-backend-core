<?php

namespace Tests\Feature;

use App\Enums\OrgUnitType;
use App\Models\OrgUnit;
use App\Models\WorkSchedule;
use App\Services\WorkScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WorkScheduleGenerateDownTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_independent_copies_to_descendant_org_units(): void
    {
        $holding = OrgUnit::factory()->create(['type' => OrgUnitType::Holding, 'parent_id' => null]);
        $bu1 = OrgUnit::factory()->create(['type' => OrgUnitType::BusinessUnit, 'parent_id' => $holding->id]);
        $bu2 = OrgUnit::factory()->create(['type' => OrgUnitType::BusinessUnit, 'parent_id' => $holding->id]);

        $schedule = WorkSchedule::create([
            'name' => 'Standard Week',
            'code' => 'STD',
            'owner_org_unit_id' => $holding->id,
            'is_active' => true,
        ]);
        $schedule->workScheduleDays()->create(['day_of_week' => 1, 'shift_id' => null, 'is_workday' => true]);

        $count = app(WorkScheduleService::class)->generateDown($schedule->id);

        $this->assertSame(2, $count);

        foreach ([$bu1, $bu2] as $bu) {
            $copy = WorkSchedule::where('code', "STD-{$bu->code}")->with('workScheduleDays')->first();
            $this->assertNotNull($copy, "missing copy for {$bu->code}");
            $this->assertSame($bu->id, $copy->owner_org_unit_id);
            $this->assertNotSame($schedule->id, $copy->id); // independent row
            $this->assertCount(1, $copy->workScheduleDays);
        }

        // Editing the source day does not touch the copies.
        $schedule->workScheduleDays()->first()->update(['is_workday' => false]);
        $copy = WorkSchedule::where('code', "STD-{$bu1->code}")->with('workScheduleDays')->first();
        $this->assertTrue((bool) $copy->workScheduleDays->first()->is_workday);
    }

    public function test_generate_down_requires_an_owner(): void
    {
        $schedule = WorkSchedule::create(['name' => 'Orphan', 'code' => 'ORP', 'is_active' => true]);

        $this->expectException(ValidationException::class);
        app(WorkScheduleService::class)->generateDown($schedule->id);
    }
}
