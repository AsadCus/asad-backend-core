<?php

namespace Database\Seeders;

use App\Models\Maid;
use App\Models\Country;
use App\Models\Religion;
use App\Models\EducationLevel;
use App\Models\MaidAttribute;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class MaidSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        $religionIds = Religion::pluck('id')->toArray();
        $educationLevelIds = EducationLevel::pluck('id')->toArray();
        $countryIds = Country::pluck('id')->toArray();
        $supplierIds = \App\Models\Supplier::pluck('id')->toArray();

        $statuses = ['available', 'interviewing', 'pending', 'assigned'];
        $maritalStatuses = ['Single', 'Married', 'Widowed', 'Divorced'];

        $attributeCategories = [
            'ILLNESS' => ['Mental Illness', 'Epilepsy', 'Asthma', 'Diabetes', 'Hypertension', 'Tuberculosis', 'Heart Disease', 'Malaria', 'Operations'],
            'ILLNESS_OTHERS' => ['Mild Back Pain', 'Migraine'],
            'FOOD_PREFERENCE' => ['No Beef', 'No Pork'],
            'FOOD_PREFERENCE_OTHERS' => ['Prefers spicy food', 'Prefers non-spicy food'],
            'ALLERGY' => ['Peanuts', 'Seafood', 'Dust', 'Pollen', 'Dairy'],
            'PHYSICAL_DISABILITY' => ['Slight Limp', 'Hearing Issue', 'Vision Issue'],
            'DIET_RESTRICTION' => ['Vegetarian', 'Halal', 'Kosher', 'Vegan']
        ];

        for ($i = 0; $i < 20; $i++) {
            $numChildren = $faker->numberBetween(0, 3);
            $childrenAges = $numChildren > 0
                ? implode(', ', $faker->randomElements(range(1, 18), $numChildren))
                : null;

            // Generate financial data
            $remainingLoan = $faker->randomFloat(2, 0, 24); // decimal value between 0-24 months
            $monthlySalary = $faker->randomFloat(2, 500, 1000); // monthly salary between 500-1000
            $costOfMaid = round($remainingLoan * $monthlySalary, 2);

            // Generate employment history
            $employmentHistory = $this->generateEmploymentHistory($faker);
            $experienceYears = $this->calculateExperienceYears($employmentHistory);

            // Ensure first 5 maids are available for quotation seeder
            $status = $i < 5 ? 'available' : $faker->randomElement($statuses);

            $maid = Maid::create([
                // Profile (Section A1)
                'name' => $faker->name(),
                'date_of_birth' => $faker->dateTimeBetween('-40 years', '-20 years')->format('Y-m-d'),
                'place_of_birth' => $faker->city(),
                'height' => $faker->numberBetween(145, 175),
                'weight' => $faker->numberBetween(45, 80),
                'country_id' => $faker->randomElement($countryIds),
                'address' => $faker->address(),
                'passport_number' => strtoupper($faker->bothify('??######')),
                'repatriation_port_airport' => $faker->city() . ' International Airport',
                'contact_number_home_country' => $faker->phoneNumber(),
                'religion_id' => $faker->randomElement($religionIds),
                'education_level_id' => $faker->randomElement($educationLevelIds),
                'marital_status' => $faker->randomElement($maritalStatuses),
                'number_of_siblings' => $faker->numberBetween(0, 6),
                'number_of_children' => $numChildren,
                'children_ages' => $childrenAges,

                // Medical History (Section A2) - removed as now using attributes table

                // Others (Section A3)
                'rest_days_per_month' => $faker->randomElement([2, 4, 6]),
                'other_remarks' => $faker->optional()->sentence(),
                'status' => $status,
                'supplier_id' => !empty($supplierIds) ? $faker->randomElement($supplierIds) : null,
                'remaining_loan' => $remainingLoan,
                'monthly_salary' => $monthlySalary,
                'cost_of_maid' => $costOfMaid,

                // Skills Assessment (Section B)
                'skills_assessment_singapore' => $this->generateSkillsSingapore($faker),
                'skills_assessment_overseas' => $this->generateSkillsOverseas($faker),

                // Employment History (Section C)
                'employment_history' => $employmentHistory,
                'singapore_experience' => $faker->boolean(30),
                'experience_years' => $experienceYears,
                'employment_feedback' => $faker->optional()->sentence(),

                // Availability (Section E)
                'availability_remarks' => $faker->randomElement(['Face-to-face', 'Video call', 'Telephone', 'Face-to-face, Video call']),

                // Evaluation Methods (Section B)
                'eval_declaration_no_eval' => $faker->boolean(20),
                'eval_sg_interview' => $faker->boolean(60),
                'eval_sg_phone' => $faker->boolean(40),
                'eval_sg_video' => $faker->boolean(50),
                'eval_sg_in_person' => $faker->boolean(30),
                'eval_sg_in_person_observed' => $faker->boolean(20),
                'eval_overseas_interview' => $faker->boolean(40),
                'eval_overseas_name' => $faker->optional()->company(),
                'eval_overseas_cert' => $faker->optional()->randomElement(['ISO9001', 'Audited periodically']),
                'eval_overseas_phone' => $faker->boolean(30),
                'eval_overseas_video' => $faker->boolean(40),
                'eval_overseas_in_person' => $faker->boolean(20),
                'eval_overseas_in_person_observed' => $faker->boolean(15),
            ]);

            // Create attributes for medical history
            foreach ($attributeCategories as $category => $options) {
                // For ILLNESS, randomly select 0-3 items
                if ($category === 'ILLNESS') {
                    if ($faker->boolean(40)) { // 40% chance to have illness
                        $count = $faker->numberBetween(1, 2);
                        $selected = $faker->randomElements($options, $count);
                        foreach ($selected as $attr) {
                            MaidAttribute::create([
                                'maid_id' => $maid->id,
                                'attribute_category' => $category,
                                'attribute_name' => $attr,
                            ]);
                        }
                    }
                }
                // For ILLNESS_OTHERS, add text if has illness
                elseif ($category === 'ILLNESS_OTHERS') {
                    if ($faker->boolean(20)) {
                        MaidAttribute::create([
                            'maid_id' => $maid->id,
                            'attribute_category' => $category,
                            'attribute_name' => $faker->randomElement($options),
                        ]);
                    }
                }
                // For FOOD_PREFERENCE, select 0-2 items
                elseif ($category === 'FOOD_PREFERENCE') {
                    if ($faker->boolean(30)) {
                        $count = $faker->numberBetween(1, 2);
                        $selected = $faker->randomElements($options, $count);
                        foreach ($selected as $attr) {
                            MaidAttribute::create([
                                'maid_id' => $maid->id,
                                'attribute_category' => $category,
                                'attribute_name' => $attr,
                            ]);
                        }
                    }
                }
                // For other categories
                else {
                    if ($faker->boolean(30)) {
                        $count = $faker->numberBetween(1, min(2, count($options)));
                        $selected = $faker->randomElements($options, $count);
                        foreach ($selected as $attr) {
                            MaidAttribute::create([
                                'maid_id' => $maid->id,
                                'attribute_category' => $category,
                                'attribute_name' => $attr,
                            ]);
                        }
                    }
                }
            }
        }
    }

    private function generateSkillsSingapore($faker): array
    {
        $areas = ['Care of infants/children', 'Care of elderly', 'Care of disabled', 'General housework', 'Cooking', 'Language abilities (spoken)', 'Other skills'];
        $skills = [];
        foreach ($areas as $area) {
            $hasExperience = $faker->boolean(60);
            $skills[] = [
                'area' => $area,
                'willingness' => $faker->randomElement(['Yes', 'No']),
                'experience' => $hasExperience ? 'Yes, ' . $faker->numberBetween(1, 10) . ' years' : 'No',
                'assesment_observation' => $faker->optional()->sentence(),
            ];
        }
        return $skills;
    }

    private function generateSkillsOverseas($faker): array
    {
        $areas = ['Care of infants/children', 'Care of elderly', 'Care of disabled', 'General housework', 'Cooking', 'Language abilities (spoken)', 'Other skills'];
        $skills = [];
        foreach ($areas as $area) {
            $hasExperience = $faker->boolean(60);
            $skills[] = [
                'area' => $area,
                'willingness' => $faker->randomElement(['Yes', 'No']),
                'experience' => $hasExperience ? 'Yes, ' . $faker->numberBetween(1, 10) . ' years' : 'No',
                'assesment_observation' => $faker->optional()->sentence(),
            ];
        }
        return $skills;
    }

    private function generateEmploymentHistory($faker): array
    {
        $count = $faker->numberBetween(0, 3);
        $history = [];
        for ($i = 0; $i < $count; $i++) {
            $startYear = $faker->numberBetween(2010, 2020);
            $endYear = $faker->numberBetween($startYear, $startYear + 5);
            $history[] = [
                'country' => $faker->country(),
                'employer' => $faker->name(),
                'period' => $startYear . '-' . $endYear,
                'duties' => $faker->sentence(),
                'remarks' => $faker->optional()->sentence(),
            ];
        }
        return $history;
    }

    private function calculateExperienceYears($employmentHistory): int
    {
        $totalYears = 0;
        foreach ($employmentHistory as $emp) {
            if (isset($emp['period'])) {
                preg_match('/(\d{4})\s*-\s*(\d{4})/', $emp['period'], $matches);
                if (count($matches) === 3) {
                    $startYear = (int) $matches[1];
                    $endYear = (int) $matches[2];
                    if ($endYear >= $startYear) {
                        $totalYears += ($endYear - $startYear + 1);
                    }
                }
            }
        }
        return $totalYears;
    }
}
