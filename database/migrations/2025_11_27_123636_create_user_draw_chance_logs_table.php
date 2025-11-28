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
        Schema::create('user_draw_chance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('transaction_id')->nullable()->constrained()->onDelete('set null');
            $table->unsignedInteger('chances_awarded');
            $table->string('source')->default('product_purchase')->comment('product_purchase, admin_manual, promo, etc.');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('product_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_draw_chance_logs');
    }
};
