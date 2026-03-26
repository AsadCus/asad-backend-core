<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\EducationLevel;
use App\Models\Maid;
use App\Models\Religion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Maid>
 */
class MaidFactory extends Factory
{
    protected $model = Maid::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'date_of_birth' => $this->faker->date(),
            'place_of_birth' => $this->faker->city(),
            'height' => $this->faker->randomFloat(2, 140, 180),
            'weight' => $this->faker->randomFloat(2, 40, 90),
            'country_id' => Country::factory(),
            'address' => $this->faker->address(),
            'repatriation_port_airport' => $this->faker->city(),
            'contact_number_home_country' => $this->faker->e164PhoneNumber(),
            'religion_id' => Religion::factory(),
            'education_level_id' => EducationLevel::factory(),
            'marital_status' => $this->faker->randomElement(['single', 'married', 'widowed']),
            'number_of_siblings' => $this->faker->numberBetween(0, 8),
            'number_of_children' => $this->faker->numberBetween(0, 5),
            'children_ages' => '5,8',
            'photo_url' => null,

            // a3 others
            'bio_code' => strtoupper($this->faker->bothify('BIO-####')),
            'rest_days_per_month' => 4,
            'other_remarks' => $this->faker->sentence(),

            // system fields
            'status' => 'available',
            'remaining_loan' => null,
            'cost_of_maid' => null,

            // Section D: interview availability flags (booleans default false)
            'interview_not_available' => false,
            'interview_by_phone' => true,
            'interview_by_video' => true,
            'interview_in_person' => false,
        ];
    }
}
