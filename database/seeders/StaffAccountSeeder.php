<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Staff; // Make sure this matches your new Model name
use Illuminate\Support\Facades\Hash;

class StaffAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Staff::updateOrCreate(
            ['username' => 'staff01'],
            [
                'name' => 'Staff Member',
                'password' => Hash::make('staff123'),
                'role' => 'Staff',
            ]
        );
    }
}

