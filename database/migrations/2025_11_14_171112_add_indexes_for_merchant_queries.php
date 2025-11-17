<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add indexes for merchant_offer_campaigns
        Schema::table('merchant_offer_campaigns', function (Blueprint $table) {
            // Index for merchant queries
            if (!$this->indexExists('merchant_offer_campaigns', 'merchant_offer_campaigns_merchant_id_index')) {
                $table->index('merchant_id', 'merchant_offer_campaigns_merchant_id_index');
            }
            
            // Composite index for merchant + status queries
            if (!$this->indexExists('merchant_offer_campaigns', 'merchant_offer_campaigns_merchant_status_index')) {
                $table->index(['merchant_id', 'status'], 'merchant_offer_campaigns_merchant_status_index');
            }
        });

        // Add indexes for merchant_offers
        Schema::table('merchant_offers', function (Blueprint $table) {
            // Index for merchant queries
            if (!$this->indexExists('merchant_offers', 'merchant_offers_merchant_id_index')) {
                $table->index('merchant_id', 'merchant_offers_merchant_id_index');
            }
            
            // Composite index for merchant + status queries
            if (!$this->indexExists('merchant_offers', 'merchant_offers_merchant_status_index')) {
                $table->index(['merchant_id', 'status'], 'merchant_offers_merchant_status_index');
            }
            
            // Composite index for campaign + merchant queries
            if (!$this->indexExists('merchant_offers', 'merchant_offers_campaign_merchant_index')) {
                $table->index(['merchant_offer_campaign_id', 'merchant_id'], 'merchant_offers_campaign_merchant_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchant_offer_campaigns', function (Blueprint $table) {
            $table->dropIndex('merchant_offer_campaigns_merchant_id_index');
            $table->dropIndex('merchant_offer_campaigns_merchant_status_index');
        });

        Schema::table('merchant_offers', function (Blueprint $table) {
            $table->dropIndex('merchant_offers_merchant_id_index');
            $table->dropIndex('merchant_offers_merchant_status_index');
            $table->dropIndex('merchant_offers_campaign_merchant_index');
        });
    }

    /**
     * Check if index exists
     */
    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();
        
        $result = $connection->select(
            "SELECT COUNT(*) as count 
             FROM information_schema.statistics 
             WHERE table_schema = ? 
             AND table_name = ? 
             AND index_name = ?",
            [$databaseName, $table, $index]
        );
        
        return $result[0]->count > 0;
    }
};
