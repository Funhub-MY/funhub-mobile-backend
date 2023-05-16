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
        Schema::create('article_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rss_channel_id')->constrained();
            $table->tinyInteger('status')->default(1);
            $table->json('description')->nullable();
            // this is to indicate the articles that were published by each of the channel.
            // this is use to determine whether the article has been update / renew hourly.
            // it is nullable as when calling API, it might be failed already.
            $table->timestamp('article_pub_date')->nullable();
            $table->timestamp('last_run_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('article_imports');
    }
};
