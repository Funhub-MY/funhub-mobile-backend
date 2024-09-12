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
        Schema::table('user_cards', function (Blueprint $table) {
            $table->string('card_holder_name')->nullable()->change();
            $table->string('card_expiry_month')->nullable()->change();
            $table->string('card_expiry_year')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_cards', function (Blueprint $table) {
            $table->string('card_holder_name')->change();
            $table->string('card_expiry_month')->change();
            $table->string('card_expiry_year')->change();
        });
    }
};
