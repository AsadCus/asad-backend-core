<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Sales;
use App\Models\User;
use Illuminate\Database\Seeder;

class SalesSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::first();

        if (! $branch) {
            $this->command->warn('No branches found. Please run BranchSeeder first.');

            return;
        }

        $salesUsers = User::whereHas('roles', function ($query) {
            $query->where('name', 'sales');
        })->get();

        if ($salesUsers->isEmpty()) {
            $this->command->warn('No sales users found. Creating sales from regular users.');
            $salesUsers = User::limit(3)->get();
        }

        foreach ($salesUsers as $user) {
            Sales::create([
                'user_id' => $user->id,
                'branch_id' => $branch->id,
            ]);
        }
    }
}
