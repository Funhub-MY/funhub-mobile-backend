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
        Schema::create('merchant_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\Merchant::class);
            $table->foreignIdFor(\App\Models\User::class);
            $table->integer('rating');
            $table->text('comment')->nullable();
            $table->boolean('is_active')->default(true);
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
        Schema::dropIfExists('merchant_ratings');
    }
};
