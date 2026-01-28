<?php

namespace Database\Seeders;

use App\Models\GeneralEnquiry;
use Illuminate\Database\Seeder;

class GeneralEnquirySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        GeneralEnquiry::create([
            'full_name' => 'John Smith',
            'mobile' => '+1234567890',
            'email' => 'john.smith@example.com',
            'preferred_destinations' => 'Paris, London, Amsterdam',
            'preferred_travelling_date' => '2026-05-15',
            'no_of_adults' => 2,
            'no_of_children' => 1,
            'requires_mobility_assistance' => null,
        ]);

        GeneralEnquiry::create([
            'full_name' => 'Sarah Johnson',
            'mobile' => '+9876543210',
            'email' => 'sarah.johnson@example.com',
            'preferred_destinations' => 'Tokyo, Bangkok, Singapore',
            'preferred_travelling_date' => '2026-06-20',
            'no_of_adults' => 3,
            'no_of_children' => 2,
            'requires_mobility_assistance' => null,
        ]);

        GeneralEnquiry::create([
            'full_name' => 'Michael Brown',
            'mobile' => '+1122334455',
            'email' => 'michael.brown@example.com',
            'preferred_destinations' => 'Sydney, Melbourne, Brisbane',
            'preferred_travelling_date' => '2026-07-10',
            'no_of_adults' => 2,
            'no_of_children' => 0,
            'requires_mobility_assistance' => 'Yes, wheelchair accessibility required',
        ]);

        GeneralEnquiry::create([
            'full_name' => 'Emily White',
            'mobile' => '+5566778899',
            'email' => 'emily.white@example.com',
            'preferred_destinations' => 'Barcelona, Madrid, Lisbon',
            'preferred_travelling_date' => '2026-08-05',
            'no_of_adults' => 1,
            'no_of_children' => 0,
            'requires_mobility_assistance' => null,
        ]);

        GeneralEnquiry::create([
            'full_name' => 'David Martinez',
            'mobile' => '+4433221100',
            'email' => 'david.martinez@example.com',
            'preferred_destinations' => 'New York, Los Angeles, Miami',
            'preferred_travelling_date' => '2026-09-12',
            'no_of_adults' => 2,
            'no_of_children' => 2,
            'requires_mobility_assistance' => null,
        ]);
    }
}
