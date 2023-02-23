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
        Schema::create('merchant_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('store_id')->nullable()->constrained();
            $table->string('name');
            $table->text('description');
            $table->decimal('unit_price', 8,2);
            $table->timestamp('available_at');
            $table->timestamp('available_until');
            $table->integer('quantity');
            $table->string('sku');
            $table->softDeletes();

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
        Schema::dropIfExists('merchant_offers');
    }
};
