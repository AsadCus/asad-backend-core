<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Maid;
use App\Models\Country;
use App\Models\Religion;
use App\Models\EducationLevel;

class MaidBiodataTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get or create reference data
        $country = Country::firstOrCreate(
            ['code' => 'ID'],
            ['name' => 'Indonesia', 'adjective' => 'Indonesian']
        );

        $religion = Religion::firstOrCreate(['name' => 'Islam']);

        $educationLevel = EducationLevel::firstOrCreate(['name' => 'Senior High School']);

        // Create sample maid data
        $maid = Maid::create([
            // A1: Personal Profile
            'bio_code' => 'MBC-2025-0001',
            'name' => 'KURNIASARI',
            'date_of_birth' => '1999-12-15',
            'place_of_birth' => 'TEMPASAN',
            'height' => 152,
            'weight' => 45,
            'country_id' => $country->id,
            'address' => 'Lajut, Praya Tengah, Central Lombok Regency, West Nusa Tenggara, Indonesia',
            'repatriation_port_airport' => 'LOMBOK',
            'contact_number_home_country' => '+62812345678',
            'religion_id' => $religion->id,
            'education_level_id' => $educationLevel->id,
            'marital_status' => 'Single',
            'number_of_siblings' => 3,
            'number_of_children' => 0,
            'status' => 'available',

            // A2: Medical History
            'allergies' => 'No',
            'physical_disabilities' => 'No',
            'dietary_restrictions' => 'No pork',
            'food_preferences' => 'Halal',
            'mental_illness' => false,
            'tuberculosis' => false,
            'epilepsy' => false,
            'malaria' => false,
            'asthma' => false,
            'operations' => false,
            'diabetes' => false,
            'hypertension' => false,

            // A3: Others
            'rest_days_per_month' => 4,
            'other_remarks' => 'Willing to learn new skills. Good with children.',

            // Skills Assessment
            'skills_assessment' => [
                'care_of_baby' => [
                    'willingness' => 'Yes',
                    'experience' => 'Yes'
                ],
                'care_of_young_children' => [
                    'willingness' => 'Yes',
                    'experience' => 'Yes'
                ],
                'care_of_elderly' => [
                    'willingness' => 'Yes',
                    'experience' => 'No'
                ],
                'care_of_disabled' => [
                    'willingness' => 'Yes',
                    'experience' => 'No'
                ],
                'general_housework' => [
                    'willingness' => 'Yes',
                    'experience' => 'Yes'
                ],
                'cooking' => [
                    'willingness' => 'Yes',
                    'experience' => 'Yes'
                ],
                'language_abilities' => [
                    'willingness' => 'Yes',
                    'experience' => 'No'
                ],
                'other_skills' => [
                    'willingness' => 'Yes',
                    'experience' => 'No'
                ],
            ],

            'skills_assessment_numeric' => [
                'care_of_baby' => 2,
                'care_of_young_children' => 3,
                'care_of_elderly' => 0,
                'care_of_disabled' => 0,
                'general_housework' => 5,
                'cooking' => 4,
                'language_abilities' => 0,
                'other_skills' => 0,
            ],

            'skills_assessment_qualitative' => [
                'care_of_baby' => '5',
                'care_of_young_children' => '6',
                'care_of_elderly' => 'WILLING',
                'care_of_disabled' => 'WILLING',
                'general_housework' => '7',
                'cooking' => '6',
                'language_abilities' => 'WILLING',
                'other_skills' => 'WILLING',
            ],

            // Employment History
            'employment_history' => [
                [
                    'country' => 'INDONESIA',
                    'date_from' => '2022',
                    'date_to' => '2024',
                    'employer' => 'JAVANESE FAMILY',
                    'work_duties' => 'TOOK CARE OF 2 CHILDREN (1-8 YEAR OLD), SIBLING ALSO ASSIST BUYING GROCERIES, LAUNDRY, COOKING. SHE ALSO ASSIST BUYING GROCERIES'
                ],
                [
                    'country' => 'INDONESIA',
                    'date_from' => '2020',
                    'date_to' => '2022',
                    'employer' => 'LOCAL FAMILY',
                    'work_duties' => 'GENERAL HOUSEWORK, COOKING, CLEANING'
                ],
            ],

            'singapore_experience' => false,

            // Employment Feedback
            'employment_feedback' => [
                [
                    'employer' => 'JAVANESE FAMILY',
                    'feedback' => 'PLEASANT AND CHEERFUL. GOOD FOR CHILD MINDING.'
                ],
                [
                    'employer' => 'LOCAL FAMILY',
                    'feedback' => 'Excellent worker, very responsible and reliable.'
                ],
            ],

            // Evaluation Methods
            'eval_declaration_no_eval' => false,
            'eval_sg_interview' => false,
            'eval_sg_phone' => true,
            'eval_sg_video' => false,
            'eval_sg_in_person' => false,
            'eval_sg_in_person_observed' => false,

            // Interview Availability
            'interview_not_available' => false,
            'interview_by_phone' => true,
            'interview_by_video' => true,
            'interview_in_person' => false,
        ]);

        $this->command->info("Sample maid data created with ID: {$maid->id}");
        $this->command->info("Bio Code: {$maid->bio_code}");
        $this->command->info("Name: {$maid->name}");
    }
}
