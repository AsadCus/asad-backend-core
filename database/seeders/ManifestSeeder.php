<?php

namespace Database\Seeders;

use App\Models\Manifest;
use App\Models\Package;
use Illuminate\Database\Seeder;

class ManifestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $package1 = Package::where('name', 'Umrah Economy 14 Days')->first();
        $package2 = Package::where('name', 'Umrah Premium 10 Days')->first();

        if (!$package1 || !$package2) {
            $this->command->warn('Packages not found. Please run PackageSeeder first.');
            return;
        }

        // Manifest 1 for Economy package
        $manifest1 = Manifest::create([
            'package_id' => $package1->id,
            'reference_number' => 'MNF-2026-001',
            'company_address' => '123 Travel House, London, UK',
            'company_phone' => '+44 20 1234 5678',
            'departure_date' => '2026-01-15',
            'return_date' => '2026-01-29',
            'duration' => '14 Days / 13 Nights',
            'makkah_hotel' => 'Al Shohada Hotel',
            'makkah_check_in' => '2026-01-16',
            'makkah_check_out' => '2026-01-23',
            'madinah_hotel' => 'Dar Al Taqwa Hotel',
            'madinah_check_in' => '2026-01-23',
            'madinah_check_out' => '2026-01-28',
            'flight_details' => json_encode([
                ['type' => 'Departure', 'airline' => 'Saudi Airlines', 'flight_no' => 'SV-116', 'date' => '2026-01-15', 'route' => 'LHR → JED'],
                ['type' => 'Return', 'airline' => 'Saudi Airlines', 'flight_no' => 'SV-117', 'date' => '2026-01-29', 'route' => 'MED → LHR'],
            ]),
            'notes' => 'Group of families from East London community. Special dietary requirements for 2 travelers.',
            'first_meal' => 'Dinner',
            'last_meal' => 'Breakfast',
            'status' => 'confirmed',
        ]);

        $manifest1->travelers()->createMany([
            [
                'sn' => 1,
                'name_as_per_passport' => 'AHMED KHAN',
                'relationship' => 'Self',
                'passport_no' => 'GB1234567',
                'room_no' => '101',
                'room_type' => 'Quad',
                'bed_type' => 'Single',
                'date_of_birth' => '1985-03-12',
                'age' => 41,
                'meal' => 'Breakfast',
                'total_cost' => 2500.00,
                'total_paid' => 2500.00,
                'outstanding_amount' => 0.00,
            ],
            [
                'sn' => 2,
                'name_as_per_passport' => 'FATIMA KHAN',
                'relationship' => 'Wife',
                'passport_no' => 'GB2345678',
                'room_no' => '101',
                'room_type' => 'Quad',
                'bed_type' => 'Single',
                'date_of_birth' => '1988-07-22',
                'age' => 37,
                'meal' => 'Breakfast',
                'total_cost' => 2500.00,
                'total_paid' => 2000.00,
                'outstanding_amount' => 500.00,
            ],
            [
                'sn' => 3,
                'name_as_per_passport' => 'YUSUF KHAN',
                'relationship' => 'Son',
                'passport_no' => 'GB3456789',
                'room_no' => '101',
                'room_type' => 'Quad',
                'bed_type' => 'Single',
                'date_of_birth' => '2010-11-05',
                'age' => 15,
                'meal' => 'Breakfast',
                'total_cost' => 2200.00,
                'total_paid' => 2200.00,
                'outstanding_amount' => 0.00,
            ],
            [
                'sn' => 4,
                'name_as_per_passport' => 'IBRAHIM PATEL',
                'relationship' => 'Self',
                'passport_no' => 'GB4567890',
                'room_no' => '102',
                'room_type' => 'Double',
                'bed_type' => 'Double',
                'date_of_birth' => '1975-01-18',
                'age' => 51,
                'meal' => 'Breakfast',
                'total_cost' => 3200.00,
                'total_paid' => 3200.00,
                'outstanding_amount' => 0.00,
            ],
            [
                'sn' => 5,
                'name_as_per_passport' => 'KHADIJA PATEL',
                'relationship' => 'Wife',
                'passport_no' => 'GB5678901',
                'room_no' => '102',
                'room_type' => 'Double',
                'bed_type' => 'Double',
                'date_of_birth' => '1978-09-30',
                'age' => 47,
                'meal' => 'Breakfast',
                'total_cost' => 3200.00,
                'total_paid' => 1600.00,
                'outstanding_amount' => 1600.00,
            ],
        ]);

        $manifest1->rooms()->createMany([
            ['location' => 'Makkah', 'room_number' => '101', 'room_type' => 'Quad', 'bed_type' => 'Single', 'capacity' => 4],
            ['location' => 'Makkah', 'room_number' => '102', 'room_type' => 'Double', 'bed_type' => 'Double', 'capacity' => 2],
            ['location' => 'Madinah', 'room_number' => '201', 'room_type' => 'Quad', 'bed_type' => 'Single', 'capacity' => 4],
            ['location' => 'Madinah', 'room_number' => '202', 'room_type' => 'Double', 'bed_type' => 'Double', 'capacity' => 2],
        ]);

        $manifest1->payments()->createMany([
            ['traveler_name' => 'AHMED KHAN', 'description' => 'Full payment - Quad room', 'amount' => 2500.00, 'paid_amount' => 2500.00, 'outstanding_amount' => 0.00, 'payment_date' => '2025-12-01', 'status' => 'paid'],
            ['traveler_name' => 'FATIMA KHAN', 'description' => 'Partial payment - Quad room', 'amount' => 2500.00, 'paid_amount' => 2000.00, 'outstanding_amount' => 500.00, 'payment_date' => '2025-12-15', 'status' => 'partial'],
            ['traveler_name' => 'YUSUF KHAN', 'description' => 'Full payment - Child with bed', 'amount' => 2200.00, 'paid_amount' => 2200.00, 'outstanding_amount' => 0.00, 'payment_date' => '2025-12-01', 'status' => 'paid'],
            ['traveler_name' => 'IBRAHIM PATEL', 'description' => 'Full payment - Double room', 'amount' => 3200.00, 'paid_amount' => 3200.00, 'outstanding_amount' => 0.00, 'payment_date' => '2025-11-20', 'status' => 'paid'],
            ['traveler_name' => 'KHADIJA PATEL', 'description' => 'Partial payment - Double room', 'amount' => 3200.00, 'paid_amount' => 1600.00, 'outstanding_amount' => 1600.00, 'payment_date' => '2025-12-10', 'status' => 'partial'],
        ]);

        // Manifest 2 for Premium package
        $manifest2 = Manifest::create([
            'package_id' => $package2->id,
            'reference_number' => 'MNF-2026-002',
            'company_address' => '123 Travel House, London, UK',
            'company_phone' => '+44 20 1234 5678',
            'departure_date' => '2026-02-10',
            'return_date' => '2026-02-20',
            'duration' => '10 Days / 9 Nights',
            'makkah_hotel' => 'Fairmont Makkah Clock Royal Tower',
            'makkah_check_in' => '2026-02-11',
            'makkah_check_out' => '2026-02-16',
            'madinah_hotel' => 'The Oberoi Madinah',
            'madinah_check_in' => '2026-02-16',
            'madinah_check_out' => '2026-02-19',
            'flight_details' => json_encode([
                ['type' => 'Departure', 'airline' => 'British Airways', 'flight_no' => 'BA-263', 'date' => '2026-02-10', 'route' => 'LHR → JED'],
                ['type' => 'Return', 'airline' => 'British Airways', 'flight_no' => 'BA-264', 'date' => '2026-02-20', 'route' => 'MED → LHR'],
            ]),
            'notes' => 'VIP group. All rooms to be premium suites.',
            'first_meal' => 'Dinner',
            'last_meal' => 'Breakfast',
            'status' => 'draft',
        ]);

        $manifest2->travelers()->createMany([
            [
                'sn' => 1,
                'name_as_per_passport' => 'MOHAMMAD ALI RAZA',
                'relationship' => 'Self',
                'passport_no' => 'GB6789012',
                'room_no' => '501',
                'room_type' => 'Double',
                'bed_type' => 'Double',
                'date_of_birth' => '1970-05-14',
                'age' => 55,
                'meal' => 'Dinner',
                'total_cost' => 4800.00,
                'total_paid' => 4800.00,
                'outstanding_amount' => 0.00,
            ],
            [
                'sn' => 2,
                'name_as_per_passport' => 'SARAH ALI RAZA',
                'relationship' => 'Wife',
                'passport_no' => 'GB7890123',
                'room_no' => '501',
                'room_type' => 'Double',
                'bed_type' => 'Double',
                'date_of_birth' => '1972-08-25',
                'age' => 53,
                'meal' => 'Dinner',
                'total_cost' => 4800.00,
                'total_paid' => 2400.00,
                'outstanding_amount' => 2400.00,
            ],
        ]);
    }
}
