<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('payment_methods')->updateOrInsert(
            ['code' => 'TNG eWallet'],
            ['name' => 'TNG e-Wallet']
        );

        DB::table('payment_methods')->updateOrInsert(
            ['code' => 'GrabPay'],
			['name' => 'GrabPay']
        );
    }
}
