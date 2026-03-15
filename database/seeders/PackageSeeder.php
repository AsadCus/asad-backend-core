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
            'package_number' => NumberGenerator::generate('package'),
            'name' => 'Umrah Economy 14 Days',
            'status' => 'open',
            'price_single' => 4000.00,
            'price_double' => 3200.00,
            'price_triple' => 2800.00,
            'price_quad' => 2500.00,
            'child_with_bed_price' => 2200.00,
            'child_no_bed_price' => 1800.00,
            'infant_price' => 500.00,
            'departure_date' => '2026-01-15',
            'return_date' => '2026-01-29',
            'total_seats' => 45,
            'seats_left' => 45,
            'visa_type' => 'Umrah Visa',
            'vehicle_type' => 'Bus',
            'vehicle_driver_name' => 'Ahmad Zaki',
            'vehicle_driver_contact_number' => '0123456789',
            'ticket_type' => 'two_way',
            'included' => "Flight Tickets\nHotel Accommodation\nVisa Processing\nGround Transport\nMeals \nZiyarah Tours\nTravel Insurance",
            'not_included' => "Personal Expenses\nLaundry\nTips & Gratuities",
            'offer' => "Early bird discount 10% off for bookings before Dec 2025\nFree airport transfer",
            'remarks' => 'Economy package with comfortable 4-star hotels near Haram.',
        ]);

        $package1->accommodations()->createMany([
            ['location' => 'Makkah', 'hotel_name' => 'Elaf Ajyad Hotel', 'type_of_meal' => 'Half Board', 'check_in' => '2026-01-16', 'check_out' => '2026-01-23'],
            ['location' => 'Madinah', 'hotel_name' => 'Dar Al Taqwa Hotel', 'type_of_meal' => 'Half Board', 'check_in' => '2026-01-23', 'check_out' => '2026-01-28'],
        ]);

        $package1->flights()->createMany([
            [
                'from' => 'KUL',
                'to' => 'JED',
                'description' => 'Outbound',
                'airline' => 'Saudi Airlines',
                'pnr' => 'ABC123',
                'departure_datetime' => '2026-01-15 09:00:00',
                'arrival_datetime' => '2026-01-15 15:30:00',
                'sort_order' => 1,
            ],
            [
                'from' => 'MED',
                'to' => 'KUL',
                'description' => 'Return',
                'airline' => 'Saudi Airlines',
                'pnr' => 'ABC123',
                'departure_datetime' => '2026-01-29 10:00:00',
                'arrival_datetime' => '2026-01-29 22:30:00',
                'sort_order' => 2,
            ],
        ]);

        $package1->trainTickets()->createMany([
            [
                'from' => 'Makkah',
                'to' => 'Madinah',
                'travel_date' => '2026-01-23',
                'travel_time' => '09:30',
                'remarks' => 'High-speed train (Group A)',
                'sort_order' => 1,
            ],
            [
                'from' => 'Madinah',
                'to' => 'Makkah',
                'travel_date' => '2026-01-27',
                'travel_time' => '16:15',
                'remarks' => 'High-speed train (Return)',
                'sort_order' => 2,
            ],
        ]);

        $package1->transportationPlans()->createMany([
            [
                'from' => 'Jeddah Airport',
                'to' => 'Makkah Hotel',
                'travel_date' => '2026-01-15',
                'travel_time' => '17:30',
                'remarks' => 'Group coach transfer',
                'sort_order' => 1,
            ],
            [
                'from' => 'Madinah Hotel',
                'to' => 'Madinah Airport',
                'travel_date' => '2026-01-29',
                'travel_time' => '06:00',
                'remarks' => 'Check-in assistance included',
                'sort_order' => 2,
            ],
        ]);

        $package1->rawdahTasreehs()->createMany([
            [
                'date' => '2026-01-25',
                'women_passengers' => 18,
                'women_time' => '10:00',
                'men_passengers' => 20,
                'men_time' => '11:00',
                'remarks' => 'Batch 1',
                'sort_order' => 1,
            ],
            [
                'date' => '2026-01-26',
                'women_passengers' => 12,
                'women_time' => '14:00',
                'men_passengers' => 15,
                'men_time' => '15:00',
                'remarks' => 'Batch 2',
                'sort_order' => 2,
            ],
        ]);

        $package1->officials()->createMany([
            ['type' => 'mutawif', 'name' => 'Ustaz Hadi', 'contact_number' => '0101001001', 'nationality' => 'Malaysian', 'passport_number' => 'P0010001', 'gender' => 'male', 'date_of_birth' => '1980-05-10', 'passport_issue_date' => '2022-01-10', 'passport_expiry_date' => '2032-01-09', 'passport_place_of_issue' => 'Kuala Lumpur', 'place_of_birth' => 'Kuala Lumpur', 'sort_order' => 1],
            ['type' => 'mutawifah', 'name' => 'Ustazah Aisyah', 'contact_number' => '0101001002', 'nationality' => 'Malaysian', 'passport_number' => 'P0010002', 'gender' => 'female', 'date_of_birth' => '1985-09-20', 'passport_issue_date' => '2021-03-15', 'passport_expiry_date' => '2031-03-14', 'passport_place_of_issue' => 'Shah Alam', 'place_of_birth' => 'Shah Alam', 'sort_order' => 2],
            ['type' => 'official', 'name' => 'Ops Lead A', 'contact_number' => '0101001003', 'nationality' => 'Malaysian', 'passport_number' => 'P0010003', 'gender' => 'male', 'date_of_birth' => '1990-11-02', 'passport_issue_date' => '2020-07-01', 'passport_expiry_date' => '2030-06-30', 'passport_place_of_issue' => 'Johor Bahru', 'place_of_birth' => 'Johor Bahru', 'sort_order' => 3],
        ]);

        $package2 = Package::create([
            'package_number' => NumberGenerator::generate('package'),
            'name' => 'Umrah Premium 10 Days',
            'status' => 'open',
            'price_single' => 6000.00,
            'price_double' => 4800.00,
            'price_triple' => 4000.00,
            'price_quad' => 3500.00,
            'child_with_bed_price' => 3000.00,
            'child_no_bed_price' => 2500.00,
            'infant_price' => 800.00,
            'departure_date' => '2026-02-10',
            'return_date' => '2026-02-20',
            'total_seats' => 30,
            'seats_left' => 30,
            'visa_type' => 'Umrah Visa',
            'vehicle_type' => 'VIP Van',
            'vehicle_driver_name' => 'Rizal Osman',
            'vehicle_driver_contact_number' => '0128887722',
            'ticket_type' => 'one_way',
            'included' => "Flight Tickets (Business Class)\n5-Star Hotel\nVisa Processing\nVIP Transport\nAll Meals\nPrivate Ziyarah Tours\nTravel Insurance\nLaundry Service\nSim Card",
            'not_included' => "Personal Expenses\nTips & Gratuities",
            'offer' => "Complimentary spa session at hotel\nFree upgrade to suite (subject to availability)",
            'remarks' => 'Premium package with 5-star hotels walking distance to Haram. VIP transport and dedicated guide.',
        ]);

        $package2->accommodations()->createMany([
            ['location' => 'Makkah', 'hotel_name' => 'Fairmont Makkah Clock Royal Tower', 'type_of_meal' => 'Full Board', 'check_in' => '2026-02-11', 'check_out' => '2026-02-16'],
            ['location' => 'Madinah', 'hotel_name' => 'The Oberoi Madina', 'type_of_meal' => 'Full Board', 'check_in' => '2026-02-16', 'check_out' => '2026-02-19'],
        ]);

        $package2->flights()->createMany([
            [
                'from' => 'KUL',
                'to' => 'JED',
                'description' => 'Outbound',
                'airline' => 'Emirates',
                'pnr' => 'EMR456',
                'departure_datetime' => '2026-02-10 08:00:00',
                'arrival_datetime' => '2026-02-10 14:00:00',
                'sort_order' => 1,
            ],
            [
                'from' => 'MED',
                'to' => 'KUL',
                'description' => 'Return',
                'airline' => 'Emirates',
                'pnr' => 'EMR456',
                'departure_datetime' => '2026-02-20 11:00:00',
                'arrival_datetime' => '2026-02-20 23:00:00',
                'sort_order' => 2,
            ],
        ]);

        $package2->trainTickets()->createMany([
            [
                'from' => 'Makkah',
                'to' => 'Madinah',
                'travel_date' => '2026-02-15',
                'travel_time' => '08:45',
                'remarks' => 'VIP cabin booking',
                'sort_order' => 1,
            ],
        ]);

        $package2->transportationPlans()->createMany([
            [
                'from' => 'Jeddah Airport',
                'to' => 'Fairmont Makkah',
                'travel_date' => '2026-02-10',
                'travel_time' => '15:30',
                'remarks' => 'VIP transfer',
                'sort_order' => 1,
            ],
            [
                'from' => 'The Oberoi Madina',
                'to' => 'Madinah Airport',
                'travel_date' => '2026-02-20',
                'travel_time' => '07:00',
                'remarks' => 'VIP airport lounge access',
                'sort_order' => 2,
            ],
        ]);

        $package2->rawdahTasreehs()->createMany([
            [
                'date' => '2026-02-17',
                'women_passengers' => 10,
                'women_time' => '09:00',
                'men_passengers' => 12,
                'men_time' => '10:00',
                'remarks' => 'Premium batch',
                'sort_order' => 1,
            ],
            [
                'date' => '2026-02-18',
                'women_passengers' => 8,
                'women_time' => '13:00',
                'men_passengers' => 9,
                'men_time' => '14:00',
                'remarks' => 'Premium batch 2',
                'sort_order' => 2,
            ],
        ]);

        $package2->officials()->createMany([
            ['type' => 'mutawif', 'name' => 'Ustaz Rahman', 'contact_number' => '0111111111', 'nationality' => 'Malaysian', 'passport_number' => 'P0020001', 'gender' => 'male', 'date_of_birth' => '1978-04-18', 'passport_issue_date' => '2019-06-12', 'passport_expiry_date' => '2029-06-11', 'passport_place_of_issue' => 'Kota Bharu', 'place_of_birth' => 'Kota Bharu', 'sort_order' => 1],
            ['type' => 'official', 'name' => 'Ops Lead B', 'contact_number' => '0111111112', 'nationality' => 'Malaysian', 'passport_number' => 'P0020002', 'gender' => 'male', 'date_of_birth' => '1988-12-05', 'passport_issue_date' => '2021-02-20', 'passport_expiry_date' => '2031-02-19', 'passport_place_of_issue' => 'Penang', 'place_of_birth' => 'Penang', 'sort_order' => 2],
        ]);

        $package3 = Package::create([
            'package_number' => NumberGenerator::generate('package'),
            'name' => 'Hajj Standard 2026',
            'status' => 'open',
            'price_single' => 13000.00,
            'price_double' => 10500.00,
            'price_triple' => 9000.00,
            'price_quad' => 8000.00,
            'child_with_bed_price' => 7000.00,
            'child_no_bed_price' => 5500.00,
            'infant_price' => 2000.00,
            'departure_date' => '2026-06-01',
            'return_date' => '2026-06-21',
            'total_seats' => 50,
            'seats_left' => 50,
            'visa_type' => 'Hajj Visa',
            'vehicle_type' => 'Bus',
            'vehicle_driver_name' => 'Azlan Karim',
            'vehicle_driver_contact_number' => '0137002200',
            'ticket_type' => 'two_way',
            'included' => "Flight Tickets\nHotel Accommodation\nHajj Visa\nGround Transport\nAll Meals\nZiyarah Tours\nTravel Insurance\nHajj Kit\nPre-Hajj Training",
            'not_included' => "Personal Expenses\nLaundry\nTips & Gratuities\nQurbani (Optional)",
            'offer' => "Group discount: 5% off for groups of 10 or more\nFree Hajj training workshop",
            'remarks' => 'Standard Hajj package with comfortable accommodation in Aziziyah and Mina tents.',
        ]);

        $package3->accommodations()->createMany([
            ['location' => 'Makkah', 'hotel_name' => 'Hilton Makkah Convention', 'type_of_meal' => 'Full Board', 'check_in' => '2026-06-02', 'check_out' => '2026-06-10'],
            ['location' => 'Madinah', 'hotel_name' => 'Pullman Zamzam Madina', 'type_of_meal' => 'Full Board', 'check_in' => '2026-06-14', 'check_out' => '2026-06-20'],
            ['location' => 'Taif', 'hotel_name' => 'InterContinental Taif', 'type_of_meal' => 'Breakfast', 'check_in' => '2026-06-10', 'check_out' => '2026-06-14'],
        ]);

        $package3->flights()->createMany([
            [
                'from' => 'KUL',
                'to' => 'JED',
                'description' => 'Outbound',
                'airline' => 'Saudi Airlines',
                'pnr' => 'HJJ789',
                'departure_datetime' => '2026-06-01 07:30:00',
                'arrival_datetime' => '2026-06-01 13:45:00',
                'sort_order' => 1,
            ],
            [
                'from' => 'MED',
                'to' => 'KUL',
                'description' => 'Return',
                'airline' => 'Saudi Airlines',
                'pnr' => 'HJJ789',
                'departure_datetime' => '2026-06-21 09:15:00',
                'arrival_datetime' => '2026-06-21 21:45:00',
                'sort_order' => 2,
            ],
        ]);

        $package3->trainTickets()->createMany([
            [
                'from' => 'Makkah',
                'to' => 'Madinah',
                'travel_date' => '2026-06-12',
                'travel_time' => '07:45',
                'remarks' => 'Hajj group transfer',
                'sort_order' => 1,
            ],
            [
                'from' => 'Madinah',
                'to' => 'Makkah',
                'travel_date' => '2026-06-18',
                'travel_time' => '17:15',
                'remarks' => 'Return to Makkah',
                'sort_order' => 2,
            ],
        ]);

        $package3->transportationPlans()->createMany([
            [
                'from' => 'Jeddah Airport',
                'to' => 'Hilton Makkah Convention',
                'travel_date' => '2026-06-01',
                'travel_time' => '14:30',
                'remarks' => 'Large group coach',
                'sort_order' => 1,
            ],
            [
                'from' => 'Hilton Makkah Convention',
                'to' => 'Taif Hotel',
                'travel_date' => '2026-06-10',
                'travel_time' => '09:00',
                'remarks' => 'Optional Taif excursion',
                'sort_order' => 2,
            ],
            [
                'from' => 'Pullman Zamzam Madina',
                'to' => 'Madinah Airport',
                'travel_date' => '2026-06-21',
                'travel_time' => '06:00',
                'remarks' => 'Early departure',
                'sort_order' => 3,
            ],
        ]);

        $package3->rawdahTasreehs()->createMany([
            [
                'date' => '2026-06-16',
                'women_passengers' => 22,
                'women_time' => '10:30',
                'men_passengers' => 25,
                'men_time' => '11:30',
                'remarks' => 'Main batch',
                'sort_order' => 1,
            ],
            [
                'date' => '2026-06-17',
                'women_passengers' => 18,
                'women_time' => '14:30',
                'men_passengers' => 20,
                'men_time' => '15:30',
                'remarks' => 'Secondary batch',
                'sort_order' => 2,
            ],
        ]);

        $package3->officials()->createMany([
            ['type' => 'mutawif', 'name' => 'Ustaz Hakim', 'contact_number' => '0144003300', 'nationality' => 'Malaysian', 'passport_number' => 'P0030001', 'gender' => 'male', 'date_of_birth' => '1975-01-25', 'passport_issue_date' => '2018-10-10', 'passport_expiry_date' => '2028-10-09', 'passport_place_of_issue' => 'Kuantan', 'place_of_birth' => 'Kuantan', 'sort_order' => 1],
            ['type' => 'mutawifah', 'name' => 'Ustazah Siti', 'contact_number' => '0144003301', 'nationality' => 'Malaysian', 'passport_number' => 'P0030002', 'gender' => 'female', 'date_of_birth' => '1983-07-30', 'passport_issue_date' => '2020-12-05', 'passport_expiry_date' => '2030-12-04', 'passport_place_of_issue' => 'Seremban', 'place_of_birth' => 'Seremban', 'sort_order' => 2],
            ['type' => 'official', 'name' => 'Hajj Ops Lead', 'contact_number' => '0144003302', 'nationality' => 'Malaysian', 'passport_number' => 'P0030003', 'gender' => 'male', 'date_of_birth' => '1986-08-14', 'passport_issue_date' => '2022-05-21', 'passport_expiry_date' => '2032-05-20', 'passport_place_of_issue' => 'Ipoh', 'place_of_birth' => 'Ipoh', 'sort_order' => 3],
        ]);

        $package4 = Package::create([
            'package_number' => NumberGenerator::generate('package'),
            'name' => 'Umrah Ramadan Special',
            'status' => 'open',
            'price_single' => 7500.00,
            'price_double' => 6000.00,
            'price_triple' => 5200.00,
            'price_quad' => 4500.00,
            'child_with_bed_price' => 4000.00,
            'child_no_bed_price' => 3200.00,
            'infant_price' => 1000.00,
            'departure_date' => '2026-03-01',
            'return_date' => '2026-03-15',
            'total_seats' => 40,
            'seats_left' => 40,
            'visa_type' => 'Umrah Visa',
            'vehicle_type' => 'Bus',
            'vehicle_driver_name' => 'Suhail Musa',
            'vehicle_driver_contact_number' => '0168004400',
            'ticket_type' => 'one_way',
            'included' => "Flight Tickets\nHotel near Haram\nVisa Processing\nGround Transport\nIftar & Suhoor\nZiyarah Tours\nTravel Insurance",
            'not_included' => "Personal Expenses\nLaundry\nTips & Gratuities",
            'offer' => 'Special Ramadan gift pack for all travellers',
            'remarks' => 'Special Ramadan package with premium hotel facing Haram. Extended stay for last 10 nights of Ramadan.',
        ]);

        $package4->accommodations()->createMany([
            ['location' => 'Makkah', 'hotel_name' => 'Swissotel Makkah', 'type_of_meal' => 'Iftar & Suhoor', 'check_in' => '2026-03-02', 'check_out' => '2026-03-12'],
            ['location' => 'Madinah', 'hotel_name' => 'Anwar Al Madinah Movenpick', 'type_of_meal' => 'Iftar & Suhoor', 'check_in' => '2026-03-12', 'check_out' => '2026-03-14'],
        ]);

        $package4->flights()->createMany([
            [
                'from' => 'KUL',
                'to' => 'JED',
                'description' => 'Outbound',
                'airline' => 'Qatar Airways',
                'pnr' => 'QTR321',
                'departure_datetime' => '2026-03-01 10:00:00',
                'arrival_datetime' => '2026-03-01 16:00:00',
                'sort_order' => 1,
            ],
            [
                'from' => 'MED',
                'to' => 'KUL',
                'description' => 'Return',
                'airline' => 'Qatar Airways',
                'pnr' => 'QTR321',
                'departure_datetime' => '2026-03-15 12:00:00',
                'arrival_datetime' => '2026-03-15 23:30:00',
                'sort_order' => 2,
            ],
        ]);

        $package4->trainTickets()->createMany([
            [
                'from' => 'Makkah',
                'to' => 'Madinah',
                'travel_date' => '2026-03-08',
                'travel_time' => '10:15',
                'remarks' => 'Ramadan schedule',
                'sort_order' => 1,
            ],
        ]);

        $package4->transportationPlans()->createMany([
            [
                'from' => 'Jeddah Airport',
                'to' => 'Swissotel Makkah',
                'travel_date' => '2026-03-01',
                'travel_time' => '18:00',
                'remarks' => 'Iftar pack on arrival',
                'sort_order' => 1,
            ],
            [
                'from' => 'Anwar Al Madinah',
                'to' => 'Madinah Airport',
                'travel_date' => '2026-03-15',
                'travel_time' => '07:30',
                'remarks' => 'Suhoor before departure',
                'sort_order' => 2,
            ],
        ]);

        $package4->rawdahTasreehs()->createMany([
            [
                'date' => '2026-03-10',
                'women_passengers' => 16,
                'women_time' => '09:30',
                'men_passengers' => 18,
                'men_time' => '10:30',
                'remarks' => 'Ramadan batch',
                'sort_order' => 1,
            ],
            [
                'date' => '2026-03-11',
                'women_passengers' => 14,
                'women_time' => '13:30',
                'men_passengers' => 15,
                'men_time' => '14:30',
                'remarks' => 'Ramadan batch 2',
                'sort_order' => 2,
            ],
        ]);

        $package4->officials()->createMany([
            ['type' => 'mutawif', 'name' => 'Ustaz Faiz', 'contact_number' => '0177005500', 'nationality' => 'Malaysian', 'passport_number' => 'P0040001', 'gender' => 'male', 'date_of_birth' => '1981-02-13', 'passport_issue_date' => '2019-01-01', 'passport_expiry_date' => '2028-12-31', 'passport_place_of_issue' => 'Melaka', 'place_of_birth' => 'Melaka', 'sort_order' => 1],
            ['type' => 'official', 'name' => 'Ramadan Ops', 'contact_number' => '0177005501', 'nationality' => 'Malaysian', 'passport_number' => 'P0040002', 'gender' => 'female', 'date_of_birth' => '1992-06-19', 'passport_issue_date' => '2023-04-15', 'passport_expiry_date' => '2033-04-14', 'passport_place_of_issue' => 'Putrajaya', 'place_of_birth' => 'Putrajaya', 'sort_order' => 2],
        ]);

        $package5 = Package::create([
            'package_number' => NumberGenerator::generate('package'),
            'name' => 'Umrah Budget 7 Days',
            'status' => 'closed',
            'price_single' => 3000.00,
            'price_double' => 2200.00,
            'price_triple' => 1800.00,
            'price_quad' => 1500.00,
            'child_with_bed_price' => 1300.00,
            'child_no_bed_price' => 1000.00,
            'infant_price' => 400.00,
            'departure_date' => '2025-12-01',
            'return_date' => '2025-12-08',
            'total_seats' => 35,
            'seats_left' => 0,
            'visa_type' => 'Umrah Visa',
            'vehicle_type' => 'Bus',
            'vehicle_driver_name' => 'Johan Ismail',
            'vehicle_driver_contact_number' => '0189006600',
            'ticket_type' => 'one_way',
            'included' => "Flight Tickets\nHotel Accommodation\nVisa Processing\nGround Transport",
            'not_included' => "Meals\nPersonal Expenses\nLaundry\nTravel Insurance",
            'offer' => null,
            'remarks' => 'Budget-friendly short Umrah package. This package is now closed.',
        ]);

        $package5->accommodations()->createMany([
            ['location' => 'Makkah', 'hotel_name' => 'Al Marwa Rayhaan', 'type_of_meal' => 'No Meals', 'check_in' => '2025-12-02', 'check_out' => '2025-12-05'],
            ['location' => 'Madinah', 'hotel_name' => 'Grand Mercure Madinah', 'type_of_meal' => 'No Meals', 'check_in' => '2025-12-05', 'check_out' => '2025-12-07'],
        ]);

        $package5->flights()->createMany([
            [
                'from' => 'KUL',
                'to' => 'JED',
                'description' => 'Outbound',
                'airline' => 'AirAsia X',
                'pnr' => 'AAX654',
                'departure_datetime' => '2025-12-01 06:45:00',
                'arrival_datetime' => '2025-12-01 13:00:00',
                'sort_order' => 1,
            ],
            [
                'from' => 'MED',
                'to' => 'KUL',
                'description' => 'Return',
                'airline' => 'AirAsia X',
                'pnr' => 'AAX654',
                'departure_datetime' => '2025-12-08 08:00:00',
                'arrival_datetime' => '2025-12-08 19:30:00',
                'sort_order' => 2,
            ],
        ]);

        $package5->trainTickets()->createMany([
            [
                'from' => 'Makkah',
                'to' => 'Madinah',
                'travel_date' => '2025-12-04',
                'travel_time' => '09:00',
                'remarks' => 'Budget train ticket',
                'sort_order' => 1,
            ],
        ]);

        $package5->transportationPlans()->createMany([
            [
                'from' => 'Jeddah Airport',
                'to' => 'Al Marwa Rayhaan',
                'travel_date' => '2025-12-01',
                'travel_time' => '14:00',
                'remarks' => 'Shared coach',
                'sort_order' => 1,
            ],
            [
                'from' => 'Grand Mercure Madinah',
                'to' => 'Madinah Airport',
                'travel_date' => '2025-12-08',
                'travel_time' => '06:30',
                'remarks' => 'Budget transfer',
                'sort_order' => 2,
            ],
        ]);

        $package5->rawdahTasreehs()->createMany([
            [
                'date' => '2025-12-05',
                'women_passengers' => 8,
                'women_time' => '10:30',
                'men_passengers' => 9,
                'men_time' => '11:30',
                'remarks' => 'Budget batch',
                'sort_order' => 1,
            ],
            [
                'date' => '2025-12-06',
                'women_passengers' => 6,
                'women_time' => '14:30',
                'men_passengers' => 7,
                'men_time' => '15:30',
                'remarks' => 'Budget batch 2',
                'sort_order' => 2,
            ],
        ]);

        $package5->officials()->createMany([
            ['type' => 'mutawif', 'name' => 'Ustaz Farid', 'contact_number' => '0191007700', 'nationality' => 'Malaysian', 'passport_number' => 'P0050001', 'gender' => 'male', 'date_of_birth' => '1979-03-17', 'passport_issue_date' => '2017-09-25', 'passport_expiry_date' => '2027-09-24', 'passport_place_of_issue' => 'Alor Setar', 'place_of_birth' => 'Alor Setar', 'sort_order' => 1],
            ['type' => 'official', 'name' => 'Budget Ops', 'contact_number' => '0191007701', 'nationality' => 'Malaysian', 'passport_number' => 'P0050002', 'gender' => 'male', 'date_of_birth' => '1991-10-09', 'passport_issue_date' => '2022-11-11', 'passport_expiry_date' => '2032-11-10', 'passport_place_of_issue' => 'Kangar', 'place_of_birth' => 'Kangar', 'sort_order' => 2],
        ]);
    }
}
