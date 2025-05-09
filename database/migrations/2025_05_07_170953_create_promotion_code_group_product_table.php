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
		Schema::create('promotion_code_group_product', function (Blueprint $table) {
			$table->id();
			$table->foreignId('promotion_code_group_id')->constrained()->onDelete('cascade');
			$table->foreignId('product_id')->constrained()->onDelete('cascade');
			$table->timestamps();

			// Add unique constraint to prevent duplicate product entries
			$table->unique(['promotion_code_group_id', 'product_id'], 'promo_code_group_product_unique');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('promotion_code_group_product');
	}
};