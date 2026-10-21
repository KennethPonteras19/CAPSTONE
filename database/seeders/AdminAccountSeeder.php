<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

class AdminAccountSeeder extends Seeder
{
    public function run(): void
    {
        Admin::updateOrCreate(
            ['username' => 'admin01'],
            [
                'name' => 'System Admin',
                'username' => 'admin01',
                'email' => 'admin@tmta.com',
                'password' => Hash::make('Admin01FEUC'),
            ]
        );
    }
}
