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
        Schema::create('approval_settings', function (Blueprint $table) {
            $table->id();
            $table->string('approvable_type'); // eg. model class name App\Models\Reward
            $table->unsignedInteger('role_id');
            $table->integer('sequence'); // eg. 1, 2, 3
            $table->text('remarks')->nullable();
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
        Schema::dropIfExists('approval_settings');
    }
};
