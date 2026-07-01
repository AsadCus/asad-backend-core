<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'Cuti Tahunan',
                'code' => 'ANNUAL',
                'max_days_per_year' => 12,
                'requires_balance' => true,
                'requires_attachment' => false,
                'is_paid' => true,
                'gender_restriction' => null,
                'description' => 'Hak cuti tahunan berbayar.',
            ],
            [
                'name' => 'Cuti Sakit',
                'code' => 'SICK',
                'max_days_per_year' => 12,
                'requires_balance' => true,
                'requires_attachment' => true,
                'is_paid' => true,
                'gender_restriction' => null,
                'description' => 'Cuti sakit; lampirkan surat dokter bila diminta.',
            ],
            [
                'name' => 'Cuti Melahirkan',
                'code' => 'MATERNITY',
                'max_days_per_year' => 90,
                'requires_balance' => false,
                'requires_attachment' => true,
                'is_paid' => true,
                'gender_restriction' => 'female',
                'description' => 'Cuti melahirkan hingga 90 hari berbayar.',
            ],
            [
                'name' => 'Cuti Ayah',
                'code' => 'PATERNITY',
                'max_days_per_year' => 3,
                'requires_balance' => false,
                'requires_attachment' => false,
                'is_paid' => true,
                'gender_restriction' => 'male',
                'description' => 'Cuti berbayar untuk karyawan yang istrinya melahirkan.',
            ],
            [
                'name' => 'Cuti Duka',
                'code' => 'BEREAVEMENT',
                'max_days_per_year' => 3,
                'requires_balance' => false,
                'requires_attachment' => false,
                'is_paid' => true,
                'gender_restriction' => null,
                'description' => 'Cuti berbayar karena anggota keluarga inti meninggal dunia.',
            ],
            [
                'name' => 'Cuti Tanpa Gaji',
                'code' => 'UNPAID',
                'max_days_per_year' => null,
                'requires_balance' => false,
                'requires_attachment' => false,
                'is_paid' => false,
                'gender_restriction' => null,
                'description' => 'Cuti tanpa gaji yang disetujui (tanpa batas).',
            ],
            [
                'name' => 'Cuti Menikah',
                'code' => 'MARRIAGE',
                'max_days_per_year' => 3,
                'requires_balance' => false,
                'requires_attachment' => false,
                'is_paid' => true,
                'gender_restriction' => null,
                'description' => 'Cuti berbayar untuk pernikahan karyawan.',
            ],
            [
                'name' => 'Libur Pengganti',
                'code' => 'COMP_DAY',
                'max_days_per_year' => null,
                'requires_balance' => false,
                'requires_attachment' => false,
                'is_paid' => true,
                'gender_restriction' => null,
                'description' => 'Libur pengganti atas hari kerja pada hari libur/istirahat.',
            ],
            [
                'name' => 'Izin Terlambat',
                'code' => 'LATE_PERMIT',
                'max_days_per_year' => null,
                'requires_balance' => false,
                'requires_attachment' => true,
                'is_paid' => true,
                'gender_restriction' => null,
                'description' => 'Izin datang terlambat dengan bukti pendukung.',
            ],
            [
                'name' => 'Izin Pulang Awal',
                'code' => 'EARLY_LEAVE',
                'max_days_per_year' => null,
                'requires_balance' => false,
                'requires_attachment' => true,
                'is_paid' => true,
                'gender_restriction' => null,
                'description' => 'Izin pulang lebih awal dengan bukti pendukung.',
            ],
            [
                'name' => 'Cuti Haid',
                'code' => 'MENSTRUAL',
                'max_days_per_year' => null,
                'requires_balance' => false,
                'requires_attachment' => false,
                'is_paid' => true,
                'gender_restriction' => 'female',
                'description' => 'Cuti haid berbayar.',
            ],
            [
                'name' => 'Izin Keperluan Penting',
                'code' => 'IMPORTANT_LEAVE',
                'max_days_per_year' => null,
                'requires_balance' => false,
                'requires_attachment' => false,
                'is_paid' => true,
                'gender_restriction' => null,
                'description' => 'Keperluan pribadi mendesak yang tidak tercakup kategori lain.',
            ],
            [
                'name' => 'Kerja dari Rumah',
                'code' => 'WFH',
                'max_days_per_year' => null,
                'requires_balance' => false,
                'requires_attachment' => true,
                'is_paid' => true,
                'gender_restriction' => null,
                'description' => 'Hari kerja dari rumah yang disetujui, dengan bukti/agenda.',
            ],
            [
                'name' => 'Lainnya',
                'code' => 'OTHER',
                'max_days_per_year' => null,
                'requires_balance' => false,
                'requires_attachment' => true,
                'is_paid' => false,
                'gender_restriction' => null,
                'description' => 'Kategori lain yang belum tercantum.',
            ],
        ];

        foreach ($types as $type) {
            LeaveType::updateOrCreate(['code' => $type['code']], $type + ['is_active' => true]);
        }
    }
}
