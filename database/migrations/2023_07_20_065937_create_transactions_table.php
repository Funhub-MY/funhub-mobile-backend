<?php

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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_no'); // unique transaction no
            $table->foreignId('user_id'); // who the transacted
            $table->foreignId('product_id'); // which product
            $table->double('amount');
            $table->string('gateway');
            $table->text('gateway_transaction_id'); // gatway transaction ID
            $table->string('payment_method')->nullable(); 
            $table->string('bank')->nullable(); // if using fpx
            $table->string('card_last_four')->nullable();
            $table->string('card_type')->nullable(); // master/visa
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
        Schema::dropIfExists('transactions');
    }
};
