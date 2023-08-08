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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->double('unit_price');
            $table->double('discount_price')->nullable();
            $table->integer('quantity')->default(0);
            $table->string('sku')->nullable();
            $table->tinyInteger('status')->default(1); // 0 = draft, 1 = published, 2 = archived

            $table->foreignId('created_by_id')->nullable();
            $table->foreignId('updated_by_id')->nullable();
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
        Schema::dropIfExists('products');
    }
};
