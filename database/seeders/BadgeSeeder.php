<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Usage: php artisan db:seed --class=BadgeSeeder
     * 
     * Set the campaign ID before running:
     * - Update the $campaignId variable below, or
     * - Pass via tinker: (new \Database\Seeders\BadgeSeeder)->run(66)
     *
     * @param int|null $campaignId
     * @return void
     */
    public function run($campaignId = null)
    {
        // Set your campaign ID here before running
        // Change this value to match your target campaign
        $campaignId = $campaignId ?? 1; // Default to campaign 1, change as needed

        $badges = [
            ['name' => 'Top 800', 'color' => '#94A3B8', 'description' => 'Top 800 participant'],
            ['name' => 'Top 500', 'color' => '#64748B', 'description' => 'Top 500 participant'],
            ['name' => 'Top 300', 'color' => '#475569', 'description' => 'Top 300 participant'],
            ['name' => 'Top 200', 'color' => '#0EA5E9', 'description' => 'Top 200 participant'],
            ['name' => 'Top 100', 'color' => '#22C55E', 'description' => 'Top 100 participant'],
            ['name' => 'Top 50', 'color' => '#EAB308', 'description' => 'Top 50 participant'],
            ['name' => 'Top 10', 'color' => '#EF4444', 'description' => 'Top 10 participant'],
            ['name' => 'Funhub Ambassador', 'color' => '#8B5CF6', 'description' => 'Funhub Ambassador'],
            ['name' => 'Artist', 'color' => '#EC4899', 'description' => 'Artist badge'],
            ['name' => 'FunHalloween2025', 'color' => '#F97316', 'description' => 'FunHalloween 2025 participant'],
        ];

        foreach ($badges as $badge) {
            Badge::updateOrCreate(
                ['name' => $badge['name']],
                [
                    'campaign_id' => $campaignId,
                    'color' => $badge['color'],
                    'description' => $badge['description'],
                    'is_active' => true,
                ]
            );
        }

        $this->command->info("Seeded " . count($badges) . " badges for campaign ID: {$campaignId}");
    }
}
