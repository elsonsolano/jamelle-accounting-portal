<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            ['name' => 'Head Office',    'is_cost_center' => true],
            ['name' => 'SM Lanang',      'is_cost_center' => false],
            ['name' => 'SM Ecoland',     'is_cost_center' => false],
            ['name' => 'Ayala Abreeza',  'is_cost_center' => false],
            ['name' => 'NCCC',           'is_cost_center' => false],
        ];

        foreach ($branches as $data) {
            Branch::firstOrCreate(
                ['name' => $data['name']],
                ['is_cost_center' => $data['is_cost_center']]
            );

            // Ensure existing rows are updated too
            Branch::where('name', $data['name'])
                ->update(['is_cost_center' => $data['is_cost_center']]);
        }
    }
}
