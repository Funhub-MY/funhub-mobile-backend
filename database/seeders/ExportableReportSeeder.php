<?php

namespace Database\Seeders;

use App\Models\ExportableReport;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ExportableReportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        ExportableReport::create([
            'name' => 'Sales Report'
        ]);

        ExportableReport::create([
            'name' => 'Stock Vouchers Report'
        ]);

        ExportableReport::create([
            'name' => 'Stock Funbox Report'
        ]);

        ExportableReport::create([
            'name' => 'Merchant Shop Report'
        ]);

        ExportableReport::create([
            'name' => 'Sales Person Report'
        ]);
    }
}
