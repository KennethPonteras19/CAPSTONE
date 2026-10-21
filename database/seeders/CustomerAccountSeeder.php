<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;
use Illuminate\Support\Facades\Hash;

class CustomerAccountSeeder extends Seeder
{
    public function run(): void
    {
        Customer::updateOrCreate(
            ['username' => 'customer01'],
            [
                'name' => 'Customer',
                'username' => 'customer01',
                'email' => 'customer@tmta.com',
                'password' => Hash::make('Customer01FEUC'),
            ]
        );
    }
}
