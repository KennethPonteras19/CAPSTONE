<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SuperAdmin;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        SuperAdmin::updateOrCreate(
            ['username' => 'super_admin'], 
            [
                'name' => 'System Admin',
                'username' => 'super_admin',
                'email' => 'admin@tmta.com',
                'password' => Hash::make('SuperAdmin01FEUC'),
            ]
        );
    }
}
// KENNETH PONTERAS 19-2005-08