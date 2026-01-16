<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ReceiptSeeder extends Seeder
{
    public function run(): void {}

    // private function generateReference(string $paymentMethod): string
    // {
    //     switch ($paymentMethod) {
    //         case 'transfer':
    //             return 'TXN' . strtoupper(uniqid());
    //         case 'paynow':
    //             return 'PN' . date('YmdHis') . rand(100, 999);
    //         case 'cash':
    //             return 'CASH' . date('Ymd') . rand(100, 999);
    //         default:
    //             return 'REF' . uniqid();
    //     }
    // }
}
