<?php

use App\Models\User;
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
        Schema::create('media_partners_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class);
            $table->string('keyword');
            //whitelist or blacklist
            $table->enum('type', ['whitelist', 'blacklist']);
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
        Schema::dropIfExists('media_partners_keywords');
    }
};
