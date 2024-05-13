<?php

use App\Models\Article;
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
        Schema::create('article_engagements', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class);
            $table->foreignIdFor(Article::class);
            // action, like, comment

            $table->string('action');
            $table->text('comment')->nullable();

            // schedule
            $table->timestamp('scheduled_at')->nullable(); // can be now or scheduled for later to add

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
        Schema::dropIfExists('article_engagements');
    }
};
