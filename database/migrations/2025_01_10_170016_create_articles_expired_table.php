<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up()
	{
		Schema::create('articles_expired', function (Blueprint $table) {
			$table->id();
			$table->foreignId('article_id')->constrained('articles')->onDelete('cascade');
			$table->boolean('is_expired');
			$table->timestamp('processed_time');
			$table->timestamps();

			$table->unique('article_id');
		});
	}

	public function down()
	{
		Schema::dropIfExists('articles_expired');
	}
};