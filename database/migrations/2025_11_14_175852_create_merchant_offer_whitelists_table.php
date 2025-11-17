<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\MerchantOffer;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('merchant_offer_whitelists', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(MerchantOffer::class, 'merchant_offer_id')->constrained('merchant_offers')->onDelete('cascade');
            $table->unsignedBigInteger('merchant_user_id')->nullable(); // Merchant's user_id for easy querying
            $table->integer('override_days')->nullable()->comment('Custom days limit for this offer. If null, offer is fully whitelisted (no restriction). If set, uses this value instead of config default.');
            $table->text('notes')->nullable()->comment('Optional notes about why this offer is whitelisted');
            $table->timestamps();
            
            // Indexes for performance
            $table->index('merchant_offer_id');
            $table->index('merchant_user_id');
            $table->unique('merchant_offer_id', 'unique_merchant_offer_whitelist');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_offer_whitelists');
    }
};
