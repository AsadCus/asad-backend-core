<?php

namespace Database\Seeders;

use App\Models\ReportSetting;
use Illuminate\Database\Seeder;

class ReportSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default report settings (singleton pattern)
        // Note: logo_path is null by default; frontend will fallback to /logo-primary.png
        ReportSetting::firstOrCreate(
            ['id' => 1],
            [
                'company_name' => 'Karva Travel & Tours',
                'company_address' => "390 Victoria Street\nGolden Landmark Shopping Centre\n#03-28 Singapore 188061",
                'company_phone' => null,
                'company_email' => null,
                'logo_path' => null,
                'footer_text' => "Thank you for your business.\nFor any inquiries, please contact us.",
                'stamp_path' => null,
                'signature_path' => null,
                'brand_color' => '#c05427',
            ]
        );
    }
}
