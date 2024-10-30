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
        Schema::create('koc_merchant_history', function (Blueprint $table) {
            $table->id();
			$table->unsignedBigInteger('merchant_id');
			$table->unsignedBigInteger('koc_user_id');
            $table->timestamps();

			$table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('cascade');
			$table->foreign('koc_user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
		Schema::dropIfExists('koc_merchant_history');
    }
};
