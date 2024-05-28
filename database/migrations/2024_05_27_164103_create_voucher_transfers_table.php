<?php

use App\Models\MerchantOffer;
use App\Models\MerchantOfferVoucher;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('voucher_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(MerchantOffer::class)->nullable();
            // quantity
            $table->integer('quantity')->default(1);
            $table->tinyInteger('status')->default(0); // 0 = pending, 1 = approved, 2 = rejected
            $table->text('remarks')->nullable();
            $table->timestamp('transferred_on')->nullable();
            $table->foreignIdFor(User::class, 'from_user_id')->nullable();
            $table->foreignIdFor(User::class, 'to_user_id');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('voucher_transfers');
    }
};
