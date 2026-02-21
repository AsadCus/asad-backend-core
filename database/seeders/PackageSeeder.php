<?php

namespace Database\Seeders;

use App\Helpers\NumberGenerator;
use App\Models\Package;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $package1 = Package::create([
            'group_number' => NumberGenerator::generate('package'),
            'name' => 'Umrah Economy 14 Days',
            'status' => 'open',
            'price_single' => 4000.00,
            'price_double' => 3200.00,
            'price_triple' => 2800.00,
            'price_quad' => 2500.00,
            'child_with_bed_price' => 2200.00,
            'child_no_bed_price' => 1800.00,
            'infant_price' => 500.00,
            'airline' => 'Saudi Airlines',
            'pnr' => 'ABC123',
            'departure_date' => '2026-01-15',
            'arrival_date' => '2026-01-29',
            'total_seats' => 45,
            'seats_left' => 45,
            'visa_type' => 'Umrah Visa',
            'vehicle_type' => 'Bus',
            'ticket_type' => 'Economy',
            'included' => "Flight Tickets\nHotel Accommodation\nVisa Processing\nGround Transport\nMeals (Breakfast & Dinner)\nZiyarah Tours\nTravel Insurance",
            'not_included' => "Personal Expenses\nLaundry\nTips & Gratuities",
            'offer' => "Early bird discount 10% off for bookings before Dec 2025\nFree airport transfer",
            'remarks' => 'Economy package with comfortable 4-star hotels near Haram.',
        ]);

        $package1->accommodations()->createMany([
            ['location' => 'Mekkah', 'hotel_name' => 'Elaf Ajyad Hotel', 'type_of_meal' => 'Breakfast & Dinner', 'check_in' => '2026-01-16', 'check_out' => '2026-01-23'],
            ['location' => 'Madinah', 'hotel_name' => 'Dar Al Taqwa Hotel', 'type_of_meal' => 'Breakfast & Dinner', 'check_in' => '2026-01-23', 'check_out' => '2026-01-28'],
        ]);

        $package2 = Package::create([
            'group_number' => NumberGenerator::generate('package'),
            'name' => 'Umrah Premium 10 Days',
            'status' => 'open',
            'price_single' => 6000.00,
            'price_double' => 4800.00,
            'price_triple' => 4000.00,
            'price_quad' => 3500.00,
            'child_with_bed_price' => 3000.00,
            'child_no_bed_price' => 2500.00,
            'infant_price' => 800.00,
            'airline' => 'Emirates',
            'pnr' => 'EMR456',
            'departure_date' => '2026-02-10',
            'arrival_date' => '2026-02-20',
            'total_seats' => 30,
            'seats_left' => 30,
            'visa_type' => 'Umrah Visa',
            'vehicle_type' => 'VIP Van',
            'ticket_type' => 'Business',
            'included' => "Flight Tickets (Business Class)\n5-Star Hotel\nVisa Processing\nVIP Transport\nAll Meals\nPrivate Ziyarah Tours\nTravel Insurance\nLaundry Service\nSim Card",
            'not_included' => "Personal Expenses\nTips & Gratuities",
            'offer' => "Complimentary spa session at hotel\nFree upgrade to suite (subject to availability)",
            'remarks' => 'Premium package with 5-star hotels walking distance to Haram. VIP transport and dedicated guide.',
        ]);

        $package2->accommodations()->createMany([
            ['location' => 'Mekkah', 'hotel_name' => 'Fairmont Makkah Clock Royal Tower', 'type_of_meal' => 'Full Board', 'check_in' => '2026-02-11', 'check_out' => '2026-02-16'],
            ['location' => 'Madinah', 'hotel_name' => 'The Oberoi Madina', 'type_of_meal' => 'Full Board', 'check_in' => '2026-02-16', 'check_out' => '2026-02-19'],
        ]);

        $package3 = Package::create([
            'group_number' => NumberGenerator::generate('package'),
            'name' => 'Hajj Standard 2026',
            'status' => 'open',
            'price_single' => 13000.00,
            'price_double' => 10500.00,
            'price_triple' => 9000.00,
            'price_quad' => 8000.00,
            'child_with_bed_price' => 7000.00,
            'child_no_bed_price' => 5500.00,
            'infant_price' => 2000.00,
            'airline' => 'Saudi Airlines',
            'pnr' => 'HJJ789',
            'departure_date' => '2026-06-01',
            'arrival_date' => '2026-06-21',
            'total_seats' => 50,
            'seats_left' => 50,
            'visa_type' => 'Hajj Visa',
            'vehicle_type' => 'Bus',
            'ticket_type' => 'Economy',
            'included' => "Flight Tickets\nHotel Accommodation\nHajj Visa\nGround Transport\nAll Meals\nZiyarah Tours\nTravel Insurance\nHajj Kit\nPre-Hajj Training",
            'not_included' => "Personal Expenses\nLaundry\nTips & Gratuities\nQurbani (Optional)",
            'offer' => "Group discount: 5% off for groups of 10 or more\nFree Hajj training workshop",
            'remarks' => 'Standard Hajj package with comfortable accommodation in Aziziyah and Mina tents.',
        ]);

        $package3->accommodations()->createMany([
            ['location' => 'Mekkah', 'hotel_name' => 'Hilton Makkah Convention', 'type_of_meal' => 'Full Board', 'check_in' => '2026-06-02', 'check_out' => '2026-06-10'],
            ['location' => 'Madinah', 'hotel_name' => 'Pullman Zamzam Madina', 'type_of_meal' => 'Full Board', 'check_in' => '2026-06-14', 'check_out' => '2026-06-20'],
            ['location' => 'Taif', 'hotel_name' => 'InterContinental Taif', 'type_of_meal' => 'Breakfast', 'check_in' => '2026-06-10', 'check_out' => '2026-06-14'],
        ]);

        $package4 = Package::create([
            'group_number' => NumberGenerator::generate('package'),
            'name' => 'Umrah Ramadan Special',
            'status' => 'open',
            'price_single' => 7500.00,
            'price_double' => 6000.00,
            'price_triple' => 5200.00,
            'price_quad' => 4500.00,
            'child_with_bed_price' => 4000.00,
            'child_no_bed_price' => 3200.00,
            'infant_price' => 1000.00,
            'airline' => 'Qatar Airways',
            'pnr' => 'QTR321',
            'departure_date' => '2026-03-01',
            'arrival_date' => '2026-03-15',
            'total_seats' => 40,
            'seats_left' => 40,
            'visa_type' => 'Umrah Visa',
            'vehicle_type' => 'Bus',
            'ticket_type' => 'Economy',
            'included' => "Flight Tickets\nHotel near Haram\nVisa Processing\nGround Transport\nIftar & Suhoor\nZiyarah Tours\nTravel Insurance",
            'not_included' => "Personal Expenses\nLaundry\nTips & Gratuities",
            'offer' => 'Special Ramadan gift pack for all travellers',
            'remarks' => 'Special Ramadan package with premium hotel facing Haram. Extended stay for last 10 nights of Ramadan.',
        ]);

        $package4->accommodations()->createMany([
            ['location' => 'Mekkah', 'hotel_name' => 'Swissotel Makkah', 'type_of_meal' => 'Iftar & Suhoor', 'check_in' => '2026-03-02', 'check_out' => '2026-03-12'],
            ['location' => 'Madinah', 'hotel_name' => 'Anwar Al Madinah Movenpick', 'type_of_meal' => 'Iftar & Suhoor', 'check_in' => '2026-03-12', 'check_out' => '2026-03-14'],
        ]);

        $package5 = Package::create([
            'group_number' => NumberGenerator::generate('package'),
            'name' => 'Umrah Budget 7 Days',
            'status' => 'closed',
            'price_single' => 3000.00,
            'price_double' => 2200.00,
            'price_triple' => 1800.00,
            'price_quad' => 1500.00,
            'child_with_bed_price' => 1300.00,
            'child_no_bed_price' => 1000.00,
            'infant_price' => 400.00,
            'airline' => 'AirAsia X',
            'pnr' => 'AAX654',
            'departure_date' => '2025-12-01',
            'arrival_date' => '2025-12-08',
            'total_seats' => 35,
            'seats_left' => 0,
            'visa_type' => 'Umrah Visa',
            'vehicle_type' => 'Bus',
            'ticket_type' => 'Economy',
            'included' => "Flight Tickets\nHotel Accommodation\nVisa Processing\nGround Transport",
            'not_included' => "Meals\nPersonal Expenses\nLaundry\nTravel Insurance",
            'offer' => null,
            'remarks' => 'Budget-friendly short Umrah package. This package is now closed.',
        ]);

        $package5->accommodations()->createMany([
            ['location' => 'Mekkah', 'hotel_name' => 'Al Marwa Rayhaan', 'type_of_meal' => 'No Meals', 'check_in' => '2025-12-02', 'check_out' => '2025-12-05'],
            ['location' => 'Madinah', 'hotel_name' => 'Grand Mercure Madinah', 'type_of_meal' => 'No Meals', 'check_in' => '2025-12-05', 'check_out' => '2025-12-07'],
        ]);
    }
}
