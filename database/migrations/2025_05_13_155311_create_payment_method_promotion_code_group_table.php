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
        Schema::create('payment_method_promo_group', function (Blueprint $table) {
			$table->id();

			$table->unsignedBigInteger('payment_method_id');
			$table->unsignedBigInteger('promotion_code_group_id');
			$table->timestamps();

			// Shorter foreign key constraint names
			$table->foreign('payment_method_id', 'pmpg_payment_method_fk')
				->references('id')
				->on('payment_methods')
				->onDelete('cascade');

			$table->foreign('promotion_code_group_id', 'pmpg_promo_group_fk')
				->references('id')
				->on('promotion_code_groups')
				->onDelete('cascade');

			// Composite unique constraint instead of primary key
			$table->unique(['payment_method_id', 'promotion_code_group_id'], 'pmpg_unique');
		});
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payment_method_promo_group');
    }
};
