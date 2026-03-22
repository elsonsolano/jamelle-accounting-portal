<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Staff Payroll and Allowance',
            'SSS Employer Share',
            'Pag-ibig Employer Share',
            'PHIC Employer Share',
            'Store Rental & CUSA',
            'Ins., Renewals and Other Fees',
            'Unreleased 13th Month',
            'Released 13th Month & SIL',
            'Service Incentive Leave(SIL)',
            'Store Maintenance',
            'Equipment Maintenance',
            'Pest Control',
            'Hydro Lab',
            'Store Supplies',
            'Representations',
            'Other Expense',
            'BIR & City Gov\'t Fees',
            'Retainer\'s Fee',
            'Royalty Fee',
            'Ads Fee',
            'Stocks Cost',
            'Cashless Fees',
            'Unreleased Separation/Retirement Pay',
            'Released Separation/Retirement Pay',
            'Miscellaneous',
            'Tel, Cable, Internet & Cel.',
            'Fuel',
            'Office Equipments',
            'Logistics',
            'Loans Payable',
            'Vehicle Maintenance',
            'Commissary Rental & Electricity',
        ];

        foreach ($categories as $name) {
            ExpenseCategory::firstOrCreate(['name' => $name]);
        }
    }
}
