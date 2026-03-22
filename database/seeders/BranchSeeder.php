<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            'Head Office',
            'SM Lanang',
            'SM Ecoland',
            'Ayala Abreeza',
        ];

        foreach ($branches as $name) {
            Branch::firstOrCreate(['name' => $name]);
        }
    }
}
