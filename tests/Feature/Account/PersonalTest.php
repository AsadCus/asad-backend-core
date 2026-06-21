<?php

namespace Tests\Feature\Account;

use App\Models\EducationLevel;
use App\Models\Employee;
use App\Models\Religion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalTest extends TestCase
{
    use RefreshDatabase;

    private function makeEmployeeUser(array $attrs = []): array
    {
        $user = User::factory()->create();
        $employee = Employee::query()->create(array_merge([
            'employee_no' => 'EMP-'.fake()->unique()->numerify('####'),
            'hire_date' => '2024-01-01',
            'user_id' => $user->id,
        ], $attrs));

        return [$user, $employee];
    }

    public function test_show_returns_personal_data_and_options(): void
    {
        $religion = Religion::query()->create(['name' => 'Islam']);
        [$user] = $this->makeEmployeeUser([
            'phone' => '0812345678',
            'religion_id' => $religion->id,
        ]);
        EducationLevel::query()->create(['name' => 'S1']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/account/personal')
            ->assertOk()
            ->assertJsonPath('personal.phone', '0812345678')
            ->assertJsonPath('personal.religion_id', $religion->id)
            ->assertJsonStructure([
                'personal' => ['nik', 'gender', 'birth_date', 'religion_id', 'phone'],
                'options' => ['genders', 'religions', 'educationLevels'],
            ]);
    }

    public function test_user_can_update_their_personal_data(): void
    {
        $religion = Religion::query()->create(['name' => 'Islam']);
        $education = EducationLevel::query()->create(['name' => 'S1']);
        [$user, $employee] = $this->makeEmployeeUser();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/account/personal', [
                'nik' => '1234567890',
                'gender' => 'male',
                'birth_date' => '2001-05-03',
                'religion_id' => $religion->id,
                'education_level_id' => $education->id,
                'phone' => '0899',
                'address' => 'Jakarta',
                'emergency_contact_name' => 'Mom',
                'emergency_contact_phone' => '0800',
            ])
            ->assertOk()
            ->assertJsonPath('personal.nik', '1234567890')
            ->assertJsonPath('personal.gender', 'male');

        $employee->refresh();
        $this->assertSame('1234567890', $employee->nik);
        $this->assertSame('Jakarta', $employee->address);
        $this->assertSame($religion->id, $employee->religion_id);
        $this->assertSame('2001-05-03', $employee->birth_date->toDateString());
    }

    public function test_invalid_references_are_rejected(): void
    {
        [$user] = $this->makeEmployeeUser();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/account/personal', [
                'gender' => 'other',
                'religion_id' => 999999,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['gender', 'religion_id']);
    }

    public function test_user_without_employee_record_cannot_update(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/account/personal', ['phone' => '0812'])
            ->assertNotFound();
    }
}
