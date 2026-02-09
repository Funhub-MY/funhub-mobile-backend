<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $items = [
            ['name' => 'Popmart Crybaby Premium', 'quantity' => 2, 'win_percentage' => 0.01, 'order' => 1],
            ['name' => 'Tumbler', 'quantity' => 4, 'win_percentage' => 0.99, 'order' => 2],
            ['name' => 'Tote Bag (CNY Edition)', 'quantity' => 6, 'win_percentage' => 2.00, 'order' => 3],
            ['name' => 'Poker Card', 'quantity' => 8, 'win_percentage' => 2.00, 'order' => 4],
            ['name' => 'Tote Bag (New)', 'quantity' => 10, 'win_percentage' => 5.00, 'order' => 5],
        ];

        foreach ($items as $item) {
            DB::table('cny_merchandise')->insert(array_merge($item, [
                'given_out' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        DB::table('cny_merchandise')->truncate();
    }
};
