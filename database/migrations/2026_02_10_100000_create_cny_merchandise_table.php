<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cny_merchandise', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('given_out')->default(0);
            $table->decimal('win_percentage', 5, 2)->default(0); // e.g. 0.01, 0.99, 2.00, 5.00
            $table->unsignedTinyInteger('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cny_merchandise');
    }
};
