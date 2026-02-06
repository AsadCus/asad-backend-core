<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PrivateEnquiry;

class PrivateEnquirySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PrivateEnquiry::insert([
            [
                'full_name' => 'Ahmad Bin Ali',
                'contact_number' => '0123456789',
                'email' => 'ahmad.ali@example.com',
                'passport_expiry_date' => '2027-12-31',
                'departure_date' => '2026-03-01',
                'return_date' => '2026-03-15',
                'no_of_pax' => 4,
                'no_of_children' => 2,
                'airline' => 'Saudi Airlines',
                'class' => 'Economy',
                'require_mutawif' => true,
                'require_umrah_course' => false,
                'require_umrah_official' => true,
                'makkah_or_madinah_first' => 'Makkah',
                'no_of_nights_makkah' => '5',
                'hotel_makkah' => 'Hilton Suites',
                'meals_makkah' => 'Breakfast',
                'no_of_nights_madinah' => '4',
                'hotel_madinah' => 'Anwar Al Madinah',
                'meals_madinah' => 'Half Board',
                'land_transfer' => 'Bus',
                'add_on_speed_train' => true,
                'require_meet_greet' => false,
                'require_mutawiffah_ustazah_rawdah' => false,
                'madinah_tour_with_mutawif' => true,
                'makkah_tour_with_mutawif' => false,
                'has_chronic_disease' => false,
                'chronic_disease_details' => null,
                'need_wheelchair' => 'No',
                'other_remarks' => 'Vegetarian meal preferred',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'full_name' => 'Siti Aminah',
                'contact_number' => '0198765432',
                'email' => 'siti.aminah@example.com',
                'passport_expiry_date' => '2028-05-20',
                'departure_date' => '2026-04-10',
                'return_date' => '2026-04-25',
                'no_of_pax' => 2,
                'no_of_children' => 0,
                'airline' => 'Emirates',
                'class' => 'Business',
                'require_mutawif' => false,
                'require_umrah_course' => true,
                'require_umrah_official' => false,
                'makkah_or_madinah_first' => 'Madinah',
                'no_of_nights_makkah' => '3',
                'hotel_makkah' => 'Swissotel',
                'meals_makkah' => 'Full Board',
                'no_of_nights_madinah' => '5',
                'hotel_madinah' => 'Pullman Zamzam',
                'meals_madinah' => 'Breakfast',
                'land_transfer' => 'Private Car',
                'add_on_speed_train' => false,
                'require_meet_greet' => true,
                'require_mutawiffah_ustazah_rawdah' => true,
                'madinah_tour_with_mutawif' => false,
                'makkah_tour_with_mutawif' => true,
                'has_chronic_disease' => true,
                'chronic_disease_details' => 'Diabetes',
                'need_wheelchair' => 'Yes',
                'other_remarks' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
